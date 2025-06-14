<?php
require_once('init.php');

// Restrict page access according to valid level/category
$userLevel = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$userCategory = isset($_SESSION['category']) ? $_SESSION['category'] : '';

$allowed = false;
if (
  ($userLevel === 'ADMINISTRATOR' && in_array($userCategory, ['HR', 'MINISTER', 'AAO'])) ||
  ($userCategory === 'AAO')
) {
  $allowed = true;
}
if (!$allowed) {
  // Force logout on denied access
  session_unset();
  session_destroy();
  header("Location: login.php");
  exit;
}

// Fetch user details using session 'userid'
$userid = $_SESSION['userid'];
$category = isset($_SESSION['category']) ? $_SESSION['category'] : '';
$stmt = $pdo->prepare("SELECT fullname FROM employee WHERE id = :userid");
$stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
  $fullName = ucwords(strtolower($user['fullname']));
  $initial = strtoupper(substr($fullName, 0, 1));
} else {
  $fullName = "Unknown User";
  $initial = "U";
}


// Fetch user's office (for AAO/office-based filtering)
$userOffice = null;
$stmtOffice = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
$stmtOffice->bindParam(':userid', $userid, PDO::PARAM_INT);
$stmtOffice->execute();
$rowOffice = $stmtOffice->fetch(PDO::FETCH_ASSOC);
if ($rowOffice && isset($rowOffice['office'])) {
  $userOffice = $rowOffice['office'];
}

// --- SEARCH LOGIC ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build search WHERE clause
$where = [];
$params = [];

// Only add search if not empty
if ($search !== '') {
  $where[] = "(employee.fullname LIKE :search OR emp_leave.leave_type LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}
$whereSql = '';
if (count($where) > 0) {
  $whereSql = "WHERE " . implode(' AND ', $where);
}

// Bulk fetch all leaves and join roles and applicant's office
$sql = "
  SELECT 
    emp_leave.*,
    employee.fullname,
    emp_leave.hr AS hr_id,
    emp_leave.supervisor AS supervisor_id,
    emp_leave.manager AS manager_id,
    plantilla_position.office AS applicant_office
  FROM emp_leave
  LEFT JOIN employee ON emp_leave.userid = employee.id
  LEFT JOIN plantilla_position ON emp_leave.userid = plantilla_position.userid
  $whereSql
  ORDER BY emp_leave.id DESC
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
  $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$allLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtering logic
$leaves = [];
foreach ($allLeaves as $leave) {
  $isAllowed = (
    $userid == $leave['userid'] ||
    $userid == $leave['hr_id'] ||
    $userid == $leave['supervisor_id'] ||
    $userid == $leave['manager_id']
  );

// ADMINISTRATOR + (HR or MINISTER) can see all
  if (
    !$isAllowed &&
    $userLevel === 'ADMINISTRATOR' &&
    in_array($userCategory, ['HR', 'MINISTER'])
  ) {
    $isAllowed = true;
  }

// ADMINISTRATOR+AAO or AAO can see leaves in their office
  if (
    !$isAllowed &&
    (
      ($userLevel === 'ADMINISTRATOR' && $userCategory === 'AAO') ||
      $userCategory === 'AAO'
    ) &&
    !empty($userOffice) && !empty($leave['applicant_office']) &&
    $userOffice === $leave['applicant_office']
  ) {
    $isAllowed = true;
  }

  if ($isAllowed) {
    $leaves[] = $leave;
  }
}

// Fix pagination based on filtered leaves
$totalRows = count($leaves);
$totalPages = max(1, ceil($totalRows / $perPage));
// Only show the "current page" filtered leaves
$leaves = array_slice($leaves, $offset, $perPage);

function getStatusDetails($status) {
  switch ($status) {
    case 1:
    case 2:
    case 3:
    return [
      'text' => 'Pending',
      'badge_class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/10 dark:text-yellow-500',
      'svg' => '<svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1a7 7 0 1 0 7 7A7.008 7.008 0 0 0 8 1zm0 12a.75.75 0 1 1 0-1.5A.75.75 0 0 1 8 13zm.75-3.25h-1.5V5h1.5z"/></svg>'
    ];
    case 4:
    return [
      'text' => 'Approved',
      'badge_class' => 'bg-teal-100 text-teal-800 dark:bg-teal-500/10 dark:text-teal-500',
      'svg' => '<svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>'
    ];
    case 5:
    return [
      'text' => 'Rejected',
      'badge_class' => 'bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-500',
      'svg' => '<svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
    ];
    case 6:
    return [
      'text' => 'Cancelled',
      'badge_class' => 'bg-orange-100 text-orange-800 dark:bg-orange-500/10 dark:text-orange-500',
      'svg' => '<svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>'
    ];
    default:
    return [
      'text' => 'Unknown',
      'badge_class' => 'bg-gray-100 text-gray-800',
      'svg' => ''
    ];
  }
}

// Corrected: use 'appdate' as the source for application date
function getApplicationDate($leave) {
  if (!empty($leave['appdate'])) {
    return date('M d, Y', strtotime($leave['appdate']));
  }
  $dateFields = ['date_filed', 'application_date', 'applied_at', 'date_applied'];
  foreach ($dateFields as $field) {
    if (!empty($leave[$field])) {
      return date('M d, Y', strtotime($leave[$field]));
    }
  }
  return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>  

<!-- Title -->
<title> HRIS | All Leaves</title>

<!-- CSS Preline -->
<link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>

<body class="bg-gray-50 dark:bg-neutral-900">
<!-- ========== HEADER ========== -->
<header class="sticky top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-48 w-full bg-white border-b border-gray-200 text-sm py-2.5 lg:ps-65 dark:bg-neutral-800 dark:border-neutral-700">
  <nav class="px-4 sm:px-6 flex basis-full items-center w-full mx-auto">
    <div class="me-5 lg:me-0 lg:hidden flex items-center">
      <!-- Logo -->
      <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline">
        <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline" style="color: #155dfc; font-weight: bold; text-decoration: none;">
          MSSD PULSE
        </a>
      </a>
      <!-- End Logo -->
    </div>

    <div class="w-full flex items-center justify-end ms-auto md:justify-between gap-x-1 md:gap-x-3">

      <div class="hidden md:block">

      </div>

      <div class="flex flex-row items-center justify-end gap-1">


        <button type="button" class="size-9.5 relative inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span class="sr-only">Notifications</span>
        </button>

        <!-- Dropdown -->
        <div class="hs-dropdown [--placement:bottom-right] relative inline-flex">
          <button id="hs-dropdown-account" type="button" class="size-9.5 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent text-gray-800 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none dark:text-white" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
            <span class="shrink-0 size-9.5 flex items-center justify-center rounded-full bg-gray-200 text-gray-800 dark:bg-neutral-700 dark:text-neutral-200 font-medium text-sm">
              <?php echo htmlspecialchars($initial); ?>
            </span>
          </button>


          <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700 dark:divide-neutral-700 after:h-4 after:absolute after:-bottom-4 after:start-0 after:w-full before:h-4 before:absolute before:-top-4 before:start-0 before:w-full" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-account">
            <div class="py-3 px-5 bg-gray-100 rounded-t-lg dark:bg-neutral-700">
              <p class="text-sm text-gray-500 dark:text-neutral-500">Signed in as</p>
              <p class="text-sm font-medium text-gray-800 dark:text-neutral-200"><?php echo htmlspecialchars($fullName); ?></p>
            </div>
            <div class="p-1.5 space-y-0.5">
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="profile.php">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="7" r="4" />
                  <path d="M4 20c0-4 4-7 8-7s8 3 8 7" />
                </svg>
                My Profile
              </a>
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="changepassword.php">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0110 0v4" />
              </svg>
              Change Password
            </a>
            <a href="logout.php" class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12H21" />
                <path d="M16 6l6 6-6 6" />
                <path d="M3 12h6" />
              </svg>
              Logout
            </a>

          </div>
        </div>
      </div>
      <!-- End Dropdown -->
    </div>
  </div>
</nav>
</header>
<!-- ========== END HEADER ========== -->

<!-- ========== MAIN CONTENT ========== -->
<!-- Breadcrumb -->
<div class="sticky top-0 inset-x-0 z-20 bg-white border-y border-gray-200 px-4 sm:px-6 lg:px-8 lg:hidden dark:bg-neutral-800 dark:border-neutral-700">
  <div class="flex items-center py-2">
    <!-- Navigation Toggle -->
    <button type="button" class="size-8 flex justify-center items-center gap-x-2 border border-gray-200 text-gray-800 hover:text-gray-500 rounded-lg focus:outline-hidden focus:text-gray-500 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700 dark:text-neutral-200 dark:hover:text-neutral-500 dark:focus:text-neutral-500" aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-application-sidebar" aria-label="Toggle navigation" data-hs-overlay="#hs-application-sidebar">
      <span class="sr-only">Toggle Navigation</span>
      <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect width="18" height="18" x="3" y="3" rx="2" />
        <path d="M15 3v18" />
        <path d="m8 9 3 3-3 3" />
      </svg>
    </button>
    <!-- End Navigation Toggle -->

    <!-- Breadcrumb -->
    <ol class="ms-3 flex items-center whitespace-nowrap">
      <li class="flex items-center text-sm text-gray-800 dark:text-neutral-400">
        HRIS
        <svg class="shrink-0 mx-3 overflow-visible size-2.5 text-gray-400 dark:text-neutral-500" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M5 1L10.6869 7.16086C10.8637 7.35239 10.8637 7.64761 10.6869 7.83914L5 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
      </li>
      <li class="text-sm font-semibold text-gray-800 truncate dark:text-neutral-400" aria-current="page">
        All Leaves
      </li>
    </ol>
    <!-- End Breadcrumb -->
  </div>
</div>
<!-- End Breadcrumb -->

<!-- Sidebar -->
<div id="hs-application-sidebar" class="hs-overlay  [--auto-close:lg]
hs-overlay-open:translate-x-0
-translate-x-full transition-all duration-300 transform
w-65 h-full
hidden
fixed inset-y-0 start-0 z-60
bg-white border-e border-gray-200
lg:block lg:translate-x-0 lg:end-auto lg:bottom-0
dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-label="Sidebar">
<div class="relative flex flex-col h-full max-h-full">
  <div class="px-6 pt-4 flex justify-center items-center">
    <!-- Logo -->
    <a class="flex-none rounded-xl text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline">
      <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline" style="color: #155dfc; font-weight: bold; text-decoration: none;">
        MSSD PULSE
      </a>
    </a>
    <!-- End Logo -->
  </div>
<!-- Content -->
<div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-gray-100 [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
  <nav class="hs-accordion-group p-3 w-full flex flex-col flex-wrap" data-hs-accordion-always-open>
    <ul class="flex flex-col space-y-1">
      <li>
        <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
          <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="dashboard.php">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            Dashboard
          </a>
        <?php endif; ?>
      </li>

      <li>
        <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
          <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="employeelist.php">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>                        
            Employee List
          </a>
        <?php endif; ?>
      </li>

      <li>
        <?php if (
          isset($_SESSION['level'], $_SESSION['category']) &&
          $_SESSION['level'] === 'ADMINISTRATOR' &&
          in_array($_SESSION['category'], ['HR', 'AAO', 'MINISTER'])
        ): ?>
          <li class="hs-accordion" id="projects-accordion">
            <button type="button" class="hs-accordion-toggle w-full text-start flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" aria-expanded="true" aria-controls="projects-accordion-child">
              <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                <line x1="16" x2="16" y1="2" y2="6"/>
                <line x1="8" x2="8" y1="2" y2="6"/>
                <line x1="3" x2="21" y1="10" y2="10"/>
                <path d="M8 14h.01"/>
                <path d="M12 14h.01"/>
                <path d="M16 14h.01"/>
                <path d="M8 18h.01"/>
                <path d="M12 18h.01"/>
                <path d="M16 18h.01"/>
              </svg>
              Employee Leave

              <svg class="hs-accordion-active:block ms-auto hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m18 15-6-6-6 6"/>
              </svg>

              <svg class="hs-accordion-active:hidden ms-auto block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </button>

            <div id="projects-accordion-child" class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300 !block" role="region" aria-labelledby="projects-accordion">
              <ul class="ps-8 pt-1 space-y-1">
                <li>
                  <a class="flex items-center gap-x-3.5 py-2 px-2.5 bg-gray-100 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="allleave.php">
                    Employee Applications
                  </a>
                </li>
                <li>
                  <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="leavecredit.php">
                    Employee Leave Credits
                  </a>
                </li>
                <li>
                  <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="employeecreditlog.php">
                    Employee Credit Logs
                  </a>
                </li>
              </ul>
            </div>
          </li>    
        <?php endif; ?>
      </li>

    <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200  dark:text-white dark:after:border-neutral-600"></div>

    <li class="hs-accordion" id="projects-accordion">
      <button type="button" class="hs-accordion-toggle w-full text-start flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" aria-expanded="true" aria-controls="projects-accordion-child">
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="20" height="14" x="2" y="7" rx="2" ry="2" />
          <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
        </svg>
        My Leaves

        <svg class="hs-accordion-active:block ms-auto hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m18 15-6-6-6 6" />
        </svg>

        <svg class="hs-accordion-active:hidden ms-auto block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m6 9 6 6 6-6" />
        </svg>
      </button>

      <div id="projects-accordion-child" class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300 hidden" role="region" aria-labelledby="projects-accordion">
        <ul class="ps-8 pt-1 space-y-1">
          <li>
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="leaveform.php">
              Apply Leave
            </a>
          </li>
          <li>
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="myapplications.php">
              My Applications
            </a>
          </li>
          <li> 
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="creditlogs.php">
              Credit Logs
            </a>
          </li>
        </ul>
      </div>
    </li>            
  </ul>
</nav>
</div>
<!-- End Content -->
</div>
</div>
<!-- End Sidebar -->

<!-- Content -->
<div class="w-full lg:ps-64">
  <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">      

   <!-- Card -->
<div class="flex flex-col">
  <div class="-m-1.5 overflow-x-auto">
    <div class="p-1.5 min-w-full inline-block align-middle">
      <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
        <!-- Header -->
        <div class="px-6 py-4 grid gap-3 md:flex md:justify-between md:items-center border-b border-gray-200 dark:border-neutral-700">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">
              All Employee Leave Application
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              List of all leave applications submitted by employees under your supervision.
            </p>
          </div>
          <div>
            <div class="inline-flex gap-x-2">
              <a class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" href="#">
                View all
              </a>
            </div>
          </div>
        </div>
        <!-- End Header -->

        <!-- Search Box -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-neutral-700">
          <div class="relative max-w-xs">
            <form method="get" action="">
              <label for="employee-search" class="sr-only">Search</label>
              <input type="text" name="q" id="employee-search"
                class="py-1.5 sm:py-2 px-3 ps-9 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                placeholder="Search for employees"
                value="<?= htmlspecialchars($search) ?>">
            </form>
            <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3">
              <svg class="size-4 text-gray-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
                width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.3-4.3"></path>
              </svg>
            </div>
          </div>
        </div>
        <!-- End Search Box -->

        <!-- Start Table -->
        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
          <colgroup>
            <col style="width: 24%">
            <col style="width: 22%">
            <col style="width: 18%">
            <col style="width: 18%">
            <col style="width: 18%">
          </colgroup>
          <thead class="bg-gray-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="ps-6 pe-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Leave Type</span>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Application Date</span>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Requested Days</span>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Status</span>
                </div>
              </th>
              <th scope="col" class="px-8 py-3 text-end"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            <?php if (count($leaves) > 0): ?>
              <?php foreach ($leaves as $leave): 
                $fullName = ucwords(strtolower($leave['fullname']));
                $statusDetails = getStatusDetails($leave['leave_status']);
                $leaveTypeInitial = strtoupper(substr($leave['leave_type'], 0, 1));
                $applicationDate = getApplicationDate($leave);
                $requestedDays = $leave['total_leave_days'];
              ?>
              <tr>
                <td class="ps-6 pe-6 py-3 whitespace-nowrap align-middle">
                  <div class="flex items-center gap-x-3">
                    <span class="inline-flex items-center justify-center size-9.5 rounded-full bg-white border border-gray-300 dark:bg-neutral-800 dark:border-neutral-700">
                      <span class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($leaveTypeInitial) ?></span>
                    </span>
                    <div class="grow">
                      <span class="block text-sm font-semibold text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($fullName) ?></span>
                      <span class="block text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($leave['leave_type']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($applicationDate) ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($requestedDays) ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="py-1 px-1.5 inline-flex items-center gap-x-1 text-xs font-medium rounded-full <?= $statusDetails['badge_class'] ?>">
                    <?= $statusDetails['svg'] ?>
                    <?= htmlspecialchars($statusDetails['text']) ?>
                  </span>
                </td>
                <td class="px-8 py-3 whitespace-nowrap text-end align-middle">
                  <div class="flex gap-2 justify-end">
                    <a 
                      class="py-1 px-2 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-2xs align-middle hover:bg-gray-50 focus:outline-none focus:ring-0 transition-all text-sm dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:text-white dark:focus:ring-offset-gray-800" 
                      href="viewleave.php?id=<?= htmlspecialchars($leave['id']) ?>">
                      View
                    </a>

                    <div class="hs-dropdown relative inline-flex">
                      <button 
                        type="button" 
                        class="flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-none focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" 
                        disabled 
                        aria-disabled="true">
                        <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M4 20h16M12 3v12m0 0l-6-6m6 6l6-6" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-gray-500 dark:text-neutral-400">
                No leave applications found.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
        <!-- End Table -->

        <!-- Footer -->
        <div class="px-6 py-4 grid gap-3 md:flex md:justify-between md:items-center border-t border-gray-200 dark:border-neutral-700">
          <div>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              <span class="font-semibold text-gray-800 dark:text-neutral-200"><?= $totalRows ?></span> results
            </p>
          </div>
          <div class="inline-flex gap-x-2">
            <!-- "Prev" Button -->
            <a 
            href="?page=<?= max(1, $page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '') ?>" 
            class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?> focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
              Prev
            </a>
            <!-- "Next" Button -->
            <a 
            href="?page=<?= min($totalPages, $page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '') ?>" 
            class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?> focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Next
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </a>
        </div>
        </div>
        <!-- End Footer -->
      </div>
    </div>
  </div>
</div>
<!-- End Card -->

</div>
</div>
<!-- End Content -->

<script>
// Wait for the DOM to fully load
  document.addEventListener("DOMContentLoaded", function () {
  // Select all links with the "view-link" class
    const viewLinks = document.querySelectorAll(".view-link");

  // Attach a click event listener to each "View" link
    viewLinks.forEach(link => {
      link.addEventListener("click", function () {
      // Retrieve the leave ID from the data-id attribute
        const leaveId = this.getAttribute("data-id");

      // Display the leave ID in an alert
        if (leaveId) {
          alert(`Leave ID: ${leaveId}`);
        } else {
          console.error("Leave ID not found for this link.");
        }
      });
    });
  });
</script>

<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>


</body>
</html>

