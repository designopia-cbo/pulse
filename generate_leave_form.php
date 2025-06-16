<?php
require_once('init.php');
require __DIR__ . '/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

// 1. Fetch all data from the database
$leaveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$leaveId) {
    die("Missing leave application ID.");
}

// Fetch leave application
$stmt = $pdo->prepare("SELECT * FROM emp_leave WHERE id = :id");
$stmt->execute([':id' => $leaveId]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$leave) die("Leave application not found.");

// Fetch employee
$stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :userid");
$stmt->execute([':userid' => $leave['userid']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Employment details (edstatus=1)
$stmt = $pdo->prepare("SELECT * FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->execute([':userid' => $leave['userid']]);
$employment = $stmt->fetch(PDO::FETCH_ASSOC);

// Plantilla position
$stmt = $pdo->prepare("SELECT * FROM plantilla_position WHERE id = :position_id");
$stmt->execute([':position_id' => $employment['position_id'] ?? 0]);
$plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

// Utility function to format employee name
function format_employee_name($emp) {
    if (!$emp) return '';
    $first = isset($emp['first_name']) ? $emp['first_name'] : '';
    $middle = (isset($emp['middle_name']) && $emp['middle_name'] !== '') ? ' ' . strtoupper(substr($emp['middle_name'], 0, 1)) . '.' : '';
    $last = isset($emp['last_name']) ? ' ' . $emp['last_name'] : '';
    $suffix = (isset($emp['suffix']) && $emp['suffix'] !== '') ? ' ' . $emp['suffix'] : '';
    $full = trim($first . $middle . $last . $suffix);
    return strtoupper($full);
}

// today_date (JAN 02, 2024 format)
$today_date = !empty($leave['h_date']) ? strtoupper(date("M d, Y", strtotime($leave['h_date']))) : '';

// HR name
$hr_name = '';
if (!empty($leave['hr'])) {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
    $stmt->execute([':id' => $leave['hr']]);
    $hr_emp = $stmt->fetch(PDO::FETCH_ASSOC);
    $hr_name = format_employee_name($hr_emp);
}

// Supervisor name
$supervisor_name = '';
if (!empty($leave['supervisor'])) {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
    $stmt->execute([':id' => $leave['supervisor']]);
    $sup_emp = $stmt->fetch(PDO::FETCH_ASSOC);
    $supervisor_name = format_employee_name($sup_emp);
}

// Director name
$director_name = '';
if (!empty($leave['manager'])) {
    $stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :id");
    $stmt->execute([':id' => $leave['manager']]);
    $dir_emp = $stmt->fetch(PDO::FETCH_ASSOC);
    $director_name = format_employee_name($dir_emp);
}

// Prepare values
$fullname = $employee['fullname'] ?? '';
$date_receipt = $leave['appdate'] ?? '';
$date_filing = !empty($leave['appdate']) ? strtoupper(date("M d, Y", strtotime($leave['appdate']))) : '';
$office = $plantilla['org_unit'] ?? '';
$position = $plantilla['position_title'] ?? '';
$salary = $employment['monthly_salary'] ?? '';
$working_days_applied = $leave['total_leave_days'] ?? '';
$inclusive_dates = '';
if (!empty($leave['startdate']) && !empty($leave['enddate'])) {
    $inclusive_dates = strtoupper(date("M d, Y", strtotime($leave['startdate']))) . " - " . strtoupper(date("M d, Y", strtotime($leave['enddate'])));
}
$abroad = ($leave['leave_details'] ?? '') === 'ABROAD' ? $leave['leave_reason'] : '';
$in_hospital_illness = ($leave['leave_details'] ?? '') === 'IN HOSPITAL' ? $leave['leave_reason'] : '';
$out_hospital_illness = ($leave['leave_details'] ?? '') === 'OUT PATIENT' ? $leave['leave_reason'] : '';
// SPL FOR WOMEN illness
$spl_women_illness = (strtoupper($leave['leave_type'] ?? '') === 'SPL FOR WOMEN') ? ($leave['leave_reason'] ?? '') : '';

// s_recommendation and d_recommendation
$s_recommendation = $leave['reject_reason'] ?? '';

// 2. Now generate the PDF
$templatePath = __DIR__ . '/assets/pdf-templates/LEAVE_FORM.pdf';

$pdf = new FPDI();
$pdf->AddPage();
$pdf->setSourceFile($templatePath);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);

$pdf->SetTextColor(0,0,0);

// Set font for each field individually:
$pdf->SetFont('Arial', '', 6); // date_filing font size
$pdf->SetXY(169, 34);
$pdf->Write(8, $date_filing);

$pdf->SetFont('Arial', '', 7); // office font size
$pdf->SetXY(12, 79);
$pdf->Write(8, $office);

$pdf->SetFont('Arial', '', 7); // fullname font size
$pdf->SetXY(82, 79);
$pdf->Write(8, $fullname);

$pdf->SetFont('Arial', '', 6); // date_filing font size
$pdf->SetXY(35, 85.9);
$pdf->Write(8, $date_filing);

$pdf->SetFont('Arial', '', 4); // position font size
$pdf->SetXY(96, 86);
$pdf->Write(8, $position);

$pdf->SetFont('Arial', '', 6); // salary font size
$pdf->SetXY(154, 85.9);
$pdf->Write(8, $salary);

$pdf->SetFont('Arial', '', 7); // working_days_applied font size
$pdf->SetXY(16, 182.7);
$pdf->Write(8, $working_days_applied);

$pdf->SetFont('Arial', '', 7); // inclusive_dates font size
$pdf->SetXY(16, 191.5);
$pdf->Write(8, $inclusive_dates);

$pdf->SetFont('Arial', '', 7); // abroad font size
$pdf->SetXY(148, 110.8);
$pdf->Write(8, $abroad);

$pdf->SetFont('Arial', '', 7); // in_hospital_illness font size
$pdf->SetXY(159, 119.7);
$pdf->Write(8, $in_hospital_illness);

$pdf->SetFont('Arial', '', 7); // out_hospital_illness font size
$pdf->SetXY(159, 124.1);
$pdf->Write(8, $out_hospital_illness);

$pdf->SetFont('Arial', '', 7); // spl_women_illness font size
$pdf->SetXY(125, 142);
$pdf->Write(8, $spl_women_illness);

// s_recommendation
$pdf->SetFont('Arial', '', 7);
$pdf->SetXY(128.5, 218.5); // adjust as needed for your PDF
$pdf->Write(8, $s_recommendation);

// hr_name
$pdf->SetFont('Arial', '', 7);
$pdf->SetXY(49, 237); // adjust as needed
$pdf->Write(8, $hr_name);

// supervisor_name
$pdf->SetFont('Arial', '', 7);
$pdf->SetXY(144.5, 235); // adjust as needed
$pdf->Write(8, $supervisor_name);

// director_name
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY(86.5, 268.10); // adjust as needed
$pdf->Write(8, $director_name);





$pdf->Output('I', 'leave_filled.pdf');
exit;