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
    !in_array($_SESSION['category'], ['HR', 'MINISTER', 'SUPERADMIN'])
) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

// --- Search, Filter & Sorting Logic ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$rowsPerPage = 20;
$offset = ($page - 1) * $rowsPerPage;

$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'desc' : 'asc';
$allowedSort = [
    'employee_name', 'field_name', 'old_value', 'new_value', 'updated_by', 'updated_at'
];
if (!in_array($sort, $allowedSort)) $sort = '';

// --- Build Search WHERE Clause ---
$where = "WHERE 1";
$params = [];

if ($search !== '') {
  $where .= " AND (
      emp.fullname LIKE :search OR
      euh.field_name LIKE :search OR
      euh.old_value LIKE :search OR
      euh.new_value LIKE :search OR
      updater.fullname LIKE :search OR
      euh.updated_at LIKE :search
    )";
  $params[':search'] = '%' . $search . '%';
}

// --- Count total for pagination ---
$countSql = "
  SELECT COUNT(*) as total
  FROM employee_update_history euh
  LEFT JOIN employee emp ON euh.employee_id = emp.id
  LEFT JOIN employee updater ON euh.updated_by = updater.id
  $where
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

// --- Fetch data for current page ---
$orderBySql = $sort ? "$sort $order" : "euh.updated_at DESC";
$sql = "
  SELECT 
    euh.id,
    euh.employee_id,
    euh.field_name,
    euh.old_value,
    euh.new_value,
    euh.updated_by,
    euh.updated_at,
    emp.fullname as employee_name,
    updater.fullname as updater_name
  FROM employee_update_history euh
  LEFT JOIN employee emp ON euh.employee_id = emp.id
  LEFT JOIN employee updater ON euh.updated_by = updater.id
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

$auditRows = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $auditRows[] = [
        'employee_name' => $row['employee_name'] ?? $row['employee_id'],
        'field_name' => $row['field_name'],
        'old_value' => $row['old_value'],
        'new_value' => $row['new_value'],
        'updated_by' => $row['updater_name'] ?? $row['updated_by'],
        'updated_at' => $row['updated_at']
    ];
}

// Helper for sort links (active column = blue font)
function auditlog_sort_link($title, $column, $currentSort, $currentOrder, $search, $page) {
    $nextOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
    $isActive = $currentSort === $column;
    $params = [
        'q' => $search,
        'page' => $page,
        'sort' => $column,
        'order' => $nextOrder
    ];
    $url = '?' . http_build_query($params);
    $classes = $isActive
        ? 'hover:underline text-blue-600 dark:text-blue-400'
        : 'hover:underline text-gray-800 dark:text-neutral-200';
    return "<a href=\"$url\" class=\"$classes\">$title</a>";
}

// Helper for pagination links
function auditlog_page_link($label, $page, $currentPage, $search, $sort, $order) {
    $disabled = $currentPage == $page ? 'opacity-50 pointer-events-none' : '';
    $url = '?page=' . $page . '&q=' . urlencode($search) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order);
    return "<a href=\"$url\" class=\"py-1.5 px-2 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 $disabled dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800\">$label</a>";
}
?>



  <!DOCTYPE html>
  <html lang="en">
  <head>  
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Audit Log</title>

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
        Audit Log
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
   <?php include 'includes/sidebar.php'; ?>
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
              Audit Log
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              All employee record changes are listed below.
            </p>
          </div>
        </div>
        <!-- End Header -->

        <!-- Search Box -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-neutral-700 flex flex-row items-center gap-x-2">
          <div class="relative max-w-xs flex-1">
            <form method="get" action="" class="flex">
              <label for="auditlog-search" class="sr-only">Search</label>
              <input type="text" name="q" id="auditlog-search"
                class="h-9 px-3 ps-9 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                placeholder="Search audit log"
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
            <col style="width: 20%">
            <col style="width: 14%">
            <col style="width: 15%">
            <col style="width: 15%">
            <col style="width: 18%">
            <col style="width: 18%">
          </colgroup>
          <thead class="bg-gray-50 dark:bg-neutral-800">
            <tr>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('Employee', 'employee_name', $sort, $order, $search, $page); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('Field', 'field_name', $sort, $order, $search, $page); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('Old Value', 'old_value', $sort, $order, $search, $page); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('New Value', 'new_value', $sort, $order, $search, $page); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('Updated By', 'updated_by', $sort, $order, $search, $page); ?>
              </span>
            </th>
            <th scope="col" class="px-6 py-3 text-start whitespace-nowrap">
              <span class="text-xs font-semibold uppercase">
                <?php echo auditlog_sort_link('Updated At', 'updated_at', $sort, $order, $search, $page); ?>
              </span>
            </th>
          </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
            <?php if (count($auditRows) == 0): ?>
              <tr>
                <td colspan="6" class="ps-6 pe-6 py-3 text-center align-middle text-gray-500 dark:text-neutral-400">
                  No audit log entries found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($auditRows as $row): ?>
                <tr>                  
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['employee_name']); ?>
                  </td>
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['field_name']); ?>
                  </td>
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['old_value']); ?>
                  </td>
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['new_value']); ?>
                  </td>
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['updated_by']); ?>
                  </td>
                  <td class="font-mono text-sm text-gray-500 dark:text-blue-500 px-6 py-3 whitespace-nowrap align-middle">
                    <?php echo htmlspecialchars($row['updated_at']); ?>
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
            <?php
              echo auditlog_page_link('Prev', max($page - 1, 1), $page, $search, $sort, $order);
              echo auditlog_page_link('Next', min($page + 1, $totalPages), $page, $search, $sort, $order);
            ?>
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

