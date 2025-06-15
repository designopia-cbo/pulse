<?php
header('Content-Type: application/json');
session_start();
require_once('../config/db_connection.php');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$profile_userid = isset($data['profile_userid']) && $data['profile_userid'] !== ''
    ? $data['profile_userid']
    : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0);

if (!$profile_userid) {
    echo json_encode(['success' => false, 'message' => 'Missing profile_userid.']);
    exit;
}

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

// --- FAMILY BACKGROUND FIELDS ---
$f_firstname = strtoupper(trim($data['f_firstname'] ?? ''));
$f_middlename = strtoupper(trim($data['f_middlename'] ?? ''));
$f_surename = strtoupper(trim($data['f_surename'] ?? ''));
$m_firstname = strtoupper(trim($data['m_firstname'] ?? ''));
$m_middlename = strtoupper(trim($data['m_middlename'] ?? ''));
$m_surename = strtoupper(trim($data['m_surename'] ?? ''));

// --- CHILDREN LIST ---
$children = isset($data['children']) && is_array($data['children']) ? $data['children'] : [];

// --- LOGGING SETUP ---
$actions = [];

// --- GET OLD PARENTS DATA ---
$stmt = $pdo->prepare("SELECT * FROM parents_name WHERE userid = ?");
$stmt->execute([$profile_userid]);
$dbParent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dbParent) {
    // UPDATE
    $fields = [
        'f_firstname' => $f_firstname,
        'f_middlename' => $f_middlename,
        'f_surename' => $f_surename,
        'm_firstname' => $m_firstname,
        'm_middlename' => $m_middlename,
        'm_surename' => $m_surename
    ];
    $changed = false;
    foreach ($fields as $f => $val) {
        if (($dbParent[$f] ?? '') !== $val) {
            $actions[] = [
                'field_name' => "FAMILY " . strtoupper(str_replace('f_', 'FATHER ', str_replace('m_', 'MOTHER ', $f))),
                'old_value' => $dbParent[$f] ?? '',
                'new_value' => $val,
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
            $changed = true;
        }
    }
    if ($changed) {
        $stmt = $pdo->prepare("UPDATE parents_name SET f_firstname=?, f_middlename=?, f_surename=?, m_firstname=?, m_middlename=?, m_surename=? WHERE userid=?");
        $stmt->execute([$f_firstname, $f_middlename, $f_surename, $m_firstname, $m_middlename, $m_surename, $profile_userid]);
    }
} else {
    // INSERT
    $stmt = $pdo->prepare("INSERT INTO parents_name (userid, f_firstname, f_middlename, f_surename, m_firstname, m_middlename, m_surename) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$profile_userid, $f_firstname, $f_middlename, $f_surename, $m_firstname, $m_middlename, $m_surename]);
    foreach (['f_firstname','f_middlename','f_surename','m_firstname','m_middlename','m_surename'] as $f) {
        $actions[] = [
            'field_name' => "FAMILY " . strtoupper(str_replace('f_', 'FATHER ', str_replace('m_', 'MOTHER ', $f))),
            'old_value' => '',
            'new_value' => $$f,
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// --- GET OLD CHILDREN DATA ---
$stmt = $pdo->prepare("SELECT * FROM children WHERE userid = ?");
$stmt->execute([$profile_userid]);
$dbChildren = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map of old children by "name|dob" for easy lookup
$dbChildrenMap = [];
foreach ($dbChildren as $c) {
    $key = strtoupper(trim($c['c_fullname'])) . '|' . strtoupper(trim($c['c_bday']));
    $dbChildrenMap[$key] = $c;
}

// Build a map of new children by "name|dob"
$newChildrenMap = [];
foreach ($children as $c) {
    $name = strtoupper(trim($c['name'] ?? ''));
    $dob = strtoupper(trim($c['dob'] ?? ''));
    if ($name === '' && $dob === '') continue;
    $newChildrenMap["$name|$dob"] = true;
}

// --- DELETE ALL OLD CHILDREN, THEN INSERT NEW ONES ---
$pdo->prepare("DELETE FROM children WHERE userid = ?")->execute([$profile_userid]);
foreach ($children as $c) {
    $name = strtoupper(trim($c['name'] ?? ''));
    $dob = strtoupper(trim($c['dob'] ?? ''));
    if ($name === '' && $dob === '') continue;
    $stmt = $pdo->prepare("INSERT INTO children (userid, c_fullname, c_bday) VALUES (?, ?, ?)");
    $stmt->execute([$profile_userid, $name, $dob]);
}

// --- LOG CHILD CHANGES: REMOVED ---
foreach ($dbChildrenMap as $key => $oc) {
    if (!isset($newChildrenMap[$key])) {
        $actions[] = [
            'field_name' => 'FAMILY CHILD',
            'old_value' => "{$oc['c_fullname']} ({$oc['c_bday']})",
            'new_value' => '',
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}
// --- LOG CHILD CHANGES: ADDED ---
foreach ($newChildrenMap as $key => $dummy) {
    if (!isset($dbChildrenMap[$key])) {
        list($name, $dob) = explode('|', $key, 2);
        $actions[] = [
            'field_name' => 'FAMILY CHILD',
            'old_value' => '',
            'new_value' => "$name ($dob)",
            'employee_id' => $profile_userid,
            'updated_by' => $updated_by,
        ];
    }
}

// --- LOG TO HISTORY ---
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