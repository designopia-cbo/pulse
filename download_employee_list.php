<?php
require_once('init.php');

// Only ADMINISTRATOR + SUPERADMIN allowed
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || $_SESSION['category'] !== 'SUPERADMIN'
) {
    session_unset();
    session_destroy();
    die("Unauthorized access.");
}

// --- Search Logic ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "WHERE ed.edstatus = 1";
$params = [];
if ($search !== '') {
  $where .= " AND (
    e.fullname LIKE :search
    OR pp.position_title LIKE :search
  )";
  $params[':search'] = '%' . $search . '%';
}

$sql = "
SELECT 
  e.fullname,
  ed.sg,
  ed.step,
  pp.item_number,
  pp.position_title,
  pp.org_unit,
  pp.office,
  pp.cost_structure,
  e.date_orig_appt,
  ed.date_of_assumption,
  ed.date_appointment,
  pp.classification
FROM employment_details ed
INNER JOIN employee e ON ed.userid = e.id
INNER JOIN plantilla_position pp ON ed.position_id = pp.id
$where
ORDER BY e.fullname ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=employee_list.csv');

// Output CSV column headers (in your requested order)
$output = fopen('php://output', 'w');
fputcsv($output, [
    'Full Name',
    'SG',
    'Step',
    'Item Number',
    'Position Title',
    'Org Unit',
    'Office',
    'Cost Structure',
    'Date of Original Appointment',
    'Date of Assumption',
    'Date of Appointment',
    'Classification'
]);

foreach ($rows as $row) {
    // Format name and classification
    $properFullName = ucwords(strtolower($row['fullname']));
    $classification = '';
    switch (strtoupper($row['classification'])) {
      case 'P':
        $classification = 'Permanent';
        break;
      case 'CTI':
        $classification = 'Coterminous with the Incumbent';
        break;
      case 'CT':
        $classification = 'Coterminous';
        break;
      default:
        $classification = $row['classification'];
        break;
    }
    fputcsv($output, [
        $properFullName,
        $row['sg'],
        $row['step'],
        $row['item_number'],
        strtoupper($row['position_title']),
        $row['org_unit'],
        $row['office'],
        $row['cost_structure'],
        $row['date_orig_appt'],
        $row['date_of_assumption'],
        $row['date_appointment'],
        $classification
    ]);
}
fclose($output);
exit;
?>