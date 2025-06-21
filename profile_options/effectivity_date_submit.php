<?php
session_start();
require_once(__DIR__ . '/../init.php'); // Adjust as needed

header('Content-Type: application/json');

// Security: Must be logged in
if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Validate POST input
$employment_details_id = isset($_POST['employment_details_id']) ? intval($_POST['employment_details_id']) : 0;
$date_of_assumption = isset($_POST['date_of_assumption']) ? trim($_POST['date_of_assumption']) : '';
$date_appointment = isset($_POST['date_appointment']) ? trim($_POST['date_appointment']) : '';

if ($employment_details_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid employment record.']);
    exit;
}

// Optionally: Validate date format (YYYY-MM-DD)
function is_valid_date($date) {
    if (empty($date)) return true;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
if (!is_valid_date($date_of_assumption) || !is_valid_date($date_appointment)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date format.']);
    exit;
}

// Optionally: Check user has permission to update this record
$stmt = $pdo->prepare("SELECT userid FROM employment_details WHERE id = :id LIMIT 1");
$stmt->bindParam(':id', $employment_details_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Employment record not found.']);
    exit;
}

$record_userid = $row['userid'];
$current_userid = $_SESSION['userid'];

// Only allow updates if the current user is owner or has admin/HR/AAO/MINISTER rights
$allowed = false;
if ($record_userid == $current_userid) {
    $allowed = true;
} elseif (isset($_SESSION['category']) && in_array($_SESSION['category'], ['HR', 'AAO', 'MINISTER'])) {
    $allowed = true;
}
if (!$allowed) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

// Perform the update
$stmt = $pdo->prepare("UPDATE employment_details SET date_of_assumption = :date_of_assumption, date_appointment = :date_appointment WHERE id = :id");
$stmt->bindParam(':date_of_assumption', $date_of_assumption, PDO::PARAM_STR);
$stmt->bindParam(':date_appointment', $date_appointment, PDO::PARAM_STR);
$stmt->bindParam(':id', $employment_details_id, PDO::PARAM_INT);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed.']);
}
exit;