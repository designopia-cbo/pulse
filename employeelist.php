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

// Check if the user is an ADMINISTRATOR
if ($_SESSION['level'] !== 'ADMINISTRATOR') {
    session_unset();
    session_destroy();
    header("Location: profile");
    exit;
}

// --- Search Logic ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// --- Pagination Logic ---
$rowsPerPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $rowsPerPage;

// --- Build Search WHERE Clause ---
$where = "WHERE ed.edstatus = 1";
$params = [];

// ------------------------------
// Access Control Logic (match profile/editprofile)
// ------------------------------
if ($category === 'MINISTER' || $category === 'HR' || $category === 'SUPERADMIN') {
    // MINISTER/HR can view all employees
    // No extra filter
} elseif ($category === 'AAO') {
    // AAO can view only employees in the same office
    $officeStmt = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
    $officeStmt->bindParam(':userid', $userid, PDO::PARAM_INT);
    $officeStmt->execute();
    $officeRow = $officeStmt->fetch(PDO::FETCH_ASSOC);
    $aaoOffice = $officeRow ? $officeRow['office'] : null;

    if ($aaoOffice !== null) {
        $where .= " AND pp.office = :aao_office";
        $params[':aao_office'] = $aaoOffice;
    } else {
        $where .= " AND 1=0"; // If AAO user has no office, show nothing
    }
} else {
    // All other users:
    // Show only: self, supervised, managed
    $orConditions = [];
    $orConditions[] = "ed.userid = :self_userid";
    $params[':self_userid'] = $userid;
    $orConditions[] = "ed.supervisor = :supervisor_userid";
    $params[':supervisor_userid'] = $userid;
    $orConditions[] = "ed.manager = :manager_userid";
    $params[':manager_userid'] = $userid;
    $where .= " AND (" . implode(" OR ", $orConditions) . ")";
}

if ($search !== '') {
  $where .= " AND (
    e.fullname LIKE :search
    OR pp.position_title LIKE :search
  )";
  $params[':search'] = '%' . $search . '%';
}

// --- Count total active employees for pagination ---
$countSql = "
SELECT COUNT(*) as total
FROM employment_details ed
INNER JOIN employee e ON ed.userid = e.id
INNER JOIN plantilla_position pp ON ed.position_id = pp.id
$where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

// --- Fetch paginated, sorted employee data ---
$sql = "
SELECT 
  ed.userid,
  e.fullname,
  pp.position_title,
  pp.classification
FROM employment_details ed
INNER JOIN employee e ON ed.userid = e.id
INNER JOIN plantilla_position pp ON ed.position_id = pp.id
$where
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$allEmployees = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  // Format full name using fullname column and proper case
  $properFullName = ucwords(strtolower($row['fullname']));

  // Map classification code to label
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

  $allEmployees[] = [
    'userid' => $row['userid'],
    'name' => $properFullName,
    'classification' => $classification,
    'position_title' => strtoupper($row['position_title']), // Convert to uppercase
    'initial' => strtoupper(substr($properFullName, 0, 1))
  ];
}

// Sort employees by concatenated name (A-Z)
usort($allEmployees, function($a, $b) {
  return strcmp($a['name'], $b['name']);
});

// Get only the employees for the current page
$employees = array_slice($allEmployees, $offset, $rowsPerPage);

// Add additional security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
?>

  <!DOCTYPE html>
  <html lang="en">
  <head> 
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">  

<!-- Title -->
<title> HRIS | Employee List</title>

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
        Employee List
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
                  List of Employees
                </h2>
                <p class="text-sm text-gray-600 dark:text-neutral-400">
                  The following employees are under your supervision.
                </p>
              </div>
              <div>
                <div class="inline-flex gap-x-2">

                  <?php if (
                    isset($_SESSION['level'], $_SESSION['category']) &&
                    $_SESSION['level'] === 'ADMINISTRATOR' &&
                    in_array($_SESSION['category'], ['HR', 'SUPERADMIN', 'MINISTER'])
                  ): ?>

                  <button id="add-employee" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" onclick="window.location.href='addemployee'">
                  <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                </button>

                <button 
                  type="button"
                  class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                  aria-label="Download CSV"
                  onclick="window.location.href='download_employee_list.php?q=<?php echo urlencode($search); ?>'">

                  <svg xmlns="http://www.w3.org/2000/svg" class="flex-none size-4 text-gray-600 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                  </svg>
                </button>

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
          </div>
          <!-- End Search Box -->

          <!-- Start Table -->
          <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
            <colgroup>
              <col style="width: 40%">
              <col style="width: 30%">
              <col style="width: 30%">
            </colgroup>
            <thead class="bg-gray-50 dark:bg-neutral-800">
              <tr>
                <th scope="col" class="ps-6 pe-6 py-3 text-start">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Employee Name</span>
                </th>
                <th scope="col" class="px-6 py-3 text-start">
                  <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Position</span>
                </th>
                <th scope="col" class="px-8 py-3 text-end"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
              <?php if (count($employees) == 0): ?>
                <tr>
                  <td colspan="3" class="ps-6 pe-6 py-3 text-center align-middle text-gray-500 dark:text-neutral-400">
                    No employees found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($employees as $emp): ?>
                  <?php
                    $profile_img_path = "assets/prof_img/" . $emp['userid'] . ".jpg";
                    $has_img = file_exists($profile_img_path);
                  ?>
                  <tr>
                    <td class="ps-6 pe-6 py-3 whitespace-nowrap align-middle">
                      <div class="flex items-center gap-x-3">
                        <span class="inline-flex items-center justify-center size-9.5 rounded-full bg-white border border-gray-300 dark:bg-neutral-800 dark:border-neutral-700">
                          <a href="profile?userid=<?php echo urlencode($emp['userid']); ?>"
                            class="font-medium text-sm text-gray-800 hover:text-blue-600 dark:text-neutral-200 dark:hover:text-blue-400 transition-colors duration-200">
                            <?php if ($has_img): ?>
                              <img src="<?php echo $profile_img_path; ?>" alt="Profile Image" class="size-9.5 rounded-full object-cover object-center border border-gray-200 dark:border-neutral-700" />
                            <?php else: ?>
                              <?php echo htmlspecialchars($emp['initial']); ?>
                            <?php endif; ?>
                          </a>
                        </span>
                        <div class="grow">
                          <a href="profile?userid=<?php echo urlencode($emp['userid']); ?>"
                            class="block text-sm font-semibold text-gray-800 hover:text-blue-600 dark:text-neutral-200 dark:hover:text-blue-400 transition-colors duration-200">
                            <?php echo htmlspecialchars($emp['name']); ?>
                          </a>
                          <span class="text-sm text-gray-500 dark:text-neutral-500"><?php echo htmlspecialchars($emp['classification']); ?></span>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap align-middle">
                      <span class="text-sm text-gray-500 dark:text-neutral-500"><?php echo htmlspecialchars($emp['position_title']); ?></span>
                    </td>
                    <td class="px-8 py-3 whitespace-nowrap text-end align-middle">
                      <div class="flex gap-2 justify-end">
                        <a href="profile?userid=<?php echo urlencode($emp['userid']); ?>"
                          class="view-link py-1 px-2 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-2xs align-middle hover:bg-gray-50 focus:outline-none focus:ring-0 transition-all text-sm dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:text-white dark:focus:ring-offset-gray-800">
                          View
                        </a>
                        <div class="hs-dropdown relative inline-flex">
                          <button type="button" class="flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-none focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" disabled aria-disabled="true">
                            <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <circle cx="12" cy="12" r="1"/>
                              <circle cx="12" cy="5" r="1"/>
                              <circle cx="12" cy="19" r="1"/>
                            </svg>
                          </button>
                        </div>
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
            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo max($page - 1, 1); ?>"
             class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?php echo $page == 1 ? 'opacity-50 pointer-events-none' : ''; ?> dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
             <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path d="m15 18-6-6 6-6"/>
            </svg>
            Prev
          </a>
          <!-- Next button -->
          <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo min($page + 1, $totalPages); ?>"
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

<script src="/pulse/js/secure.js"></script>

</body>
</html>

