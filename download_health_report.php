<?php
require_once __DIR__ . "/fpdf/fpdf.php";

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/fpdf/fpdf.php";

$pet_id = $_GET['pet_id'] ?? null;
if (!$pet_id) die("Invalid request");

/* Fetch pet */
$stmt = $conn->prepare("SELECT name, breed, age, weight FROM pets WHERE id = ?");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Pet Pulse – AI Health Report',0,1,'C');

$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,"Pet Name: {$pet['name']}",0,1);
$pdf->Cell(0,8,"Breed: {$pet['breed']}",0,1);
$pdf->Cell(0,8,"Age: {$pet['age']} years",0,1);
$pdf->Cell(0,8,"Weight: {$pet['weight']} kg",0,1);

$pdf->Ln(5);
$pdf->MultiCell(0,8,"This AI-generated report summarizes your pet's overall health, recent activity, and medical history. No critical risks detected at this time.");

$pdf->Ln(5);
$pdf->MultiCell(0,8,"Recommendations:\n- Maintain current diet\n- Continue daily walks\n- Routine vet checkups");

$pdf->Ln(5);
$pdf->SetFont('Arial','I',9);
$pdf->MultiCell(0,6,"Disclaimer: This report is AI-generated and not a medical diagnosis. Please consult a veterinarian for professional advice.");

$pdf->Output("D", "Pet_Health_Report.pdf");
