<?php
require_once('init.php');

// Handle AJAX leave cancellation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_leave']) && isset($_POST['leave_id'])) {
    $leave_id = (int) $_POST['leave_id'];
    $userId = $_SESSION['userid'];

    // Fetch current leave_status, leave_type, total_leave_days and verify the user has permission
    $stmt = $pdo->prepare("SELECT leave_status, userid, leave_type, total_leave_days FROM emp_leave WHERE id = :id");
    $stmt->bindParam(':id', $leave_id, PDO::PARAM_INT);
    $stmt->execute();
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($leave && $leave['userid'] == $userId && in_array($leave['leave_status'], [1,2,3,4])) {
        $specialLeaveTypes = ['VACATION LEAVE', 'SICK LEAVE', 'SPECIAL PRIVILEGE LEAVE'];
        $shouldCredit = in_array($leave['leave_type'], $specialLeaveTypes) && $leave['leave_status'] == 4;
        $success = false;

        if ($shouldCredit) {
            // Map column and log values based on leave_type
            $leaveTypeMap = [
                'VACATION LEAVE' => ['column' => 'vacationleave', 'log_type' => 'VACATION'],
                'SICK LEAVE' => ['column' => 'sickleave', 'log_type' => 'SICK'],
                'SPECIAL PRIVILEGE LEAVE' => ['column' => 'spleave', 'log_type' => 'SPL'],
            ];
            $leaveType = $leave['leave_type'];
            $logType = $leaveTypeMap[$leaveType]['log_type'];
            $creditColumn = $leaveTypeMap[$leaveType]['column'];

            // Get previous balance from credit_leave
            $creditStmt = $pdo->prepare("SELECT $creditColumn FROM credit_leave WHERE userid = :userid LIMIT 1");
            $creditStmt->bindParam(':userid', $leave['userid'], PDO::PARAM_INT);
            $creditStmt->execute();
            $creditRow = $creditStmt->fetch(PDO::FETCH_ASSOC);
            $previous_balance = $creditRow ? (float)$creditRow[$creditColumn] : 0;
            $changed_amount = (float)$leave['total_leave_days'];
            $new_balance = $previous_balance + $changed_amount;
            $now = date('Y-m-d H:i:s');

            // Insert into leave_credit_log
            $logStmt = $pdo->prepare("INSERT INTO leave_credit_log 
                (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, change_date, leave_id)
                VALUES (:userid, :leave_type, :change_type, :previous_balance, :changed_amount, :new_balance, :change_date, :leave_id)");
            $logStmt->bindParam(':userid', $leave['userid'], PDO::PARAM_INT);
            $logStmt->bindParam(':leave_type', $logType, PDO::PARAM_STR);
            $changeType = 'ADDITION DUE TO CANCELLED LEAVE';
            $logStmt->bindParam(':change_type', $changeType, PDO::PARAM_STR);
            $logStmt->bindParam(':previous_balance', $previous_balance);
            $logStmt->bindParam(':changed_amount', $changed_amount);
            $logStmt->bindParam(':new_balance', $new_balance);
            $logStmt->bindParam(':change_date', $now);
            $logStmt->bindParam(':leave_id', $leave_id, PDO::PARAM_INT);
            $logStmt->execute();

            // Update credit_leave
            $updateCreditStmt = $pdo->prepare("UPDATE credit_leave SET $creditColumn = :new_balance WHERE userid = :userid");
            $updateCreditStmt->bindParam(':new_balance', $new_balance);
            $updateCreditStmt->bindParam(':userid', $leave['userid'], PDO::PARAM_INT);
            $updateCreditStmt->execute();
        }

        // Update leave_status to 6 (cancelled)
        $update = $pdo->prepare("UPDATE emp_leave SET leave_status = 6 WHERE id = :id");
        $update->bindParam(':id', $leave_id, PDO::PARAM_INT);
        $success = $update->execute();

        if ($success) {
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update leave status.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Not allowed or invalid status.']);
        exit;
    }
}

// Validate, fetch leave details, and check access
if (isset($_GET['id'])) {
    $requestid = (int) $_GET['id']; // Convert to integer for validation
    $userId = $_SESSION['userid'];
    $userLevel = isset($_SESSION['level']) ? $_SESSION['level'] : '';
    $userCategory = isset($_SESSION['category']) ? $_SESSION['category'] : '';

    // Fetch leave details, HR, Supervisor, and Manager data from emp_leave, not employment_details
    $stmt = $pdo->prepare("
        SELECT 
            emp_leave.*,
            employee.first_name AS emp_first_name, 
            employee.middle_name AS emp_middle_name, 
            employee.last_name AS emp_last_name,
            hr_employee.first_name AS hr_first_name,
            hr_employee.middle_name AS hr_middle_name,
            hr_employee.last_name AS hr_last_name,
            supervisor_employee.first_name AS supervisor_first_name,
            supervisor_employee.middle_name AS supervisor_middle_name,
            supervisor_employee.last_name AS supervisor_last_name,
            manager_employee.first_name AS manager_first_name,
            manager_employee.middle_name AS manager_middle_name,
            manager_employee.last_name AS manager_last_name
        FROM emp_leave
        LEFT JOIN employee ON emp_leave.userid = employee.id
        LEFT JOIN employee AS hr_employee ON emp_leave.hr = hr_employee.id
        LEFT JOIN employee AS supervisor_employee ON emp_leave.supervisor = supervisor_employee.id
        LEFT JOIN employee AS manager_employee ON emp_leave.manager = manager_employee.id
        WHERE emp_leave.id = :id
    ");
    $stmt->bindParam(':id', $requestid, PDO::PARAM_INT);
    $stmt->execute();
    $leaveDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leaveDetails) {
        // Redirect if the leave ID is invalid
        header("Location: myapplications?error=access_denied");
        exit;
    }

    // ------------------ ACCESS VALIDATION ------------------
    $isAllowed = (
        $userId == $leaveDetails['userid'] ||
        $userId == $leaveDetails['hr'] ||
        $userId == $leaveDetails['supervisor'] ||
        $userId == $leaveDetails['manager']
    );

    // NEW: Allow if ADMINISTRATOR + (HR or MINISTER)
    if (
        !$isAllowed &&
        $userLevel === 'ADMINISTRATOR' &&
        in_array($userCategory, ['HR', 'MINISTER'])
    ) {
        $isAllowed = true;
    }

    // AAO logic: ADMINISTRATOR + AAO or AAO
    if (
        !$isAllowed &&
        (
            ($userLevel === 'ADMINISTRATOR' && $userCategory === 'AAO') ||
            $userCategory === 'AAO'
        )
    ) {
        // Get AAO office
        $aaoOffice = null;
        $stmtAao = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
        $stmtAao->bindParam(':userid', $userId, PDO::PARAM_INT);
        $stmtAao->execute();
        $rowAao = $stmtAao->fetch(PDO::FETCH_ASSOC);
        if ($rowAao && isset($rowAao['office'])) {
            $aaoOffice = $rowAao['office'];
        }

        // Get leave owner office
        $leaveOwnerOffice = null;
        $stmtOwner = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
        $stmtOwner->bindParam(':userid', $leaveDetails['userid'], PDO::PARAM_INT);
        $stmtOwner->execute();
        $rowOwner = $stmtOwner->fetch(PDO::FETCH_ASSOC);
        if ($rowOwner && isset($rowOwner['office'])) {
            $leaveOwnerOffice = $rowOwner['office'];
        }

        if ($aaoOffice !== null && $leaveOwnerOffice !== null && $aaoOffice === $leaveOwnerOffice) {
            $isAllowed = true;
        }
    }

    if (!$isAllowed) {
        // Redirect if user is not owner, HR, Supervisor, Manager, or privileged ADMINISTRATOR/AAO
        header("Location: myapplications?error=access_denied");
        exit;
    }
    // -------------------------------------------------------

    // Format names
    $name = ucwords(strtolower($leaveDetails['emp_first_name'] . ' ' . (isset($leaveDetails['emp_middle_name']) && $leaveDetails['emp_middle_name'] ? strtoupper(substr($leaveDetails['emp_middle_name'], 0, 1)) . '. ' : '') . $leaveDetails['emp_last_name']));
    $hr_name = ucwords(strtolower($leaveDetails['hr_first_name'] . ' ' . (isset($leaveDetails['hr_middle_name']) && $leaveDetails['hr_middle_name'] ? strtoupper(substr($leaveDetails['hr_middle_name'], 0, 1)) . '. ' : '') . $leaveDetails['hr_last_name']));
    $supervisor_name = ucwords(strtolower($leaveDetails['supervisor_first_name'] . ' ' . (isset($leaveDetails['supervisor_middle_name']) && $leaveDetails['supervisor_middle_name'] ? strtoupper(substr($leaveDetails['supervisor_middle_name'], 0, 1)) . '. ' : '') . $leaveDetails['supervisor_last_name']));
    $manager_name = ucwords(strtolower($leaveDetails['manager_first_name'] . ' ' . (isset($leaveDetails['manager_middle_name']) && $leaveDetails['manager_middle_name'] ? strtoupper(substr($leaveDetails['manager_middle_name'], 0, 1)) . '. ' : '') . $leaveDetails['manager_last_name']));

    // For frontend button logic
    $isCancelable = in_array($leaveDetails['leave_status'], [1,2,3,4]);
} else {
    // Redirect if no leave ID is provided
    header("Location: myapplications?error=missing_leave_id");
    exit;
}

// Helper function to format statuses
function formatStatus($status) {
    return $status === 'APPROVED' ? '✅' : ($status === 'DISAPPROVED' ? '❌' : '');
}

// Helper function to capitalize first letter
function capitalize($text) {
    return ucfirst(strtolower($text));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 
  <title>HRIS | Leave Details</title>
  <link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>

<body class="bg-gray-50 dark:bg-neutral-900">
<!-- Container -->
<div class="max-w-2xl mx-auto my-8 p-6 bg-white shadow-md rounded-lg dark:bg-neutral-800">
    <!-- Body -->

    <?php if ($_SESSION['userid'] == $leaveDetails['userid']) : ?>
    <!-- floating ui -->
    <div class="hs-dropdown relative inline-flex">
      <button id="hs-dropdown-custom-icon-trigger" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
      </button>

      <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-custom-icon-trigger">
        <div class="p-1 space-y-0.5">
          <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700" href="myapplications">
            My Leaves
          </a>
          <a
          href="#"
          class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700"
          aria-haspopup="dialog"
          aria-expanded="false"
          aria-controls="hs-scale-animation-modal"
          data-hs-overlay="#hs-scale-animation-modal">
          Cancel Leave
        </a>     
        </div>
      </div>
    </div>
    <!-- end floating ui -->

    <!-- cancel modal -->
        <div id="hs-scale-animation-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-scale-animation-modal-label">
          <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
            <div class="w-full flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
              <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                <h3 id="hs-scale-animation-modal-label" class="font-bold text-gray-800 dark:text-white">
                  Attention!
                </h3>
                <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-scale-animation-modal">
                  <span class="sr-only">Close</span>
                  <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                  </svg>
                </button>
              </div>
              <div class="p-4 overflow-y-auto">
                <p class="mt-1 text-gray-800 dark:text-neutral-400">
                  Are you sure you want to cancel this leave application?
                </p>
              </div>
              <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-scale-animation-modal">
                  Close
                </button>
                <button 
                  type="button"
                  class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-red-600 text-white hover:bg-red-700 focus:outline-hidden focus:bg-red-700 disabled:opacity-50 disabled:pointer-events-none"
                  id="modal-cancel-leave-btn"
                  <?= !$isCancelable ? 'disabled' : '' ?>
                >
                    Cancel Leave
                </button>
              </div>
            </div>
          </div>
        </div>
        <!-- end modal --> 
        <?php endif; ?>       

    <div class="p-4 sm:p-7 overflow-y-auto">
        <div class="text-center">
            <h3 id="hs-ai-modal-label" class="text-lg font-semibold text-gray-800 dark:text-neutral-200">
                <?= htmlspecialchars($name) ?>
            </h3>
            <p class="text-sm text-gray-500 dark:text-neutral-500">
                Ref No. <?= htmlspecialchars($requestid) ?>
            </p>
        </div>

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Type:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
              <?= htmlspecialchars($leaveDetails['leave_type']) ?>
            </span>
          </div>
        </div>
        <!-- End Grid -->

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Details:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
              <?= htmlspecialchars($leaveDetails['leave_details']) ?>
            </span>
          </div>
        </div>
        <!-- End Grid -->

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Reason:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
               <?= htmlspecialchars($leaveDetails['leave_reason']) ?>
            </span>
          </div>
        </div>
        <!-- End Grid -->  

        <!-- Keep other static content as it is -->
        <div class="mt-5 sm:mt-10">
            <h4 class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Summary</h4>

            <ul class="mt-3 flex flex-col">
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Application Date</span>
                        <span><?= date('F j, Y', strtotime($leaveDetails['appdate'])) ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Start Date</span>
                        <span><?= date('F j, Y', strtotime($leaveDetails['startdate'])) ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>End Date</span>
                        <span><?= date('F j, Y', strtotime($leaveDetails['enddate'])) ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Requested Leave Days</span>
                        <span><?= htmlspecialchars($leaveDetails['total_leave_days']) ?></span>
                    </div>
                </li>
            </ul>
        </div>

        <div class="py-10 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Approval Progress</div>



        <!-- Timeline -->
        <div>
        
        <?php if (!is_null($leaveDetails['h_reject_status'])): ?>
        <!-- Status 1 -->
        <div class="flex gap-x-3">
            <div class="min-w-14 text-end">
                <span class="text-xs text-gray-500 dark:text-neutral-400">
                    <?= formatStatus($leaveDetails['h_reject_status']) ?>
                </span>
            </div>
            <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                <div class="relative z-10 size-7 flex justify-center items-center">
                    <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                </div>
            </div>
            <div class="grow pt-0.5 pb-8">
                <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                    <?= capitalize($leaveDetails['h_reject_status']) ?> by <?= htmlspecialchars($hr_name) ?>
                </h3>
                <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                    <span><?= date('F j, Y', strtotime($leaveDetails['h_date'])) ?></span>
                </button>
                <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                    <?= ucfirst(strtolower($leaveDetails['h_reject_reason'])) ?>
                </p>
            </div>
        </div>
        <!-- End of Status 1 -->
        <?php endif; ?>

        <?php if (!is_null($leaveDetails['reject_status'])): ?>
        <!-- Status 2 -->
        <div class="flex gap-x-3">
            <div class="min-w-14 text-end">
                <span class="text-xs text-gray-500 dark:text-neutral-400">
                    <?= formatStatus($leaveDetails['reject_status']) ?>
                </span>
            </div>
            <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                <div class="relative z-10 size-7 flex justify-center items-center">
                    <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                </div>
            </div>
            <div class="grow pt-0.5 pb-8">
                <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                    <?= capitalize($leaveDetails['reject_status']) ?> by <?= htmlspecialchars($supervisor_name) ?>
                </h3>
                <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                    <span><?= date('F j, Y', strtotime($leaveDetails['s_date'])) ?></span>
                </button>
                <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                    <?= ucfirst(strtolower($leaveDetails['reject_reason'])) ?>
                </p>
            </div>
        </div>
        <!-- End of Status 2 -->
        <?php endif; ?>

        <?php if (!is_null($leaveDetails['d_reject_status'])): ?>
        <!-- Status 3 -->
        <div class="flex gap-x-3">
            <div class="min-w-14 text-end">
                <span class="text-xs text-gray-500 dark:text-neutral-400">
                    <?= formatStatus($leaveDetails['d_reject_status']) ?>
                </span>
            </div>
            <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                <div class="relative z-10 size-7 flex justify-center items-center">
                    <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                </div>
            </div>
            <div class="grow pt-0.5 pb-8">
                <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                    <?= capitalize($leaveDetails['d_reject_status']) ?> by <?= htmlspecialchars($manager_name) ?>
                </h3>
                <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                    <span><?= date('F j, Y', strtotime($leaveDetails['d_date'])) ?></span>
                </button>
                <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                    <?= ucfirst(strtolower($leaveDetails['d_reject_reason'])) ?>
                </p>
            </div>
        </div>
        <!-- End of Status 3 -->
        <?php endif; ?>
            
        </div>
        <!-- End Timeline -->        
        
        <!-- Form -->
        <form method="POST" action="">           

            <!-- Dropdown and Textarea -->
            <div class="mt-5 flex flex-col gap-y-4">
                <button type="button" id="back-btn" class="w-full py-3 px-4 rounded-lg bg-gray-400 text-white text-sm font-medium hover:bg-gray-500 focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50" onclick="window.location.href='myapplications'">
                    Back
                </button>
            </div>
            
        </form>
    </div>
    <!-- End Body -->
</div>
<!-- End Container -->

<!-- Required plugins -->
  <script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Get the modal's Cancel Leave button
  const modalCancelBtn = document.getElementById('modal-cancel-leave-btn');
  // Get the modal (for optional closing)
  const modal = document.getElementById('hs-scale-animation-modal');
  // Get the leave ID from a data attribute or PHP variable
  const leaveId = <?= (int)$leaveDetails['id'] ?>;

  if (modalCancelBtn) {
    modalCancelBtn.addEventListener('click', function(e) {
      e.preventDefault();
      modalCancelBtn.disabled = true; // Prevent double submit

      fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          cancel_leave: 1,
          leave_id: leaveId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          window.location.href = 'myapplications';
        } else {
          modalCancelBtn.disabled = false;
          alert(data.error || 'Failed to cancel leave. Please try again.');
        }
      })
      .catch(() => {
        modalCancelBtn.disabled = false;
        alert('An error occurred. Please try again.');
      });
    });
  }
});
</script>

</body>
</html>