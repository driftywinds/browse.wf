<?php
require_once __DIR__ . '/notif/db.php';
session_start_safe();
session_destroy();
header('Location: /login');
exit;
