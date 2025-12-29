<?php
require_once __DIR__ . "/fpdf/fpdf.php";

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'FPDF is working!',0,1,'C');
$pdf->Output();
