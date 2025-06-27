<?php
require_once(__DIR__ . '/../init.php');

// Determine userid for GET (show modal)
$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;

// Handle POST (AJAX form submit for saving credit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Correctly determine the profile_userid from POST, GET, or session
    $profile_userid = null;
    if (!empty($_POST['profile_userid']) && is_numeric($_POST['profile_userid'])) {
        $profile_userid = intval($_POST['profile_userid']);
    } elseif (!empty($_GET['userid']) && is_numeric($_GET['userid'])) {
        $profile_userid = intval($_GET['userid']);
    } elseif (isset($_SESSION['userid'])) {
        $profile_userid = intval($_SESSION['userid']);
    }

    $vacationleave = isset($_POST['vacation_leave']) ? $_POST['vacation_leave'] : null;
    $sickleave = isset($_POST['sick_leave']) ? $_POST['sick_leave'] : null;
    $spleave = isset($_POST['special_privilege_leave']) ? $_POST['special_privilege_leave'] : null;

    // Validate fields
    if (
        $profile_userid === null ||
        $vacationleave === null ||
        $sickleave === null ||
        $spleave === null
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required values.']);
        exit;
    }

    // Check if row exists
    $stmt = $pdo->prepare("SELECT vacationleave, sickleave, spleave FROM credit_leave WHERE userid = :userid LIMIT 1");
    $stmt->execute([':userid' => $profile_userid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Prepare for logging changes
        $now = date('Y-m-d H:i:s');
        $logs = [];

        // Vacation Leave log
        if ((string)$row['vacationleave'] !== (string)$vacationleave) {
            $change_type = ($vacationleave > $row['vacationleave']) ? 'ADDITION' : 'DEDUCTION';
            $logs[] = [
                'userid' => $profile_userid,
                'leave_type' => 'VACATION',
                'change_type' => $change_type,
                'previous_balance' => $row['vacationleave'],
                'changed_amount' => $vacationleave,
                'new_balance' => $vacationleave,
                'change_date' => $now,
                'leave_id' => null
            ];
        }
        // Sick Leave log
        if ((string)$row['sickleave'] !== (string)$sickleave) {
            $change_type = ($sickleave > $row['sickleave']) ? 'ADDITION' : 'DEDUCTION';
            $logs[] = [
                'userid' => $profile_userid,
                'leave_type' => 'SICK',
                'change_type' => $change_type,
                'previous_balance' => $row['sickleave'],
                'changed_amount' => $sickleave,
                'new_balance' => $sickleave,
                'change_date' => $now,
                'leave_id' => null
            ];
        }
        // Special Privilege Leave log
        if ((string)$row['spleave'] !== (string)$spleave) {
            $change_type = ($spleave > $row['spleave']) ? 'ADDITION' : 'DEDUCTION';
            $logs[] = [
                'userid' => $profile_userid,
                'leave_type' => 'SPL',
                'change_type' => $change_type,
                'previous_balance' => $row['spleave'],
                'changed_amount' => $spleave,
                'new_balance' => $spleave,
                'change_date' => $now,
                'leave_id' => null
            ];
        }

        // Transaction: Log then update
        $pdo->beginTransaction();
        try {
            // Log changes if any
            if (!empty($logs)) {
                $logStmt = $pdo->prepare(
                    "INSERT INTO leave_credit_log 
                    (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, change_date, leave_id) 
                    VALUES 
                    (:userid, :leave_type, :change_type, :previous_balance, :changed_amount, :new_balance, :change_date, :leave_id)"
                );
                foreach ($logs as $log) {
                    $logStmt->execute([
                        ':userid' => $log['userid'],
                        ':leave_type' => $log['leave_type'],
                        ':change_type' => $log['change_type'],
                        ':previous_balance' => $log['previous_balance'],
                        ':changed_amount' => $log['changed_amount'],
                        ':new_balance' => $log['new_balance'],
                        ':change_date' => $log['change_date'],
                        ':leave_id' => $log['leave_id'],
                    ]);
                }
            }

            // Update values
            $update = $pdo->prepare("UPDATE credit_leave SET vacationleave = :vacationleave, sickleave = :sickleave, spleave = :spleave WHERE userid = :userid");
            $update->execute([
                ':vacationleave' => $vacationleave,
                ':sickleave' => $sickleave,
                ':spleave' => $spleave,
                ':userid' => $profile_userid,
            ]);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $ex) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        }
    } else {
        // Do not insert
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No matching row found for this user.']);
    }
    exit;
}

// Default values for GET (show modal)
$vacationleave = $sickleave = $spleave = "";
$stmt = $pdo->prepare("SELECT vacationleave, sickleave, spleave FROM credit_leave WHERE userid = :userid LIMIT 1");
$stmt->execute([':userid' => $profile_userid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $vacationleave = $row['vacationleave'];
    $sickleave = $row['sickleave'];
    $spleave = $row['spleave'];
}
?>
<div id="hs-edit-credit-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-edit-credit-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="edit-credit-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70" autocomplete="off">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-edit-credit-modal-label" class="font-bold text-gray-800 dark:text-white">Edit Credit</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-edit-credit-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Vacation Leave -->
        <div class="py-4">
          <label for="vacation-leave" class="inline-block text-sm font-normal dark:text-white mb-1">Vacation Leave</label>
          <input type="number" step="0.01" min="0" id="vacation-leave" name="vacation_leave"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            placeholder="Enter vacation leave credits"
            value="<?= htmlspecialchars($vacationleave) ?>"
            required
            disabled>
        </div>
        <!-- Sick Leave -->
        <div class="py-4">
          <label for="sick-leave" class="inline-block text-sm font-normal dark:text-white mb-1">Sick Leave</label>
          <input type="number" step="0.01" min="0" id="sick-leave" name="sick_leave"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            placeholder="Enter sick leave credits"
            value="<?= htmlspecialchars($sickleave) ?>"
            required
            disabled>
        </div>
        <!-- Special Privilege Leave -->
        <div class="py-4">
          <label for="special-privilege-leave" class="inline-block text-sm font-normal dark:text-white mb-1">Special Privilege Leave</label>
          <input type="number" step="0.01" min="0" id="special-privilege-leave" name="special_privilege_leave"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            placeholder="Enter special privilege leave credits"
            value="<?= htmlspecialchars($spleave) ?>"
            required
            disabled>
        </div>
        <div id="edit-credit-error" class="text-red-600 text-sm py-2 hidden"></div>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" id="edit-credit-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
        >
          Edit
        </button>
        <button type="button" id="cancel-edit-credit-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
          style="display:none;"
          data-hs-overlay="#hs-edit-credit-modal"
        >
          Cancel
        </button>
        <button
          type="submit"
          id="submit-edit-credit-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:border-gray-400 transition"
          style="display:none;"
        >
          Save Credit
        </button>
      </div>
    </form>
  </div>
</div>