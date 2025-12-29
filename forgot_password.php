<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

/* ---------- INPUT ---------- */
$data  = json_decode(file_get_contents("php://input"), true);
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

/* ---------- FETCH USER (NO ENUMERATION) ---------- */
$stmt = $conn->prepare(
    "SELECT is_verified, reset_otp_expiry
     FROM users
     WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

/* Always respond success (even if email doesn't exist) */
if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "success",
        "message" => "If the email exists, a reset OTP has been sent"
    ]);
    exit;
}

$user = $result->fetch_assoc();

/* ---------- BLOCK UNVERIFIED ACCOUNTS ---------- */
if ((int)$user["is_verified"] === 0) {
    echo json_encode([
        "status" => "success",
        "message" => "If the email exists, a reset OTP has been sent"
    ]);
    exit;
}

/* ---------- RATE LIMIT RESET OTP ---------- */
if (!empty($user["reset_otp_expiry"])) {
    $remaining = strtotime($user["reset_otp_expiry"]) - time();
    if ($remaining > 7 * 60) { // prevent OTP spam
        http_response_code(429);
        echo json_encode([
            "status" => "error",
            "message" => "Please wait before requesting another reset OTP"
        ]);
        exit;
    }
}

/* ---------- GENERATE RESET OTP ---------- */
$resetOtp     = (string) random_int(100000, 999999);
$resetExpiry  = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$update = $conn->prepare(
    "UPDATE users
     SET reset_otp = ?, reset_otp_expiry = ?
     WHERE email = ?"
);
$update->bind_param("sss", $resetOtp, $resetExpiry, $email);
$update->execute();

/* ---------- SEND OTP ---------- */
sendOTP($email, $resetOtp, "reset");

/* ---------- RESPONSE ---------- */
echo json_encode([
    "status" => "success",
    "message" => "If the email exists, a reset OTP has been sent"
]);
