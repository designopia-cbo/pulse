<?php
require_once('init.php');

// Restrict access as in main file
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || !in_array($_SESSION['category'], ['HR', 'SUPERADMIN'])
) {
    session_unset();
    session_destroy();
    die("Unauthorized access.");
}

// Get filters
$currentYear = (int)date('Y');
$year_options = [$currentYear, $currentYear + 1, $currentYear + 2];
$selectedYear = isset($_GET['year']) && in_array((int)$_GET['year'], $year_options) ? (int)$_GET['year'] : $currentYear;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "WHERE ed.edstatus = 1
    AND TIMESTAMPDIFF(YEAR, e.birthdate, :selectedYearDate) >= 60
    AND TIMESTAMPDIFF(YEAR, e.birthdate, :selectedYearDate) <= 65";
$params = [':selectedYearDate' => $selectedYear . '-12-31'];
if ($search !== '') {
    $where .= " AND e.fullname LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$sql = "
SELECT 
    e.fullname, 
    pp.position_title, 
    pp.item_number, 
    pp.cost_structure, 
    e.gender, 
    e.birthdate, 
    ed.sg, 
    ed.step, 
    ed.monthly_salary
FROM employee e
INNER JOIN employment_details ed ON e.id = ed.userid AND ed.edstatus = 1
LEFT JOIN plantilla_position pp ON ed.position_id = pp.id
$where
GROUP BY e.id
ORDER BY e.birthdate ASC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->execute();
$retirees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=retirees_list_' . $selectedYear . '.csv');

// Output CSV column headers
$output = fopen('php://output', 'w');
fputcsv($output, [
    'Full Name',
    'Position Title',
    'Item Number',
    'Cost Structure',
    'Gender',
    'Birthdate',
    'Age (as of Dec 31, ' . $selectedYear . ')',
    'Status',
    'SG',
    'Step',
    'Monthly Salary'
]);

foreach ($retirees as $emp) {
    // Calculate age as of selected year end
    $age = '';
    if (!empty($emp['birthdate']) && $emp['birthdate'] !== '0000-00-00') {
        $birth = new DateTime($emp['birthdate']);
        $asOf = new DateTime($selectedYear . '-12-31');
        $age = $birth->diff($asOf)->y;
    }
    // Determine status
    if ($age == 65) {
        $status = "Mandatory Retirement";
    } elseif ($age >= 60 && $age < 65) {
        $status = "Optional Retirement";
    } else {
        $status = "";
    }
    fputcsv($output, [
        $emp['fullname'],
        isset($emp['position_title']) ? $emp['position_title'] : '',
        isset($emp['item_number']) ? $emp['item_number'] : '',
        isset($emp['cost_structure']) ? $emp['cost_structure'] : '',
        $emp['gender'],
        (!empty($emp['birthdate']) && $emp['birthdate'] !== '0000-00-00') ? date('Y-m-d', strtotime($emp['birthdate'])) : '',
        $age,
        $status,
        $emp['sg'],
        $emp['step'],
        number_format($emp['monthly_salary'], 2)
    ]);
}
fclose($output);
exit;
?>