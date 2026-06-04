<?php
session_start();

$method = $_SERVER["REQUEST_METHOD"];
$action = $_GET["action"] ?? "";

function jsonResponse($data, $code = 200): void
{
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

function requireAuth(): array
{
    if (!isset($_SESSION["user_id"])) {
        jsonResponse(["success" => false, "message" => "Not authenticated."], 401);
    }
    $path = __DIR__ . "/../data/users.json";
    $db = json_decode(file_get_contents($path), true);
    foreach ($db["users"] as $user) {
        if ($user["id"] === $_SESSION["user_id"]) {
            return $user;
        }
    }
    jsonResponse(["success" => false, "message" => "User not found."], 401);
    exit;
}

function loadConfigs(): array
{
    $path = __DIR__ . "/../data/notification-configs.json";
    if (!file_exists($path)) {
        return ["configs" => [], "last_notified" => []];
    }
    return json_decode(file_get_contents($path), true) ?? ["configs" => [], "last_notified" => []];
}

function saveConfigs(array $data): void
{
    $path = __DIR__ . "/../data/notification-configs.json";
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Returns a default notification config for a given user.
 */
function defaultConfig(): array
{
    return [
        "apprise_server_url" => "",
        "endpoints" => "",
        "subscriptions" => [
            "sortie" => false,
            "litesortie" => false,
            "alert" => false,
            "darvo" => false,
            "baro" => false,
            "bounties" => false,
            "nightfall" => false,
            "news" => false,
            "teshin" => false,
            "circuit" => false,
            "labconquest" => false,
            "hexconquest" => false,
            "invasions" => false,
            "weekly" => false,
            "fissures" => [
                "enabled" => false,
                "mission_types" => [],
                "tiers" => [],
            ],
            "arbys" => [
                "enabled" => false,
                "mission_types" => [],
            ],
        ],
    ];
}

if ($method === "GET" && $action === "config") {
    $user = requireAuth();
    $db = loadConfigs();
    $uid = (string)$user["id"];
    $config = $db["configs"][$uid] ?? defaultConfig();
    jsonResponse(["success" => true, "config" => $config, "username" => $user["username"]]);
}

if ($method === "POST" && $action === "config") {
    $user = requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);
    $db = loadConfigs();
    $uid = (string)$user["id"];

    $config = $input["config"] ?? [];
    // Merge with defaults to ensure all keys exist
    $defaults = defaultConfig();
    $config["apprise_server_url"] = $config["apprise_server_url"] ?? $defaults["apprise_server_url"];
    $config["endpoints"] = $config["endpoints"] ?? $defaults["endpoints"];
    $config["subscriptions"] = array_merge($defaults["subscriptions"], $config["subscriptions"] ?? []);
    if (is_array($config["subscriptions"]["fissures"] ?? null)) {
        $config["subscriptions"]["fissures"] = array_merge($defaults["subscriptions"]["fissures"], $config["subscriptions"]["fissures"]);
    }
    if (is_array($config["subscriptions"]["arbys"] ?? null)) {
        $config["subscriptions"]["arbys"] = array_merge($defaults["subscriptions"]["arbys"], $config["subscriptions"]["arbys"]);
    }

    $db["configs"][$uid] = $config;
    saveConfigs($db);
    jsonResponse(["success" => true, "message" => "Config saved."]);
}

if ($method === "POST" && $action === "test") {
    $user = requireAuth();
    $db = loadConfigs();
    $uid = (string)$user["id"];
    $config = $db["configs"][$uid] ?? defaultConfig();

    $appriseUrl = $config["apprise_server_url"] ?? "";
    if (empty($appriseUrl)) {
        jsonResponse(["success" => false, "message" => "No Apprise server URL configured."], 400);
    }

    $endpoints = $config["endpoints"] ?? "";
    $payload = [
        "body" => "🧪 Test notification from browse.wf — if you see this, your notification setup works!",
        "title" => "browse.wf Test",
        "type" => "success",
        "format" => "text",
    ];
    if (!empty($endpoints)) {
        $payload["urls"] = $endpoints;
    }

    $result = sendAppriseNotification($appriseUrl, $payload);
    if ($result["success"]) {
        jsonResponse(["success" => true, "message" => "Test notification sent successfully!"]);
    } else {
        jsonResponse(["success" => false, "message" => "Failed to send: " . $result["error"]], 502);
    }
}

if ($method === "POST" && $action === "send") {
    $user = requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);
    $eventType = $input["event_type"] ?? "";
    $title = $input["title"] ?? "browse.wf";
    $body = $input["body"] ?? "";
    $details = $input["details"] ?? "";

    if (empty($eventType) || empty($body)) {
        jsonResponse(["success" => false, "message" => "Missing event_type or body."], 400);
    }

    $db = loadConfigs();
    $uid = (string)$user["id"];
    $config = $db["configs"][$uid] ?? null;

    if (!$config || empty($config["apprise_server_url"])) {
        jsonResponse(["sent" => false, "reason" => "No Apprise server configured."]);
    }

    // Check if subscribed to this event
    $subs = $config["subscriptions"] ?? [];
    $isSubscribed = false;

    // Simple event types
    $simpleEvents = ["sortie", "litesortie", "alert", "darvo", "baro", "bounties", "nightfall", "news", "teshin", "circuit", "labconquest", "hexconquest", "invasions", "weekly"];
    if (in_array($eventType, $simpleEvents)) {
        $isSubscribed = !empty($subs[$eventType]);
    }

    // Granular fissure event
    if ($eventType === "fissure") {
        if (!empty($subs["fissures"]["enabled"])) {
            $fissureMissionType = $input["mission_type"] ?? "";
            $fissureTier = $input["tier"] ?? "";
            $missionTypes = $subs["fissures"]["mission_types"] ?? [];
            $tiers = $subs["fissures"]["tiers"] ?? [];
            $matchesMission = empty($missionTypes) || in_array($fissureMissionType, $missionTypes);
            $matchesTier = empty($tiers) || in_array($fissureTier, $tiers);
            $isSubscribed = $matchesMission && $matchesTier;
        }
    }

    // Granular arbitration event
    if ($eventType === "arby") {
        if (!empty($subs["arbys"]["enabled"])) {
            $arbyMissionType = $input["mission_type"] ?? "";
            $missionTypes = $subs["arbys"]["mission_types"] ?? [];
            $isSubscribed = empty($missionTypes) || in_array($arbyMissionType, $missionTypes);
        }
    }

    if (!$isSubscribed) {
        jsonResponse(["sent" => false, "reason" => "Not subscribed to this event."]);
    }

    // Check if already notified recently (avoid duplicates)
    $lastNotified = $db["last_notified"][$uid][$eventType] ?? 0;
    $now = time();
    // Don't re-notify the same event type within 5 minutes
    if ($now - $lastNotified < 300) {
        jsonResponse(["sent" => false, "reason" => "Already notified recently."]);
    }

    $appriseUrl = $config["apprise_server_url"];
    $endpoints = $config["endpoints"] ?? "";

    $payload = [
        "body" => $body,
        "title" => $title,
        "type" => "info",
        "format" => "text",
    ];
    if (!empty($endpoints)) {
        $payload["urls"] = $endpoints;
    }

    $result = sendAppriseNotification($appriseUrl, $payload);
    if ($result["success"]) {
        // Update last notified timestamp
        if (!isset($db["last_notified"][$uid])) {
            $db["last_notified"][$uid] = [];
        }
        $db["last_notified"][$uid][$eventType] = $now;
        saveConfigs($db);
        jsonResponse(["sent" => true, "message" => "Notification sent."]);
    } else {
        jsonResponse(["sent" => false, "error" => $result["error"]]);
    }
}

function sendAppriseNotification(string $serverUrl, array $payload): array
{
    $url = rtrim($serverUrl, "/") . "/notify/";

    $jsonPayload = json_encode($payload);
    $context = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\nContent-Length: " . strlen($jsonPayload) . "\r\n",
            "content" => $jsonPayload,
            "timeout" => 10,
            "ignore_errors" => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ["success" => false, "error" => "Could not reach Apprise server."];
    }

    $httpCode = 0;
    if (isset($http_response_header)) {
        preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches);
        $httpCode = (int)($matches[1] ?? 0);
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ["success" => true];
    }

    return ["success" => false, "error" => "Apprise returned HTTP $httpCode: " . substr($response, 0, 200)];
}

jsonResponse(["success" => false, "message" => "Unknown action."], 400);
