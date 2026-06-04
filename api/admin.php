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

function requireAdmin(): array
{
    if (!isset($_SESSION["user_id"])) {
        jsonResponse(["success" => false, "message" => "Not authenticated."], 401);
    }
    $path = __DIR__ . "/../data/users.json";
    $db = json_decode(file_get_contents($path), true);
    foreach ($db["users"] as $user) {
        if ($user["id"] === $_SESSION["user_id"]) {
            if ($user["is_admin"]) {
                return $user;
            }
            jsonResponse(["success" => false, "message" => "Admin access required."], 403);
        }
    }
    jsonResponse(["success" => false, "message" => "User not found."], 401);
    exit;
}

function loadUsers(): array
{
    $path = __DIR__ . "/../data/users.json";
    if (!file_exists($path)) {
        return ["users" => [], "next_id" => 1];
    }
    return json_decode(file_get_contents($path), true) ?? ["users" => [], "next_id" => 1];
}

function saveUsers(array $data): void
{
    $path = __DIR__ . "/../data/users.json";
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

if ($method === "GET" && $action === "users") {
    $admin = requireAdmin();
    $db = loadUsers();
    // Don't expose password hashes
    $safeUsers = array_map(function ($u) {
        return [
            "id" => $u["id"],
            "username" => $u["username"],
            "is_admin" => $u["is_admin"],
            "created_at" => $u["created_at"],
        ];
    }, $db["users"]);
    jsonResponse(["success" => true, "users" => $safeUsers]);
}

if ($method === "POST" && $action === "create_user") {
    $admin = requireAdmin();
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input["username"] ?? "");
    $password = $input["password"] ?? "";

    if (strlen($username) < 2 || strlen($username) > 32) {
        jsonResponse(["success" => false, "message" => "Username must be 2-32 characters."], 400);
    }
    if (strlen($password) < 4) {
        jsonResponse(["success" => false, "message" => "Password must be at least 4 characters."], 400);
    }

    $db = loadUsers();
    foreach ($db["users"] as $user) {
        if (strtolower($user["username"]) === strtolower($username)) {
            jsonResponse(["success" => false, "message" => "Username already taken."], 409);
        }
    }

    $newUser = [
        "id" => $db["next_id"],
        "username" => $username,
        "password_hash" => password_hash($password, PASSWORD_DEFAULT),
        "is_admin" => false,
        "created_at" => time(),
    ];
    $db["users"][] = $newUser;
    $db["next_id"]++;
    saveUsers($db);

    jsonResponse(["success" => true, "message" => "User created."]);
}

if ($method === "POST" && $action === "delete_user") {
    $admin = requireAdmin();
    $input = json_decode(file_get_contents("php://input"), true);
    $userId = (int)($input["user_id"] ?? 0);

    if ($userId === $admin["id"]) {
        jsonResponse(["success" => false, "message" => "Cannot delete yourself."], 400);
    }

    $db = loadUsers();
    $found = false;
    foreach ($db["users"] as $i => $user) {
        if ($user["id"] === $userId) {
            array_splice($db["users"], $i, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        jsonResponse(["success" => false, "message" => "User not found."], 404);
    }

    saveUsers($db);

    // Also clean up their notification config
    $configPath = __DIR__ . "/../data/notification-configs.json";
    if (file_exists($configPath)) {
        $configDb = json_decode(file_get_contents($configPath), true);
        $uid = (string)$userId;
        if (isset($configDb["configs"][$uid])) {
            unset($configDb["configs"][$uid]);
        }
        if (isset($configDb["last_notified"][$uid])) {
            unset($configDb["last_notified"][$uid]);
        }
        file_put_contents($configPath, json_encode($configDb, JSON_PRETTY_PRINT));
    }

    jsonResponse(["success" => true, "message" => "User deleted."]);
}

jsonResponse(["success" => false, "message" => "Unknown action."], 400);
