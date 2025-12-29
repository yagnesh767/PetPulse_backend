<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id        = $data['user_id'] ?? null;
$appointment_id = $data['appointment_id'] ?? null;
$action         = $data['action'] ?? null; // confirm | cancel

if (!$user_id || !$appointment_id || !in_array($action, ['confirm', 'cancel'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);
    exit;
}

/* STEP 1: Verify appointment belongs to user */
$stmt = $conn->prepare("
    SELECT id, status
    FROM appointments
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Appointment not found"
    ]);
    exit;
}

$row = $result->fetch_assoc();

if ($row['status'] !== 'Draft') {
    echo json_encode([
        "status" => "error",
        "message" => "Appointment already processed"
    ]);
    exit;
}

/* STEP 2: Update status */
$new_status = $action === 'confirm' ? 'Confirmed' : 'Cancelled';

$update = $conn->prepare("
    UPDATE appointments
    SET status = ?
    WHERE id = ?
");
$update->bind_param("si", $new_status, $appointment_id);
$update->execute();

/* STEP 3: Response */
echo json_encode([
    "status" => "success",
    "appointment_status" => $new_status,
    "message" => $action === 'confirm'
        ? "Appointment confirmed successfully"
        : "Appointment cancelled"
]);
