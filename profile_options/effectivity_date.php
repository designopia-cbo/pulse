<?php
require_once(__DIR__ . '/../init.php'); // Handles session, agent, and DB

// Handle AJAX POST for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id = isset($_POST['employment_details_id']) ? intval($_POST['employment_details_id']) : 0;
    $date_of_assumption = $_POST['date_of_assumption'] ?? '';
    $date_appointment = $_POST['date_appointment'] ?? '';

    if (!$id || !$date_of_assumption || !$date_appointment) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    // 1. Fetch current values before update
    $stmt = $pdo->prepare("SELECT date_of_assumption, date_appointment, userid FROM employment_details WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old) {
        echo json_encode(['success' => false, 'error' => 'Record not found.']);
        exit;
    }

    // 2. Get current user's (updated_by) name in UPPERCASE
    $updated_by = '';
    $user_stmt = $pdo->prepare("SELECT first_name, last_name FROM employee WHERE id = :id LIMIT 1");
    $user_stmt->execute([':id' => $_SESSION['userid']]);
    if ($user_row = $user_stmt->fetch(PDO::FETCH_ASSOC)) {
        $updated_by = strtoupper(trim($user_row['first_name'] . ' ' . $user_row['last_name']));
    } else {
        $updated_by = strtoupper($_SESSION['userid']); // fallback
    }

    // 3. Compare and log changes for each field
    $fields = [
        'date_of_assumption' => 'DATE OF ASSUMPTION',
        'date_appointment'   => 'DATE OF APPOINTMENT'
    ];
    $new_values = [
        'date_of_assumption' => $date_of_assumption,
        'date_appointment'   => $date_appointment
    ];
    foreach ($fields as $field_key => $field_label) {
        $old_val = strtoupper(strval($old[$field_key]));
        $new_val = strtoupper(strval($new_values[$field_key]));
        if ($old_val !== $new_val) {
            $log = $pdo->prepare(
                "INSERT INTO employee_update_history
                 (employee_id, field_name, old_value, new_value, updated_by, updated_at)
                 VALUES (:employee_id, :field_name, :old_value, :new_value, :updated_by, NOW())"
            );
            $log->execute([
                ':employee_id' => $old['userid'],
                ':field_name'  => $field_label,
                ':old_value'   => $old_val,
                ':new_value'   => $new_val,
                ':updated_by'  => $updated_by,
            ]);
        }
    }

    // 4. Perform the update
    $stmt = $pdo->prepare("UPDATE employment_details SET date_of_assumption = :date_of_assumption, date_appointment = :date_appointment WHERE id = :id LIMIT 1");
    $stmt->bindParam(':date_of_assumption', $date_of_assumption);
    $stmt->bindParam(':date_appointment', $date_appointment);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        $err = $stmt->errorInfo();
        echo json_encode(['success' => false, 'error' => 'Failed to update.', 'pdo_error' => $err]);
    }
    exit;
}

// -------- GET: Modal rendering --------

$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;

$stmt = $pdo->prepare("SELECT id, date_of_assumption, date_appointment FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$employment_details_id = $row ? $row['id'] : '';
$date_of_assumption = $row && !empty($row['date_of_assumption']) && $row['date_of_assumption'] !== '0000-00-00' ? $row['date_of_assumption'] : '';
$date_appointment = $row && !empty($row['date_appointment']) && $row['date_appointment'] !== '0000-00-00' ? $row['date_appointment'] : '';
?>
<!-- Update Effectivity Modal) -->
<div id="hs-medium-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-medium-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="effectivity-dates-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-medium-modal-label" class="font-bold text-gray-800 dark:text-white">Effective Dates</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-medium-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <div class="py-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <input type="hidden" id="employment_details_id" name="employment_details_id" value="<?= htmlspecialchars($employment_details_id) ?>">
              <label for="oldassumption" class="inline-block text-sm font-normal dark:text-white">Date of Assumption</label>
              <input type="date" id="oldassumption" name="date_of_assumption" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" value="<?= htmlspecialchars($date_of_assumption) ?>" readonly>
            </div>
            <div>
              <label for="oldappointment" class="inline-block text-sm font-normal dark:text-white">Date of Appointment</label>
              <input type="date" id="oldappointment" name="date_appointment" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" value="<?= htmlspecialchars($date_appointment) ?>" readonly>
            </div>
          </div>
        </div>
        <div id="effectivity-dates-error" class="text-red-600 text-sm py-2 hidden"></div>
        <div class="py-3"></div>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <!-- Edit button -->
        <button type="button" id="edit-effectivity-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
          Edit
        </button>
        <!-- Cancel button (hidden initially) -->
        <button type="button" id="cancel-effectivity-btn" style="display:none"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
          data-hs-overlay="#hs-medium-modal">
          Cancel
        </button>
        <!-- Update Details button (hidden initially) -->
        <button type="submit" id="update-effectivity-btn" style="display:none"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700">
          Update Details
        </button>
      </div>
    </form>
  </div>
</div>