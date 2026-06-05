<?php
// notif/save.php — POST: save notification config for the current user
require_once __DIR__ . '/../notif/db.php';
header('Content-Type: application/json');

$user = require_login();

$raw = file_get_contents('php://input');
$cfg = json_decode($raw, true);

if (!is_array($cfg)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate Apprise server URL if provided
$server = trim($cfg['apprise_server'] ?? '');
if ($server && !filter_var($server, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Apprise server URL']);
    exit;
}

// Sanitise endpoints — must be non-empty strings
$endpoints = array_values(array_filter(
    array_map('trim', (array)($cfg['endpoints'] ?? [])),
    fn($s) => $s !== ''
));

// Sanitise events — only known keys, boolean values
$allowed_events = [
    'nightfall','news','darvo','sortie','litesortie','baro',
    'alerts','bounties','teshin','circuit','labconquest','hexconquest',
];
$events = [];
foreach ($allowed_events as $k) {
    if (!empty($cfg['events'][$k])) $events[$k] = true;
}

$allowed_tiers = ['Lith','Meso','Neo','Axi','Requiem','Omnia'];
$allowed_types = [
    'Assassination','Assault','Capture','Crossfire','Defense',
    'Disruption','Excavation','Exterminate','Hijack','Hive',
    'Infested Salvage','Interception','Mobile Defense','Orphix',
    'Pursuit','Rescue','Rush','Sabotage','Skirmish','Spy',
    'Survival','Volatile',
];

function sanitise_filter(array $raw_filter, array $ok_tiers, array $ok_types): array {
    return [
        'tiers' => array_values(array_intersect((array)($raw_filter['tiers'] ?? []), $ok_tiers)),
        'types' => array_values(array_intersect((array)($raw_filter['types'] ?? []), $ok_types)),
    ];
}

$clean = [
    'apprise_server'    => $server,
    'endpoints'         => $endpoints,
    'events'            => $events,
    'fissure_filters'   => sanitise_filter($cfg['fissure_filters']    ?? [], $allowed_tiers, $allowed_types),
    'sp_fissure_filters'=> sanitise_filter($cfg['sp_fissure_filters'] ?? [], $allowed_tiers, $allowed_types),
    'arby_filters'      => array_values(array_intersect((array)($cfg['arby_filters'] ?? []), $allowed_types)),
];

save_user_config((int)$user['id'], $clean);
echo json_encode(['ok' => true]);
