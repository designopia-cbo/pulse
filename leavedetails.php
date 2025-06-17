<?php
require_once('init.php');

// --- AJAX HANDLER: Transfer to Minister ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) && $_POST['action'] === 'transfer_to_minister' &&
    isset($_POST['leave_id']) && is_numeric($_POST['leave_id'])
) {
    $leaveId = (int)$_POST['leave_id'];

    // Security: Check if the user is allowed to update this leave
    $sessionUserId = $_SESSION['userid'];
    $leaveQuery = $pdo->prepare("SELECT * FROM emp_leave WHERE id = :id");
    $leaveQuery->execute([':id' => $leaveId]);
    $leaveDetailsAjax = $leaveQuery->fetch(PDO::FETCH_ASSOC);

    if (
        !$leaveDetailsAjax ||
        !(($leaveDetailsAjax['manager'] == $sessionUserId && $leaveDetailsAjax['leave_status'] == 3) ||
          ($leaveDetailsAjax['supervisor'] == $sessionUserId && $leaveDetailsAjax['leave_status'] == 2) ||
          ($leaveDetailsAjax['hr'] == $sessionUserId && $leaveDetailsAjax['leave_status'] == 1))
    ) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized action.']);
        exit;
    }

    // Perform the transfer: set manager = 2025
    $stmt = $pdo->prepare("UPDATE emp_leave SET manager = 2025 WHERE id = :id");
    $success = $stmt->execute([':id' => $leaveId]);
    echo json_encode(['success' => $success]);
    exit;
}

// Retrieve the leave ID from the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect if no valid ID is provided
    header("Location: dashboard?error=invalid_id");
    exit;
}

$leaveId = (int)$_GET['id'];

// Step 1: Retrieve the leave request details
$leaveQuery = $pdo->prepare("SELECT * FROM emp_leave WHERE id = :id");
$leaveQuery->execute([':id' => $leaveId]);
$leaveDetails = $leaveQuery->fetch(PDO::FETCH_ASSOC);

if (!$leaveDetails) {
    // Redirect if the leave request does not exist
    header("Location: dashboard");
    exit;
}

// ---------------------------------------------------------------------
// ACCESS VALIDATION SECTION (as per your requirements)
// ---------------------------------------------------------------------
$sessionUserId = $_SESSION['userid'];
$canView = false;

if (
    ($leaveDetails['hr'] == $sessionUserId && $leaveDetails['leave_status'] == 1) ||
    ($leaveDetails['supervisor'] == $sessionUserId && $leaveDetails['leave_status'] == 2) ||
    ($leaveDetails['manager'] == $sessionUserId && $leaveDetails['leave_status'] == 3)
) {
    $canView = true;
}

if (!$canView) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}
// ---------------------------------------------------------------------

// Function to get employee name
function getEmployeeName($employeeId, $pdo) {
    $employeeQuery = $pdo->prepare("
        SELECT first_name, middle_name, last_name 
        FROM employee 
        WHERE id = :id
    ");
    $employeeQuery->execute([':id' => $employeeId]);
    $employee = $employeeQuery->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $fullName = ucwords(strtolower($employee['first_name'])) . " " .
                    (isset($employee['middle_name']) && !empty($employee['middle_name']) 
                        ? strtoupper(substr($employee['middle_name'], 0, 1)) . ". " 
                        : "") .
                    ucwords(strtolower($employee['last_name']));
        return $fullName;
    }
    return "Unknown";
}

// Function to format a string to have the first letter of each sentence capitalized
function formatSentenceCase($string) {
    $string = strtolower($string);
    return ucfirst($string);
}

// Retrieve HR, Supervisor, and Manager names using emp_leave columns
$hrName = getEmployeeName($leaveDetails['hr'], $pdo);
$spName = getEmployeeName($leaveDetails['supervisor'], $pdo);
$drName = getEmployeeName($leaveDetails['manager'], $pdo);

// Step 4: Retrieve the employee_name
$employeeQuery = $pdo->prepare("
    SELECT first_name, middle_name, last_name 
    FROM employee 
    WHERE id = :userid
");
$employeeQuery->execute([':userid' => $leaveDetails['userid']]);
$employee = $employeeQuery->fetch(PDO::FETCH_ASSOC);

if ($employee) {
    $firstName = ucwords(strtolower($employee['first_name']));
    $middleInitial = strtoupper(substr($employee['middle_name'], 0, 1)) . ".";
    $lastName = ucwords(strtolower($employee['last_name']));

    $employeeName = htmlspecialchars("$firstName $middleInitial $lastName");
    $leaveDetails['employee_name'] = $employeeName;
} else {
    $leaveDetails['employee_name'] = "Unknown Employee";
}

// Step 5: Calculate Current Leave Balance, Requested Leave Days, and Leave Balance After Approval
$currentLeaveBalance = '-';
$requestedLeaveDays = $leaveDetails['total_leave_days'];
$leaveBalanceAfterApproval = '-';

$creditLeaveQuery = $pdo->prepare("SELECT * FROM credit_leave WHERE userid = :userid");
$creditLeaveQuery->execute([':userid' => $leaveDetails['userid']]);
$creditLeave = $creditLeaveQuery->fetch(PDO::FETCH_ASSOC);

if ($creditLeave) {
    switch (strtoupper($leaveDetails['leave_type'])) {
        case 'VACATION LEAVE':
            $currentLeaveBalance = $creditLeave['vacationleave'];
            break;
        case 'SICK LEAVE':
            $currentLeaveBalance = $creditLeave['sickleave'];
            break;
        case 'SPECIAL PRIVILEGE LEAVE':
            $currentLeaveBalance = $creditLeave['spleave'];
            break;
        default:
            $currentLeaveBalance = '-';
    }

    if ($currentLeaveBalance !== '-') {
        $leaveBalanceAfterApproval = $currentLeaveBalance - $requestedLeaveDays;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $requestId = (int)$_POST['requestid']; // Get the request ID
    $approvalStatus = strtoupper($_POST['approval_status']); // Ensure approval status is uppercase
    $reasonField = isset($_POST['reason_field']) ? strtoupper(trim($_POST['reason_field'])) : null; // Ensure reason field is uppercase
    $todayDate = date('Y-m-d'); // Get today's date in YYYY-MM-DD format

    try {
        // Retrieve the current leave_status and row details
        $leaveQuery = $pdo->prepare("SELECT * FROM emp_leave WHERE id = :id");
        $leaveQuery->execute([':id' => $requestId]);
        $leaveDetails = $leaveQuery->fetch(PDO::FETCH_ASSOC);

        if (!$leaveDetails) {
            header("Location: dashboard?status=error&message=leave_request_not_found");
            exit;
        }

        if ($leaveDetails['leave_status'] == 1) {
            if ($approvalStatus === 'APPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET h_reject_status = :status, h_date = :todayDate, leave_status = 2 
                    WHERE id = :id
                ");
                $updateQuery->execute([':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);
                header("Location: dashboard?status=success");
                exit;
            } elseif ($approvalStatus === 'DISAPPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET h_reject_reason = :reason, h_reject_status = :status, h_date = :todayDate, leave_status = 5 
                    WHERE id = :id
                ");
                $updateQuery->execute([':reason' => $reasonField, ':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);
                header("Location: dashboard?status=success");
                exit;
            }
        } elseif ($leaveDetails['leave_status'] == 2) {
            if ($approvalStatus === 'APPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET reject_status = :status, s_date = :todayDate, leave_status = 3 
                    WHERE id = :id
                ");
                $updateQuery->execute([':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);
                header("Location: dashboard?status=success");
                exit;
            } elseif ($approvalStatus === 'DISAPPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET reject_reason = :reason, reject_status = :status, s_date = :todayDate, leave_status = 5 
                    WHERE id = :id
                ");
                $updateQuery->execute([':reason' => $reasonField, ':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);
                header("Location: dashboard?status=success");
                exit;
            }
        } elseif ($leaveDetails['leave_status'] == 3) {
            if ($approvalStatus === 'APPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET d_reject_status = :status, d_date = :todayDate, leave_status = 4 
                    WHERE id = :id
                ");
                $updateQuery->execute([':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);

                // Only log deduction and update credit_leave for specific leave types
                $leaveTypeUpper = strtoupper($leaveDetails['leave_type']);
                if (
                    $leaveTypeUpper === 'VACATION LEAVE' ||
                    $leaveTypeUpper === 'SICK LEAVE' ||
                    $leaveTypeUpper === 'SPECIAL PRIVILEGE LEAVE'
                ) {
                    // Insert into leave_credit_log
                    $creditQuery = $pdo->prepare("SELECT * FROM credit_leave WHERE userid = :userid");
                    $creditQuery->execute([':userid' => $leaveDetails['userid']]);
                    $creditLeave = $creditQuery->fetch(PDO::FETCH_ASSOC);

                    if (!$creditLeave) {
                        header("Location: dashboard?status=error&message=credit_leave_not_found");
                        exit;
                    }

                    $previousBalance = 0;
                    if ($leaveTypeUpper === 'VACATION LEAVE') {
                        $previousBalance = $creditLeave['vacationleave'];
                    } elseif ($leaveTypeUpper === 'SICK LEAVE') {
                        $previousBalance = $creditLeave['sickleave'];
                    } elseif ($leaveTypeUpper === 'SPECIAL PRIVILEGE LEAVE') {
                        $previousBalance = $creditLeave['spleave'];
                    }

                    $changedAmount = $leaveDetails['total_leave_days'];
                    $newBalance = $previousBalance - $changedAmount;

                    // Log current balances to balance_log before leave_credit_log entry
                    $insertBalanceLogQuery = $pdo->prepare("
                        INSERT INTO balance_log (vl, sl, leave_id)
                        VALUES (:vl, :sl, :leave_id)
                    ");
                    $insertBalanceLogQuery->execute([
                        ':vl' => $creditLeave['vacationleave'],
                        ':sl' => $creditLeave['sickleave'],
                        ':leave_id' => $requestId
                    ]);

                    $insertLogQuery = $pdo->prepare("
                        INSERT INTO leave_credit_log (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, change_date, leave_id)
                        VALUES (:userid, :leave_type, 'DEDUCTION', :previous_balance, :changed_amount, :new_balance, :change_date, :leave_id)
                    ");
                    $insertLogQuery->execute([
                        ':userid' => $leaveDetails['userid'],
                        ':leave_type' => $leaveTypeUpper,
                        ':previous_balance' => $previousBalance,
                        ':changed_amount' => $changedAmount,
                        ':new_balance' => $newBalance,
                        ':change_date' => $todayDate,
                        ':leave_id' => $requestId
                    ]);

                    // Update credit_leave
                    $updateCreditQuery = $pdo->prepare("
                        UPDATE credit_leave 
                        SET vacationleave = CASE WHEN :leave_type = 'VACATION LEAVE' THEN :new_balance ELSE vacationleave END,
                            sickleave = CASE WHEN :leave_type = 'SICK LEAVE' THEN :new_balance ELSE sickleave END,
                            spleave = CASE WHEN :leave_type = 'SPECIAL PRIVILEGE LEAVE' THEN :new_balance ELSE spleave END
                        WHERE userid = :userid
                    ");
                    $updateCreditQuery->execute([
                        ':leave_type' => $leaveTypeUpper,
                        ':new_balance' => $newBalance,
                        ':userid' => $leaveDetails['userid']
                    ]);
                }

                header("Location: dashboard?status=success");
                exit;
            } elseif ($approvalStatus === 'DISAPPROVED') {
                $updateQuery = $pdo->prepare("
                    UPDATE emp_leave 
                    SET d_reject_reason = :reason, d_reject_status = :status, d_date = :todayDate, leave_status = 5 
                    WHERE id = :id
                ");
                $updateQuery->execute([':reason' => $reasonField, ':status' => $approvalStatus, ':todayDate' => $todayDate, ':id' => $requestId]);
                header("Location: dashboard?status=success");
                exit;
            }
        } else {
            header("Location: dashboard?status=error&message=invalid_leave_status");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: dashboard?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
}

// Timeline values
$hRejectStatus = formatSentenceCase($leaveDetails['h_reject_status']);
$hRejectReason = formatSentenceCase($leaveDetails['h_reject_reason']);
$hDate = $leaveDetails['h_date'];

$rejectStatus = formatSentenceCase($leaveDetails['reject_status']);
$rejectReason = formatSentenceCase($leaveDetails['reject_reason']);
$sDate = $leaveDetails['s_date'];

$dRejectStatus = formatSentenceCase($leaveDetails['d_reject_status']);
$dRejectReason = formatSentenceCase($leaveDetails['d_reject_reason']);
$dDate = $leaveDetails['d_date'];

// Update icons logic for statuses
$hIcon = strtoupper($hRejectStatus) === "APPROVED" ? "✅" : "❌";
$sIcon = strtoupper($rejectStatus) === "APPROVED" ? "✅" : "❌";
$dIcon = strtoupper($dRejectStatus) === "APPROVED" ? "✅" : "❌";
?>

<!DOCTYPE html>
<html lang="en">
<head>  
  <title>HRIS | Leave Details</title>
  <link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>

<body class="bg-gray-50 dark:bg-neutral-900">
<!-- Container -->
<div class="max-w-2xl mx-auto my-8 p-6 bg-white shadow-md rounded-lg dark:bg-neutral-800">
    <!-- Body -->

    <?php if ($_SESSION['userid'] == $leaveDetails['hr']) : ?>
    <!-- floating ui -->
    <div class="hs-dropdown relative inline-flex">
      <button id="hs-dropdown-custom-icon-trigger" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
      </button>

      <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-custom-icon-trigger">
        <div class="p-1 space-y-0.5">              
          <a 
            class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700"
            href="javascript:void(0);"
            data-hs-overlay="#hs-scale-animation-modal"
          >            
            For Minister
          </a>
        </div>
      </div>
    </div>
    <!-- end of floating ui -->
    <?php endif; ?>

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
              Are you sure you want to transfer the final approval of this leave request to the ministerial level?
            </p>
          </div>
          <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
            <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-scale-animation-modal">
              Close
            </button>
            <button type="button" id="transfer-to-minister-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
              Transfer to Minister
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="p-4 sm:p-7 overflow-y-auto">
        <div class="text-center">
            <h3 id="hs-ai-modal-label" class="text-lg font-semibold text-gray-800 dark:text-neutral-200">
                <?php echo htmlspecialchars($leaveDetails['employee_name']); ?>
            </h3>
            <p class="text-sm text-gray-500 dark:text-neutral-500">
                Ref No. <?php echo htmlspecialchars($leaveDetails['id']); ?>
            </p>
        </div>

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Type:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
              <?php echo htmlspecialchars($leaveDetails['leave_type']); ?>
            </span>
          </div>
        </div>
        <!-- End Grid -->

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Details:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
              <?php echo htmlspecialchars($leaveDetails['leave_details']); ?>
            </span>
          </div>
        </div>
        <!-- End Grid -->

        <!-- Grid -->
        <div class="mt-5 sm:mt-10 grid grid-cols-3 gap-5">
          <div class="col-span-3 sm:col-span-2 w-full">
            <span class="block text-xs uppercase text-gray-500 dark:text-neutral-500">Leave Reason:</span>
            <span class="block text-sm font-medium text-gray-800 dark:text-neutral-200 break-words">
              <?php echo htmlspecialchars($leaveDetails['leave_reason']); ?>
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
                        <span><?php echo htmlspecialchars(date('M d, Y', strtotime($leaveDetails['appdate']))); ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Start Date</span>
                        <span><?php echo htmlspecialchars(date('M d, Y', strtotime($leaveDetails['startdate']))); ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>End Date</span>
                        <span><?php echo htmlspecialchars(date('M d, Y', strtotime($leaveDetails['enddate']))); ?></span>
                    </div>
                </li>
                <!-- Static placeholders for now -->
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Current Leave Balance</span>
                        <span><?php echo htmlspecialchars($currentLeaveBalance); ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Requested Leave Days</span>
                        <span><?php echo htmlspecialchars($requestedLeaveDays); ?></span>
                    </div>
                </li>
                <li class="inline-flex items-center gap-x-2 py-3 px-4 text-sm font-semibold bg-gray-50 border border-gray-200 text-gray-800 -mt-px first:rounded-t-lg first:mt-0 last:rounded-b-lg dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200">
                    <div class="flex items-center justify-between w-full">
                        <span>Leave Balance After Approval</span>
                        <span><?php echo htmlspecialchars($leaveBalanceAfterApproval); ?></span>
                    </div>
                </li>
            </ul>
        </div>

        <div class="py-10 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Approval Progress</div>


        <!-- Timeline -->
        <div>
            <?php if (!empty($hRejectStatus)): ?>
                    <!-- Status 1 -->
                    <div class="flex gap-x-3">
                        <div class="min-w-14 text-end">
                            <span class="text-xs text-gray-500 dark:text-neutral-400">
                                <?php echo strtoupper($hRejectStatus) === "APPROVED" ? "✅" : (strtoupper($hRejectStatus) === "DISAPPROVED" ? "❌" : ""); ?>
                            </span>
                        </div>
                        <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                            <div class="relative z-10 size-7 flex justify-center items-center">
                                <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                            </div>
                        </div>
                        <div class="grow pt-0.5 pb-8">
                            <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                                <?php echo $hRejectStatus; ?> by <?php echo $hrName; ?>
                            </h3>
                            <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                                <span><?php echo $hDate; ?></span>
                            </button>
                            <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                                <?php echo $hRejectReason; ?>
                            </p>
                        </div>
                    </div>
                    <!-- End of Status 1 -->
                <?php endif; ?>

                <?php if (!empty($rejectStatus)): ?>
                    <!-- Status 2 -->
                    <div class="flex gap-x-3">
                        <div class="min-w-14 text-end">
                            <span class="text-xs text-gray-500 dark:text-neutral-400">
                                <?php echo strtoupper($rejectStatus) === "APPROVED" ? "✅" : (strtoupper($rejectStatus) === "DISAPPROVED" ? "❌" : ""); ?>
                            </span>
                        </div>
                        <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                            <div class="relative z-10 size-7 flex justify-center items-center">
                                <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                            </div>
                        </div>
                        <div class="grow pt-0.5 pb-8">
                            <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                                <?php echo $rejectStatus; ?> by <?php echo $spName; ?>
                            </h3>
                            <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                                <span><?php echo $sDate; ?></span>
                            </button>
                            <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                                <?php echo $rejectReason; ?>
                            </p>
                        </div>
                    </div>
                    <!-- End of Status 2 -->
                <?php endif; ?>

                <?php if (!empty($dRejectStatus)): ?>
                <!-- Status 3 -->
                <div class="flex gap-x-3">
                    <div class="min-w-14 text-end">
                        <span class="text-xs text-gray-500 dark:text-neutral-400">
                            <?php 
                                $status = strtoupper(trim($dRejectStatus)); 
                                echo $status === "APPROVED" ? "✅" : ($status === "DISAPPROVED" ? "❌" : ""); 
                            ?>
                        </span>
                    </div>
                    <div class="relative last:after:hidden after:absolute after:top-7 after:bottom-0 after:start-3.5 after:w-px after:-translate-x-[0.5px] after:bg-gray-200 dark:after:bg-neutral-700">
                        <div class="relative z-10 size-7 flex justify-center items-center">
                            <div class="size-2 rounded-full bg-gray-400 dark:bg-neutral-600"></div>
                        </div>
                    </div>
                    <div class="grow pt-0.5 pb-8">
                        <h3 class="flex gap-x-1.5 font-semibold text-gray-800 dark:text-white">
                            <?php echo htmlspecialchars($dRejectStatus); ?> by <?php echo htmlspecialchars($drName); ?>
                        </h3>
                        <button type="button" class="mt-1 -ms-1 p-1 inline-flex items-center gap-x-2 text-xs rounded-lg border border-transparent text-gray-500 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
                            <span><?php echo htmlspecialchars($dDate); ?></span>
                        </button>
                        <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400" style="text-align: justify;">
                            <?php echo htmlspecialchars($dRejectReason); ?>
                        </p>
                    </div>
                </div>
                <!-- End of Status 3 -->
            <?php endif; ?>
        </div>
        <!-- End Timeline -->

              
        
        <hr class="my-6 border-t border-gray-300 dark:border-neutral-700">

        <!-- Form -->
        <form method="POST" action="">
            <!-- Radio Buttons for Approval -->
            <div class="grid sm:grid-cols-2 gap-2 mt-5">
                <label for="radio-approved" class="flex p-3 w-full bg-white border border-gray-200 rounded-lg text-sm cursor-pointer focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
                    <input type="radio" name="approval_status" value="approved" class="shrink-0 mt-0.5 border-gray-200 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="radio-approved" onclick="toggleReasonField(true)" checked>
                    <span class="ms-3 text-sm text-gray-500 dark:text-neutral-400">Approve</span>
                </label>

                <label for="radio-disapproved" class="flex p-3 w-full bg-white border border-gray-200 rounded-lg text-sm cursor-pointer focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
                    <input type="radio" name="approval_status" value="disapproved" class="shrink-0 mt-0.5 border-gray-200 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="radio-disapproved" onclick="toggleReasonField(false)">
                    <span class="ms-3 text-sm text-gray-500 dark:text-neutral-400">Disapprove</span>
                </label>            
            </div>

            <!-- Dropdown and Textarea -->
            <div class="mt-5 flex flex-col gap-y-4">              
                <textarea id="reason-field" name="reason_field" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" rows="4" placeholder="Reason for rejection." oninput="validateForm()"></textarea>
                <button id="submit-button" type="submit" class="py-3 px-3 inline-flex items-center justify-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                style="background-color: #155dfc; color: white;">
                Submit
                </button>
            </div>

            <!-- Hidden Input for Request ID -->
            <input type="hidden" name="requestid" value="<?php echo htmlspecialchars($leaveDetails['id']); ?>">
        </form>
    </div>
    <!-- End Body -->
</div>
<!-- End Container -->

<!-- Required plugins -->
  <script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script>
    function toggleReasonField(isApproved) {
        const reasonField = document.getElementById('reason-field');
        if (isApproved) {
            reasonField.style.display = 'none';
            reasonField.value = ''; // Optional: Clear the reason field
        } else {
            reasonField.style.display = 'block';
        }
    }

    function showAlert(requestid) {
        // Show an alert with the value of requestid
        alert("Request ID: " + requestid);
    }

    // Initial setup: Hide the reason field if "Approved" is the default selected radio button
    document.addEventListener('DOMContentLoaded', () => {
        const approvedRadio = document.getElementById('radio-approved');
        toggleReasonField(approvedRadio.checked);
    });
</script>

<script>
document.getElementById('transfer-to-minister-btn').addEventListener('click', function() {
    var leaveId = <?php echo json_encode($leaveId); ?>;
    fetch('leavedetails', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=transfer_to_minister&leave_id=' + encodeURIComponent(leaveId)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Failed to transfer.');
        }
    }).catch(err => alert('Request error: ' + err));
});
</script>

</body>
</html>