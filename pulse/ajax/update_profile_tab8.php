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

$organization_names = isset($data['organization_names']) ? $data['organization_names'] : [];
$membership_ids = isset($data['membership_id']) ? $data['membership_id'] : [];

if (!is_array($organization_names) || !is_array($membership_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

// Fetch current memberships for this user from DB
$stmt = $pdo->prepare("SELECT `id`, `association` FROM `membership` WHERE `userid` = ?");
$stmt->execute([$profile_userid]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dbMap = [];
foreach ($dbRows as $row) {
    $dbMap[$row['id']] = $row['association'];
}

$actions = [];

// 1. Update or Insert memberships
foreach ($organization_names as $i => $association) {
    $association = strtoupper(trim($association));
    $id = trim($membership_ids[$i]);
    if ($id && isset($dbMap[$id])) {
        // Update if changed
        if ($dbMap[$id] !== $association) {
            $stmt = $pdo->prepare("UPDATE `membership` SET `association` = ? WHERE id = ? AND userid = ?");
            $stmt->execute([$association, $id, $profile_userid]);
            $actions[] = [
                'field_name' => 'MEMBERSHIP',
                'old_value' => $dbMap[$id],
                'new_value' => $association,
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    } elseif ($association !== '') {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO `membership` (`userid`, `association`) VALUES (?, ?)");
        $stmt->execute([$profile_userid, $association]);
        $actions[] = [
            'field_name' => 'MEMBERSHIP',
            'old_value' => '',
            'new_value' => $association,
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 2. Delete removed memberships
foreach ($dbRows as $row) {
    if (!in_array($row['id'], $membership_ids)) {
        $stmt = $pdo->prepare("DELETE FROM `membership` WHERE id = ? AND userid = ?");
        $stmt->execute([$row['id'], $profile_userid]);
        $actions[] = [
            'field_name' => 'MEMBERSHIP',
            'old_value' => $row['association'],
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
    $updated_by = strtoupper($log['updated_by']);
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
        $updated_by
    ]);
}

echo json_encode(['success' => true]);
exit;