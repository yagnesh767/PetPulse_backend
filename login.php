<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$email    = strtolower(trim($data["email"] ?? ""));
$password = $data["password"] ?? "";

/* ---------- VALIDATION ---------- */
if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid credentials"
    ]);
    exit;
}

/* ---------- FETCH USER ---------- */
$stmt = $conn->prepare(
    "SELECT id, full_name, password, is_verified, is_premium
     FROM users
     WHERE email = ?"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Do NOT reveal account existence
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid credentials"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- PASSWORD CHECK ---------- */
if (!password_verify($password, $user["password"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid credentials"
    ]);
    exit;
}

/* ---------- EMAIL VERIFICATION CHECK ---------- */
if ((int)$user["is_verified"] === 0) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Email not verified"
    ]);
    exit;
}

/* ---------- SUCCESS ---------- */
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "data" => [
        "user_id"    => (int) $user["id"],
        "full_name"  => $user["full_name"],
        "is_premium" => (int) $user["is_premium"]
    ]
]);
