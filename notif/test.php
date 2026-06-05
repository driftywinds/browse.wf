<?php
// notif/test.php — POST: send a test notification using the current user's saved config
require_once __DIR__ . '/../notif/db.php';
header('Content-Type: application/json');

$user = require_login();
$cfg  = get_user_config((int)$user['id']);

if (empty($cfg['apprise_server'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No Apprise server URL configured. Save your settings first.']);
    exit;
}

$result = send_apprise(
    $cfg['apprise_server'],
    $cfg['endpoints'],
    'browse.wf · Test',
    'This is a test notification from browse.wf — Apprise is working! 🔔'
);

echo json_encode($result);
