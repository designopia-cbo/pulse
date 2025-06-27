<?php
require_once(__DIR__ . '/../init.php'); // session, agent, DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Determine userid: POST, GET, or session
    $profile_userid = null;
    if (!empty($_POST['profile_userid']) && is_numeric($_POST['profile_userid'])) {
        $profile_userid = intval($_POST['profile_userid']);
    } elseif (!empty($_GET['userid']) && is_numeric($_GET['userid'])) {
        $profile_userid = intval($_GET['userid']);
    } elseif (isset($_SESSION['userid'])) {
        $profile_userid = intval($_SESSION['userid']);
    }

    $separation_reason = isset($_POST['separation_reason']) ? strtoupper(trim($_POST['separation_reason'])) : '';
    $date_of_separation = isset($_POST['date_of_assumption']) ? $_POST['date_of_assumption'] : null;

    if (!$profile_userid || !$separation_reason || !$date_of_separation) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required data.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Step 1: Update status in employee table
        $updateEmployee = $pdo->prepare("UPDATE employee SET status = :status WHERE id = :id");
        $updateEmployee->execute([
            ':status' => $separation_reason,
            ':id' => $profile_userid
        ]);

        // Step 2: Vacate plantilla_position
        $updatePlantilla = $pdo->prepare("UPDATE plantilla_position SET userid = NULL WHERE userid = :userid");
        $updatePlantilla->execute([
            ':userid' => $profile_userid
        ]);

        // Step 3: Inactivate employment_details, set end_date
        $updateEmployment = $pdo->prepare("UPDATE employment_details SET edstatus = 0, end_date = :end_date WHERE userid = :userid AND edstatus = 1");
        $updateEmployment->execute([
            ':end_date' => $date_of_separation,
            ':userid' => $profile_userid
        ]);

        // Step 4: Update work_experience_mssd w_to_date
        $updateWorkExp = $pdo->prepare("UPDATE work_experience_mssd SET w_to_date = :w_to_date WHERE userid = :userid AND w_to_date IS NULL");
        $updateWorkExp->execute([
            ':w_to_date' => $date_of_separation,
            ':userid' => $profile_userid
        ]);

        // Step 5: Delete from users table where userid matches
        $deleteUser = $pdo->prepare("DELETE FROM users WHERE userid = :userid");
        $deleteUser->execute([
            ':userid' => $profile_userid
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- HTML Modal (GET) ---
$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;
?>

<div id="hs-separate-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-separate-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="separate-employee-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70" autocomplete="off">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-separate-modal-label" class="font-bold text-gray-800 dark:text-white">Separate</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-separate-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">

        <!-- Reason for Separation -->
        <div class="py-4">
          <label for="separation-reason" class="inline-block text-sm font-normal dark:text-white mb-1">Reason for Separation</label>
          <select id="separation-reason" name="separation_reason" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
            <option value="">Select Reason</option>
            <option value="retirement">Retirement</option>
            <option value="resignation">Resignation</option>
            <option value="termination">Termination</option>
            <option value="death">Death</option>
            <option value="dismissal">Dismissal</option>
            <option value="abolition-of-position">Abolition of Position</option>
            <option value="disability">Permanent Total Disability</option>
            <option value="early-retirement">Early Retirement (RA 6683)</option>
            <option value="voluntary-separation">Voluntary Separation (RA 6656)</option>
          </select>
        </div>

        <!-- Date Fields -->
        <div class="py-4">
          <div class="grid grid-cols-1 gap-3">
            <div>
              <input type="hidden" id="employment_details_id" name="employment_details_id" value="">
              <label for="date-of-separation" class="inline-block text-sm font-normal dark:text-white">Date of Effectivity</label>
              <input type="date" id="date-of-separation" name="date_of_assumption" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
            </div>
          </div>
        </div>

        <div id="separate-employee-error" class="text-red-600 text-sm py-2 hidden"></div>
      </div>

      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" id="cancel-separate-btn" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white" data-hs-overlay="#hs-separate-modal">
          Cancel
        </button>
        <button
          type="submit"
          id="submit-separate-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:border-gray-400 transition"
          disabled
        >
          Separate
        </button>
      </div>
    </form>
  </div>
</div>