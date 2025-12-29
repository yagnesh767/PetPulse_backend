<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id  = $data['user_id'] ?? null;
$pet_id   = $data['pet_id'] ?? null;
$severity = $data['severity'] ?? null;

if (!$user_id || !$pet_id || $severity !== 'High') {
    echo json_encode([
        "status" => "ignored",
        "message" => "AI agent not triggered"
    ]);
    exit;
}

/* STEP 1: Pick nearest vet (simple fallback logic) */
$vet = $conn->query("
    SELECT id, name, phone, address
    FROM vets
    WHERE verified = 1
    LIMIT 1
")->fetch_assoc();

if (!$vet) {
    echo json_encode([
        "status" => "error",
        "message" => "No vet available"
    ]);
    exit;
}

/* STEP 2: Create draft appointment */
$stmt = $conn->prepare("
    INSERT INTO appointments (user_id, pet_id, vet_id)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iii", $user_id, $pet_id, $vet['id']);
$stmt->execute();

$appointment_id = $stmt->insert_id;

/* STEP 3: Respond with emergency action */
echo json_encode([
    "status" => "triggered",
    "emergency" => true,
    "message" => "High severity detected. Immediate vet attention recommended.",
    "vet" => $vet,
    "appointment_id" => $appointment_id
]);
