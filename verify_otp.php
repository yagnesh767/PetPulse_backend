<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$email = strtolower(trim($data["email"] ?? ""));
$otp   = trim($data["otp"] ?? "");

/* ---------- VALIDATION ---------- */
if ($email === "" || $otp === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Email and OTP are required"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format"
    ]);
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "OTP must be 6 digits"
    ]);
    exit;
}

/* ---------- FETCH OTP ---------- */
$stmt = $conn->prepare(
    "SELECT otp_code, otp_expiry, is_verified
     FROM users
     WHERE email = ?"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error"
    ]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Do not reveal account existence
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid OTP"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- ALREADY VERIFIED ---------- */
if ((int)$user["is_verified"] === 1) {
    echo json_encode([
        "status" => "success",
        "message" => "Email already verified"
    ]);
    exit;
}

/* ---------- OTP CHECK ---------- */
if (
    empty($user["otp_code"]) ||
    !hash_equals($user["otp_code"], $otp)
) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid OTP"
    ]);
    exit;
}

/* ---------- EXPIRY CHECK ---------- */
if (strtotime($user["otp_expiry"]) < time()) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "OTP expired"
    ]);
    exit;
}

/* ---------- VERIFY USER ---------- */
$update = $conn->prepare(
    "UPDATE users
     SET is_verified = 1,
         otp_code = NULL,
         otp_expiry = NULL
     WHERE email = ?"
);

if (!$update) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Verification failed"
    ]);
    exit;
}

$update->bind_param("s", $email);
$update->execute();

/* ---------- SUCCESS ---------- */
echo json_encode([
    "status" => "success",
    "message" => "Email verified successfully"
]);
