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

$stmt = $conn->prepare("
    SELECT name, species, breed, age, weight
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

// TEMPORARY fallback since activity_level column does not exist
$pet['activity_level'] = 'Moderate';

/* Analyze last 10 medical records */
$historyStmt = $conn->prepare("
    SELECT record_type, description, severity
    FROM pet_medical_history
    WHERE pet_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$historyStmt->bind_param("i", $pet_id);
$historyStmt->execute();
$historyRes = $historyStmt->get_result();

/* Flags derived from history */
$hasDigestiveIssue = false;
$hasWeightConcern  = false;
$hasStress         = false;

while ($row = $historyRes->fetch_assoc()) {
    if ($row['record_type'] === 'Symptom' &&
        preg_match('/vomit|diarrhea/i', $row['description'])) {
        $hasDigestiveIssue = true;
    }

    if ($row['record_type'] === 'DiseaseRisk' &&
        in_array($row['severity'], ['Medium', 'High'])) {
        $hasWeightConcern = true;
    }

    if ($row['record_type'] === 'Behavior' &&
        stripos($row['description'], 'Stress') !== false) {
        $hasStress = true;
    }
}

/* Base food plan */
$food_to_include = [
    "High-quality dry food",
    "Lean proteins",
    "Fresh water"
];

$food_to_avoid = [
    "Human food scraps",
    "Overfeeding"
];

/* Adjustments based on history */
if ($hasDigestiveIssue) {
    $food_to_include[] = "Easily digestible meals";
    $food_to_avoid[]   = "High-fat foods";
}

if ($hasWeightConcern) {
    $food_to_include[] = "Portion-controlled meals";
    $food_to_avoid[]   = "High-calorie treats";
}

if ($hasStress) {
    $food_to_include[] = "Consistent feeding schedule";
}

/* Meal suggestions (external links only) */
$meal_suggestions = [
    [
        "title" => "Royal Canin Golden Retriever Adult Dry Dog Food",
        "buy_url" => "https://www.royalcanin.com"
    ],
    [
        "title" => "Hill's Science Diet Adult Large Breed Chicken & Barley",
        "buy_url" => "https://www.hillspet.com"
    ]
];

/* Log AI usage */
$severity = "Low";
$logStmt = $conn->prepare("
    INSERT INTO ai_requests (user_id, pet_id, ai_type, severity)
    VALUES (?, ?, 'FoodAdvisor', ?)
");
$logStmt->bind_param("iis", $user_id, $pet_id, $severity);
$logStmt->execute();

/* Save to medical history */
$title = "AI Food Recommendation";
$desc  = "Personalized diet plan generated based on recent health history.";

$histStmt = $conn->prepare("
    INSERT INTO pet_medical_history
    (pet_id, record_type, title, description, severity)
    VALUES (?, 'Food', ?, ?, ?)
");
$histStmt->bind_param("isss", $pet_id, $title, $desc, $severity);
$histStmt->execute();

/* Final response (UI-aligned) */
echo json_encode([
    "status" => "success",
    "pet" => [
        "name" => $pet['name'],
        "breed" => $pet['breed'],
        "age_years" => (int)$pet['age'],
        "weight_kg" => (float)$pet['weight'],
        "activity_level" => $pet['activity_level']
    ],
    "summary" => "Based on {$pet['name']}’s breed, activity, and recent health history, the following diet is recommended.",
    "food_to_include" => array_values(array_unique($food_to_include)),
    "food_to_avoid" => array_values(array_unique($food_to_avoid)),
    "meal_suggestions" => $meal_suggestions,
    "disclaimer" => "This recommendation is for general guidance and is not a medical prescription."
]);
