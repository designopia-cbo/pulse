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

// Check if the user is an ADMINISTRATOR and category is HR, MINISTER, or SUPERADMIN
if (
    $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !in_array($_SESSION['category'], ['HR', 'SUPERADMIN', 'MINISTER', 'SUPERADMIN'])
) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

// --- Search, Filter & Sorting Logic ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all'; // 'all', 'filled', 'vacant'
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$rowsPerPage = 50;
$offset = ($page - 1) * $rowsPerPage;

$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'desc' : 'asc';
$allowedSort = [
    'item_number', 'position_title', 'salary_grade', 'org_unit', 'office', 'cost_structure', 'classification', 'pstatus'
];
if (!in_array($sort, $allowedSort)) $sort = '';

// --- Build Search & Filter WHERE Clause ---
$where = "WHERE 1";
$params = [];

if ($search !== '') {
  $where .= " AND (e.fullname LIKE :search OR pp.item_number LIKE :search OR pp.position_title LIKE :search OR pp.office LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}

if ($statusFilter === 'filled') {
    $where .= " AND pp.userid IS NOT NULL";
} elseif ($statusFilter === 'vacant') {
    $where .= " AND pp.userid IS NULL";
}

// --- Count total for pagination ---
$countSql = "
  SELECT COUNT(*) as total
  FROM plantilla_position pp
  LEFT JOIN employee e ON pp.userid = e.id
  $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

// --- Fetch data for current page ---
$orderBySql = $sort ? "$sort $order" : "item_number ASC";
$sql = "
  SELECT 
    pp.id,
    pp.userid,
    pp.item_number,
    pp.position_title,
    pp.salary_grade,
    pp.org_unit,
    pp.office,
    pp.cost_structure,
    pp.classification,
    pp.pstatus
  FROM plantilla_position pp
  LEFT JOIN employee e ON pp.userid = e.id
  $where
  ORDER BY $orderBySql
  LIMIT :offset, :rowsPerPage
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
$stmt->execute();

$plantillaRows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Classification mapping
    $classMap = [
        'P' => 'PERM.',
        'CT' => 'COTRM',
        'CTI' => 'COTRM W/INC'
    ];
    $classification = isset($classMap[strtoupper($row['classification'])]) ? $classMap[strtoupper($row['classification'])] : $row['classification'];

    // pstatus mapping
    $pstatus = $row['pstatus'] == 1 ? 'ACTIVE' : 'CLOSED';

    $plantillaRows[] = [
        'userid' => $row['userid'],
        'item_number' => $row['item_number'],
        'position_title' => strtoupper($row['position_title']),
        'salary_grade' => $row['salary_grade'],
        'org_unit' => $row['org_unit'],
        'office' => $row['office'],
        'cost_structure' => $row['cost_structure'],
        'classification' => $classification,
        'pstatus' => $pstatus
    ];
}

$plantilla = $plantillaRows;

// Helper for sort links (active column = blue font)
function sort_link($title, $column, $currentSort, $currentOrder, $search, $page, $statusFilter) {
    $nextOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $isActive = $currentSort === $column;
    $params = [
        'q' => $search,
        'page' => $page,
        'sort' => $column,
        'order' => $nextOrder
    ];
    if ($statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    $url = '?' . http_build_query($params);
    $classes = $isActive
        ? 'hover:underline text-blue-600 dark:text-blue-400'
        : 'hover:underline text-gray-800 dark:text-neutral-200';
    return "<a href=\"$url\" class=\"$classes\">$title</a>";
}
?>



  <!DOCTYPE html>
  <html lang="en">
  <head>  
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Plantilla</title>

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
        Plantilla
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
                  List of Plantilla Positions
                </h2>
                <p class="text-sm text-gray-600 dark:text-neutral-400">
                  The following plantilla positions are listed below.
                </p>
              </div>
              <div>
                <div class="inline-flex gap-x-2">

                  <?php if (
                    isset($_SESSION['level'], $_SESSION['category']) &&
                    $_SESSION['level'] === 'ADMINISTRATOR' &&
                    in_array($_SESSION['category'], ['HR', 'SUPERADMIN', 'MINISTER'])
                  ): ?>

                  <button id="add-employee" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown" onclick="window.location.href='addplantilla'">
                  <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                </button>

                <?php endif; ?>

                </div>
              </div>
            </div>
            <!-- End Header -->

        <!-- Search Box with Dropdown Filter -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-neutral-700 flex flex-row items-center gap-x-2">
          <div class="relative max-w-xs flex-1">
            <form method="get" action="" class="flex">
              <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
              <label for="plantilla-search" class="sr-only">Search</label>
              <input type="text" name="q" id="plantilla-search"
              class="h-9 px-3 ps-9 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Search for plantilla position"
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
          <!-- Dropdown filter beside search -->
          <div>
            <div class="hs-dropdown relative inline-flex">
              <button id="hs-dropdown-custom-icon-trigger" type="button"
                class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
                <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
                  width="24" height="24" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round"
                  stroke-linejoin="round">
                  <polygon points="3 4 21 4 14 14 14 20 10 20 10 14 3 4" />
                </svg>
              </button>
              <div
                class="hs-dropdown-menu min-w-40 transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700"
                role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-custom-icon-trigger">
                <div class="p-1 space-y-0.5">
                  <?php
                  $statusOptions = [
                    'all' => 'All',
                    'filled' => 'Filled',
                    'vacant' => 'Vacant'
                  ];
                  foreach ($statusOptions as $key => $label):
                    $query = '?status=' . urlencode($key);
                    if ($search !== '') $query .= '&q=' . urlencode($search);
                    if ($sort !== '') $query .= '&sort=' . urlencode($sort);
                    if ($order !== '') $query .= '&order=' . urlencode($order);
                  ?>
                  <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 <?= $statusFilter === $key ? 'font-semibold text-blue-600 dark:text-blue-400' : '' ?>"
                    href="<?= $query ?>" role="menuitem">
                    <?= $label ?>
                    <?php if ($statusFilter === $key): ?>
                      <svg class="ml-auto size-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" />
                      </svg>
                    <?php endif; ?>
                  </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- End Search Box & Dropdown -->


      <!-- Start Table -->
      <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-neutral-700">
        <colgroup>
          <col style="width: 11%">
          <col style="width: 15%">
          <col style="width: 17%">
          <col style="width: 11%">
          <col style="width: 13%">
          <col style="width: 13%">
          <col style="width: 10%">
          <col style="width: 10%">
        </colgroup>
        <thead class="bg-gray-50 dark:bg-neutral-800">
          <tr>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Status', 'pstatus', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="ps-6 pe-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Item Number', 'item_number', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Position', 'position_title', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('SG', 'salary_grade', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Org Unit', 'org_unit', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Office', 'office', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Structure', 'cost_structure', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start">
              <span class="text-xs font-semibold uppercase">
                <?php echo sort_link('Classification', 'classification', $sort, $order, $search, $page, $statusFilter); ?>
              </span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
          <?php if (count($plantilla) == 0): ?>
            <tr>
              <td colspan="8" class="ps-6 pe-6 py-3 text-center align-middle text-gray-500 dark:text-neutral-400">
                No plantilla positions found.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($plantilla as $row): ?>
                <tr>
                  <td class="px-6 py-3 whitespace-nowrap align-middle">
                    <?php if ($row['pstatus'] === 'ACTIVE'): ?>
                        <span class="py-1 px-1.5 inline-flex items-center gap-x-1 text-xs font-medium bg-teal-100 text-teal-800 rounded-full dark:bg-teal-500/10 dark:text-teal-500">
                            <svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                            </svg>
                            Active
                        </span>
                    <?php else: ?>
                        <span class="py-1 px-1.5 inline-flex items-center gap-x-1 text-xs font-medium bg-gray-100 text-gray-500 rounded-full dark:bg-gray-700 dark:text-gray-300">
                          <svg class="size-2.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                              <path d="M8 1a7 7 0 1 0 7 7A7.008 7.008 0 0 0 8 1zm0 12a.75.75 0 1 1 0-1.5A.75.75 0 0 1 8 13zm.75-3.25h-1.5V5h1.5z"/>
                          </svg>
                          Closed
                      </span>
                    <?php endif; ?>
                </td>
                <td class="ps-6 pe-6 py-3 whitespace-nowrap align-middle">
                  <?php if (!empty($row['userid'])): ?>
                    <a href="profile.php?userid=<?php echo urlencode($row['userid']); ?>" class="font-mono text-sm text-blue-600 dark:text-blue-500">
                      <?php echo htmlspecialchars($row['item_number']); ?>
                    </a>
                  <?php else: ?>
                    <span class="font-mono text-sm text-gray-500 dark:text-blue-500">
                      <?php echo htmlspecialchars($row['item_number']); ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['position_title']); ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['salary_grade']); ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['org_unit']); ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['office']); ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['cost_structure']); ?></span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap align-middle">
                  <span class="font-mono text-sm text-gray-500 dark:text-blue-500"><?php echo htmlspecialchars($row['classification']); ?></span>
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
          <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo max($page - 1, 1); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>&status=<?php echo urlencode($statusFilter); ?>"
           class="py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 <?php echo $page == 1 ? 'opacity-50 pointer-events-none' : ''; ?> dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
           <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path d="m15 18-6-6 6-6"/>
          </svg>
          Prev
        </a>
        <!-- Next button -->
        <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo min($page + 1, $totalPages); ?>&sort=<?php echo urlencode($sort); ?>&order=<?php echo urlencode($order); ?>&status=<?php echo urlencode($statusFilter); ?>"
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



<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>


</body>
</html>

