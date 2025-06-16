<?php
require __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

$templatePath = __DIR__ . '/assets/pdf-templates/LEAVE_FORM.pdf';

$pdf = new FPDI();
$pdf->AddPage();
$pdf->setSourceFile($templatePath);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);

// Overlay bold red text near the top left
$pdf->SetFont('Helvetica', 'B', 16);
$pdf->SetTextColor(255, 0, 0);
$pdf->SetXY(20, 30); // Try (20,30) for visibility
$pdf->Write(12, 'Name: John Doe');

$pdf->Output('I', 'leave_form_sample_overlay.pdf');
?>