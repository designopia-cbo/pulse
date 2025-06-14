<?php
session_start();
header('Content-Type: application/json');

// Include the DB connection
require_once('../config/db_connection.php');


// --- Get JSON data
$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'No POST body received.']);
    exit;
}
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg(), 'raw' => $raw]);
    exit;
}

// --- Profile owner (whose data is being edited)
if (isset($data['profile_userid']) && is_numeric($data['profile_userid'])) {
    $profile_userid = intval($data['profile_userid']);
} elseif (isset($_SESSION['userid']) && $_SESSION['userid']) {
    $profile_userid = $_SESSION['userid'];
} else {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// --- Editor (person making the change)
if (isset($_SESSION['userid']) && $_SESSION['userid']) {
    $editor_userid = $_SESSION['userid'];
} else {
    $editor_userid = $profile_userid; // fallback
}

// --- Fetch updated_by full name in UPPER CASE ---
$updated_by = '';
$emp_stmt = $pdo->prepare("SELECT UPPER(CONCAT(first_name, ' ', last_name)) AS full_name FROM employee WHERE id = ?");
$emp_stmt->execute([$editor_userid]);
if ($emp_row = $emp_stmt->fetch(PDO::FETCH_ASSOC)) {
    $updated_by = $emp_row['full_name'];
} else {
    $updated_by = strtoupper('UNKNOWN USER');
}

// Logging helper
function logChange($pdo, $employee_id, $field_name, $old_value, $new_value, $updated_by) {
    $stmt = $pdo->prepare("INSERT INTO employee_update_history 
        (employee_id, field_name, old_value, new_value, updated_by, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $employee_id,
        strtoupper($field_name),
        strtoupper($old_value),
        strtoupper($new_value),
        strtoupper($updated_by)
    ]);
}

// Helper to format field_name
function formatFieldName($section, $field) {
    $sections = [
        'work_experience' => 'WORK EXPERIENCE',
        'voluntary_works' => 'VOLUNTARY WORK'
    ];
    $field = str_replace('_', ' ', $field);
    return $sections[$section] . ':' . strtoupper($field);
}

// Determine which section to update
$section = $data['section'] ?? '';
if (!in_array($section, ['work_experience', 'voluntary_works'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing section.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($section === 'work_experience') {
        $workRows = $data['work_experience'] ?? null;
        if (!is_array($workRows)) {
            echo json_encode(['success' => false, 'message' => 'No work experience data provided or not an array.']);
            exit;
        }

        // Get existing work_experience rows for the profile user (not the editor!)
        $stmt = $pdo->prepare("SELECT * FROM work_experience WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $existingRows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingRows[$row['id']] = $row;
        }
        $existingIds = array_keys($existingRows);

        $submittedIds = [];
        foreach ($workRows as $row) {
            $id = isset($row['id']) ? trim($row['id']) : '';
            $w_from_date = strtoupper($row['w_from_date'] ?? '');
            $w_to_date = strtoupper($row['w_to_date'] ?? '');
            $position_title = strtoupper($row['position_title'] ?? '');
            $agency_name = strtoupper($row['agency_name'] ?? '');
            $monthly_salary = strtoupper($row['monthly_salary'] ?? '');
            $sg_step = strtoupper($row['sg_step'] ?? '');
            $status_appt = strtoupper($row['status_appt'] ?? '');
            $government_service = strtoupper($row['government_service'] ?? '');

            if ($id) $submittedIds[] = $id;

            if ($id && in_array($id, $existingIds)) {
                // Log changes
                $old = $existingRows[$id];
                $fields = [
                    'w_from_date', 'w_to_date', 'position_title', 'agency_name',
                    'monthly_salary', 'sg_step', 'status_appt', 'government_service'
                ];
                $newValues = [
                    'w_from_date' => $w_from_date,
                    'w_to_date' => $w_to_date,
                    'position_title' => $position_title,
                    'agency_name' => $agency_name,
                    'monthly_salary' => $monthly_salary,
                    'sg_step' => $sg_step,
                    'status_appt' => $status_appt,
                    'government_service' => $government_service
                ];
                foreach ($fields as $f) {
                    if (strtoupper((string)$old[$f]) !== strtoupper((string)$newValues[$f])) {
                        logChange(
                            $pdo,
                            $profile_userid,
                            formatFieldName('work_experience', $f),
                            $old[$f],
                            $newValues[$f],
                            $updated_by
                        );
                    }
                }
                // UPDATE for the profile user
                $update = $pdo->prepare("UPDATE work_experience SET w_from_date=?, w_to_date=?, position_title=?, agency_name=?, monthly_salary=?, sg_step=?, status_appt=?, government_service=? WHERE id=? AND userid=?");
                if (!$update->execute([
                    $w_from_date,
                    $w_to_date,
                    $position_title,
                    $agency_name,
                    $monthly_salary,
                    $sg_step,
                    $status_appt,
                    $government_service,
                    $id,
                    $profile_userid
                ])) {
                    throw new Exception('DB update failed: ' . print_r($update->errorInfo(), true));
                }
            } else {
                // INSERT for the profile user
                $insert = $pdo->prepare("INSERT INTO work_experience (userid, w_from_date, w_to_date, position_title, agency_name, monthly_salary, sg_step, status_appt, government_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$insert->execute([
                    $profile_userid,
                    $w_from_date,
                    $w_to_date,
                    $position_title,
                    $agency_name,
                    $monthly_salary,
                    $sg_step,
                    $status_appt,
                    $government_service
                ])) {
                    throw new Exception('DB insert failed: ' . print_r($insert->errorInfo(), true));
                }
                // Log as added
                $fields = [
                    'w_from_date' => $w_from_date,
                    'w_to_date' => $w_to_date,
                    'position_title' => $position_title,
                    'agency_name' => $agency_name,
                    'monthly_salary' => $monthly_salary,
                    'sg_step' => $sg_step,
                    'status_appt' => $status_appt,
                    'government_service' => $government_service
                ];
                foreach ($fields as $f => $v) {
                    logChange(
                        $pdo,
                        $profile_userid,
                        formatFieldName('work_experience', $f),
                        '',
                        $v,
                        $updated_by
                    );
                }
            }
        }
        // DELETE removed rows
        $idsToKeep = array_filter($submittedIds);
        $idsToDelete = array_diff($existingIds, $idsToKeep);
        if ($idsToDelete) {
            foreach ($idsToDelete as $delId) {
                $old = $existingRows[$delId];
                $fields = [
                    'w_from_date', 'w_to_date', 'position_title', 'agency_name',
                    'monthly_salary', 'sg_step', 'status_appt', 'government_service'
                ];
                foreach ($fields as $f) {
                    logChange(
                        $pdo,
                        $profile_userid,
                        formatFieldName('work_experience', $f),
                        $old[$f],
                        '',
                        $updated_by
                    );
                }
            }
            $in = str_repeat('?,', count($idsToDelete) - 1) . '?';
            $del = $pdo->prepare("DELETE FROM work_experience WHERE userid = ? AND id IN ($in)");
            if (!$del->execute(array_merge([$profile_userid], $idsToDelete))) {
                throw new Exception('DB delete failed: ' . print_r($del->errorInfo(), true));
            }
        }
    } else if ($section === 'voluntary_works') {
        $volRows = $data['voluntary_works'] ?? null;
        if (!is_array($volRows)) {
            echo json_encode(['success' => false, 'message' => 'No voluntary works data provided or not an array.']);
            exit;
        }

        // Get existing voluntary_works rows for the profile user (not the editor!)
        $stmt = $pdo->prepare("SELECT * FROM voluntary_works WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $existingRows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingRows[$row['id']] = $row;
        }
        $existingIds = array_keys($existingRows);

        $submittedIds = [];
        foreach ($volRows as $row) {
            $id = isset($row['id']) ? trim($row['id']) : '';
            $name_org_address = strtoupper($row['name_org_address'] ?? '');
            $v_from_date = strtoupper($row['v_from_date'] ?? '');
            $v_to_date = strtoupper($row['v_to_date'] ?? '');
            $number_hours = strtoupper($row['number_hours'] ?? '');
            $position_nature_work = strtoupper($row['position_nature_work'] ?? '');

            if ($id) $submittedIds[] = $id;

            if ($id && in_array($id, $existingIds)) {
                // Log changes
                $old = $existingRows[$id];
                $fields = [
                    'name_org_address', 'v_from_date', 'v_to_date', 'number_hours', 'position_nature_work'
                ];
                $newValues = [
                    'name_org_address' => $name_org_address,
                    'v_from_date' => $v_from_date,
                    'v_to_date' => $v_to_date,
                    'number_hours' => $number_hours,
                    'position_nature_work' => $position_nature_work
                ];
                foreach ($fields as $f) {
                    if (strtoupper((string)$old[$f]) !== strtoupper((string)$newValues[$f])) {
                        logChange(
                            $pdo,
                            $profile_userid,
                            formatFieldName('voluntary_works', $f),
                            $old[$f],
                            $newValues[$f],
                            $updated_by
                        );
                    }
                }
                // UPDATE for the profile user
                $update = $pdo->prepare("UPDATE voluntary_works SET name_org_address=?, v_from_date=?, v_to_date=?, number_hours=?, position_nature_work=? WHERE id=? AND userid=?");
                if (!$update->execute([
                    $name_org_address,
                    $v_from_date,
                    $v_to_date,
                    $number_hours,
                    $position_nature_work,
                    $id,
                    $profile_userid
                ])) {
                    throw new Exception('DB update failed: ' . print_r($update->errorInfo(), true));
                }
            } else {
                // INSERT for the profile user
                $insert = $pdo->prepare("INSERT INTO voluntary_works (userid, name_org_address, v_from_date, v_to_date, number_hours, position_nature_work) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$insert->execute([
                    $profile_userid,
                    $name_org_address,
                    $v_from_date,
                    $v_to_date,
                    $number_hours,
                    $position_nature_work
                ])) {
                    throw new Exception('DB insert failed: ' . print_r($insert->errorInfo(), true));
                }
                // Log as added
                $fields = [
                    'name_org_address' => $name_org_address,
                    'v_from_date' => $v_from_date,
                    'v_to_date' => $v_to_date,
                    'number_hours' => $number_hours,
                    'position_nature_work' => $position_nature_work
                ];
                foreach ($fields as $f => $v) {
                    logChange(
                        $pdo,
                        $profile_userid,
                        formatFieldName('voluntary_works', $f),
                        '',
                        $v,
                        $updated_by
                    );
                }
            }
        }
        // DELETE removed rows
        $idsToKeep = array_filter($submittedIds);
        $idsToDelete = array_diff($existingIds, $idsToKeep);
        if ($idsToDelete) {
            foreach ($idsToDelete as $delId) {
                $old = $existingRows[$delId];
                $fields = [
                    'name_org_address', 'v_from_date', 'v_to_date', 'number_hours', 'position_nature_work'
                ];
                foreach ($fields as $f) {
                    logChange(
                        $pdo,
                        $profile_userid,
                        formatFieldName('voluntary_works', $f),
                        $old[$f],
                        '',
                        $updated_by
                    );
                }
            }
            $in = str_repeat('?,', count($idsToDelete) - 1) . '?';
            $del = $pdo->prepare("DELETE FROM voluntary_works WHERE userid = ? AND id IN ($in)");
            if (!$del->execute(array_merge([$profile_userid], $idsToDelete))) {
                throw new Exception('DB delete failed: ' . print_r($del->errorInfo(), true));
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}