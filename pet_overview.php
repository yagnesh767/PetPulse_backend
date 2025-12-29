<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$pet_id  = $data['pet_id'] ?? null;

if (!$user_id || !$pet_id) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID and Pet ID are required"
    ]);
    exit;
}

/* STEP 1: Verify pet belongs to user */
$stmt = $conn->prepare("
    SELECT id, name, species, breed, age, weight, gender, photo
    FROM pets
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $pet_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Pet not found"
    ]);
    exit;
}

$pet = $result->fetch_assoc();

/* STEP 2: Placeholder stats (will expand later) */
$overview = [
    "pet" => $pet,
    "last_activity" => null,
    "last_weight" => $pet['weight'],
    "health_status" => "Normal",
    "alerts" => []
];

echo json_encode([
    "status" => "success",
    "overview" => $overview
]);
