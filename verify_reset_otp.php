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

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid OTP"
    ]);
    exit;
}

/* ---------- FETCH RESET OTP ---------- */
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
        "message" => "Invalid OTP"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- OTP CHECK ---------- */
if (
    empty($user["reset_otp"]) ||
    !hash_equals($user["reset_otp"], $otp)
) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid OTP"
    ]);
    exit;
}

/* ---------- EXPIRY CHECK ---------- */
if (strtotime($user["reset_otp_expiry"]) < time()) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "OTP expired"
    ]);
    exit;
}

/* ---------- SUCCESS ---------- */
echo json_encode([
    "status" => "success",
    "message" => "Reset OTP verified"
]);
