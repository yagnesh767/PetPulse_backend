<?php
header("Content-Type: application/json");

// Database configuration
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "petpulse";

// Create connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// Force UTF-8 (important for names, pets, reports)
$conn->set_charset("utf8mb4");

// Optional: Strict SQL mode (prevents silent bugs)
$conn->query("SET sql_mode = 'STRICT_ALL_TABLES'");
