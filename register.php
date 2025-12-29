<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/mailer.php";

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$full_name = trim($data["full_name"] ?? "");
$email     = strtolower(trim($data["email"] ?? ""));
$password  = $data["password"] ?? "";

/* ---------- VALIDATION ---------- */
if ($full_name === "" || $email === "" || $password === "") {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required"
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

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 6 characters"
    ]);
    exit;
}

/* ---------- DUPLICATE CHECK ---------- */
$check = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $existing = $result->fetch_assoc();

    if ($existing["is_verified"] == 1) {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Email already registered"
        ]);
        exit;
    } else {
        http_response_code(409);
        echo json_encode([
            "status" => "error",
            "message" => "Account exists but not verified. Please verify OTP."
        ]);
        exit;
    }
}

/* ---------- CREATE USER ---------- */
$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$otp          = (string) random_int(100000, 999999);
$otpExpiry    = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$insert = $conn->prepare(
    "INSERT INTO users (full_name, email, password, otp_code, otp_expiry, is_verified)
     VALUES (?, ?, ?, ?, ?, 0)"
);
$insert->bind_param("sssss", $full_name, $email, $passwordHash, $otp, $otpExpiry);

if (!$insert->execute()) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Unable to create account"
    ]);
    exit;
}

/* ---------- SEND OTP ---------- */
$sent = sendOTP($email, $otp, "signup");

if (!$sent) {
    // Rollback safety: user exists but OTP not sent
    $cleanup = $conn->prepare("DELETE FROM users WHERE email = ?");
    if ($cleanup) {
        $cleanup->bind_param("s", $email);
        $cleanup->execute();
    }

    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "OTP sending failed. Please try again."
    ]);
    exit;
}

/* ---------- SUCCESS ---------- */
echo json_encode([
    "status" => "success",
    "message" => "OTP sent to email. Please verify to continue."
]);
