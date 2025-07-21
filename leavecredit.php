<?php
require_once('init.php');

// Restrict page access according to valid level/category
$userLevel = isset($_SESSION['level']) ? $_SESSION['level'] : '';
$userCategory = isset($_SESSION['category']) ? $_SESSION['category'] : '';

$allowed = false;
if (
  $userLevel === 'ADMINISTRATOR' && in_array($userCategory, ['HR', 'SUPERADMIN', 'MINISTER', 'AAO'])
) {
  $allowed = true;
}
if (!$allowed) {
  session_unset();
  session_destroy();
  header("Location: profile");
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
  $where[] = "(employee.fullname LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}

// Only show employees with edstatus = 1 (active)
$where[] = "employment_details.edstatus = 1";

$whereSql = '';
if (count($where) > 0) {
  $whereSql = "WHERE " . implode(' AND ', $where);
}

// Fetch credits, join with employee for fullname, office, employment_details for edstatus, and position_title
$sql = "
  SELECT 
    credit_leave.id,
    credit_leave.userid,
    credit_leave.vacationleave,
    credit_leave.sickleave,
    credit_leave.spleave,
    employee.fullname,
    plantilla_position.office AS employee_office,
    plantilla_position.position_title AS position_title
  FROM credit_leave
  LEFT JOIN employee ON credit_leave.userid = employee.id
  LEFT JOIN plantilla_position ON credit_leave.userid = plantilla_position.userid
  LEFT JOIN employment_details ON credit_leave.userid = employment_details.userid
  $whereSql
  ORDER BY credit_leave.id DESC
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
  $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->execute();
$allCredits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's office if category is AAO
$userOffice = null;
if ($userCategory === 'AAO') {
  $stmtOffice = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
  $stmtOffice->bindParam(':userid', $userid, PDO::PARAM_INT);
  $stmtOffice->execute();
  $rowOffice = $stmtOffice->fetch(PDO::FETCH_ASSOC);
  if ($rowOffice && isset($rowOffice['office'])) {
    $userOffice = $rowOffice['office'];
  }
}

// Filtering logic
$credits = [];
foreach ($allCredits as $credit) {
  // ADMINISTRATOR + HR/MINISTER: see all employees
  if (
    $userLevel === 'ADMINISTRATOR' && in_array($userCategory, ['HR', 'SUPERADMIN', 'MINISTER'])
  ) {
    $credits[] = $credit;
    continue;
  }

  // ADMINISTRATOR + AAO: see only employees in their own office
  if (
    $userLevel === 'ADMINISTRATOR' && $userCategory === 'AAO' &&
    !empty($userOffice) && !empty($credit['employee_office']) &&
    $userOffice === $credit['employee_office']
  ) {
    $credits[] = $credit;
    continue;
  }

  // Otherwise, don't include
}

// Prepare exportCredits before pagination
$exportCredits = $credits;

// Fix pagination based on filtered credits
$totalRows = count($credits);
$totalPages = max(1, ceil($totalRows / $perPage));
$credits = array_slice($credits, $offset, $perPage);

// CSV export logic (exports all filtered rows, not just current page)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=leave_credits_" . date('Ymd_His') . ".csv");
    $output = fopen('php://output', 'w');
    // Output header row
    fputcsv($output, [
        'User ID',
        'Employee Name',
        'Position Title',
        'Vacation Leave',
        'Sick Leave',
        'VL Tardiness',
        'SL Tardiness'
    ]);
    // Output data rows
    foreach ($exportCredits as $credit) {
        $userid = $credit['userid'];
        $fullName = ucwords(strtolower($credit['fullname']));
        $position = $credit['position_title'];
        $vacationleave = $credit['vacationleave'] !== null ? $credit['vacationleave'] : 0;
        $sickleave = $credit['sickleave'] !== null ? $credit['sickleave'] : 0;
        $vlTardiness = 0;
        $slTardiness = 0;
        fputcsv($output, [
            $userid,
            $fullName,
            $position,
            $vacationleave,
            $sickleave,
            $vlTardiness,
            $slTardiness
        ]);
    }
    fclose($output);
    exit;
}

// (The rest of your page logic and HTML follows here...)
?>

<!DOCTYPE html>
<html lang="en">
<head>  
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">  

<!-- Title -->
<title> HRIS | Employee Leave Credits</title>

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
        <?php include __DIR__ . '/includes/header_dropdown.php'; ?>
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
        Leave Credits
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
              Employee Leave Credits
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              List of vacation and sick leave credits per employee.
            </p>
          </div>
          <div>
            <div class="inline-flex gap-x-2">

              <?php if (
                  isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR' &&
                  isset($_SESSION['category']) && 
                  ($_SESSION['category'] === 'HR' || $_SESSION['category'] === 'SUPERADMIN')
              ): ?>
              <a class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                 href="?export=csv<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                .CSV
                <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500"
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round">
                  <path d="M4 20h16M12 3v12m0 0l-6-6m6 6l6-6" />
                </svg>
              </a>
              <?php endif; ?>

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
            <col style="width: 32%">
            <col style="width: 18%">
            <col style="width: 18%">
            <col style="width: 18%">
            <col style="width: 6%">
          </colgroup>
          <thead class="bg-gray-50 dark:bg-neutral-800">
            <tr>
              <th scope="col" class="ps-6 pe-6 py-3 text-start">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Employee Name</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Vacation Leave</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Sick Leave</span>
              </th>
              <th scope="col" class="px-6 py-3 text-start">
                <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Special Privilege Leave</span>
              </th>
              <th scope="col" class="px-4 py-3 text-end"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            <?php if (count($credits) > 0): ?>
              <?php foreach ($credits as $credit): 
                $fullName = $credit['fullname'];
                $initial = strtoupper(substr($fullName, 0, 1));
                $userid = $credit['userid'];
                $profile_img_path_fs = __DIR__ . "/assets/prof_img/" . $userid . ".jpg";
                $profile_img_path_web = "assets/prof_img/" . $userid . ".jpg";
                $has_img = file_exists($profile_img_path_fs);
                $vacationleave = $credit['vacationleave'] !== null ? $credit['vacationleave'] : 0;
                $sickleave = $credit['sickleave'] !== null ? $credit['sickleave'] : 0;
                $spleave = $credit['spleave'] !== null ? $credit['spleave'] : 0;
              ?>
              <tr>
                <td class="ps-6 pe-6 py-3 whitespace-nowrap align-middle">
                  <div class="flex items-center gap-x-3">
                    <span class="inline-flex items-center justify-center size-9.5 rounded-full bg-white border border-gray-300 dark:bg-neutral-800 dark:border-neutral-700">
                      <?php if ($has_img): ?>
                        <img src="<?= htmlspecialchars($profile_img_path_web) ?>" alt="Profile Image" class="size-9.5 rounded-full object-cover object-center border border-gray-200 dark:border-neutral-700" />
                      <?php else: ?>
                        <span class="font-medium text-sm text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($initial) ?></span>
                      <?php endif; ?>
                    </span>
                    <div class="grow">
                      <a href="profile?userid=<?= urlencode($userid) ?>" class="block text-sm font-semibold text-gray-800 dark:text-neutral-200 hover:text-blue-600">
                        <?= htmlspecialchars(ucwords(strtolower($fullName))) ?>
                      </a>
                      <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($credit['position_title']) ?></span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($vacationleave) ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($sickleave) ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="text-sm text-gray-500 dark:text-neutral-500"><?= htmlspecialchars($spleave) ?></span>
                </td>
                <td class="px-4 py-3 whitespace-nowrap text-end align-middle">
                  <div class="flex gap-2 justify-end">
                    <!-- Dropdown -->
                    <?php if ($userLevel === 'ADMINISTRATOR' && $userCategory === 'SUPERADMIN'): ?>
                    <div class="hs-dropdown relative inline-flex">
                      <button type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                        <svg class="flex-none size-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="12" cy="12" r="1"/>
                          <circle cx="12" cy="5" r="1"/>
                          <circle cx="12" cy="19" r="1"/>
                        </svg>
                      </button>
                      <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2" role="menu" aria-orientation="vertical">
                        <div class="p-1 space-y-0.5">
                          <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" href="#">
                            Newsletter
                          </a>
                          <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" href="#">
                            Purchases
                          </a>
                          <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" href="#">
                            Downloads
                          </a>
                          <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" href="#">
                            Team Account
                          </a>
                        </div>
                      </div>
                    </div>
                    <!-- End Dropdown -->
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-gray-500 dark:text-neutral-400">
                No leave credits found.
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

<script src="/pulse/js/secure.js"></script>

</body>
</html>

