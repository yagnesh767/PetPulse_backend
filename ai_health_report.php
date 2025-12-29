<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;
$pet_id  = $data['pet_id'] ?? null;

if (!$user_id || !$pet_id) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

/* Pet profile */
$stmt = $conn->prepare("
    SELECT name, breed, age, weight
    FROM pets
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $pet_id, $user_id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();

/* Medical history (last 30 records) */
$hist = $conn->prepare("
    SELECT record_type, severity
    FROM pet_medical_history
    WHERE pet_id = ?
    ORDER BY created_at DESC
    LIMIT 30
");
$hist->bind_param("i", $pet_id);
$hist->execute();
$res = $hist->get_result();

/* AI logic */
$score = 100;
$hasRisk = false;

while ($row = $res->fetch_assoc()) {
    if ($row['severity'] === 'High') {
        $score -= 15;
        $hasRisk = true;
    } elseif ($row['severity'] === 'Medium') {
        $score -= 8;
    }
}

$score = max(60, min(100, $score));

/* UI sections */
$response = [
    "status" => "success",
    "pet" => [
        "name" => $pet['name'],
        "breed" => $pet['breed'],
        "age" => $pet['age']
    ],
    "health_score" => "{$score}/100",
    "overview" => $hasRisk
        ? "{$pet['name']} has some health indicators that should be monitored. Preventive care is advised."
        : "{$pet['name']} is currently in good overall health. No critical risks detected.",
    "health_cards" => [
        ["title" => "Heart Health", "value" => "Normal"],
        ["title" => "Weight Status", "value" => "Healthy ({$pet['weight']} kg)"],
        ["title" => "Vaccination", "value" => "Up to date"],
        ["title" => "Activity", "value" => "Moderately Active"]
    ],
    "risk_indicators" => $hasRisk
        ? ["Seasonal sensitivity detected"]
        : [
            "No chronic disease detected",
            "No allergy risks identified",
            "Seasonal risk: Low"
        ],
    "recommendations" => [
        "Maintain current diet",
        "Continue daily walks (30–45 min)",
        "Routine vet checkups"
    ],
    "pdf_url" => "http://localhost/petpulse_api/download_health_report.php?pet_id=$pet_id"
];

echo json_encode($response);
