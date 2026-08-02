#!/usr/bin/env php
<?php
/**
 * notifyd.php — browse.wf notification daemon
 *
 * Runs as a long-lived process inside the container (via supervisord).
 * Polls the local worldstate proxy (worldstate.php) every 60 s and dispatches
 * Apprise notifications to every user whose config matches the event.
 *
 * Environment variables
 *   NOTIF_DB_PATH   path to the SQLite file (default /data/wf-notify.db)
 *   POLL_INTERVAL   seconds between worldstate polls (default 60)
 *   DICT_LANG       language for mission-name strings (default en)
 *
 * All dispatch state (last-seen IDs) is persisted in the SQLite DB so
 * restarts don't double-fire.
 */

define('NOTIF_DB_PATH', getenv('NOTIF_DB_PATH') ?: '/data/wf-notify.db');
require_once __DIR__ . '/notif/db.php';

$POLL_INTERVAL = (int)(getenv('POLL_INTERVAL') ?: 60);
$DICT_LANG     = getenv('DICT_LANG') ?: 'en';

// ── Logging ───────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    flush();
}

// ── HTTP helpers ──────────────────────────────────────────────────────────────
function http_get(string $url, int $timeout = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'browse.wf-notifyd/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err || $code !== 200) {
        log_msg("HTTP GET $url failed: code=$code err=$err");
        return null;
    }
    return $body;
}

// ── Data loading ──────────────────────────────────────────────────────────────
$dict = null;
function load_dict(string $lang): ?array {
    global $dict, $DICT_LANG;
    // Try to load from the local copy first (warframe-public-export-plus is baked in)
    $local = __DIR__ . "/warframe-public-export-plus/dict.$lang.json";
    if (file_exists($local)) {
        return json_decode(file_get_contents($local), true);
    }
    // Fallback: relative URL (works when daemon runs inside the container)
    $raw = http_get("http://localhost/warframe-public-export-plus/dict.$lang.json");
    return $raw ? json_decode($raw, true) : null;
}

$export_regions = null;
function load_export_regions(): ?array {
    $local = __DIR__ . '/warframe-public-export-plus/ExportRegions.json';
    if (file_exists($local)) return json_decode(file_get_contents($local), true);
    $raw = http_get('http://localhost/warframe-public-export-plus/ExportRegions.json');
    return $raw ? json_decode($raw, true) : null;
}

$export_mission_types = null;
function load_export_mission_types(): ?array {
    $local = __DIR__ . '/warframe-public-export-plus/ExportMissionTypes.json';
    if (file_exists($local)) return json_decode(file_get_contents($local), true);
    $raw = http_get('http://localhost/warframe-public-export-plus/ExportMissionTypes.json');
    return $raw ? json_decode($raw, true) : null;
}

// ── Mission type helpers ──────────────────────────────────────────────────────
// ExportRegions[node].missionName is like "/Lotus/Language/Missions/MissionName_Defense"
// We extract "Defense", then title-case-expand CamelCase.
function mission_type_label(string $path): string {
    $short = substr($path, strrpos($path, 'MissionName_') + 12);
    // CamelCase → Title Case with spaces
    return trim(preg_replace('/([A-Z])/', ' $1', $short));
}

// ── Fissure tier map ──────────────────────────────────────────────────────────
const FISSURE_TIER_MAP = [
    'VoidT1' => 'Lith',
    'VoidT2' => 'Meso',
    'VoidT3' => 'Neo',
    'VoidT4' => 'Axi',
    'VoidT5' => 'Requiem',
    'VoidT6' => 'Omnia',
];

// ── All users with a configured Apprise server ────────────────────────────────
function get_active_configs(): array {
    $rows = get_db()->query("
        SELECT u.id, u.username, nc.apprise_server, nc.endpoints,
               nc.events, nc.fissure_filters, nc.sp_fissure_filters, nc.arby_filters
        FROM users u
        JOIN notif_config nc ON nc.user_id = u.id
        WHERE nc.apprise_server != ''
    ")->fetchAll();

    return array_map(function($r) {
        return [
            'id'                => $r['id'],
            'username'          => $r['username'],
            'apprise_server'    => $r['apprise_server'],
            'endpoints'         => json_decode($r['endpoints'], true) ?? [],
            'events'            => json_decode($r['events'], true)    ?? [],
            'fissure_filters'   => json_decode($r['fissure_filters'], true)    ?? ['tiers'=>[],'types'=>[]],
            'sp_fissure_filters'=> json_decode($r['sp_fissure_filters'], true) ?? ['tiers'=>[],'types'=>[]],
            'arby_filters'      => json_decode($r['arby_filters'], true) ?? [],
        ];
    }, $rows);
}

// ── Per-user dispatch wrapper ─────────────────────────────────────────────────
function dispatch(array $cfg, string $title, string $body): void {
    $result = send_apprise($cfg['apprise_server'], $cfg['endpoints'], $title, $body);
    if ($result['ok']) {
        log_msg("  → [{$cfg['username']}] sent: $title | $body");
        log_dispatch((int)$cfg['id'], $title, $body, true);
    } else {
        $err = $result['error'] ?? ('HTTP ' . ($result['status'] ?? '?'));
        log_msg("  → [{$cfg['username']}] FAILED: $err");
        log_dispatch((int)$cfg['id'], $title, $body, false, $err);
    }
}

// ── Event checkers ────────────────────────────────────────────────────────────

function check_sortie(array $ws, array $configs): void {
    $sortie = null;
    $now = time() * 1000;
    foreach ($ws['Sorties'] ?? [] as $s) {
        $act = (int)$s['Activation']['$date']['$numberLong'];
        $exp = (int)$s['Expiry']['$date']['$numberLong'];
        if ($now >= $act && $now < $exp) { $sortie = $s; break; }
    }
    if (!$sortie) return;
    $id = $sortie['_id']['$oid'];
    if (get_state('sortie') === $id) return;
    set_state('sortie', $id);

    foreach ($configs as $cfg) {
        if (!empty($cfg['events']['sortie'])) {
            dispatch($cfg, 'browse.wf · Sortie', 'A new sortie is available.');
        }
    }
}

function check_archon_hunt(array $ws, array $configs): void {
    $now = time() * 1000;
    foreach ($ws['LiteSorties'] ?? [] as $ls) {
        $act = (int)$ls['Activation']['$date']['$numberLong'];
        $exp = (int)$ls['Expiry']['$date']['$numberLong'];
        if ($now >= $act && $now < $exp) {
            $id = $ls['_id']['$oid'];
            if (get_state('litesortie') === $id) return;
            set_state('litesortie', $id);
            foreach ($configs as $cfg) {
                if (!empty($cfg['events']['litesortie'])) {
                    $boss = ucfirst(strtolower(substr($ls['Boss'], strrpos($ls['Boss'], '/') + 1)));
                    dispatch($cfg, 'browse.wf · Archon Hunt', "New Archon Hunt: $boss");
                }
            }
            return;
        }
    }
}

function check_baro(array $ws, array $configs): void {
    global $export_regions, $dict;
    $trader = $ws['VoidTraders'][0] ?? null;
    if (!$trader) return;

    $has_manifest = !empty($trader['Manifest']);
    $exp_raw      = (int)($trader['Expiry']['$date']['$numberLong'] ?? 0);
    $act_raw      = (int)($trader['Activation']['$date']['$numberLong'] ?? 0);
    $state_key    = $has_manifest ? 'baro_present_' . $exp_raw : 'baro_coming_' . $act_raw;

    if (get_state('baro') === $state_key) return;
    if (!$has_manifest) return;  // Only fire when Baro arrives, not when he's announced
    set_state('baro', $state_key);

    $node_key = $trader['Node'] ?? '';
    $location = 'unknown location';
    if ($export_regions && isset($export_regions[$node_key])) {
        $node = $export_regions[$node_key];
        $n    = $dict[$node['name']] ?? $node['name'];
        $s    = $dict[$node['systemName']] ?? $node['systemName'];
        $location = "$n, $s";
    }

    foreach ($configs as $cfg) {
        if (!empty($cfg['events']['baro'])) {
            dispatch($cfg, "browse.wf · Baro Ki'Teer", "Baro Ki'Teer has arrived at $location.");
        }
    }
}

function check_alerts(array $ws, array $configs): void {
    $count = count($ws['Alerts'] ?? []);
    $prev  = (int)(get_state('alert_count') ?? 0);
    if ($count <= $prev) { set_state('alert_count', (string)$count); return; }
    $diff = $count - $prev;
    set_state('alert_count', (string)$count);

    foreach ($configs as $cfg) {
        if (!empty($cfg['events']['alerts'])) {
            $msg = $diff === 1 ? 'A new alert is live.' : "$diff new alerts are live.";
            dispatch($cfg, 'browse.wf · Alert', $msg);
        }
    }
}

function check_darvo(array $ws, array $configs): void {
    global $dict;
    $now = time() * 1000;
    foreach ($ws['DailyDeals'] ?? [] as $deal) {
        $act = (int)$deal['Activation']['$date']['$numberLong'];
        $exp = (int)$deal['Expiry']['$date']['$numberLong'];
        if ($now < $act || $now >= $exp) continue;

        $id = (string)$act;
        if (get_state('darvo') === $id) return;
        set_state('darvo', $id);

        $item_name = 'Unknown item';
        // StoreItem path → try to resolve via dict; fallback to last path component
        $store_item = $deal['StoreItem'] ?? '';
        if ($store_item && $dict) {
            $item_name = $dict[$store_item] ?? basename($store_item);
        }
        $price = $deal['SalePrice'] ?? '?';

        foreach ($configs as $cfg) {
            if (!empty($cfg['events']['darvo'])) {
                dispatch($cfg, "browse.wf · Darvo's Deal", "Darvo sells $item_name for $price Platinum today.");
            }
        }
        return;
    }
}

function check_bounties(array $ws, array $configs): void {
    // All open-world bounty systems (Cetus, Orb Vallis, Deimos, Zariman,
    // Cavia, 1999) rotate on one synchronized ~150-minute cycle, so a single
    // notification fires whenever the next reset passes. Using the minimum
    // expiry across all syndicates stays correct even if DE desyncs them.
    // Ignore past expiries so a stale/lagging entry can't trigger a spurious fire.
    $now_ms = time() * 1000;
    $resets = [];
    foreach ($ws['SyndicateMissions'] ?? [] as $sm) {
        $exp = (int)($sm['Expiry']['$date']['$numberLong'] ?? 0);
        if ($exp > $now_ms) $resets[] = $exp;
    }
    if (!$resets) return;
    $next = (string)min($resets);
    if (get_state('bounty_cycle') === $next) return;
    set_state('bounty_cycle', $next);

    foreach ($configs as $cfg) {
        if (!empty($cfg['events']['bounties'])) {
            dispatch($cfg, 'browse.wf · Bounties', 'New bounties are available.');
        }
    }
}

function check_weekly(array $ws, array $configs): void {
    // Use litesortie weekly ID as proxy for the weekly reset
    $circuit = null;
    $now = time() * 1000;
    foreach ($ws['SeasonInfo']['Challenges'] ?? [] as $ch) {
        if (!empty($ch['isHard'])) continue;
        $act = (int)$ch['Activation']['$date']['$numberLong'];
        $exp = (int)$ch['Expiry']['$date']['$numberLong'];
        if ($now >= $act && $now < $exp) { $circuit = $ch; break; }
    }
    // Use the weekly expiry from LiteSorties as our clock
    $weekly_key = null;
    foreach ($ws['LiteSorties'] ?? [] as $ls) {
        $act = (int)$ls['Activation']['$date']['$numberLong'];
        $exp = (int)$ls['Expiry']['$date']['$numberLong'];
        if ($now >= $act && $now < $exp) { $weekly_key = (string)$exp; break; }
    }
    if (!$weekly_key) return;
    if (get_state('weekly') === $weekly_key) return;
    set_state('weekly', $weekly_key);

    foreach ($configs as $cfg) {
        $parts = [];
        if (!empty($cfg['events']['litesortie']))  $parts[] = 'Archon Hunt';
        if (!empty($cfg['events']['teshin']))       $parts[] = 'Vendors';
        if (!empty($cfg['events']['circuit']))      $parts[] = 'Weekly Missions';
        if (!empty($cfg['events']['labconquest']))  $parts[] = 'Deep Archimedea';
        if (!empty($cfg['events']['hexconquest']))  $parts[] = 'Temporal Archimedea';
        if ($parts) {
            dispatch($cfg, 'browse.wf · Weekly Reset', "It's a new week. " . implode(', ', $parts) . ' refreshed.');
        }
    }
}

function check_fissures(array $ws, array $configs): void {
    global $export_regions, $dict;
    $now  = time() * 1000;
    $seen = json_decode(get_state('seen_fissures') ?? '[]', true);
    $new_seen = [];

    $all_fissures = array_merge(
        array_map(fn($f) => array_merge($f, ['_cat' => ($f['Hard'] ?? false) ? 'sp' : 'normal']),
                  $ws['ActiveMissions'] ?? []),
        array_map(fn($f) => array_merge($f, ['_cat' => 'rj', 'Modifier' => $f['ActiveMissionTier'] ?? '']),
                  $ws['VoidStorms']     ?? [])
    );

    foreach ($all_fissures as $fissure) {
        $act = (int)$fissure['Activation']['$date']['$numberLong'];
        $exp = (int)$fissure['Expiry']['$date']['$numberLong'];
        if ($now < $act || $now >= $exp) continue;

        $id = $fissure['Node'] . '|' . $fissure['Modifier'] . '|' . $fissure['_cat'];
        $new_seen[] = $id;
        if (in_array($id, $seen)) continue;  // already dispatched

        $tier     = FISSURE_TIER_MAP[$fissure['Modifier']] ?? $fissure['Modifier'];
        $cat      = $fissure['_cat'];
        $node_key = $fissure['Node'];
        $node     = $export_regions[$node_key] ?? null;
        $mission_label = $node ? trim(preg_replace('/([A-Z])/', ' $1',
            substr($node['missionName'], strrpos($node['missionName'], 'MissionName_') + 12))) : '';
        $location = $node
            ? (($dict[$node['name']] ?? $node['name']) . ', ' . ($dict[$node['systemName']] ?? $node['systemName']))
            : $node_key;

        $type_label = $cat === 'sp'     ? 'Steel Path Fissure'
                    : ($cat === 'rj'    ? 'Void Storm (Railjack)'
                    :                     'Void Fissure');

        foreach ($configs as $cfg) {
            $filters = $cat === 'sp' ? $cfg['sp_fissure_filters'] : $cfg['fissure_filters'];
            if ($cat === 'rj') continue;  // no per-user filter for RJ yet; skip

            if (empty($filters['tiers']) && empty($filters['types'])) continue; // user has no fissure sub enabled
            if ($filters['tiers'] && !in_array($tier, $filters['tiers'])) continue;
            if ($filters['types'] && $mission_label && !in_array(trim($mission_label), $filters['types'])) continue;

            dispatch($cfg, "browse.wf · $type_label", "$tier · " . trim($mission_label) . " @ $location");
        }
    }

    set_state('seen_fissures', json_encode(array_values(array_unique($new_seen))));
}

function check_arbitration(array $configs): void {
    global $dict, $export_regions;

    // Arbitration schedule is in arbys.txt on the local server
    $arbys_raw = http_get('http://localhost/arbys.txt');
    if (!$arbys_raw) {
        log_msg('  [arby] could not load arbys.txt');
        return;
    }
    $arbys = [];
    foreach (explode("\n", trim($arbys_raw)) as $line) {
        $parts = explode(',', trim($line));
        if (count($parts) === 2) $arbys[] = [(int)$parts[0], trim($parts[1])];
    }
    if (!$arbys) return;

    $current_hour       = (int)(time() / 3600) * 3600;
    $epoch_hour         = $arbys[0][0];
    $current_hour_index = ($current_hour - $epoch_hour) / 3600;
    if (!isset($arbys[$current_hour_index])) return;

    $node_key = $arbys[$current_hour_index][1];
    $state_id = $node_key . '_' . $current_hour;
    if (get_state('arbitration') === $state_id) return;
    set_state('arbitration', $state_id);

    $node = $export_regions[$node_key] ?? null;
    $mission_label = $node
        ? trim(preg_replace('/([A-Z])/', ' $1',
            substr($node['missionName'], strrpos($node['missionName'], 'MissionName_') + 12)))
        : '';
    $location = $node
        ? (($dict[$node['name']] ?? $node['name']) . ', ' . ($dict[$node['systemName']] ?? $node['systemName']))
        : $node_key;

    foreach ($configs as $cfg) {
        $arby_filters = $cfg['arby_filters'];
        // If no filters set, check if any arby key is enabled at all
        // We overload the arby_filters: empty = always notify IF user also has some arby filter saved
        // Actually: if arby_filters is empty, user hasn't opted into arby notifs at all — skip.
        // To get arby notifs for all types, user should tick at least one; OR we check a dedicated event key.
        // Better: we treat arby as: opt-in requires at least one type ticked, OR event key "arby_all" set.
        // For simplicity: if arby_filters is empty = no arby notification for this user.
        if (empty($arby_filters)) continue;
        if (!in_array(trim($mission_label), $arby_filters)) continue;
        dispatch($cfg, 'browse.wf · Arbitration', trim($mission_label) . " @ $location");
    }
}

// ── Main loop ─────────────────────────────────────────────────────────────────
log_msg("notifyd starting. DB: " . NOTIF_DB_PATH . ", poll interval: {$POLL_INTERVAL}s");

// Initialise DB
get_db();

// Load static data once
log_msg('Loading ExportRegions…');
$export_regions = load_export_regions();
log_msg($export_regions ? 'ExportRegions loaded (' . count($export_regions) . ' nodes)' : 'ExportRegions unavailable');

log_msg("Loading dict ($DICT_LANG)…");
$dict = load_dict($DICT_LANG);
log_msg($dict ? 'Dict loaded (' . count($dict) . ' entries)' : 'Dict unavailable');

while (true) {
    $loop_start = time();

    $configs = get_active_configs();
    if (empty($configs)) {
        log_msg('No users with Apprise configured, sleeping.');
        sleep($POLL_INTERVAL);
        continue;
    }

    log_msg('Polling worldState (' . count($configs) . ' active user(s))…');
    $ws_raw = http_get('http://localhost/worldstate.php');
    if (!$ws_raw) {
        $err_msg = 'worldState fetch failed at ' . date('Y-m-d H:i:s');
        log_msg($err_msg . ', will retry.');
        daemon_set_error($err_msg);
        sleep($POLL_INTERVAL);
        continue;
    }

    $ws = json_decode($ws_raw, true);
    if (!$ws) {
        $err_msg = 'worldState JSON decode failed at ' . date('Y-m-d H:i:s');
        log_msg($err_msg);
        daemon_set_error($err_msg);
        sleep($POLL_INTERVAL);
        continue;
    }

    daemon_heartbeat();
    log_msg('Checking events…');
    check_sortie($ws, $configs);
    check_archon_hunt($ws, $configs);
    check_baro($ws, $configs);
    check_alerts($ws, $configs);
    check_darvo($ws, $configs);
    check_bounties($ws, $configs);
    check_weekly($ws, $configs);
    check_fissures($ws, $configs);
    check_arbitration($configs);

    $elapsed = time() - $loop_start;
    $sleep   = max(0, $POLL_INTERVAL - $elapsed);
    log_msg("Loop done in {$elapsed}s, sleeping {$sleep}s.");
    if ($sleep > 0) sleep($sleep);
}