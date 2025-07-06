<?php
require_once('init.php');

// Check if the user is an ADMINISTRATOR
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    session_unset();
    session_destroy();
    header("Location: login");
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

// Pagination settings
$rowsPerPage = 5;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $rowsPerPage;

// Fetch leave applications with fullname (no concatenation)
$leaveStmt = $pdo->prepare("
    SELECT 
        l.id AS leave_id,
        e.fullname,
        l.leave_type, l.appdate 
    FROM emp_leave l
    INNER JOIN employee e ON l.userid = e.id
    WHERE (l.hr = :session_userid AND l.leave_status = 1)
       OR (l.supervisor = :session_userid AND l.leave_status = 2)
       OR (l.manager = :session_userid AND l.leave_status = 3)
    LIMIT :limit OFFSET :offset
");
$leaveStmt->bindValue(':session_userid', $userid, PDO::PARAM_INT);
$leaveStmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
$leaveStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$leaveStmt->execute();
$leaveApplications = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of leave applications (optimized COUNT)
$totalApplicationsStmt = $pdo->prepare("
    SELECT COUNT(l.id) 
    FROM emp_leave l
    INNER JOIN employee e ON l.userid = e.id
    WHERE (l.hr = :session_userid AND l.leave_status = 1)
       OR (l.supervisor = :session_userid AND l.leave_status = 2)
       OR (l.manager = :session_userid AND l.leave_status = 3)
");
$totalApplicationsStmt->bindValue(':session_userid', $userid, PDO::PARAM_INT);
$totalApplicationsStmt->execute();
$totalApplications = $totalApplicationsStmt->fetchColumn();

// Calculate total pages for pagination
$totalPages = ceil($totalApplications / $rowsPerPage);

// Fetch dynamic plantilla counts
$totalPlantillaStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE pstatus = 1");
$totalPlantillaStmt->execute();
$totalPlantilla = (int)$totalPlantillaStmt->fetchColumn();

$filledPlantillaStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE pstatus = 1 AND userid IS NOT NULL");
$filledPlantillaStmt->execute();
$filledPlantilla = (int)$filledPlantillaStmt->fetchColumn();

$vacantPlantillaStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE pstatus = 1 AND userid IS NULL");
$vacantPlantillaStmt->execute();
$vacantPlantilla = (int)$vacantPlantillaStmt->fetchColumn();

$filledPercent = $totalPlantilla > 0 ? round(($filledPlantilla / $totalPlantilla) * 100) : 0;
$vacantPercent = $totalPlantilla > 0 ? round(($vacantPlantilla / $totalPlantilla) * 100) : 0;

// --- New Hire calculation ---
// Date range: first day of last month to last day of current month
$firstDayLastMonth = date('Y-m-01', strtotime('first day of last month'));
$lastDayCurrentMonth = date('Y-m-t');

$newHireStmt = $pdo->prepare("
    SELECT e.id
    FROM employee e
    WHERE e.date_orig_appt BETWEEN :start_date AND :end_date
");
$newHireStmt->bindParam(':start_date', $firstDayLastMonth);
$newHireStmt->bindParam(':end_date', $lastDayCurrentMonth);
$newHireStmt->execute();
$newHireIds = $newHireStmt->fetchAll(PDO::FETCH_COLUMN);

$newHireCount = 0;
if ($newHireIds) {
    $inIds = implode(',', array_map('intval', $newHireIds));
    $query = "SELECT COUNT(*) FROM employment_details WHERE userid IN ($inIds) AND edstatus = 1";
    $stmt = $pdo->query($query);
    $newHireCount = (int)$stmt->fetchColumn();
}

// --- To Retire calculation (label updated, year removed) ---
$nextYear = date('Y') + 1;
$toRetireYear = $nextYear;

$minBirthdate = date('Y-m-d', strtotime(($toRetireYear - 66) . '-01-01'));
$maxBirthdate = date('Y-m-d', strtotime(($toRetireYear - 66) . '-12-31'));

$toRetireStmt = $pdo->prepare("
    SELECT e.id
    FROM employee e
    WHERE e.birthdate BETWEEN :min_bd AND :max_bd
");
$toRetireStmt->bindParam(':min_bd', $minBirthdate);
$toRetireStmt->bindParam(':max_bd', $maxBirthdate);
$toRetireStmt->execute();
$toRetireIds = $toRetireStmt->fetchAll(PDO::FETCH_COLUMN);

$toRetireCount = 0;
if ($toRetireIds) {
    $inIds = implode(',', array_map('intval', $toRetireIds));
    $query = "SELECT COUNT(*) FROM employment_details WHERE userid IN ($inIds) AND edstatus = 1";
    $stmt = $pdo->query($query);
    $toRetireCount = (int)$stmt->fetchColumn();
}

// Update: Remove year from retire label
$toRetireLabel = "To Retire";

// 1. Get gender distribution among ALL active employments
$genderStmt = $pdo->prepare("
    SELECT e.gender, COUNT(*) AS total
    FROM employment_details ed
    JOIN employee e ON e.id = ed.userid
    WHERE ed.edstatus = 1
    GROUP BY e.gender
");
$genderStmt->execute();

$maleCount = 0;
$femaleCount = 0;
$totalActive = 0;

while ($row = $genderStmt->fetch(PDO::FETCH_ASSOC)) {
    if (strtolower($row['gender']) === 'male') {
        $maleCount = (int)$row['total'];
    }
    if (strtolower($row['gender']) === 'female') {
        $femaleCount = (int)$row['total'];
    }
    $totalActive += (int)$row['total'];
}
$malePercent = $totalActive > 0 ? round(($maleCount / $totalActive) * 100) : 0;
$femalePercent = $totalActive > 0 ? round(($femaleCount / $totalActive) * 100) : 0;

// leave grant process
$today = new DateTime();
$thisMonth = $today->format('Y-m');

// Query to see if leave credits already granted for this month
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_credit_grant_log WHERE grant_month = :month");
$stmt->execute([':month' => $thisMonth]);
$alreadyGranted = $stmt->fetchColumn() > 0;

// Default phrase
$leavePhrase = "";

// Set phrase based on grant status and date
if ($alreadyGranted) {
    $leavePhrase = "Leave credits have already been granted for this month.";
} elseif ($today->format('j') == 1) {
    $leavePhrase = "You can now grant leave credit for this month.";
}

$currentDate = date('Y-m-d');
$currentYear = (int)date('Y');

// Count how many employees are eligible for step increment today
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM employment_details ed
    INNER JOIN employee e ON ed.userid = e.id
    INNER JOIN plantilla_position pp ON ed.position_id = pp.id
    WHERE ed.edstatus = 1
      AND ed.step < 8
      AND ed.date_of_assumption IS NOT NULL
      AND ed.date_of_assumption != '0000-00-00'
      AND (
            DATE_FORMAT(ed.date_of_assumption, '%m-%d') = DATE_FORMAT(:currentDate, '%m-%d')
        )
      AND
        (:currentYear = YEAR(ed.date_of_assumption) + 3 * ed.step)
");
$countStmt->execute([
    ':currentDate' => $currentDate,
    ':currentYear' => $currentYear
]);
$stepIncrementTodayCount = $countStmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head> 
<meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Dashboard</title>

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
        Dashboard
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
    <!-- Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">      

    <!-- Card -->
    <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800">
      <div class="p-4 md:p-5 flex justify-between gap-x-3">
        <div>
          <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">
            Pending Leave Requests
          </p>
          <div class="mt-1 flex items-center gap-x-2">
            <h3 class="text-xl sm:text-2xl font-medium text-gray-800 dark:text-neutral-200 cursor-pointer"
                onclick="document.getElementById('bottom-section').scrollIntoView({ behavior: 'smooth' });">
              <?php echo $totalApplications; ?>
            </h3>

            <span class="flex items-center gap-x-1 text-green-600">              
              <span class="inline-block text-lg">

              </span>
            </span>
          </div>
        </div>
        <div class="shrink-0 flex justify-center items-center size-11 bg-blue-600 text-white rounded-full dark:bg-blue-900 dark:text-blue-200">
          <svg class="shrink-0 size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
        </div>
      </div>

      <a class="py-3 px-4 md:px-5 inline-flex justify-between items-center text-sm text-gray-600 border-t border-gray-200 hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 rounded-b-xl dark:border-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" href="tracker">
        Handled Applications
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
      </a>
    </div>
    <!-- End Card -->   

    <?php if ( isset($_SESSION['level'], $_SESSION['category']) && $_SESSION['level'] === 'ADMINISTRATOR' && in_array($_SESSION['category'], ['HR', 'SUPERADMIN', 'MINISTER']) ): ?>

      <!-- Card -->
      <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4 md:p-5 min-h-[100px]">  
        <!-- Plantilla Data Section -->
        <div class="px-2 pb-2">
            <div class="pb-2 flex justify-between items-center">
              <a href="plantilla">
                <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">
                  Total Plantilla
                </p>
              </a>
              <h4 class="text-lg font-semibold leading-tight text-gray-800 dark:text-neutral-200">
                <?php echo $totalPlantilla; ?>
              </h4>
            </div>
            
            <div class="pb-3">
                <div class="overflow-hidden rounded-full h-3 bg-gray-300 flex dark:bg-neutral-700">
                    <div class="h-full bg-blue-500" style="width: <?php echo $filledPercent; ?>%;"></div> <!-- Filled -->
                    <div class="h-full bg-blue-100" style="width: <?php echo $vacantPercent; ?>%;"></div> <!-- Vacant -->
                </div>
            </div>
            
            <div class="flex -mx-4">
                <div class="w-1/4 px-0 border-r border-gray-300 dark:border-neutral-600"> <!-- Separated Label -->
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                        <span>Filled</span>
                    </div>
                    <div class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo $filledPlantilla; ?></div>
                </div>
                <div class="w-1/4 px-0 border-r border-gray-300 dark:border-neutral-600">
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-blue-100"></span>
                        <span>Vacant</span>
                    </div>
                    <div class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo $vacantPlantilla; ?></div>
                </div>
                <div class="w-1/4 px-0 border-r border-gray-300 dark:border-neutral-600">
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-teal-400"></span>
                        <span>New Hire</span>
                    </div>
                    <div class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo $newHireCount; ?></div>
                </div>
                <div class="w-1/4 px-0">
                    <div class="text-xs text-gray-500 dark:text-neutral-400">
                        <span class="inline-block w-2 h-2 rounded-full bg-orange-400"></span>
                        <span>To Retire</span>
                    </div>
                    <a href="retirees.php" class="font-medium text-sm text-gray-800 dark:text-neutral-200 hover:underline">
                      <?php echo $toRetireCount; ?>
                    </a>
                </div>
            </div>
        </div>
      </div>
      <!-- End Card -->

      <!-- Card -->
      <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4 md:p-5 min-h-[100px]"><div class="px-2 pb-2">
            <div class="pb-2 flex justify-between items-center">
                <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">
                  Gender Distribution
                </p>
                <h4 class="text-lg font-semibold leading-tight text-gray-800 dark:text-neutral-200">
                  <?php echo $totalActive; ?>
                </h4>
              </div>

              <div class="pb-3">
                  <div class="overflow-hidden rounded-full h-3 bg-gray-300 flex dark:bg-neutral-700">
                      <div class="h-full bg-purple-300" style="width: <?php echo $malePercent; ?>%;"></div><!-- Male -->
                      <div class="h-full bg-purple-600" style="width: <?php echo $femalePercent; ?>%;"></div><!-- Female -->
                  </div>
              </div>

              <div class="flex -mx-4">
                  <div class="w-1/4 px-0 border-r border-gray-300 dark:border-neutral-600">
                      <div class="text-xs text-gray-500 dark:text-neutral-400">
                          <span class="inline-block w-2 h-2 rounded-full bg-purple-300"></span>
                          <span>Male</span>
                      </div>
                      <div class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo $maleCount; ?></div>
                  </div>
                  <div class="w-1/4 px-0">
                      <div class="text-xs text-gray-500 dark:text-neutral-400">
                          <span class="inline-block w-2 h-2 rounded-full bg-purple-600"></span>
                          <span>Female</span>
                      </div>
                      <div class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?php echo $femaleCount; ?></div>
                  </div>
              </div>
        </div>
      </div>
      <!-- End Card -->
       
      <?php endif; ?>
    
    </div>
    <!-- End Grid -->

    <!-- Grid -->
    <?php if ( isset($_SESSION['level'], $_SESSION['category']) && $_SESSION['level'] === 'ADMINISTRATOR' && in_array($_SESSION['category'], ['SUPERADMIN']) ): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-2 gap-4 sm:gap-6">   
      <!-- Card -->
      <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800">
        <div class="p-4 md:p-5 flex justify-between gap-x-3">
          <div>
            <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">
              Monthly Leave Credit
            </p>
            <div class="mt-1 flex items-center gap-x-2">
              <p class="text-sm text-gray-600 dark:text-neutral-400">
                <?php echo $leavePhrase; ?>
            </p>
            </div>
          </div>
        </div>

        <!-- Button Footer (Atl style) -->
        <div class="mt-auto flex border-t border-gray-200 divide-x divide-gray-200 dark:border-neutral-800 dark:divide-neutral-800">
          <a id="addLeaveCreditsBtn"
             class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-normal rounded-es-xl bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
             href="#"
             <?php if ($alreadyGranted) echo 'disabled style="pointer-events:none;opacity:0.5;cursor:not-allowed;"'; ?>>
             Add leave credits
          </a>

          <a class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-normal rounded-ee-xl bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" href="employeecreditlog">
            View credit log
          </a>
        </div>
        <!-- End Button Footer -->
      </div>
      <!-- End Card -->

      <!-- Card -->
    <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800">
      <div class="p-4 md:p-5 flex justify-between gap-x-3">
        <div>
          <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">
            Employee to step increment today
          </p>
          <div class="mt-1 flex items-center gap-x-2">
            <h3 class="text-lg sm:text-xl font-medium text-gray-800 dark:text-neutral-200">
              <?php echo $stepIncrementTodayCount; ?>
            </h3>
          </div>
        </div>
      </div>

      <a class="py-3 px-4 md:px-5 inline-flex justify-between items-center text-sm text-gray-600 border-t border-gray-200 hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 rounded-b-xl dark:border-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" href="stepincrement">
        View step increment
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
      </a>
    </div>
    <!-- End Card -->

    </div>
    <?php endif; ?>
    <!-- End Grid -->


      <?php if ( isset($_SESSION['level'], $_SESSION['category']) && $_SESSION['level'] === 'ADMINISTRATOR' && in_array($_SESSION['category'], ['HR', 'SUPERADMIN', 'MINISTER']) ): ?>

      <!-- Grid: 2 columns -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-2 gap-4 sm:gap-6">    

        <!-- Card -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800">
          <div class="p-4 md:p-5 flex flex-col gap-y-4">
            <?php include __DIR__ . '/charts/barchart_workforce.php'; ?>
          </div>
        </div>
        <!-- End Card -->

        <!-- Main Card -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800">
          <div class="p-4 md:p-6">
            <?php include __DIR__ . '/charts/chart_auditlog.php'; ?>
          </div>
        </div>
        <!-- End Main Card -->

      </div>
      <!-- End Grid -->

      <!-- Tardiness Card -->

      <?php include __DIR__ . '/charts/tardiness_tracker.php'; ?>

      <!-- End Tardiness Card -->

      <?php endif; ?>

<!-- Card -->
<div class="flex flex-col">
  <div class="-m-1.5 overflow-x-auto">
    <div class="p-1.5 min-w-full inline-block align-middle">
      <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">

        <!-- Header -->
        <div class="px-6 py-4 grid gap-3 md:flex md:justify-between md:items-center border-b border-gray-200 dark:border-neutral-700">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">
              For your approval
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              Employee(s) requesting leave.
            </p>
          </div>
        </div>
        <!-- End Header -->

        <!-- Table -->
        <table id="bottom-section" class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
          <thead class="bg-gray-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="ps-6 pe-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">
                    Name
                  </span>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <div class="flex items-center gap-x-2">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">
                    Date Applied
                  </span>
                </div>
              </th>
              <th scope="col" class="px-6 py-3 text-end"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            <?php if (count($leaveApplications) > 0): ?>
              <?php foreach ($leaveApplications as $application): ?>
                <tr data-leave-id="<?php echo htmlspecialchars($application['leave_id']); ?>">
                  <td class="size-px whitespace-nowrap">
                    <div class="ps-6 pe-6 py-3">
                      <div class="flex items-center gap-x-3">
                        <span class="inline-flex items-center justify-center size-9.5 rounded-full bg-white border border-gray-300 dark:bg-neutral-800 dark:border-neutral-700">
                          <span class="font-medium text-sm text-gray-800 dark:text-neutral-200">
                            <?php echo strtoupper(substr($application['fullname'], 0, 1)); ?>
                          </span>
                        </span>
                        <div class="grow">
                          <span class="block text-sm font-semibold text-gray-800 dark:text-neutral-200">
                            <?php 
                            // Proper-case for fullname
                            echo htmlspecialchars(ucwords(strtolower($application['fullname'])));
                            ?>
                          </span>
                          <span class="block text-sm text-gray-500 dark:text-neutral-500">
                            <?php 
                            echo htmlspecialchars(ucwords(strtolower($application['leave_type']))); 
                            ?>
                          </span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="size-px whitespace-nowrap">
                    <div class="px-6 py-3">
                      <span class="text-sm text-gray-500 dark:text-neutral-500">
                        <?php 
                        echo htmlspecialchars(date('M d, Y', strtotime($application['appdate']))); 
                        ?>
                      </span>
                    </div>
                  </td>
                  <td class="size-px whitespace-nowrap">
                    <a 
                    class="px-6 py-1.5 py-1 px-2 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-2xs align-middle hover:bg-gray-50 focus:outline-none focus:ring-0 transition-all text-sm dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:text-white dark:focus:ring-offset-gray-800" 
                    href="leavedetails?id=<?= htmlspecialchars($application['leave_id']); ?>">
                    View
                  </a>

                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="3" class="text-center py-4 text-gray-500 dark:text-neutral-400">
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
            <span class="font-semibold text-gray-800 dark:text-neutral-200">
              <?= htmlspecialchars($totalApplications); ?>
            </span> results
          </p>
        </div>

        <div>
          <div class="inline-flex gap-x-2">
            <!-- Prev Button -->
            <a href="?page=<?= max(1, $currentPage - 1); ?>" 
             class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?= $currentPage <= 1 ? 'opacity-50 pointer-events-none' : '' ?> focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
              Prev
            </a>

            <!-- Next Button -->
            <a href="?page=<?= min($totalPages, $currentPage + 1); ?>" 
             class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?= $currentPage >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?> focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
             Next
             <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
           </a>
         </div>
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

<script>
document.getElementById('addLeaveCreditsBtn').addEventListener('click', function(e) {
    e.preventDefault();
    if (!confirm("Are you sure you want to add leave credits for all employees?")) return;
    this.classList.add('opacity-50', 'pointer-events-none');
    fetch('/pulse/automation/add_leave_credits', {
        method: 'POST',
        headers: {'Accept': 'application/json'}
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            alert('Server returned invalid JSON. Raw response:\n' + text);
            window.location.reload();
            return;
        }
        alert(data.message);
        window.location.reload();
    })
    .catch(err => {
        alert('Network or server error: ' + err);
        window.location.reload();
    });
});
</script>


<script src="/pulse/js/secure.js"></script>

</body>
</html>

