<?php
require_once('init.php');

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

// Restrict access: Only ADMINISTRATOR level and category HR or SUPERADMIN
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || !in_array($_SESSION['category'], ['HR', 'SUPERADMIN'])
) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

// --- Year Filter Logic ---
$currentYear = (int)date('Y');
$year_options = [$currentYear, $currentYear + 1, $currentYear + 2];
$selectedYear = isset($_GET['year']) && in_array((int)$_GET['year'], $year_options) ? (int)$_GET['year'] : $currentYear;

// --- Search Logic ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// --- Pagination Logic ---
$rowsPerPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $rowsPerPage;

// --- Employees age 60-65 (inclusive) by selected year and with edstatus = 1 ---
$where = "WHERE ed.edstatus = 1
    AND TIMESTAMPDIFF(YEAR, e.birthdate, :selectedYearDate) >= 60
    AND TIMESTAMPDIFF(YEAR, e.birthdate, :selectedYearDate) <= 65";
$params = [':selectedYearDate' => $selectedYear . '-12-31'];

if ($search !== '') {
    $where .= " AND e.fullname LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$sql = "
SELECT e.id, e.empno, e.fullname, e.last_name, e.first_name, e.middle_name, e.suffix, e.gender, e.birthdate, 
       pp.position_title, ed.sg, ed.step, ed.monthly_salary
FROM employee e
INNER JOIN employment_details ed ON e.id = ed.userid AND ed.edstatus = 1
LEFT JOIN plantilla_position pp ON ed.position_id = pp.id
$where
GROUP BY e.id
ORDER BY e.birthdate ASC
LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$retirees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total for pagination
$countSql = "
SELECT COUNT(DISTINCT e.id) 
FROM employee e
INNER JOIN employment_details ed ON e.id = ed.userid AND ed.edstatus = 1
$where
";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val, PDO::PARAM_STR);
}
$countStmt->execute();
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $rowsPerPage);
?>

  <!DOCTYPE html>
  <html lang="en">
  <head> 
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">  

<!-- Title -->
<title> HRIS | Retirees</title>

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
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="profile">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="7" r="4" />
                  <path d="M4 20c0-4 4-7 8-7s8 3 8 7" />
                </svg>
                My Profile
              </a>
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="changepassword">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0110 0v4" />
              </svg>
              Change Password
            </a>
            <a href="logout" class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300">
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
        Retirees
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
  
  <?php include 'includes/sidebar.php'; ?>
  
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
              Retirees.
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              List of employees for mandatory and optional retirement for year <?php echo $selectedYear; ?>.
            </p>
          </div>
          <div>
            <div class="inline-flex gap-x-2">
              <?php if (
                isset($_SESSION['level'], $_SESSION['category']) &&
                $_SESSION['level'] === 'ADMINISTRATOR' &&
                in_array($_SESSION['category'], ['SUPERADMIN'])
              ): ?>
              <a href="download_retirees?year=<?php echo urlencode($selectedYear); ?>&q=<?php echo urlencode($search); ?>"
               class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
               aria-haspopup="menu" aria-expanded="false" aria-label="Download CSV">
              <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
              </svg>
            </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- End Header -->

        <!-- Search Box with Dropdown Filter -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-neutral-700 flex flex-row items-center gap-x-2">
          <div class="relative max-w-xs flex-1">
            <form method="get" action="" class="flex">
              <label for="retiree-search" class="sr-only">Search</label>
              <input type="hidden" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>">
              <input type="text" name="q" id="retiree-search"
                class="h-9 px-3 ps-9 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                placeholder="Search for retirees"
                value="<?php echo htmlspecialchars($search); ?>">
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
          <!-- Year Filter Dropdown -->
          <div>
            <div class="hs-dropdown relative inline-flex">
              <button id="hs-dropdown-filter-trigger" type="button"
                class="hs-dropdown-toggle flex justify-center items-center h-9 w-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                aria-haspopup="menu" aria-expanded="false" aria-label="Filter Dropdown">
                <svg class="size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
                  width="24" height="24" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round"
                  stroke-linejoin="round">
                  <polygon points="3 4 21 4 14 14 14 20 10 20 10 14 3 4" />
                </svg>
              </button>
              <!-- Dropdown Menu -->
              <div class="hs-dropdown-menu min-w-40 transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700"
                role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-filter-trigger">
                <div class="p-1 space-y-0.5">
                  <?php foreach ($year_options as $year): ?>
                    <a href="?<?php
                        $params = $_GET;
                        $params['year'] = $year;
                        echo http_build_query($params);
                      ?>"
                      class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm
                      <?php echo $selectedYear == $year ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-800 dark:text-neutral-400'; ?>
                      hover:bg-gray-100 dark:hover:bg-neutral-700 dark:hover:text-neutral-300">
                      <?php echo htmlspecialchars($year); ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- End Search Box with Filter -->

        <!-- Start Table -->
        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
          <colgroup>
            <col style="width: 28%">
            <col style="width: 10%">
            <col style="width: 13%">
            <col style="width: 7%">
            <col style="width: 15%">
            <col style="width: 6%">
            <col style="width: 6%">
            <col style="width: 12%">
            <col style="width: 3%">
          </colgroup>
          <thead class="bg-gray-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="ps-6 pe-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Employee Name</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Gender</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Birthdate</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Age</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Status</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">SG</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Step</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Monthly Salary</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start whitespace-nowrap"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            <?php if (count($retirees) == 0): ?>
              <tr>
                <td colspan="9" class="ps-6 pe-6 py-3 text-center align-middle text-gray-500 dark:text-neutral-400">
                  No retirees found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($retirees as $emp): ?>
                <tr>
                  <td class="ps-6 pe-6 py-3 whitespace-nowrap align-middle">
                    <div class="flex items-center gap-x-3">
                      <span class="inline-flex items-center justify-center size-9.5 rounded-full bg-white border border-gray-300 dark:bg-neutral-800 dark:border-neutral-700">
                      <span class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo strtoupper(substr($emp['fullname'], 0, 1)); ?></span>
                      </span>
                      <div class="grow">
                      <a href="profile?userid=<?php echo urlencode($emp['id']); ?>" class="block text-sm font-semibold text-gray-800 dark:text-neutral-200 hover:text-blue-600">
                        <?php echo ucwords(strtolower($emp['fullname'])); ?>
                      </a>
                        <span class="text-sm text-gray-500 dark:text-neutral-500"><?php echo isset($emp['position_title']) ? htmlspecialchars($emp['position_title']) : ''; ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500"><?php echo htmlspecialchars($emp['gender']); ?></span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500">
                      <?php 
                        echo !empty($emp['birthdate']) && $emp['birthdate'] !== '0000-00-00'
                          ? date('M d, Y', strtotime($emp['birthdate']))
                          : '';
                      ?>
                    </span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500">
                      <?php
                        // Compute age as of end of selected year
                        $age = '';
                        if (!empty($emp['birthdate']) && $emp['birthdate'] !== '0000-00-00') {
                          $birth = new DateTime($emp['birthdate']);
                          $asOf = new DateTime($selectedYear . '-12-31');
                          $age = $birth->diff($asOf)->y;
                          echo $age;
                        }
                      ?>
                    </span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <?php
                      if ($age == 65) {
                        // Mandatory retirement badge
                        ?>
                        <span class="py-1 px-1.5 inline-flex items-center gap-x-1 text-xs font-medium bg-red-100 text-red-800 rounded-full dark:bg-red-500/10 dark:text-red-500">
                          <svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                          <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 0 0 1 0-2z"/>
                          </svg>
                          Mandatory Retirement
                        </span>
                        <?php
                      } elseif ($age >= 60 && $age < 65) {
                        // Optional retirement badge
                        ?>
                        <span class="py-1 px-1.5 inline-flex items-center gap-x-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-500/10 dark:text-yellow-500">
                          <svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                          <path d="M8 1a7 7 0 1 0 7 7A7.008 7.008 0 0 0 8 1zm0 12a.75.75 0 1 1 0-1.5A.75.75 0 0 1 8 13zm.75-3.25h-1.5V5h1.5z"/>
                          </svg>
                          Eligible for Optional Retirement
                        </span>
                        <?php
                      }
                    ?>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500">
                      <?php echo htmlspecialchars($emp['sg']); ?>
                    </span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500">
                      <?php echo htmlspecialchars($emp['step']); ?>
                    </span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <span class="text-sm text-gray-500 dark:text-neutral-500">
                      <?php echo number_format($emp['monthly_salary'], 2); ?>
                    </span>
                  </td>
                  <td class="px-6 py-3 whitespace-nowrap text-end align-middle">
                    <div class="flex gap-2 justify-end">
                      <!-- View button -->
                      <a href="profile?userid=<?php echo urlencode($emp['id']); ?>"
                        class="view-link h-9 px-2 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-2xs align-middle hover:bg-gray-50 focus:outline-none focus:ring-0 transition-all text-sm dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:text-white dark:focus:ring-offset-gray-800">
                        View
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <!-- End Table -->

        <!-- Footer (Pagination) -->
        <div class="px-6 py-4 grid gap-3 md:flex md:justify-between md:items-center border-t border-gray-200 dark:border-neutral-700">
          <div>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              <span class="font-semibold text-gray-800 dark:text-neutral-200"><?php echo $totalRows; ?></span> result<?php echo $totalRows == 1 ? '' : 's'; ?>
            </p>
          </div>
          <div class="inline-flex gap-x-2">
            <!-- Prev button -->
            <a href="?<?php
                $params = $_GET;
                $params['page'] = max($page - 1, 1);
                echo http_build_query($params);
              ?>"
              class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?php echo $page == 1 ? 'opacity-50 pointer-events-none' : ''; ?> dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path d="m15 18-6-6 6-6"/>
              </svg>
              Prev
            </a>
            <!-- Next button -->
            <a href="?<?php
                $params = $_GET;
                $params['page'] = min($page + 1, $totalPages);
                echo http_build_query($params);
              ?>"
              class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?php echo $page == $totalPages || $totalPages == 0 ? 'opacity-50 pointer-events-none' : ''; ?> dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              Next
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path d="m9 18 6-6-6-6"/>
              </svg>
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



<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>


</body>
</html>

