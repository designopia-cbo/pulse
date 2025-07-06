<?php
require_once('init.php');
require __DIR__ . '/vendor/autoload.php';

// Only allow ADMINISTRATOR level or HR category, else force logout.
$level = strtoupper(trim($_SESSION['level'] ?? ''));
$category = strtoupper(trim($_SESSION['category'] ?? ''));

if ($level !== 'ADMINISTRATOR' || ($category !== 'HR' && $category !== 'SUPERADMIN')) {
    session_unset();
    session_destroy();
    header("Location: logout.php");
    exit;
}

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

// ======= Fetch balance_log for this leave =======
$stmt = $pdo->prepare("SELECT * FROM balance_log WHERE leave_id = :leave_id LIMIT 1");
$stmt->execute([':leave_id' => $leaveId]);
$balance_log = $stmt->fetch(PDO::FETCH_ASSOC);

// ======= Fetch leave_credit_log for this leave =======
$stmt = $pdo->prepare("SELECT * FROM leave_credit_log WHERE leave_id = :leave_id LIMIT 1");
$stmt->execute([':leave_id' => $leaveId]);
$leave_credit_log = $stmt->fetch(PDO::FETCH_ASSOC);

// ======= asof_date =======
$asof_date = !empty($leave['h_date']) ? strtoupper(date("M d, Y", strtotime($leave['h_date']))) : '';

// ======= BALANCE FIELDS LOGIC =======
$leave_type = strtoupper(trim($leave['leave_type'] ?? ''));

// Set defaults for all fields
$v_current_total = $v_less = $v_new_total = $s_current_total = $s_less = $s_new_total = '';

// Logic for each leave type
if ($leave_type == "VACATION LEAVE" && $leave_credit_log) {
    // VACATION LEAVE
    $v_current_total = $leave_credit_log['previous_balance'] ?? 0;
    $v_less = $leave_credit_log['changed_amount'] ?? 0;
    $v_new_total = $leave_credit_log['new_balance'] ?? 0;
    $s_current_total = $balance_log['sl'] ?? 0;
    $s_less = 0;
    $s_new_total = $balance_log['sl'] ?? 0;
} elseif ($leave_type == "SICK LEAVE" && $leave_credit_log) {
    // SICK LEAVE
    $v_current_total = $balance_log['vl'] ?? 0;
    $v_less = 0;
    $v_new_total = $balance_log['vl'] ?? 0;
    $s_current_total = $leave_credit_log['previous_balance'] ?? 0;
    $s_less = $leave_credit_log['changed_amount'] ?? 0;
    $s_new_total = $leave_credit_log['new_balance'] ?? 0;
} else {
    // Other leave types OR missing leave_credit_log
    $v_current_total = $balance_log['vl'] ?? 0;
    $v_less = 0;
    $v_new_total = $balance_log['vl'] ?? 0;
    $s_current_total = $balance_log['sl'] ?? 0;
    $s_less = 0;
    $s_new_total = $balance_log['sl'] ?? 0;
}

// ==== FORMAT leave balances to 0.000 ====
$v_current_total = number_format((float)$v_current_total, 3, '.', '');
$v_less         = number_format((float)$v_less, 3, '.', '');
$v_new_total    = number_format((float)$v_new_total, 3, '.', '');
$s_current_total = number_format((float)$s_current_total, 3, '.', '');
$s_less         = number_format((float)$s_less, 3, '.', '');
$s_new_total    = number_format((float)$s_new_total, 3, '.', '');

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

// Helper for center-aligned text in PDF
function center_text($pdf, $text, $y, $field_width, $x_start, $font='Arial', $style='', $size=7, $height=8) {
    $pdf->SetFont($font, $style, $size);
    $text_width = $pdf->GetStringWidth($text);
    $x = $x_start + (($field_width - $text_width) / 2);
    $pdf->SetXY($x, $y);
    $pdf->Write($height, $text);
}

// Helper to insert a signature image if it exists
function insert_signature($pdf, $id, $x, $y, $w = 40, $h = 15) {
    $sig_path = __DIR__ . "/assets/signatures/{$id}.png";
    if ($id && file_exists($sig_path)) {
        $pdf->Image($sig_path, $x, $y, $w, $h);
    }
}

// Helper to right-align text in a cell
function right_align_text($pdf, $text, $y, $col_x, $col_width, $font='Arial', $style='', $size=7, $height=8) {
    $pdf->SetFont($font, $style, $size);
    $text_width = $pdf->GetStringWidth($text);
    $x = $col_x + ($col_width - $text_width);
    $pdf->SetXY($x, $y);
    $pdf->Write($height, $text);
}

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
$position_full = $plantilla['item_number'] ?? '';
$position = '';
if (preg_match('/([A-Z0-9]+-\d{4})/', $position_full, $matches)) {
    $position = $matches[1];
}
$salary = !empty($employment['monthly_salary']) ? 'P ' . number_format($employment['monthly_salary']) : '';
$working_days_applied = $leave['total_leave_days'] ?? '';
$inclusive_dates = '';
if (!empty($leave['startdate']) && !empty($leave['enddate'])) {
    $inclusive_dates = strtoupper(date("M d, Y", strtotime($leave['startdate']))) . " - " . strtoupper(date("M d, Y", strtotime($leave['enddate'])));
}
$abroad = ($leave['leave_details'] ?? '') === 'ABROAD' ? $leave['leave_reason'] : '';
$in_hospital_illness = ($leave['leave_details'] ?? '') === 'IN HOSPITAL' ? $leave['leave_reason'] : '';
$out_hospital_illness = ($leave['leave_details'] ?? '') === 'OUT PATIENT' ? $leave['leave_reason'] : '';
$spl_women_illness = (strtoupper($leave['leave_type'] ?? '') === 'SPL FOR WOMEN') ? ($leave['leave_reason'] ?? '') : '';
$s_recommendation = $leave['reject_reason'] ?? '';

// Checkboxes for leave_details
$check_within_ph = ($leave['leave_details'] ?? '') === 'WITHIN THE PHILIPPINES' ? 'X' : '';
$check_abroad = ($leave['leave_details'] ?? '') === 'ABROAD' ? 'X' : '';
$check_in_hospital = ($leave['leave_details'] ?? '') === 'IN HOSPITAL' ? 'X' : '';
$check_out_patient = ($leave['leave_details'] ?? '') === 'OUT PATIENT' ? 'X' : '';
$check_masters_degree = ($leave['leave_details'] ?? '') === "COMPLETION OF MASTER'S DEGREE BAR/BOARD" ? 'X' : '';
$check_exam_review = ($leave['leave_details'] ?? '') === 'EXAMINATION REVIEW' ? 'X' : '';

// Checkboxes for leave_type
$check_vacation_leave = (strtoupper($leave['leave_type'] ?? '') === 'VACATION LEAVE') ? 'X' : '';
$check_force_leave = (strtoupper($leave['leave_type'] ?? '') === 'FORCE LEAVE') ? 'X' : '';
$check_sick_leave = (strtoupper($leave['leave_type'] ?? '') === 'SICK LEAVE') ? 'X' : '';
$check_maternity_leave = (strtoupper($leave['leave_type'] ?? '') === 'MATERNITY LEAVE') ? 'X' : '';
$check_paternity_leave = (strtoupper($leave['leave_type'] ?? '') === 'PATERNITY LEAVE') ? 'X' : '';
$check_spl_leave = (strtoupper($leave['leave_type'] ?? '') === 'SPECIAL PRIVILEGE LEAVE') ? 'X' : '';
$check_solo_parent_leave = (strtoupper($leave['leave_type'] ?? '') === 'SOLO PARENT LEAVE') ? 'X' : '';
$check_study_leave = (strtoupper($leave['leave_type'] ?? '') === 'STUDY LEAVE') ? 'X' : '';
$check_vawc_leave = (strtoupper($leave['leave_type'] ?? '') === '10-DAY VAWC LEAVE') ? 'X' : '';
$check_rehabilitation_leave = (strtoupper($leave['leave_type'] ?? '') === 'REHABILITATION PRIVILEGE') ? 'X' : '';
$check_spl_women_leave = (strtoupper($leave['leave_type'] ?? '') === 'SPL FOR WOMEN') ? 'X' : '';
$check_calamity_leave = (strtoupper($leave['leave_type'] ?? '') === 'CALAMITY LEAVE') ? 'X' : '';
$check_adoption_leave = (strtoupper($leave['leave_type'] ?? '') === 'ADOPTION LEAVE') ? 'X' : '';

// Checkboxes for recommendation
$check_recommendation_yes = (strtoupper($leave['reject_status'] ?? '') === 'APPROVED') ? 'X' : '';
$check_recommendation_no = (strtoupper($leave['reject_status'] ?? '') === 'DISAPPROVED') ? 'X' : '';

// Leave Without Pay
$leave_without_pay = (strtoupper($leave['leave_type'] ?? '') === 'LEAVE WITHOUT PAY') ? 'Leave Without Pay' : '';

// Manager Note
$manager_note = $leave['d_reject_reason'] ?? '';

// PDF Generation
$templatePath = __DIR__ . '/assets/pdf-templates/LEAVE_FORM.pdf';

$pdf = new FPDI();
$pdf->SetAutoPageBreak(true, 2);
$pdf->AddPage();
$pdf->setSourceFile($templatePath);
$tplIdx = $pdf->importPage(1);
$pdf->useTemplate($tplIdx);

$pdf->SetTextColor(0,0,0);

// Set font for each field individually:
$pdf->SetFont('Arial', '', 6); // date_receipt font size
$pdf->SetXY(169, 34);
$pdf->Write(8, $date_filing);

$pdf->SetFont('Arial', '', 7); // office font size
$pdf->SetXY(12, 79);
$pdf->Write(8, $office);

$pdf->SetFont('Arial', '', 7); // fullname font size
$pdf->SetXY(82, 79);
$pdf->Write(8, $fullname);

$pdf->SetFont('Arial', '', 7); // date_filing font size
$pdf->SetXY(35, 85.9);
$pdf->Write(8, $date_filing);

$pdf->SetFont('Arial', '', 7); // position font size
$pdf->SetXY(96, 86);
$pdf->Write(8, $position);

$pdf->SetFont('Arial', '', 7); // salary font size
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
$pdf->SetXY(128.5, 218.9); // adjust as needed for your PDF
$pdf->Write(8, $s_recommendation);

// ---- Checkboxes for leave details ----
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(123.3, 106.5); $pdf->Write(8, $check_within_ph);
$pdf->SetXY(123.3, 110.9); $pdf->Write(8, $check_abroad);
$pdf->SetXY(123.5, 124.4); $pdf->Write(8, $check_out_patient);
$pdf->SetXY(123.5, 119.9); $pdf->Write(8, $check_in_hospital);
$pdf->SetXY(123.6, 151.2); $pdf->Write(8, $check_masters_degree);
$pdf->SetXY(123.6, 155.6); $pdf->Write(8, $check_exam_review);

// ---- Checkboxes for leave type ----
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(12, 102.9); $pdf->Write(8, $check_vacation_leave);
$pdf->SetXY(12, 107.7); $pdf->Write(8, $check_force_leave);
$pdf->SetXY(12, 112.7); $pdf->Write(8, $check_sick_leave);
$pdf->SetXY(12, 117.5); $pdf->Write(8, $check_maternity_leave);
$pdf->SetXY(12, 122.3); $pdf->Write(8, $check_paternity_leave);
$pdf->SetXY(12, 127.4); $pdf->Write(8, $check_spl_leave);
$pdf->SetXY(12, 132.2); $pdf->Write(8, $check_solo_parent_leave);
$pdf->SetXY(12, 136.8); $pdf->Write(8, $check_study_leave);
$pdf->SetXY(12, 141.7); $pdf->Write(8, $check_vawc_leave);
$pdf->SetXY(12, 146.6); $pdf->Write(8, $check_rehabilitation_leave);
$pdf->SetXY(12, 151.6); $pdf->Write(8, $check_spl_women_leave);
$pdf->SetXY(12, 156.9); $pdf->Write(8, $check_calamity_leave);
$pdf->SetXY(12, 162);    $pdf->Write(8, $check_adoption_leave);

$pdf->SetFont('Arial', 'B', 11); // COMMUTATION - Not Requested
$pdf->SetXY(132.5, 183.2);
$pdf->Write(8, 'X');

// ---- Checkboxes for recommendation ----
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetXY(123.5, 210.8); $pdf->Write(8, $check_recommendation_yes);
$pdf->SetXY(123.5, 215.2); $pdf->Write(8, $check_recommendation_no);

// ---- Leave without Pay ----
if ($leave_without_pay !== '') {
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY(23, 170.6);
    $pdf->Write(8, $leave_without_pay);
}

// ---- SIGNATURES ----
insert_signature($pdf, $leave['userid'],      152, 188);  // Employee
insert_signature($pdf, $leave['hr'],          49, 232);   // HR
insert_signature($pdf, $leave['supervisor'], 145, 231);   // Supervisor
insert_signature($pdf, $leave['manager'],     82, 265);   // Manager/Director

// ---- Centered signature names ----
$hr_x = 30;     $hr_width = 70;    $hr_y = 237;
$sup_x = 127;   $sup_width = 70;   $sup_y = 235.5;
$dir_x = 65.5;  $dir_width = 70;  $dir_y = 272;

center_text($pdf, $hr_name, $hr_y, $hr_width, $hr_x, 'Arial', 'B', 7, 8);
center_text($pdf, $supervisor_name, $sup_y, $sup_width, $sup_x, 'Arial', 'B', 7, 8);
center_text($pdf, $director_name, $dir_y, $dir_width, $dir_x, 'Arial', 'B', 8, 8);

$pdf->SetFont('Arial', '', 7);
$pdf->SetXY(134, 252);
$pdf->Write(8, $manager_note);

// ==== Write balances and asof_date to PDF ====
// Example coordinates -- adjust to your template!
$pdf->SetFont('Arial', '', 7);
// "AS OF" date (top right of balance table)
$pdf->SetXY(58, 209.4); // adjust as needed for your PDF
$pdf->Write(8, $asof_date);

// Vacation Leave and Sick Leave columns' X and width (adjust widths as needed)
$vl_x = 29; $vl_width = 35;
$sl_x = 68; $sl_width = 35;

// Vacation Leave row (right aligned)
right_align_text($pdf, $v_current_total, 219.8, $vl_x, $vl_width);
right_align_text($pdf, $v_less,         223,   $vl_x, $vl_width);
right_align_text($pdf, $v_new_total,    227,   $vl_x, $vl_width);

// Sick Leave row (right aligned)
right_align_text($pdf, $s_current_total, 219.8, $sl_x, $sl_width);
right_align_text($pdf, $s_less,          223,   $sl_x, $sl_width);
right_align_text($pdf, $s_new_total,     227,   $sl_x, $sl_width);

$pdf->Output('I', 'leave_filled.pdf');
exit;