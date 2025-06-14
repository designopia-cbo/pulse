<?php
header('Content-Type: application/json');
session_start();

// Include your DB connection
require_once('../config/db_connection.php');

// Read JSON payload
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

// Get the user ID for the profile being edited (the owner of the distinctions)
$profile_userid = isset($data['profile_userid']) && $data['profile_userid'] !== ''
    ? $data['profile_userid']
    : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0);

if (!$profile_userid) {
    echo json_encode(['success' => false, 'message' => 'Missing profile_userid.']);
    exit;
}

// Always use the session's userid for updated_by (the logged-in user)
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

// Get distinctions and their IDs
$distinctions = isset($data['distinctions']) ? $data['distinctions'] : [];
$distinction_ids = isset($data['distinction_id']) ? $data['distinction_id'] : [];
if (!is_array($distinctions) || !is_array($distinction_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

// Fetch current distinctions for this profile_userid
$stmt = $pdo->prepare("SELECT `id`, `n_nacademic_title` FROM `non_academic_distinctions` WHERE `userid` = ?");
$stmt->execute([$profile_userid]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dbMap = [];
foreach ($dbRows as $row) {
    $dbMap[$row['id']] = $row['n_nacademic_title'];
}

$actions = [];

// 1. Update or Insert distinctions
foreach ($distinctions as $i => $title) {
    $title = strtoupper(trim($title));
    $id = trim($distinction_ids[$i]);
    if ($id && isset($dbMap[$id])) {
        // Update if changed
        if ($dbMap[$id] !== $title) {
            $stmt = $pdo->prepare("UPDATE `non_academic_distinctions` SET `n_nacademic_title` = ? WHERE id = ? AND userid = ?");
            $stmt->execute([$title, $id, $profile_userid]);
            // Log history
            $actions[] = [
                'field_name' => 'NON-ACADEMIC DISTINCTIONS',
                'old_value' => $dbMap[$id],
                'new_value' => $title,
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    } elseif ($title !== '') {
        // Insert new row
        $stmt = $pdo->prepare("INSERT INTO `non_academic_distinctions` (`userid`, `n_nacademic_title`) VALUES (?, ?)");
        $stmt->execute([$profile_userid, $title]);
        // Log history
        $actions[] = [
            'field_name' => 'NON-ACADEMIC DISTINCTIONS',
            'old_value' => '',
            'new_value' => $title,
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 2. Delete removed distinctions
foreach ($dbRows as $row) {
    if (!in_array($row['id'], $distinction_ids)) {
        $stmt = $pdo->prepare("DELETE FROM `non_academic_distinctions` WHERE id = ? AND userid = ?");
        $stmt->execute([$row['id'], $profile_userid]);
        // Log history
        $actions[] = [
            'field_name' => 'NON-ACADEMIC DISTINCTIONS',
            'old_value' => $row['n_nacademic_title'],
            'new_value' => '',
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 3. Log all changes in employee_update_history
foreach ($actions as $log) {
    // Clean up field_name: remove underscores, replace with space, make uppercase
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