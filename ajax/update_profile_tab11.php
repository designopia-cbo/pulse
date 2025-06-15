<?php
header('Content-Type: application/json');
session_start();

// Include your DB connection (must provide $pdo)
require_once('../config/db_connection.php');

// Read JSON payload
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

// Get profile_userid being edited
$profile_userid = isset($data['profile_userid']) && $data['profile_userid'] !== ''
    ? $data['profile_userid']
    : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0);

if (!$profile_userid) {
    echo json_encode(['success' => false, 'message' => 'Missing profile_userid.']);
    exit;
}

// Who is making the edit?
$editor_userid = isset($_SESSION['userid']) ? $_SESSION['userid'] : 0;
$updated_by = 'UNKNOWN';
if ($editor_userid) {
    $stmt = $pdo->prepare("SELECT `first_name`, `last_name` FROM `employee` WHERE `id` = ?");
    $stmt->execute([$editor_userid]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($emp && (!empty($emp['first_name']) || !empty($emp['last_name']))) {
        $updated_by = strtoupper(trim($emp['first_name'] . ' ' . $emp['last_name']));
    }
}

// Get emergency contact details and their IDs
$e_names = isset($data['e_fullname']) ? $data['e_fullname'] : [];
$e_contacts = isset($data['e_contact_number']) ? $data['e_contact_number'] : [];
$e_relationships = isset($data['e_relationship']) ? $data['e_relationship'] : [];
$e_ids = isset($data['e_id']) ? $data['e_id'] : [];

if (!is_array($e_names) || !is_array($e_contacts) || !is_array($e_relationships) || !is_array($e_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

// Fetch current emergency contacts for this user from DB
$stmt = $pdo->prepare("SELECT `id`, `e_fullname`, `e_contact_number`, `e_relationship` FROM `emergency_contact` WHERE `userid` = ?");
$stmt->execute([$profile_userid]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dbMap = [];
foreach ($dbRows as $row) {
    $dbMap[$row['id']] = [
        'e_fullname' => $row['e_fullname'],
        'e_contact_number' => $row['e_contact_number'],
        'e_relationship' => $row['e_relationship'],
    ];
}

$actions = [];

// 1. Update or Insert emergency contacts
foreach ($e_names as $i => $e_name) {
    $name = strtoupper(trim($e_names[$i]));
    $contact = strtoupper(trim($e_contacts[$i]));
    $relationship = strtoupper(trim($e_relationships[$i]));
    $id = trim($e_ids[$i]);

    if ($id && isset($dbMap[$id])) {
        // Update if changed
        $old = $dbMap[$id];
        if ($old['e_fullname'] !== $name || $old['e_contact_number'] !== $contact || $old['e_relationship'] !== $relationship) {
            $stmt = $pdo->prepare("UPDATE `emergency_contact` SET `e_fullname` = ?, `e_contact_number` = ?, `e_relationship` = ? WHERE id = ? AND userid = ?");
            $stmt->execute([$name, $contact, $relationship, $id, $profile_userid]);
            $actions[] = [
                'field_name' => 'EMERGENCY CONTACT',
                'old_value' => implode(' | ', [$old['e_fullname'], $old['e_contact_number'], $old['e_relationship']]),
                'new_value' => implode(' | ', [$name, $contact, $relationship]),
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    } elseif ($name !== '' || $contact !== '' || $relationship !== '') {
        // Insert new row
        $stmt = $pdo->prepare("INSERT INTO `emergency_contact` (`userid`, `e_fullname`, `e_contact_number`, `e_relationship`) VALUES (?, ?, ?, ?)");
        $stmt->execute([$profile_userid, $name, $contact, $relationship]);
        $actions[] = [
            'field_name' => 'EMERGENCY CONTACT',
            'old_value' => '',
            'new_value' => implode(' | ', [$name, $contact, $relationship]),
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 2. Delete removed emergency contacts
foreach ($dbRows as $row) {
    if (!in_array($row['id'], $e_ids)) {
        $stmt = $pdo->prepare("DELETE FROM `emergency_contact` WHERE id = ? AND userid = ?");
        $stmt->execute([$row['id'], $profile_userid]);
        $actions[] = [
            'field_name' => 'EMERGENCY CONTACT',
            'old_value' => implode(' | ', [$row['e_fullname'], $row['e_contact_number'], $row['e_relationship']]),
            'new_value' => '',
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 3. Log all changes in employee_update_history
foreach ($actions as $log) {
    $field_name = strtoupper(str_replace('_', ' ', $log['field_name']));
    $old_value = strtoupper($log['old_value']);
    $new_value = strtoupper($log['new_value']);
    $log_updated_by = strtoupper($log['updated_by']);
    $employee_id = $log['employee_id'];

    $stmt = $pdo->prepare("
        INSERT INTO employee_update_history
        (employee_id, field_name, old_value, new_value, updated_by, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $employee_id,
        $field_name,
        $old_value,
        $new_value,
        $log_updated_by
    ]);
}

echo json_encode(['success' => true]);
exit;