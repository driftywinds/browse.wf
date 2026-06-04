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

function getLoggedInUser(): ?array
{
    if (!isset($_SESSION["user_id"])) {
        return null;
    }
    $db = loadUsers();
    foreach ($db["users"] as $user) {
        if ($user["id"] === $_SESSION["user_id"]) {
            return $user;
        }
    }
    return null;
}

if ($method === "POST" && $action === "register") {
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

    $isFirstUser = count($db["users"]) === 0;
    $newUser = [
        "id" => $db["next_id"],
        "username" => $username,
        "password_hash" => password_hash($password, PASSWORD_DEFAULT),
        "is_admin" => $isFirstUser,
        "created_at" => time(),
    ];
    $db["users"][] = $newUser;
    $db["next_id"]++;
    saveUsers($db);

    $_SESSION["user_id"] = $newUser["id"];

    jsonResponse([
        "success" => true,
        "message" => $isFirstUser ? "Admin account created." : "Account created.",
        "user" => [
            "id" => $newUser["id"],
            "username" => $newUser["username"],
            "is_admin" => $newUser["is_admin"],
        ]
    ]);
}

if ($method === "POST" && $action === "login") {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input["username"] ?? "");
    $password = $input["password"] ?? "";

    $db = loadUsers();
    foreach ($db["users"] as $user) {
        if (strtolower($user["username"]) === strtolower($username)) {
            if (password_verify($password, $user["password_hash"])) {
                $_SESSION["user_id"] = $user["id"];
                jsonResponse([
                    "success" => true,
                    "message" => "Logged in.",
                    "user" => [
                        "id" => $user["id"],
                        "username" => $user["username"],
                        "is_admin" => $user["is_admin"],
                    ]
                ]);
            }
            jsonResponse(["success" => false, "message" => "Invalid password."], 401);
        }
    }
    jsonResponse(["success" => false, "message" => "User not found."], 404);
}

if ($method === "POST" && $action === "logout") {
    $_SESSION = [];
    session_destroy();
    jsonResponse(["success" => true, "message" => "Logged out."]);
}

if ($method === "GET" && $action === "session") {
    $user = getLoggedInUser();
    if ($user) {
        jsonResponse([
            "logged_in" => true,
            "user" => [
                "id" => $user["id"],
                "username" => $user["username"],
                "is_admin" => $user["is_admin"],
            ]
        ]);
    }
    jsonResponse(["logged_in" => false, "user" => null]);
}

jsonResponse(["success" => false, "message" => "Unknown action."], 400);
