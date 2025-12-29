<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db.php";

/* Upload directory */
$uploadDir = __DIR__ . "/uploads/behavior/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* Input validation */
$user_id = $_POST['user_id'] ?? null;
$pet_id  = $_POST['pet_id'] ?? null;

if (!$user_id || !$pet_id || !isset($_FILES['media'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid input"
    ]);
    exit;
}

/* File validation */
$file = $_FILES['media'];
$allowedTypes = ['image/jpeg', 'image/png', 'video/mp4'];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode([
        "status" => "error",
        "message" => "Unsupported file type"
    ]);
    exit;
}

/* Save file */
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid("behavior_", true) . "." . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode([
        "status" => "error",
        "message" => "File upload failed"
    ]);
    exit;
}

/* -----------------------------
   BEHAVIOR DETECTION (PLACEHOLDER)
------------------------------ */
$behavior = "Normal";
$explanation = "Your pet appears to be behaving normally.";

if ($file['size'] > 5 * 1024 * 1024) {
    $behavior = "Stress";
    $explanation = "Signs may indicate stress or discomfort.";
}

if (stripos($file['name'], 'sleep') !== false) {
    $behavior = "Lethargy";
    $explanation = "Your pet appears unusually inactive.";
}

/* -----------------------------
   MAP BEHAVIOR → SEVERITY
------------------------------ */
switch ($behavior) {
    case "Aggression":
        $severity = "High";
        break;
    case "Stress":
    case "Lethargy":
        $severity = "Medium";
        break;
    default:
        $severity = "Low";
}

/* FINAL SAFETY GUARD */
if (!in_array($severity, ['Low','Medium','High'])) {
    $severity = 'Low';
}

/* -----------------------------
   SAVE AI REQUEST
------------------------------ */
$stmt = $conn->prepare("
    INSERT INTO ai_requests (user_id, pet_id, ai_type, severity)
    VALUES (?, ?, 'BehaviorAnalyzer', ?)
");
$stmt->bind_param("iis", $user_id, $pet_id, $severity);
$stmt->execute();

/* -----------------------------
   SAVE MEDICAL HISTORY
------------------------------ */
$title = "AI Behavior Analysis";
$desc  = "Behavior detected: " . $behavior;

$stmt2 = $conn->prepare("
    INSERT INTO pet_medical_history
    (pet_id, record_type, title, description, severity)
    VALUES (?, 'Behavior', ?, ?, ?)
");
$stmt2->bind_param("isss", $pet_id, $title, $desc, $severity);
$stmt2->execute();

/* -----------------------------
   RESPONSE
------------------------------ */
/* UI-mapped behavior label */
$behaviorLabel = match ($behavior) {
    "Stress" => "Mild Anxiety Detected",
    "Lethargy" => "Low Energy / Lethargy",
    "Aggression" => "Aggressive Behavior Detected",
    default => "Normal Behavior"
};

/* Key indicators (rule-based placeholders, AI-ready) */
$indicators = [
    "activity_level" => $behavior === "Lethargy" ? "Low" : "Moderate",
    "body_posture" => $behavior === "Stress" ? "Slightly tense" : "Relaxed",
    "vocalization" => "None detected",
    "movement" => $behavior === "Stress" ? "Repetitive" : "Normal"
];

echo json_encode([
    "status" => "success",
    "behavior_label" => $behaviorLabel,
    "severity" => $severity,
    "summary" => $explanation,
    "indicators" => $indicators,
    "disclaimer" => "This is not a medical diagnosis.",
    "media_url" => "uploads/behavior/" . $filename
]);
