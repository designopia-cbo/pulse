<?php
require_once('init.php');

// Restrict access: Only ADMINISTRATOR level and HR or SUPERADMIN category
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || 
    ($_SESSION['category'] !== 'HR' && $_SESSION['category'] !== 'SUPERADMIN')
) {
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

// Reset session flag on every GET request (prevents blocking new POSTs)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['tardiness_upload_done'] = false;
}

// Handle form POST and file upload
$success = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['tardiness_file']) && isset($_POST['privacy_check'])) {
    // Only process if not already done in this POST session
    if (!isset($_SESSION['tardiness_upload_done']) || $_SESSION['tardiness_upload_done'] === false) {
        $file = $_FILES['tardiness_file'];
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;

        if ($file['error'] === 0) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'csv') {
                $filename = $file['tmp_name'];
                $rows = [];
                if (($handle = fopen($filename, "r")) !== false) {
                    while (($data = fgetcsv($handle)) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }

                // Begin transaction
                try {
                    $pdo->beginTransaction();
                    // Process each row, skipping header
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $row_userid = isset($row[0]) ? trim($row[0]) : '';
                        $tardinessMins = isset($row[5]) ? floatval($row[5]) : 0; // Column F
                        $lateMins = isset($row[6]) ? floatval($row[6]) : 0;      // Column G

                        if ($row_userid === '') {
                            continue;
                        }

                        // Get employee fullname for reporting
                        $stmtEmp = $pdo->prepare("SELECT fullname FROM employee WHERE id = :userid LIMIT 1");
                        $stmtEmp->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                        $stmtEmp->execute();
                        $empRow = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                        $empName = $empRow ? ucwords(strtolower($empRow['fullname'])) : "Unknown";

                        // --- Vacation Leave Deduction (Column F) ---
                        if ($tardinessMins > 0) {
                            // Get current vacationleave
                            $stmt = $pdo->prepare("SELECT vacationleave FROM credit_leave WHERE userid = :userid LIMIT 1");
                            $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                            $stmt->execute();
                            $cl = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!$cl) {
                                throw new Exception("{$empName}: No credit_leave record found for vacation leave deduction.");
                            } else {
                                $prev_balance = floatval($cl['vacationleave']);
                                $changed_amount = floor(($tardinessMins / 480) * 1000) / 1000; // truncate to 3 decimals
                                $new_balance = floor(($prev_balance - $changed_amount) * 1000) / 1000;
                                if ($new_balance < 0) $new_balance = 0;

                                // Log to leave_credit_log
                                $stmt = $pdo->prepare("INSERT INTO leave_credit_log (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, change_date, leave_id) VALUES (:userid, 'VACATION LEAVE', 'DEDUCTION DUE TO TARDINESS', :prev, :changed, :new, NOW(), NULL)");
                                $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                                $stmt->bindParam(':prev', $prev_balance);
                                $stmt->bindParam(':changed', $changed_amount);
                                $stmt->bindParam(':new', $new_balance);
                                $stmt->execute();

                                // Update credit_leave
                                $stmt = $pdo->prepare("UPDATE credit_leave SET vacationleave = :new WHERE userid = :userid");
                                $stmt->bindParam(':new', $new_balance);
                                $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                                $stmt->execute();

                                $success[] = "{$empName}: Vacation Leave changed from {$prev_balance} to {$new_balance} (deducted {$changed_amount})";
                            }
                        }

                        // --- Sick Leave Deduction (Column G) ---
                        if ($lateMins > 0) {
                            // Get current sickleave
                            $stmt = $pdo->prepare("SELECT sickleave FROM credit_leave WHERE userid = :userid LIMIT 1");
                            $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                            $stmt->execute();
                            $cl = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!$cl) {
                                throw new Exception("{$empName}: No credit_leave record found for sick leave deduction.");
                            } else {
                                $prev_balance = floatval($cl['sickleave']);
                                $changed_amount = floor(($lateMins / 480) * 1000) / 1000; // truncate to 3 decimals
                                $new_balance = floor(($prev_balance - $changed_amount) * 1000) / 1000;
                                if ($new_balance < 0) $new_balance = 0;

                                // Log to leave_credit_log
                                $stmt = $pdo->prepare("INSERT INTO leave_credit_log (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, change_date, leave_id) VALUES (:userid, 'SICK LEAVE', 'DEDUCTION DUE TO TARDINESS', :prev, :changed, :new, NOW(), NULL)");
                                $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                                $stmt->bindParam(':prev', $prev_balance);
                                $stmt->bindParam(':changed', $changed_amount);
                                $stmt->bindParam(':new', $new_balance);
                                $stmt->execute();

                                // Update credit_leave
                                $stmt = $pdo->prepare("UPDATE credit_leave SET sickleave = :new WHERE userid = :userid");
                                $stmt->bindParam(':new', $new_balance);
                                $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                                $stmt->execute();

                                $success[] = "{$empName}: Sick Leave changed from {$prev_balance} to {$new_balance} (deducted {$changed_amount})";
                            }
                        }

                        // --- Save to tardiness table if total_tardiness > 0 ---
                        $total_tardiness = $tardinessMins + $lateMins;
                        if ($total_tardiness != 0 && $start_date && $end_date) {
                            $stmt = $pdo->prepare("INSERT INTO tardiness (userid, total_tardiness, start_date, end_date) VALUES (:userid, :total_tardiness, :start_date, :end_date)");
                            $stmt->bindParam(':userid', $row_userid, PDO::PARAM_INT);
                            $stmt->bindParam(':total_tardiness', $total_tardiness);
                            $stmt->bindParam(':start_date', $start_date);
                            $stmt->bindParam(':end_date', $end_date);
                            $stmt->execute();
                        }
                    }
                    // All operations succeeded, commit transaction
                    $pdo->commit();
                    // Set session flag to prevent re-processing on refresh
                    $_SESSION['tardiness_upload_done'] = true;
                } catch (Exception $e) {
                    // Rollback transaction on any error
                    $pdo->rollBack();
                    $errors[] = "Error processing file: " . $e->getMessage();
                }
            } else {
                $errors[] = "Only CSV files are allowed.";
            }
        } else {
            $errors[] = "Upload failed. Please try again.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['privacy_check'])) {
    $errors[] = "You must agree to the processing of personal information before submission.";
}

// Optionally, allow re-upload if user clicks a "reset" or "new upload" button
if (isset($_GET['reset'])) {
    $_SESSION['tardiness_upload_done'] = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>  
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Add Employee</title>

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
        Add Employee
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
<div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
  <div class="p-10">
    <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
      Upload Employee Tardiness
    </h2>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
      Upload the file containing updated total tardiness (in minutes) for each employee.
    </p>

    <?php if (!empty($errors)): ?>
      <div class="mb-4 p-4 rounded bg-red-100 text-red-700"><?php foreach($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="py-8 first:pt-0 last:pb-0 border-t first:border-transparent border-gray-200 dark:border-neutral-700 dark:first:border-transparent">
      <div class="font-medium text-sm text-gray-500 font-mono mb-3 dark:text-neutral-400"><?php foreach($success as $s) echo htmlspecialchars($s) . '<br>'; ?></div>
      <div class="py-8 first:pt-0 last:pb-0 border-t first:border-transparent border-gray-200 dark:border-neutral-700 dark:first:border-transparent">
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="tardiness-upload-form">
      <!-- File Upload Section -->
      <div class="grid sm:grid-cols-12 gap-2 sm:gap-4 py-8 first:pt-0 last:pb-0 border-t first:border-transparent border-gray-200 dark:border-neutral-700 dark:first:border-transparent">
        <div class="sm:col-span-3">
          <label for="tardiness_file" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
            Tardiness File (.xls)
          </label>
        </div>
        <div class="sm:col-span-9">
          <label for="tardiness_file" class="sr-only">Choose file</label>
          <input type="file" name="tardiness_file" id="tardiness_file"
            class="block w-full border border-gray-200 rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400
            file:bg-gray-50 file:border-0
            file:bg-gray-100 file:me-4
            file:py-2 file:px-4
            dark:file:bg-neutral-700 dark:file:text-neutral-400"
            required>
        </div>
      </div>
      <!-- End File Upload Section -->

      <!-- Date Range Section -->
      <div class="grid sm:grid-cols-12 gap-2 sm:gap-4 py-2">
        <div class="sm:col-span-3">
          <label for="start_date" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
            Start Date
          </label>
        </div>
        <div class="sm:col-span-9">
          <input type="date" name="start_date" id="start_date"
            class="block w-full border border-gray-200 rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            required>
        </div>
      </div>
      <div class="grid sm:grid-cols-12 gap-2 sm:gap-4 py-2">
        <div class="sm:col-span-3">
          <label for="end_date" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
            End Date
          </label>
        </div>
        <div class="sm:col-span-9">
          <input type="date" name="end_date" id="end_date"
            class="block w-full border border-gray-200 rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            required>
        </div>
      </div>
      <!-- End Date Range Section -->

      <!-- Confirmation Section -->
      <div class="py-8 first:pt-0 last:pb-0 border-t border-white dark:border-neutral-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-neutral-200">
          Confirmation
        </h2>
        <p class="mt-3 text-sm text-gray-600 dark:text-neutral-400">
          Please confirm that you have reviewed the file and agree to process these deductions. <strong>This action cannot be undone.</strong>
        </p>
        <div class="mt-5 flex">
          <input type="checkbox" name="privacy_check" id="privacy_check"
            class="shrink-0 mt-0.5 border-gray-300 rounded-sm text-blue-600 checked:border-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-600 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800">
          <label for="privacy_check" class="text-sm text-gray-500 ms-2 dark:text-neutral-400">
            I confirm I have reviewed the file and agree to process these deductions.
          </label>
        </div>
      </div>
      <!-- End Confirmation Section -->

      <button type="submit" id="submit-btn"
        class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
        disabled>
        Process Tardiness Deductions
      </button>
    </form>

  </div>
</div>
<!-- End Card -->

<script>
document.addEventListener('DOMContentLoaded', function() {
  const checkbox = document.getElementById('privacy_check');
  const submitBtn = document.getElementById('submit-btn');
  checkbox.addEventListener('change', function() {
    submitBtn.disabled = !checkbox.checked;
  });
});
</script>

</div>
</div>
<!-- End Content -->

<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script src="/pulse/js/secure.js"></script>

</body>
</html>

