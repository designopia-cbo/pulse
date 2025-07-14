<?php
header('Content-Type: application/json');
session_start();

try {
    require_once('../config/db_connection.php');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

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
    try {
        $stmt = $pdo->prepare("SELECT `first_name`, `last_name` FROM `employee` WHERE `id` = ?");
        $stmt->execute([$editor_userid]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($emp && (!empty($emp['first_name']) || !empty($emp['last_name']))) {
            $updated_by = strtoupper(trim($emp['first_name'] . ' ' . $emp['last_name']));
        }
    } catch (Exception $e) {
        // Continue with UNKNOWN if employee lookup fails
    }
}

$schoolnames = isset($data['schoolname']) ? $data['schoolname'] : [];
$basic_degree_courses = isset($data['basic_degree_course']) ? $data['basic_degree_course'] : [];
$from_dates = isset($data['from_date']) ? $data['from_date'] : [];
$to_dates = isset($data['to_date']) ? $data['to_date'] : [];
$units_earneds = isset($data['units_earned']) ? $data['units_earned'] : [];
$year_grads = isset($data['year_grad']) ? $data['year_grad'] : [];
$honors = isset($data['honor']) ? $data['honor'] : [];
$educ_ids = isset($data['educ_id']) ? $data['educ_id'] : [];

if (
    !is_array($schoolnames) || !is_array($basic_degree_courses) || !is_array($from_dates) ||
    !is_array($to_dates) || !is_array($units_earneds) || !is_array($year_grads) ||
    !is_array($honors) || !is_array($educ_ids)
) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT `id`, `schoolname`, `basic_degree_course`, `from_date`, `to_date`, `units_earned`, `year_grad`, `honor` FROM `educational_background` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database query error: ' . $e->getMessage()]);
    exit;
}

$dbMap = [];
foreach ($dbRows as $row) {
    $dbMap[$row['id']] = [
        'schoolname' => $row['schoolname'],
        'basic_degree_course' => $row['basic_degree_course'],
        'from_date' => $row['from_date'],
        'to_date' => $row['to_date'],
        'units_earned' => $row['units_earned'],
        'year_grad' => $row['year_grad'],
        'honor' => $row['honor'],
    ];
}

$actions = [];

// Function to determine level based on basic_degree_course
function getEducationLevel($basic_degree_course) {
    $course = strtoupper(trim($basic_degree_course));
    
    // If it's a custom value (from OTHERS selection), return 'GRADUATE STUDIES'
    if (!in_array($course, [
        'ELEMENTARY GRADUATE', 'ELEMENTARY UNDERGRADUATE',
        'JUNIOR HIGHSCHOOL GRADUATE', 'JUNIOR HIGHSCHOOL UNDERGRADUATE',
        'SENIOR HIGHSCHOOL GRADUATE', 'SENIOR HIGHSCHOOL UNDERGRADUATE',
        'SECONDARY GRADUATE', 'SECONDARY UNDERGRADUATE'
    ])) {
        return 'GRADUATE STUDIES';
    }
    
    // For predefined values, determine level based on the course
    if (strpos($course, 'ELEMENTARY') !== false) {
        return 'ELEMENTARY';
    } elseif (strpos($course, 'JUNIOR HIGHSCHOOL') !== false) {
        return 'JUNIOR HIGHSCHOOL';
    } elseif (strpos($course, 'SENIOR HIGHSCHOOL') !== false) {
        return 'SENIOR HIGHSCHOOL';
    } elseif (strpos($course, 'SECONDARY') !== false) {
        return 'SECONDARY';
    }
    
    return 'GRADUATE STUDIES'; // Default fallback
}

try {
    // 1. Update or Insert educational backgrounds
    foreach ($schoolnames as $i => $schoolname) {
        $school = strtoupper(trim($schoolnames[$i]));
        $basic = strtoupper(trim($basic_degree_courses[$i]));
        $from = strtoupper(trim($from_dates[$i]));
        $to = strtoupper(trim($to_dates[$i]));
        $units = strtoupper(trim($units_earneds[$i]));
        $year = strtoupper(trim($year_grads[$i]));
        $honor = strtoupper(trim($honors[$i]));
        $id = trim($educ_ids[$i]);
        $level = getEducationLevel($basic);

        if ($id && isset($dbMap[$id])) {
            $old = $dbMap[$id];
            if (
                $old['schoolname'] !== $school || $old['basic_degree_course'] !== $basic ||
                $old['from_date'] !== $from || $old['to_date'] !== $to ||
                $old['units_earned'] !== $units || $old['year_grad'] !== $year || $old['honor'] !== $honor
            ) {
                $stmt = $pdo->prepare("UPDATE `educational_background` SET `schoolname` = ?, `basic_degree_course` = ?, `from_date` = ?, `to_date` = ?, `units_earned` = ?, `year_grad` = ?, `honor` = ?, `level` = ? WHERE id = ? AND userid = ?");
                $stmt->execute([$school, $basic, $from, $to, $units, $year, $honor, $level, $id, $profile_userid]);
                $actions[] = [
                    'field_name' => 'EDUCATIONAL BACKGROUND',
                    'old_value' => implode(' | ', [$old['schoolname'], $old['basic_degree_course'], $old['from_date'], $old['to_date'], $old['units_earned'], $old['year_grad'], $old['honor']]),
                    'new_value' => implode(' | ', [$school, $basic, $from, $to, $units, $year, $honor]),
                    'employee_id' => $profile_userid,
                    'updated_by' => $updated_by,
                ];
            }
        } elseif ($school !== '' || $basic !== '' || $from !== '' || $to !== '' || $units !== '' || $year !== '' || $honor !== '') {
            $stmt = $pdo->prepare("INSERT INTO `educational_background` (`userid`, `schoolname`, `basic_degree_course`, `from_date`, `to_date`, `units_earned`, `year_grad`, `honor`, `level`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$profile_userid, $school, $basic, $from, $to, $units, $year, $honor, $level]);
            $actions[] = [
                'field_name' => 'EDUCATIONAL BACKGROUND',
                'old_value' => '',
                'new_value' => implode(' | ', [$school, $basic, $from, $to, $units, $year, $honor]),
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    }

    // 2. Delete educational backgrounds that are no longer in the form
    foreach ($dbRows as $row) {
        if (!in_array($row['id'], $educ_ids)) {
            $stmt = $pdo->prepare("DELETE FROM `educational_background` WHERE id = ? AND userid = ?");
            $stmt->execute([$row['id'], $profile_userid]);
            $actions[] = [
                'field_name' => 'EDUCATIONAL BACKGROUND',
                'old_value' => implode(' | ', [$row['schoolname'], $row['basic_degree_course'], $row['from_date'], $row['to_date'], $row['units_earned'], $row['year_grad'], $row['honor']]),
                'new_value' => '',
                'employee_id' => $profile_userid,
                'updated_by' => $updated_by,
            ];
        }
    }

    // 3. Log all actions
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

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}