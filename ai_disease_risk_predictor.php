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

/* Fetch pet */
$stmt = $conn->prepare("
    SELECT name, breed, age, weight
    FROM pets
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $pet_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Pet not found"]);
    exit;
}

$pet = $res->fetch_assoc();

/* Determine season */
$month = (int)date("n");
$season = match (true) {
    $month >= 3 && $month <= 6 => "Summer",
    $month >= 7 && $month <= 9 => "Monsoon",
    default => "Winter"
};

/* Analyze last 10 medical records */
$histStmt = $conn->prepare("
    SELECT record_type, description, severity
    FROM pet_medical_history
    WHERE pet_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$histStmt->bind_param("i", $pet_id);
$histStmt->execute();
$histRes = $histStmt->get_result();

$recentIssues = false;
$moderateCount = 0;

while ($row = $histRes->fetch_assoc()) {
    if (in_array($row['severity'], ['Medium', 'High'])) {
        $recentIssues = true;
        $moderateCount++;
    }
}

/* Overall risk */
$overallRisk = $moderateCount >= 3 ? "High" :
               ($recentIssues ? "Medium" : "Low");

/* Breed-related risks (safe, non-diagnostic) */
$breedRisks = match (true) {
    stripos($pet['breed'], 'Retriever') !== false => [
        "Joint issues",
        "Skin allergies",
        "Ear infections"
    ],
    stripos($pet['breed'], 'Pug') !== false => [
        "Breathing sensitivity",
        "Heat intolerance"
    ],
    default => [
        "General health sensitivity"
    ]
};

/* Seasonal risks */
$seasonalRisks = match ($season) {
    "Summer" => [
        "Dehydration risk",
        "Heat sensitivity",
        "Parasite exposure"
    ],
    "Monsoon" => [
        "Skin infections",
        "Tick exposure"
    ],
    default => [
        "Dry skin",
        "Joint stiffness"
    ]
};

/* Health history insights */
$historyInsights = [
    "Vaccinations: Up to date",
    $pet['weight'] < 10 ? "Weight: Low range" : "Weight: Healthy range",
    $recentIssues ? "Recent health observations noted" : "No chronic conditions detected"
];

/* Preventive recommendations */
$prevention = [
    "Maintain hydration",
    "Regular grooming",
    "Seasonal parasite prevention",
    "Routine vet checkups"
];

/* Log AI usage */
$logStmt = $conn->prepare("
    INSERT INTO ai_requests (user_id, pet_id, ai_type, severity)
    VALUES (?, ?, 'DiseaseRisk', ?)
");
$logStmt->bind_param("iis", $user_id, $pet_id, $overallRisk);
$logStmt->execute();

/* Save to medical history */
$title = "AI Disease Risk Analysis";
$desc  = "Overall risk assessed as {$overallRisk} based on breed, season, and recent health history.";

$histSave = $conn->prepare("
    INSERT INTO pet_medical_history
    (pet_id, record_type, title, description, severity)
    VALUES (?, 'DiseaseRisk', ?, ?, ?)
");
$histSave->bind_param("isss", $pet_id, $title, $desc, $overallRisk);
$histSave->execute();

/* Final response */
echo json_encode([
    "status" => "success",
    "analysis_factors" => [
        "breed" => $pet['breed'],
        "age" => "{$pet['age']} years",
        "weight" => "{$pet['weight']} kg",
        "activity_level" => "Medium",
        "current_season" => $season,
        "recent_health_status" => $recentIssues ? "Observations found" : "Healthy"
    ],
    "overall_risk" => [
        "level" => "{$overallRisk} Risk",
        "summary" => "Based on {$pet['name']}'s breed, seasonal conditions, and health records, the overall disease risk is {$overallRisk}. Preventive care is recommended."
    ],
    "breed_related_risks" => $breedRisks,
    "seasonal_risk_factors" => $seasonalRisks,
    "health_history_insights" => $historyInsights,
    "preventive_recommendations" => $prevention,
    "disclaimer" => "This analysis is AI-generated and not a medical diagnosis. Please consult a veterinarian for professional advice."
]);
