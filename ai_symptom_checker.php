<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id  = $data['user_id'] ?? null;
$pet_id   = $data['pet_id'] ?? null;
$symptoms = $data['symptoms'] ?? [];

if (!$user_id || !$pet_id || empty($symptoms)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input"
    ]);
    exit;
}

/* STEP 1: Symptom weight map (AI feature simulation) */
$symptom_weights = [
    'vomiting' => 0.7,
    'seizure' => 0.9,
    'collapse' => 0.9,
    'bleeding' => 1.0,
    'lethargy' => 0.4,
    'loss of appetite' => 0.4,
    'diarrhea' => 0.5,
    'itching' => 0.2,
    'coughing' => 0.3
];

$total_score = 0;
$matched = 0;

foreach ($symptoms as $symptom) {
    $key = strtolower(trim($symptom));
    if (isset($symptom_weights[$key])) {
        $total_score += $symptom_weights[$key];
        $matched++;
    }
}

/* STEP 2: Normalize score (0–1) */
$confidence = min(1, round($total_score / max(1, $matched), 2));

/* STEP 3: Severity classification */
if ($confidence >= 0.75) {
    $severity = "High";
} elseif ($confidence >= 0.4) {
    $severity = "Medium";
} else {
    $severity = "Low";
}

/* STEP 4: Save AI request */
$stmt = $conn->prepare("
    INSERT INTO ai_requests (user_id, pet_id, ai_type, severity, confidence)
    VALUES (?, ?, 'SymptomChecker', ?, ?)
");
$stmt->bind_param("iisd", $user_id, $pet_id, $severity, $confidence);
$stmt->execute();

/* STEP 5: Save medical history */
$title = "AI Symptom Assessment";
$desc  = "Symptoms reported: " . implode(", ", $symptoms);

$stmt2 = $conn->prepare("
    INSERT INTO pet_medical_history
    (pet_id, record_type, title, description, severity)
    VALUES (?, 'Symptom', ?, ?, ?)
");
$stmt2->bind_param("isss", $pet_id, $title, $desc, $severity);
$stmt2->execute();

/* STEP 6: Follow-up questions (AI-style) */
$follow_up = [];

if ($severity !== "Low") {
    $follow_up[] = "How long has the symptom been present?";
    $follow_up[] = "Is your pet eating and drinking normally?";
}

if ($severity === "High") {
    $follow_up[] = "Has your pet collapsed or lost consciousness?";
}

/* STEP 7: Action guidance */
$action = match ($severity) {
    "High" => "Seek veterinary attention immediately.",
    "Medium" => "Monitor closely and consult a vet if symptoms persist.",
    default => "Home care and observation recommended."
};

echo json_encode([
    "status" => "success",
    "severity" => $severity,
    "confidence" => $confidence,
    "action" => $action,
    "follow_up_questions" => $follow_up
]);
