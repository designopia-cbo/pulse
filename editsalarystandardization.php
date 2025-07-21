<?php
require_once('init.php');

// Restrict access: Only ADMINISTRATOR level and category HR or SUPERADMIN
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || !in_array($_SESSION['category'], ['SUPERADMIN'])
) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

// --- Set up notification variables for alerts ---
$tranche_status = "";
$tranche_message = "";

// Check for status in GET parameters (for alerts)
if (isset($_GET['tranche_success'])) {
    $tranche_status = "success";
    $tranche_message = "Salary tranche update completed successfully.";
}
if (isset($_GET['tranche_error'])) {
    $tranche_status = "error";
    $tranche_message = isset($_GET['tranche_error']) ? htmlspecialchars($_GET['tranche_error']) : "An error occurred during the update.";
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

// --- Update logic for salary_standardization ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ssl_amount'])) {
    foreach ($_POST['ssl_amount'] as $id => $amount) {
        // Remove commas and other formatting, then cast to float/int
        $raw = str_replace([',', '₱', ' '], '', $amount);
        $value = is_numeric($raw) ? intval($raw) : 0;

        // Update only if numeric and positive
        if ($value > 0) {
            $stmt = $pdo->prepare("UPDATE salary_standardization SET ssl_amount = :amount WHERE id = :id");
            $stmt->execute([':amount' => $value, ':id' => $id]);
        }
    }
    // Optional: redirect to avoid form resubmission
    header("Location: editsalarystandardization");
    exit;
}

// --- Update logic for salary tranche (from modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary_tranche'])) {
    $prevTrancheEnd = $_POST['prev_tranche_end'] ?? '';
    $newTrancheStart = $_POST['new_tranche_start'] ?? '';

    // Validate dates
    if ($prevTrancheEnd && $newTrancheStart) {
        try {
            $pdo->beginTransaction();

            // 1. Update all monthly_salary in employment_details using salary_standardization
            $pdo->exec("
                UPDATE employment_details
                JOIN salary_standardization 
                  ON employment_details.sg = salary_standardization.ssl_salary_grade
                 AND employment_details.step = salary_standardization.ssl_step
                SET employment_details.monthly_salary = salary_standardization.ssl_amount
            ");

            // 2. Update existing work_experience_mssd records to set w_to_date = prev tranche end date
            $stmt = $pdo->prepare("
                UPDATE work_experience_mssd we 
                JOIN employment_details ed ON we.userid = ed.userid
                JOIN employee e ON ed.userid = e.id
                SET we.w_to_date = :prevTrancheEnd
                WHERE ed.edstatus = 1 AND e.status = 'ACTIVE' AND we.w_to_date IS NULL
            ");
            $stmt->execute([':prevTrancheEnd' => $prevTrancheEnd]);

            // 3. Insert new work_experience_mssd records for all active employees
            $stmt = $pdo->prepare("
                INSERT INTO work_experience_mssd 
                    (userid, w_from_date, w_to_date, position_title, agency_name, monthly_salary, sg_step, status_appt, government_service, adjustment_type)
                SELECT 
                    ed.userid, 
                    :newTrancheStart,
                    NULL,
                    pp.position_title,
                    'MINISTRY OF SOCIAL SERVICES AND DEVELOPMENT – BARMM',
                    ed.monthly_salary,
                    CONCAT(ed.sg, '-', ed.step),
                    CASE pp.classification
                        WHEN 'P' THEN 'PERMANENT'
                        WHEN 'CT' THEN 'COTERMINOUS'
                        WHEN 'CTI' THEN 'COTERMINOUS WITH THE INCUMBENT'
                    END,
                    'YES',
                    'SALARY TRANCHE ADJUSTMENT'
                FROM employment_details ed
                JOIN plantilla_position pp ON ed.position_id = pp.id
                JOIN employee e ON ed.userid = e.id
                WHERE ed.edstatus = 1 AND e.status = 'ACTIVE'
            ");
            $stmt->execute([':newTrancheStart' => $newTrancheStart]);

            $pdo->commit();
            // Redirect and show success alert on card
            header("Location: editsalarystandardization?tranche_success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = urlencode("Error updating salary tranche: " . $e->getMessage());
            header("Location: editsalarystandardization?tranche_error={$errorMsg}");
            exit;
        }
    } else {
        // Handle validation error (optional)
        $errorMsg = urlencode("Please provide both previous tranche end date and new tranche start date.");
        header("Location: editsalarystandardization?tranche_error={$errorMsg}");
        exit;
    }
}

// Fetch salary standardization schedule
$salaryRows = [];
$stmt = $pdo->prepare("SELECT id, ssl_salary_grade, ssl_step, ssl_amount FROM salary_standardization ORDER BY ssl_salary_grade ASC, ssl_step ASC");
$stmt->execute();
$salaryRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Optionally, set these for modal defaults
$previous_tranche_end = isset($_POST['prev_tranche_end']) ? $_POST['prev_tranche_end'] : '';
$new_tranche_start = isset($_POST['new_tranche_start']) ? $_POST['new_tranche_start'] : '';
?>

  <!DOCTYPE html>
  <html lang="en">
  <head>  
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Edit Salary Standardization Schedule</title>

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
        Edit Salary Standardization Schedule
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
<div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
  <div class="p-10">

    <!-- ALERT PLACEMENT: Top of Card -->
    <?php if ($tranche_status === "success"): ?>
      <div class="mb-4 bg-teal-50 border-t-2 border-teal-500 rounded-lg p-4 dark:bg-teal-800/30" role="alert" tabindex="-1" aria-labelledby="hs-bordered-success-style-label">
        <div class="flex">
          <div class="shrink-0">
            <span class="inline-flex justify-center items-center size-8 rounded-full border-4 border-teal-100 bg-teal-200 text-teal-800 dark:border-teal-900 dark:bg-teal-800 dark:text-teal-400">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path>
                <path d="m9 12 2 2 4-4"></path>
              </svg>
            </span>
          </div>
          <div class="ms-3">
            <h3 id="hs-bordered-success-style-label" class="text-gray-800 font-semibold dark:text-white">
              Successfully updated.
            </h3>
            <p class="text-sm text-gray-700 dark:text-neutral-400">
              <?= $tranche_message ?>
            </p>
          </div>
        </div>
      </div>
    <?php elseif ($tranche_status === "error"): ?>
      <div class="mb-4 bg-red-50 border-s-4 border-red-500 p-4 dark:bg-red-800/30" role="alert" tabindex="-1" aria-labelledby="hs-bordered-red-style-label">
        <div class="flex">
          <div class="shrink-0">
            <span class="inline-flex justify-center items-center size-8 rounded-full border-4 border-red-100 bg-red-200 text-red-800 dark:border-red-900 dark:bg-red-800 dark:text-red-400">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
              </svg>
            </span>
          </div>
          <div class="ms-3">
            <h3 id="hs-bordered-red-style-label" class="text-gray-800 font-semibold dark:text-white">
              Error!
            </h3>
            <p class="text-sm text-gray-700 dark:text-neutral-400">
              <?= $tranche_message ?>
            </p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  
    <!-- Updated Header with Edit Button -->
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between mb-8">
      <div>
        <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
          Salary Standardization Schedule
        </h2>
        <p class="text-sm text-gray-600 dark:text-neutral-400">
          Manage salary schedule.
        </p>
      </div>
      <a href="#"
         data-hs-overlay="#hs-medium-modal"
         aria-haspopup="dialog"
         aria-expanded="false"
         aria-controls="hs-medium-modal"
         class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
         aria-label="Edit Salary Table"
      >
        <svg xmlns="http://www.w3.org/2000/svg"
             class="shrink-0 size-4"
             fill="none"
             viewBox="0 0 24 24"
             stroke-width="2"
             stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
      </a>

     <!-- Modal -->
      <div id="hs-medium-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-medium-modal-label">
        <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
          <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
            <!-- Header -->
            <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
              <h3 id="hs-medium-modal-label" class="font-bold text-gray-800 dark:text-white">
                Update Salary Schedule
              </h3>
              <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-medium-modal">
                <span class="sr-only">Close</span>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 6 6 18"></path>
                  <path d="m6 6 12 12"></path>
                </svg>
              </button>
            </div>
            <!-- Body with date fields in a form -->
            <form method="post" action="">
              <div class="p-4 overflow-y-auto">
                <div class="py-4">
                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label for="prev-tranche" class="inline-block text-sm font-normal dark:text-white">Previous Tranche End Date</label>
                      <input type="date" name="prev_tranche_end" id="prev-tranche"
                        value="<?= isset($previous_tranche_end) ? htmlspecialchars($previous_tranche_end) : '' ?>"
                        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
                    </div>
                    <div>
                      <label for="new-tranche" class="inline-block text-sm font-normal dark:text-white">New Tranche Start Date</label>
                      <input type="date" name="new_tranche_start" id="new-tranche"
                        value="<?= isset($new_tranche_start) ? htmlspecialchars($new_tranche_start) : '' ?>"
                        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Footer -->
              <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-medium-modal">
                  Close
                </button>
                <button type="submit" name="update_salary_tranche" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                  Update Salary Tranche
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- End Modal -->

    </div>

    <form method="post" action="">

      <!-- Salary Grid: Labeled Header -->
      <div class="space-y-4 sm:space-y-6">
        <div class="sm:grid sm:grid-cols-3 sm:gap-4">
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-neutral-200">Salary Grade</label>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-neutral-200">Salary Step</label>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-neutral-200">Monthly Salary</label>
          </div>
        </div>

        <?php foreach ($salaryRows as $index => $row): ?>
          <div class="sm:grid sm:grid-cols-3 sm:gap-4 space-y-4 sm:space-y-0">
            <!-- Salary Grade (disabled) -->
            <input type="text"
              value="<?php echo htmlspecialchars($row['ssl_salary_grade']); ?>"
              class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm 
                     focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none 
                     dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 
                     dark:focus:ring-neutral-600"
              disabled>

            <!-- Salary Step (disabled) -->
            <input type="text"
              value="<?php echo htmlspecialchars($row['ssl_step']); ?>"
              class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm 
                     focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none 
                     dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 
                     dark:focus:ring-neutral-600"
              disabled>

            <!-- Monthly Salary (editable, no decimals) -->
            <input type="text" name="ssl_amount[<?php echo $row['id']; ?>]"
              value="<?php echo number_format($row['ssl_amount'], 0); ?>"
              class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm 
                     focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none 
                     dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 
                     dark:focus:ring-neutral-600">
          </div>
        <?php endforeach; ?>
      </div>

      <div class="py-8 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200  dark:text-white dark:after:border-neutral-600"></div>

      <!-- Action Buttons -->
      <div class="mt-1 flex justify-end gap-x-2">
        <button type="button" onclick="history.back()" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
          Cancel
        </button>
        <button type="submit"
          class="py-1.5 sm:py-2 px-4 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:ring-2 focus:ring-blue-500">
          Save changes
        </button>
      </div>

    </form>

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

