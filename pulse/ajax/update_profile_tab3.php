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

// --- Use profile_userid from payload if present and valid, otherwise fallback to session ---
if (isset($data['profile_userid']) && is_numeric($data['profile_userid'])) {
    $userid = intval($data['profile_userid']);
} elseif (isset($_SESSION['userid']) && $_SESSION['userid']) {
    $userid = $_SESSION['userid'];
} else {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

$eligibility = $data['eligibility'] ?? null;
if (!is_array($eligibility)) {
    echo json_encode(['success' => false, 'message' => 'No eligibility data provided or not an array.', 'data' => $data]);
    exit;
}

// --- Get updated_by as full name (UPPERCASE) ---
$updated_by = '';
if (isset($_SESSION['userid'])) {
    $emp_stmt = $pdo->prepare("SELECT first_name, last_name FROM employee WHERE id = ?");
    $emp_stmt->execute([$_SESSION['userid']]);
    $emp = $emp_stmt->fetch(PDO::FETCH_ASSOC);
    if ($emp) {
        $updated_by = strtoupper(trim($emp['first_name'] . ' ' . $emp['last_name']));
    }
}
if (!$updated_by) {
    $updated_by = 'UNKNOWN';
}

try {
    $pdo->beginTransaction();

    // Get existing eligibility rows for this user (id => row array)
    $stmt = $pdo->prepare("SELECT * FROM eligibility WHERE userid = ?");
    $stmt->execute([$userid]);
    $existingRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingRows[$row['id']] = $row;
    }
    $existingIds = array_keys($existingRows);

    $submittedIds = [];
    foreach ($eligibility as $rowIdx => $row) {
        // Sanitize and fallback for missing fields
        $id = isset($row['id']) ? trim($row['id']) : '';
        $eligibility_type = isset($row['eligibility_type']) ? $row['eligibility_type'] : '';
        $rating = isset($row['rating']) ? $row['rating'] : '';
        $date_exam = isset($row['date_exam']) ? $row['date_exam'] : '';
        $place_exam = isset($row['place_exam']) ? $row['place_exam'] : '';
        $license_no = isset($row['license_no']) && $row['license_no'] !== '' ? $row['license_no'] : null;
        $date_validity = isset($row['date_validity']) && $row['date_validity'] !== '' ? $row['date_validity'] : null;

        if ($id) $submittedIds[] = $id;

        if ($id && in_array($id, $existingIds)) {
            // --- UPDATE: Compare old and new for each field, log if changed ---
            $orig = $existingRows[$id];
            $fields = [
                'eligibility_type' => $eligibility_type,
                'rating' => $rating,
                'date_exam' => $date_exam,
                'place_exam' => $place_exam,
                'license_number' => $license_no,
                'date_validity' => $date_validity
            ];
            foreach ($fields as $field_name => $new_value) {
                $old_value = $orig[$field_name] ?? '';
                // Normalize nulls and blanks for comparison and uppercase
                $old_value_upper = strtoupper($old_value === null ? '' : $old_value);
                $new_value_upper = strtoupper($new_value === null ? '' : $new_value);
                // Format field_name: remove underscores, uppercase, and prefix "eligibility:"
                $formatted_field = 'eligibility:' . strtoupper(str_replace('_', '', $field_name));
                if ($old_value_upper != $new_value_upper) {
                    $log_stmt = $pdo->prepare("INSERT INTO employee_update_history (employee_id, field_name, old_value, new_value, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $log_stmt->execute([
                        $userid,
                        $formatted_field,
                        $old_value_upper,
                        $new_value_upper,
                        $updated_by
                    ]);
                }
            }
            // UPDATE
            $update = $pdo->prepare("UPDATE eligibility SET eligibility_type=?, rating=?, date_exam=UPPER(?), place_exam=?, license_number=?, date_validity=? WHERE id=? AND userid=?");
            if (!$update->execute([
                $eligibility_type,
                $rating,
                $date_exam,
                $place_exam,
                $license_no,
                $date_validity,
                $id,
                $userid
            ])) {
                throw new Exception('DB update failed: ' . print_r($update->errorInfo(), true));
            }
        } else {
            // --- INSERT: After insert, log all fields with old_value='' and new_value as entered ---
            $insert = $pdo->prepare("INSERT INTO eligibility (userid, eligibility_type, rating, date_exam, place_exam, license_number, date_validity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$insert->execute([
                $userid,
                $eligibility_type,
                $rating,
                $date_exam,
                $place_exam,
                $license_no,
                $date_validity
            ])) {
                throw new Exception('DB insert failed: ' . print_r($insert->errorInfo(), true));
            }
            // Get the new id
            $new_id = $pdo->lastInsertId();
            $fields = [
                'eligibility_type' => $eligibility_type,
                'rating' => $rating,
                'date_exam' => $date_exam,
                'place_exam' => $place_exam,
                'license_number' => $license_no,
                'date_validity' => $date_validity
            ];
            foreach ($fields as $field_name => $new_value) {
                $new_value_upper = strtoupper($new_value === null ? '' : $new_value);
                if ($new_value_upper !== '') { // Only log non-empty new values
                    $formatted_field = 'eligibility:' . strtoupper(str_replace('_', '', $field_name));
                    $log_stmt = $pdo->prepare("INSERT INTO employee_update_history (employee_id, field_name, old_value, new_value, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $log_stmt->execute([
                        $userid,
                        $formatted_field,
                        '',
                        $new_value_upper,
                        $updated_by
                    ]);
                }
            }
        }
    }

    // --- DELETE removed rows and log their removal ---
    $idsToKeep = array_filter($submittedIds); // ignore blanks (new rows)
    $idsToDelete = array_diff($existingIds, $idsToKeep);
    if ($idsToDelete) {
        foreach ($idsToDelete as $del_id) {
            $row = $existingRows[$del_id];
            $fields = [
                'eligibility_type' => $row['eligibility_type'],
                'rating' => $row['rating'],
                'date_exam' => $row['date_exam'],
                'place_exam' => $row['place_exam'],
                'license_number' => $row['license_number'],
                'date_validity' => $row['date_validity']
            ];
            foreach ($fields as $field_name => $old_value) {
                $old_value_upper = strtoupper($old_value === null ? '' : $old_value);
                if ($old_value_upper !== '') { // Only log non-empty deleted values
                    $formatted_field = 'eligibility:' . strtoupper(str_replace('_', '', $field_name));
                    $log_stmt = $pdo->prepare("INSERT INTO employee_update_history (employee_id, field_name, old_value, new_value, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $log_stmt->execute([
                        $userid,
                        $formatted_field,
                        $old_value_upper,
                        '',
                        $updated_by
                    ]);
                }
            }
        }
        // Now delete
        $in = str_repeat('?,', count($idsToDelete) - 1) . '?';
        $del = $pdo->prepare("DELETE FROM eligibility WHERE userid = ? AND id IN ($in)");
        if (!$del->execute(array_merge([$userid], $idsToDelete))) {
            throw new Exception('DB delete failed: ' . print_r($del->errorInfo(), true));
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}