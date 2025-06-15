<?php
session_start();
header('Content-Type: application/json');

// Include the DB connection
require_once('../config/db_connection.php');


// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'No input received.']);
    exit;
}

// Validate and get profile_userid (the employee being edited)
$profile_userid = isset($input['profile_userid']) ? intval($input['profile_userid']) : 0;
if (!$profile_userid) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing profile_userid.']);
    exit;
}

// Get the name of the user making the update (from session userid)
$session_userid = isset($_SESSION['userid']) ? intval($_SESSION['userid']) : 0;
$updated_by = "UNKNOWN";
if ($session_userid > 0) {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM employee WHERE id = ?");
    $stmt->execute([$session_userid]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $updated_by = trim($row['first_name'] . ' ' . $row['last_name']);
    }
}

// Get skills rows
$skills = isset($input['skills']) && is_array($input['skills']) ? $input['skills'] : [];
$skill_ids = isset($input['skill_id']) && is_array($input['skill_id']) ? $input['skill_id'] : [];

try {
    $pdo->beginTransaction();

    // Fetch all existing rows for this user
    $stmt = $pdo->prepare("SELECT * FROM `special_skills` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $existingRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingRows[$row['id']] = $row;
    }
    $existingIds = array_keys($existingRows);
    $sentIds = [];

    // Helper function to log changes
    function log_history($pdo, $employee_id, $field_name, $old_value, $new_value, $updated_by) {
        $stmt = $pdo->prepare("INSERT INTO `employee_update_history`
            (`employee_id`, `field_name`, `old_value`, `new_value`, `updated_by`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $employee_id,
            $field_name,
            $old_value,
            $new_value,
            $updated_by
        ]);
    }

    // Helper to clean the field name
    function clean_field_name($raw) {
        return strtoupper(str_replace('_', ' ', $raw));
    }

    // Process each submitted row (update or insert)
    foreach ($skills as $index => $skill) {
        $skill_val = strtoupper(trim($skill ?? ''));
        $id = isset($skill_ids[$index]) && $skill_ids[$index] !== '' ? intval($skill_ids[$index]) : null;

        if ($id && isset($existingRows[$id])) {
            // Update: compare old and new for logging
            $old = $existingRows[$id];
            $field = 'specific_skills';
            $old_val = strtoupper(trim($old[$field] ?? ''));
            if ($old_val != $skill_val) {
                log_history(
                    $pdo, $profile_userid, clean_field_name($field),
                    $old_val, $skill_val, $updated_by
                );
            }
            // Update
            $stmtU = $pdo->prepare("UPDATE `special_skills`
                SET `specific_skills`=?
                WHERE `id`=? AND `userid`=?");
            $stmtU->execute([
                $skill_val, $id, $profile_userid
            ]);
            $sentIds[] = $id;
        } else {
            // Insert: log with old_value as blank
            log_history(
                $pdo, $profile_userid, clean_field_name('specific_skills'),
                '', $skill_val, $updated_by
            );
            // Insert
            $stmtI = $pdo->prepare("INSERT INTO `special_skills`
                (`userid`, `specific_skills`)
                VALUES (?, ?)");
            $stmtI->execute([
                $profile_userid, $skill_val
            ]);
            // Optionally get last insert id if needed
        }
    }

    // Delete removed IDs
    $idsToDelete = array_diff($existingIds, $sentIds);
    if (!empty($idsToDelete)) {
        foreach ($idsToDelete as $delId) {
            $old = $existingRows[$delId];
            $field = 'specific_skills';
            $old_val = strtoupper(trim($old[$field] ?? ''));
            log_history(
                $pdo, $profile_userid, clean_field_name($field),
                $old_val, '', $updated_by
            );
        }
        $qMarks = implode(',', array_fill(0, count($idsToDelete), '?'));
        $params = array_merge([$profile_userid], $idsToDelete);
        $stmtD = $pdo->prepare("DELETE FROM `special_skills` WHERE `userid` = ? AND `id` IN ($qMarks)");
        $stmtD->execute($params);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}