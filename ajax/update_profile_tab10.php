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

// Get references and their IDs
$ref_names = isset($data['ref_name']) ? $data['ref_name'] : [];
$ref_addresses = isset($data['ref_address']) ? $data['ref_address'] : [];
$ref_tel_nos = isset($data['ref_tel_no']) ? $data['ref_tel_no'] : [];
$ref_ids = isset($data['ref_id']) ? $data['ref_id'] : [];

if (!is_array($ref_names) || !is_array($ref_addresses) || !is_array($ref_tel_nos) || !is_array($ref_ids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

// Fetch current references for this user from DB
$stmt = $pdo->prepare("SELECT `id`, `r_fullname`, `r_address`, `r_contactno` FROM `references_name` WHERE `userid` = ?");
$stmt->execute([$profile_userid]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dbMap = [];
foreach ($dbRows as $row) {
    $dbMap[$row['id']] = [
        'r_fullname' => $row['r_fullname'],
        'r_address' => $row['r_address'],
        'r_contactno' => $row['r_contactno'],
    ];
}

$actions = [];

// 1. Update or Insert references
foreach ($ref_names as $i => $ref_name) {
    $name = strtoupper(trim($ref_names[$i]));
    $address = strtoupper(trim($ref_addresses[$i]));
    $tel = strtoupper(trim($ref_tel_nos[$i]));
    $id = trim($ref_ids[$i]);

    if ($id && isset($dbMap[$id])) {
        // Update if changed
        $old = $dbMap[$id];
        if ($old['r_fullname'] !== $name || $old['r_address'] !== $address || $old['r_contactno'] !== $tel) {
            $stmt = $pdo->prepare("UPDATE `references_name` SET `r_fullname` = ?, `r_address` = ?, `r_contactno` = ? WHERE id = ? AND userid = ?");
            $stmt->execute([$name, $address, $tel, $id, $profile_userid]);
            $actions[] = [
                'field_name' => 'REFERENCES',
                'old_value' => implode(' | ', [$old['r_fullname'], $old['r_address'], $old['r_contactno']]),
                'new_value' => implode(' | ', [$name, $address, $tel]),
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    } elseif ($name !== '' || $address !== '' || $tel !== '') {
        // Insert new row
        $stmt = $pdo->prepare("INSERT INTO `references_name` (`userid`, `r_fullname`, `r_address`, `r_contactno`) VALUES (?, ?, ?, ?)");
        $stmt->execute([$profile_userid, $name, $address, $tel]);
        $actions[] = [
            'field_name' => 'REFERENCES',
            'old_value' => '',
            'new_value' => implode(' | ', [$name, $address, $tel]),
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// 2. Delete removed references
foreach ($dbRows as $row) {
    if (!in_array($row['id'], $ref_ids)) {
        $stmt = $pdo->prepare("DELETE FROM `references_name` WHERE id = ? AND userid = ?");
        $stmt->execute([$row['id'], $profile_userid]);
        $actions[] = [
            'field_name' => 'REFERENCES',
            'old_value' => implode(' | ', [$row['r_fullname'], $row['r_address'], $row['r_contactno']]),
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