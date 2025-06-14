<?php
header('Content-Type: application/json');
session_start();
require_once('../config/db_connection.php');

// Ensure PDO throws exceptions for error handling
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
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

    // List all fields in the personal_disclosure table (except id, userid)
    $fields = [
        'q1','q2','r2','q3','r3','q4','r4_1','r4_2',
        'q5','r5','q6','r6','q7','r7','q8','r8',
        'q9','r9','q10','r10','q11','r11','q12','r12',
        'q13','r13','q14','r14'
    ];

    // Prepare new values (always uppercase, always present)
    $newValues = [];
    foreach ($fields as $f) {
        // If not provided, use an empty string
        $newValues[$f] = strtoupper(trim(array_key_exists($f, $data) ? $data[$f] : ''));
    }

    // Get current disclosure row
    $stmt = $pdo->prepare("SELECT * FROM personal_disclosure WHERE userid = ?");
    $stmt->execute([$profile_userid]);
    $dbDisclosure = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no row exists for this userid, insert a new row first
    if (!$dbDisclosure) {
        $insert_vals = [];
        foreach ($fields as $f) {
            $insert_vals[] = $newValues[$f];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO personal_disclosure (userid," . implode(',', $fields) . ") VALUES (?, " . implode(',', array_fill(0, count($fields), '?')) . ")"
        );
        $stmt->execute(array_merge([$profile_userid], $insert_vals));
        // After insert, fetch the newly created row for further update/logging
        $stmt = $pdo->prepare("SELECT * FROM personal_disclosure WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $dbDisclosure = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dbDisclosure) {
            throw new Exception("Could not insert personal disclosure row.");
        }
    }

    $actions = [];
    // Now update as usual (compare new and old, update if needed)
    $changed = false;
    foreach ($fields as $f) {
        $old = strtoupper(trim($dbDisclosure[$f] ?? ''));
        $new = $newValues[$f];
        if ($old !== $new) {
            $actions[] = [
                'field_name' => "PERSONAL DISCLOSURE $f",
                'old_value' => $old,
                'new_value' => $new,
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by
            ];
            $changed = true;
        }
    }
    if ($changed) {
        $set = [];
        $vals = [];
        foreach ($fields as $f) {
            $set[] = "`$f`=?";
            $vals[] = $newValues[$f];
        }
        $vals[] = $profile_userid;
        $stmt = $pdo->prepare("UPDATE personal_disclosure SET " . implode(',', $set) . " WHERE userid=?");
        $stmt->execute($vals);
    }

    // Log each change in employee_update_history
    foreach ($actions as $log) {
        $stmt = $pdo->prepare("
            INSERT INTO employee_update_history
            (employee_id, field_name, old_value, new_value, updated_by, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $log['employee_id'],
            $log['field_name'],
            $log['old_value'],
            $log['new_value'],
            $log['updated_by']
        ]);
    }

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}