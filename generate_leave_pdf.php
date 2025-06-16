<?php
require_once('init.php');

// Include Composer's autoloader for FPDI/FPDF/FPDM
require_once __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// ---- CONFIGURATION ----
// PDF template location
$pdfTemplate = __DIR__ . '/assets/pdf-templates/LEAVE_FORM.pdf';
// Temporary output file (optional, can use output buffering instead)
$tmpOutput = tempnam(sys_get_temp_dir(), 'leave_pdf_');

// ---- GET AND VALIDATE INPUT ----
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit('Invalid request.');
}
$leaveId = (int)$_GET['id'];

// ---- SESSION AND SECURITY CHECKS ----
session_start();
if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit('Not authorized.');
}
$userid = $_SESSION['userid'];

// Fetch leave application and validate user access
$stmt = $pdo->prepare("SELECT * FROM emp_leave WHERE id = :id");
$stmt->execute([':id' => $leaveId]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$leave) {
    http_response_code(404);
    exit('Leave application not found.');
}
if ($leave['userid'] != $userid) {
    // Optionally allow HR/Supervisor/Manager here if needed
    http_response_code(403);
    exit('Access denied.');
}
if ((int)$leave['leave_status'] !== 4) {
    http_response_code(403);
    exit('PDF download is only available for approved applications.');
}

// ---- FETCH RELATED DATA ----
// Employee details
$stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
$stmt->execute([':id' => $leave['userid']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Employment details (active only)
$stmt = $pdo->prepare("SELECT * FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->execute([':userid' => $leave['userid']]);
$employment = $stmt->fetch(PDO::FETCH_ASSOC);

// Plantilla position (for office)
$stmt = $pdo->prepare("SELECT * FROM plantilla_position WHERE id = :id");
$stmt->execute([':id' => $employment['position_id']]);
$plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

// ---- MAP DATA TO PDF FIELDS ----
$pdfFields = [
    'date_receipt' => date('M d, Y', strtotime($leave['appdate'] ?? '')),
    'office' => $plantilla['org_unit'] ?? '',
    'fullname' => $employee['fullname'] ?? '',
    'date_filing' => date('M d, Y', strtotime($leave['appdate'] ?? '')),
    'position' => $plantilla['position_title'] ?? '',
    'salary' => $employment['monthly_salary'] ?? '',
    'working_days_applied' => $leave['total_leave_days'] ?? '',
    'inclusive_dates' =>
        (isset($leave['startdate'], $leave['enddate']) && $leave['startdate'] && $leave['enddate']) ?
        (strtoupper(date('M d, Y', strtotime($leave['startdate']))) . ' - ' . strtoupper(date('M d, Y', strtotime($leave['enddate'])))) : '',
    // Conditional leave details fields:
    'abroad' => (strtoupper($leave['leave_details'] ?? '') == 'ABROAD') ? $leave['leave_reason'] : '',
    'in_hospital_illness' => (strtoupper($leave['leave_details'] ?? '') == 'IN HOSPITAL') ? $leave['leave_reason'] : '',
    'out_hospital_illness' => (strtoupper($leave['leave_details'] ?? '') == 'OUT PATIENT') ? $leave['leave_reason'] : '',
    'spl_women_illness' => (strtoupper($leave['leave_details'] ?? '') == 'OUT PATIENT') ? $leave['leave_reason'] : '',
    'today_date' => $leave['h_date'] ? date('M d, Y', strtotime($leave['h_date'])) : '',
];

// ---- PREPARE PDF ----
// Use FPDM (FPDF Merge) for filling in the fields if your PDF is fillable
// If not, you'll need to use FPDI to overlay text at the right coordinates (requires manual XY setup)

use mikehaertl\pdftk\Pdf; // if using pdftk-php for field filling

// Check if the PDF template exists
if (!file_exists($pdfTemplate)) {
    http_response_code(500);
    exit('PDF template not found.');
}

// ---- FILL THE PDF ----
try {
    // Using pdftk-php (recommended for fillable PDF forms)
    // composer require mikehaertl/pdftk
    $pdf = new Pdf($pdfTemplate);
    $pdf->fillForm($pdfFields)
        ->needAppearances()
        ->saveAs($tmpOutput);

    // ---- SIGNATURES (AS IMAGE) ----
    // Advanced: Use FPDI to import the filled form and stamp images, or overlay signature images if needed

    // ---- OUTPUT PDF FOR DOWNLOAD ----
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="leave_form_' . $leaveId . '.pdf"');
    header('Content-Length: ' . filesize($tmpOutput));
    readfile($tmpOutput);

    // Clean up the temp file
    unlink($tmpOutput);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    exit('Failed to generate PDF: ' . htmlspecialchars($e->getMessage()));
}
?>