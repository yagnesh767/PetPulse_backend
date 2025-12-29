<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$email       = strtolower(trim($data["email"] ?? ""));
$newPassword = $data["new_password"] ?? "";

/* ---------- VALIDATION ---------- */
if ($email === "" || $newPassword === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 6 characters"
    ]);
    exit;
}

/* ---------- VERIFY RESET OTP EXISTS ---------- */
$stmt = $conn->prepare(
    "SELECT reset_otp, reset_otp_expiry
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

/* Do NOT reveal account existence */
if ($result->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- ENSURE OTP WAS VERIFIED ---------- */
if (
    empty($user["reset_otp"]) ||
    strtotime($user["reset_otp_expiry"]) < time()
) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Reset OTP not verified or expired"
    ]);
    exit;
}

/* ---------- UPDATE PASSWORD ---------- */
$newHash = password_hash($newPassword, PASSWORD_BCRYPT);

$update = $conn->prepare(
    "UPDATE users
     SET password = ?,
         reset_otp = NULL,
         reset_otp_expiry = NULL
     WHERE email = ?"
);

if (!$update) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Password reset failed"
    ]);
    exit;
}

$update->bind_param("ss", $newHash, $email);
$update->execute();

/* ---------- SUCCESS ---------- */
echo json_encode([
    "status" => "success",
    "message" => "Password reset successful"
]);
