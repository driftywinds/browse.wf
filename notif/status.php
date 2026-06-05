<?php
// notif/status.php — GET: return daemon status + current user's notification log
require_once __DIR__ . '/../notif/db.php';
header('Content-Type: application/json');

$user = require_login();
$uid  = (int)$user['id'];

$daemon = daemon_get_status();
$log    = get_user_log($uid, 20);

// How stale is the last poll?
$last_poll = (int)$daemon['last_poll'];
$age_s     = $last_poll > 0 ? (time() - $last_poll) : -1;

// Daemon is considered "dead" if no heartbeat in 3× poll interval (default 180 s)
$poll_interval = (int)(getenv('POLL_INTERVAL') ?: 60);
$dead_threshold = $poll_interval * 3;
$daemon_ok = $last_poll > 0 && $age_s < $dead_threshold;

echo json_encode([
    'daemon' => [
        'ok'         => $daemon_ok,
        'last_poll'  => $last_poll,           // unix ts
        'age_s'      => $age_s,               // seconds since last successful poll
        'last_error' => $daemon['last_error'],
    ],
    'log' => array_map(fn($r) => [
        'ts'        => (int)$r['ts'],
        'title'     => $r['title'],
        'body'      => $r['body'],
        'ok'        => (bool)$r['ok'],
        'error_msg' => $r['error_msg'],
    ], $log),
]);