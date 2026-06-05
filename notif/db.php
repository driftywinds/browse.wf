<?php
/**
 * notif/db.php — shared DB init + session helpers
 *
 * Storage is a single SQLite file at $NOTIF_DB_PATH (default /data/wf-notify.db).
 * Mount a Docker volume at /data to persist across container restarts.
 *
 * Tables
 * ──────
 *   users          id, username, password_hash, is_admin, created_at
 *   notif_config   user_id (FK), apprise_server, endpoints (JSON array of strings),
 *                  events (JSON object — same shape as before),
 *                  fissure_filters (JSON), sp_fissure_filters (JSON),
 *                  arby_filters (JSON array of strings)
 *   notif_state    key, value  — tracks last-seen IDs so the daemon doesn't double-fire
 *   notif_log      per-user dispatch history (title, body, ok/fail, ts)
 *   daemon_status   singleton row: last_poll, last_error
 */

define('NOTIF_DB_PATH', getenv('NOTIF_DB_PATH') ?: '/data/wf-notify.db');
define('SESSION_COOKIE', 'wf_session');

// ── PDO singleton ────────────────────────────────────────────────────────────
function get_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = dirname(NOTIF_DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . NOTIF_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    // Schema migration (idempotent)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
        password_hash TEXT    NOT NULL,
        is_admin      INTEGER NOT NULL DEFAULT 0,
        created_at    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notif_config (
        user_id           INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
        apprise_server    TEXT    NOT NULL DEFAULT '',
        endpoints         TEXT    NOT NULL DEFAULT '[]',
        events            TEXT    NOT NULL DEFAULT '{}',
        fissure_filters   TEXT    NOT NULL DEFAULT '{\"tiers\":[],\"types\":[]}',
        sp_fissure_filters TEXT   NOT NULL DEFAULT '{\"tiers\":[],\"types\":[]}',
        arby_filters      TEXT    NOT NULL DEFAULT '[]'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notif_state (
        key   TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notif_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        ts         INTEGER NOT NULL DEFAULT (strftime('%s','now')),
        title      TEXT    NOT NULL,
        body       TEXT    NOT NULL,
        ok         INTEGER NOT NULL DEFAULT 1,
        error_msg  TEXT    NOT NULL DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS notif_log_user_ts ON notif_log(user_id, ts DESC)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daemon_status (
        id         INTEGER PRIMARY KEY CHECK (id = 1),
        last_poll  INTEGER NOT NULL DEFAULT 0,
        last_error TEXT    NOT NULL DEFAULT ''
    )");
    $pdo->exec("INSERT OR IGNORE INTO daemon_status (id, last_poll) VALUES (1, 0)");

    return $pdo;
}

// ── Session helpers ──────────────────────────────────────────────────────────
function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_COOKIE);
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,  // 30 days
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => false,               // set to true if behind HTTPS
        ]);
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return null;
    $db = get_db();
    $st = $db->prepare('SELECT id, username, is_admin FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    return $st->fetch() ?: null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        // AJAX requests get a 401; normal requests redirect
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        header('Location: /login');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if (!$user['is_admin']) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Admin only']);
        exit;
    }
    return $user;
}

// ── User helpers ─────────────────────────────────────────────────────────────
function user_count(): int {
    return (int) get_db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function create_user(string $username, string $password, bool $is_admin = false): int {
    $db   = get_db();
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st   = $db->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?,?,?)');
    try {
        $st->execute([$username, $hash, $is_admin ? 1 : 0]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000' || strpos($e->getMessage(), 'UNIQUE') !== false) {
            throw new RuntimeException('Username already taken.');
        }
        throw $e;
    }
    $uid = (int) $db->lastInsertId();
    $db->prepare('INSERT OR IGNORE INTO notif_config (user_id) VALUES (?)')->execute([$uid]);
    return $uid;
}

function verify_user(string $username, string $password): ?array {
    $st = get_db()->prepare('SELECT id, username, password_hash, is_admin FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return null;
    unset($row['password_hash']);
    return $row;
}

// ── Config helpers ───────────────────────────────────────────────────────────
function get_user_config(int $user_id): array {
    $db = get_db();
    $db->prepare('INSERT OR IGNORE INTO notif_config (user_id) VALUES (?)')->execute([$user_id]);
    $st = $db->prepare('SELECT * FROM notif_config WHERE user_id = ?');
    $st->execute([$user_id]);
    $row = $st->fetch();
    return [
        'apprise_server'    => $row['apprise_server'],
        'endpoints'         => json_decode($row['endpoints'], true)  ?? [],
        'events'            => json_decode($row['events'], true)     ?? [],
        'fissure_filters'   => json_decode($row['fissure_filters'], true)    ?? ['tiers'=>[],'types'=>[]],
        'sp_fissure_filters'=> json_decode($row['sp_fissure_filters'], true) ?? ['tiers'=>[],'types'=>[]],
        'arby_filters'      => json_decode($row['arby_filters'], true) ?? [],
    ];
}

function save_user_config(int $user_id, array $cfg): void {
    $db = get_db();
    $db->prepare('INSERT OR IGNORE INTO notif_config (user_id) VALUES (?)')->execute([$user_id]);
    $st = $db->prepare('
        UPDATE notif_config SET
            apprise_server     = ?,
            endpoints          = ?,
            events             = ?,
            fissure_filters    = ?,
            sp_fissure_filters = ?,
            arby_filters       = ?
        WHERE user_id = ?
    ');
    $st->execute([
        $cfg['apprise_server']                       ?? '',
        json_encode($cfg['endpoints']                ?? []),
        json_encode($cfg['events']                   ?? (object)[]),
        json_encode($cfg['fissure_filters']          ?? ['tiers'=>[],'types'=>[]]),
        json_encode($cfg['sp_fissure_filters']       ?? ['tiers'=>[],'types'=>[]]),
        json_encode($cfg['arby_filters']             ?? []),
        $user_id,
    ]);
}

// ── Apprise send helper (used by daemon + test endpoint) ─────────────────────
function send_apprise(string $server_url, array $endpoints, string $title, string $body): array {
    $payload = ['title' => $title, 'body' => $body];
    if ($endpoints) {
        $payload['urls'] = implode("\n", $endpoints);
    }

    $target = rtrim($server_url, '/') . '/notify';
    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => $err];
    return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'body' => $response];
}

// ── State helpers (daemon dedup) ─────────────────────────────────────────────
function get_state(string $key): ?string {
    $st = get_db()->prepare('SELECT value FROM notif_state WHERE key = ?');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row ? $row['value'] : null;
}

function set_state(string $key, string $value): void {
    get_db()->prepare('INSERT OR REPLACE INTO notif_state (key, value) VALUES (?,?)')->execute([$key, $value]);
}

// ── Daemon status helpers (written by notifyd, read by status API) ───────────
function daemon_heartbeat(): void {
    get_db()->prepare(
        "UPDATE daemon_status SET last_poll = strftime('%s','now'), last_error = '' WHERE id = 1"
    )->execute();
}

function daemon_set_error(string $msg): void {
    get_db()->prepare(
        'UPDATE daemon_status SET last_error = ? WHERE id = 1'
    )->execute([$msg]);
}

function daemon_get_status(): array {
    $row = get_db()->query('SELECT last_poll, last_error FROM daemon_status WHERE id = 1')->fetch();
    return $row ?: ['last_poll' => 0, 'last_error' => 'No status yet'];
}

// ── Notification log helpers ──────────────────────────────────────────────────
function log_dispatch(int $user_id, string $title, string $body, bool $ok, string $error_msg = ''): void {
    get_db()->prepare(
        'INSERT INTO notif_log (user_id, title, body, ok, error_msg) VALUES (?,?,?,?,?)'
    )->execute([$user_id, $title, $body, $ok ? 1 : 0, $error_msg]);
    // Keep last 100 entries per user
    get_db()->prepare(
        'DELETE FROM notif_log WHERE user_id = ? AND id NOT IN
         (SELECT id FROM notif_log WHERE user_id = ? ORDER BY ts DESC LIMIT 100)'
    )->execute([$user_id, $user_id]);
}

function get_user_log(int $user_id, int $limit = 20): array {
    $st = get_db()->prepare(
        'SELECT ts, title, body, ok, error_msg FROM notif_log
         WHERE user_id = ? ORDER BY ts DESC LIMIT ?'
    );
    $st->execute([$user_id, $limit]);
    return $st->fetchAll();
}