<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$pet_id  = $data['pet_id'] ?? null;
$message = trim($data['message'] ?? '');

if (!$user_id || !$pet_id || $message === '') {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

/* ---------- CHECK PREMIUM STATUS ---------- */
$userStmt = $conn->prepare("
    SELECT is_premium
    FROM users
    WHERE id = ?
");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userRes = $userStmt->get_result();

if ($userRes->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$user = $userRes->fetch_assoc();

if ((int)$user['is_premium'] !== 1) {
    echo json_encode([
        "status" => "locked",
        "message" => "AI Vet Chat is available for Premium users only.",
        "upgrade_required" => true
    ]);
    exit;
}

/* ---------- FETCH PET ---------- */
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

/* ---------- FETCH RECENT HISTORY ---------- */
$histStmt = $conn->prepare("
    SELECT severity
    FROM pet_medical_history
    WHERE pet_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$histStmt->bind_param("i", $pet_id);
$histStmt->execute();
$histRes = $histStmt->get_result();

$highestSeverity = "Low";
while ($row = $histRes->fetch_assoc()) {
    if ($row['severity'] === 'High') {
        $highestSeverity = "High";
        break;
    }
    if ($row['severity'] === 'Medium') {
        $highestSeverity = "Medium";
    }
}

/* ---------- INTENT DETECTION ---------- */
$intent = "general";

if (preg_match('/eat|vomit|diarrhea|appetite|poop|urine/i', $message)) {
    $intent = "symptom";
}
elseif (preg_match('/behavior|sleep|aggressive|anxious|normal/i', $message)) {
    $intent = "behavior";
}
elseif (preg_match('/food|diet|feed|nutrition|treat/i', $message)) {
    $intent = "food";
}

/* ---------- AI RESPONSE ---------- */
$reply = "Thanks for sharing. I’m reviewing {$pet['name']}’s health information to help you.";

$followUps = [];
$severity = "Low";

switch ($intent) {
    case "symptom":
        $reply = "Symptoms like this can sometimes be mild, but monitoring duration and changes is important.";
        $followUps = [
            "When did this symptom start?",
            "Any other symptoms noticed?",
            "Any recent routine or food changes?"
        ];
        break;

    case "behavior":
        $reply = "Behavior changes may relate to environment, stress, or health factors.";
        $followUps = [
            "Is this behavior new?",
            "Any recent changes at home?",
            "Has activity level changed?"
        ];
        break;

    case "food":
        $reply = "Diet plays a major role in overall health. Sudden changes can sometimes affect appetite or digestion.";
        $followUps = [
            "Any recent food changes?",
            "Is {$pet['name']} avoiding specific foods?",
            "Any digestive discomfort?"
        ];
        break;

    default:
        $reply = "I’m here to help with any concerns about {$pet['name']}’s health and care.";
        $followUps = [
            "Can you tell me more about your concern?",
            "Is there a specific change you noticed?"
        ];
}

/* Escalation */
if ($highestSeverity === "High") {
    $severity = "High";
    $reply .= " Given recent health observations, consulting a veterinarian would be advisable.";
}
elseif ($highestSeverity === "Medium") {
    $severity = "Medium";
}

/* ---------- LOG AI USAGE ---------- */
$logStmt = $conn->prepare("
    INSERT INTO ai_requests (user_id, pet_id, ai_type, severity)
    VALUES (?, ?, 'VetChat', ?)
");
$logStmt->bind_param("iis", $user_id, $pet_id, $severity);
$logStmt->execute();

/* ---------- FINAL RESPONSE ---------- */
echo json_encode([
    "status" => "success",
    "pet_context" => [
        "name" => $pet['name'],
        "breed" => $pet['breed'],
        "age" => (int)$pet['age'],
        "health_status" => $highestSeverity === "High" ? "Needs Attention" : "Healthy"
    ],
    "user_message" => $message,
    "ai_reply" => $reply,
    "follow_up_questions" => $followUps,
    "severity" => $severity,
    "disclaimer" => "AI guidance is not a medical diagnosis."
]);
