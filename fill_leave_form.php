<?php
require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// Path to your template PDF
$templatePath = __DIR__ . '/assets/pdf-templates/LEAVE_FORM.pdf';

// Create new FPDI instance and add a page
$pdf = new Fpdi();
$pdf->AddPage();

// Set the source PDF file and import the first page
$pdf->setSourceFile($templatePath);
$templateId = $pdf->importPage(1);
$pdf->useTemplate($templateId);

// Set font and color
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0,0,0);

// Example: Write employee name at X=40, Y=60
$pdf->SetXY(40, 60);
$pdf->Write(0, "Juan Dela Cruz");

// Example: Write leave dates at X=40, Y=70
$pdf->SetXY(40, 70);
$pdf->Write(0, "2025-06-21 to 2025-06-23");

// Example: Write leave type at X=40, Y=80
$pdf->SetXY(40, 80);
$pdf->Write(0, "Sick Leave");

// Output the filled PDF to browser (will prompt download)
$pdf->Output('D', 'leave_form_filled.pdf');
?>