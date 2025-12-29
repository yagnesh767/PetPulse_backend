<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$name    = trim($data['name'] ?? '');
$species = $data['species'] ?? '';
$breed   = trim($data['breed'] ?? '');
$age     = $data['age'] ?? null;
$weight  = $data['weight'] ?? null;
$gender  = $data['gender'] ?? '';

if (!$user_id || $name === '' || $species === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Required fields missing"
    ]);
    exit;
}

$allowed_species = ['Dog', 'Cat', 'Other'];
$allowed_gender  = ['Male', 'Female', ''];

if (!in_array($species, $allowed_species)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid species"
    ]);
    exit;
}

if ($gender !== '' && !in_array($gender, $allowed_gender)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid gender"
    ]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO pets (user_id, name, species, breed, age, weight, gender)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssids",
    $user_id,
    $name,
    $species,
    $breed,
    $age,
    $weight,
    $gender
);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Pet added successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to add pet"
    ]);
}
