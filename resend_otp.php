<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);
$email = strtolower(trim($data["email"] ?? ""));

/* ---------- VALIDATION ---------- */
if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

/* ---------- FETCH USER ---------- */
$stmt = $conn->prepare(
    "SELECT is_verified, otp_expiry 
     FROM users 
     WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

/* Do NOT reveal if user exists */
if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "success",
        "message" => "If the email exists, an OTP has been sent"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- BLOCK VERIFIED USERS ---------- */
if ((int)$user["is_verified"] === 1) {
    echo json_encode([
        "status" => "success",
        "message" => "Email already verified"
    ]);
    exit;
}

/* ---------- RATE LIMIT (OTP SPAM PROTECTION) ---------- */
if (!empty($user["otp_expiry"])) {
    $remaining = strtotime($user["otp_expiry"]) - time();
    if ($remaining > 7 * 60) { // less than 3 minutes since last OTP
        http_response_code(429);
        echo json_encode([
            "status" => "error",
            "message" => "Please wait before requesting another OTP"
        ]);
        exit;
    }
}

/* ---------- GENERATE NEW OTP ---------- */
$newOtp     = (string) random_int(100000, 999999);
$newExpiry  = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$update = $conn->prepare(
    "UPDATE users 
     SET otp_code = ?, otp_expiry = ? 
     WHERE email = ?"
);
$update->bind_param("sss", $newOtp, $newExpiry, $email);
$update->execute();

/* ---------- SEND OTP ---------- */
sendOTP($email, $newOtp, "signup");

/* ---------- RESPONSE ---------- */
echo json_encode([
    "status" => "success",
    "message" => "If the email exists, an OTP has been sent"
]);
