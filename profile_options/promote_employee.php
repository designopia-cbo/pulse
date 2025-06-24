<?php
require_once(__DIR__ . '/../init.php'); // session, agent, DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $profile_userid = (!empty($_POST['profile_userid']) && is_numeric($_POST['profile_userid']))
        ? intval($_POST['profile_userid'])
        : (isset($_SESSION['userid']) ? intval($_SESSION['userid']) : 0);

    $date_of_assumption = $_POST['date_of_assumption'] ?? null;
    $date_appointment = $_POST['date_appointment'] ?? null;
    $plantilla_item_number = $_POST['plantilla_item_number'] ?? null;

    if (!$profile_userid || !$plantilla_item_number) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required data.']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Step 1: Vacate old position
        $updateOldPosition = $pdo->prepare("UPDATE plantilla_position SET userid = NULL WHERE userid = :userid");
        $updateOldPosition->execute([':userid' => $profile_userid]);

        // Step 2a: Update previous work_experience_mssd (set w_to_date to day before assumption)
        $prev_w_to_date = $date_of_assumption ? (new DateTime($date_of_assumption))->modify('-1 day')->format('Y-m-d') : null;
        if ($prev_w_to_date) {
            $updateWorkExp = $pdo->prepare("UPDATE work_experience_mssd SET w_to_date = :w_to_date WHERE userid = :userid AND w_to_date IS NULL");
            $updateWorkExp->execute([
                ':w_to_date' => $prev_w_to_date,
                ':userid' => $profile_userid
            ]);
        }

        // Step 3: Inactivate Previous Employment Details and set end_date to the day before dateofassumption
        $getEmpDetails = $pdo->prepare("SELECT * FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
        $getEmpDetails->execute([':userid' => $profile_userid]);
        $empDetails = $getEmpDetails->fetch(PDO::FETCH_ASSOC);

        if ($empDetails) {
            // Compute the day before dateofassumption
            $dateOfAssumption = isset($_POST['date_of_assumption']) ? $_POST['date_of_assumption'] : null;
            $prevEndDate = null;
            if ($dateOfAssumption) {
                $dt = new DateTime($dateOfAssumption);
                $dt->modify('-1 day');
                $prevEndDate = $dt->format('Y-m-d');
            }
            $updateEmploymentDetails = $pdo->prepare("UPDATE employment_details SET edstatus = 0, end_date = :end_date WHERE id = :id");
            $updateEmploymentDetails->execute([
                ':id' => $empDetails['id'],
                ':end_date' => $prevEndDate
            ]);
        } else {
            throw new Exception("Active employment details not found for user.");
        }

        // ---- STEP 4: Assign new plantilla_position to user ----
        $getNewPosition = $pdo->prepare("SELECT * FROM plantilla_position WHERE item_number = :item_number LIMIT 1");
        $getNewPosition->execute([':item_number' => $plantilla_item_number]);
        $newPosition = $getNewPosition->fetch(PDO::FETCH_ASSOC);

        if (!$newPosition) {
            throw new Exception("Selected plantilla position not found.");
        }

        // Update the plantilla_position row to assign it to the user
        $assignPlantilla = $pdo->prepare("UPDATE plantilla_position SET userid = :userid WHERE id = :id");
        $assignPlantilla->execute([
            ':userid' => $profile_userid,
            ':id' => $newPosition['id']
        ]);

        // ---- STEP 5: Add new employment_details row ----

        // Get salary info
        $getSalary = $pdo->prepare("SELECT id, ssl_amount FROM salary_standardization WHERE ssl_salary_grade = :sg AND ssl_step = 1 LIMIT 1");
        $getSalary->execute([':sg' => $newPosition['salary_grade']]);
        $salaryRow = $getSalary->fetch(PDO::FETCH_ASSOC);

        $monthly_salary = $salaryRow ? $salaryRow['ssl_amount'] : null;
        $salary_id = $salaryRow ? $salaryRow['id'] : null;

        $insertEmployment = $pdo->prepare("
            INSERT INTO employment_details 
                (userid, position_id, date_of_assumption, date_appointment, sg, step, end_date, salary_id, monthly_salary, edstatus, supervisor, manager, hr, area_of_assignment)
            VALUES
                (:userid, :position_id, :date_of_assumption, :date_appointment, :sg, 1, NULL, :salary_id, :monthly_salary, 1, NULL, NULL, NULL, NULL)
        ");
        $insertEmployment->execute([
            ':userid' => $profile_userid,
            ':position_id' => $newPosition['id'],
            ':date_of_assumption' => !empty($date_of_assumption) ? $date_of_assumption : null,
            ':date_appointment' => !empty($date_appointment) ? $date_appointment : null,
            ':sg' => $newPosition['salary_grade'],
            ':salary_id' => $salary_id,
            ':monthly_salary' => $monthly_salary
        ]);

        // ---- STEP 6: Add work_experience_mssd for new employment ----
        // Get the new active employment_details row
        $getNewEmpDetails = $pdo->prepare("SELECT * FROM employment_details WHERE userid = :userid AND edstatus = 1 ORDER BY id DESC LIMIT 1");
        $getNewEmpDetails->execute([':userid' => $profile_userid]);
        $newEmpDetails = $getNewEmpDetails->fetch(PDO::FETCH_ASSOC);

        if ($newEmpDetails) {
            // Get plantilla_position for new position
            $getNewPosDetails = $pdo->prepare("SELECT position_title, classification FROM plantilla_position WHERE id = :id LIMIT 1");
            $getNewPosDetails->execute([':id' => $newEmpDetails['position_id']]);
            $posDetails = $getNewPosDetails->fetch(PDO::FETCH_ASSOC);

            // Map status_appt
            $class_map = ['P' => 'PERMANENT', 'CT' => 'COTERMINOUS', 'CTI' => 'COTERMINOUS WITH THE INCUMBENT'];
            $status_appt = isset($class_map[$posDetails['classification']]) ? $class_map[$posDetails['classification']] : $posDetails['classification'];

            $insertWorkExp = $pdo->prepare("
                INSERT INTO work_experience_mssd
                    (userid, w_from_date, w_to_date, position_title, agency_name, monthly_salary, sg_step, status_appt, government_service, adjustment_type)
                VALUES
                    (:userid, :w_from_date, NULL, :position_title, :agency_name, :monthly_salary, :sg_step, :status_appt, :government_service, :adjustment_type)
            ");
            $insertWorkExp->execute([
                ':userid' => $profile_userid,
                ':w_from_date' => $newEmpDetails['date_of_assumption'],
                ':position_title' => $posDetails['position_title'],
                ':agency_name' => 'MINISTRY OF SOCIAL SERVICES AND DEVELOPMENT - BARMM',
                ':monthly_salary' => $newEmpDetails['monthly_salary'],
                ':sg_step' => $newEmpDetails['sg'] . '-' . $newEmpDetails['step'],
                ':status_appt' => $status_appt,
                ':government_service' => 'YES',
                ':adjustment_type' => 'PROMOTION',
            ]);
        } else {
            throw new Exception("Failed to fetch new employment details for work experience.");
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- HTML FORM (GET) ----

$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;

// Fetch plantilla positions where userid IS NULL and pstatus = 1
$plantilla_stmt = $pdo->prepare(
    "SELECT id, item_number, position_title FROM plantilla_position WHERE userid IS NULL AND pstatus = 1 ORDER BY item_number ASC"
);
$plantilla_stmt->execute();
$positions = $plantilla_stmt->fetchAll(PDO::FETCH_ASSOC);

// You may want to pre-fill this from employment_details if needed (not used now, just for hidden)
$employment_details_id = '';
$emp_stmt = $pdo->prepare("SELECT id FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$emp_stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$emp_stmt->execute();
if ($row = $emp_stmt->fetch(PDO::FETCH_ASSOC)) {
    $employment_details_id = $row['id'];
}
?>
<div id="hs-promote-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-promote-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="promote-employee-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-promote-modal-label" class="font-bold text-gray-800 dark:text-white">Promotion</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-promote-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <!-- Always pass profile_userid as hidden field -->
        <input type="hidden" name="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Plantilla Item Number dropdown -->
        <div class="py-4">
          <label for="plantilla-item-number" class="inline-block text-sm font-normal dark:text-white mb-1">Plantilla Item Number</label>
          <select id="plantilla-item-number" name="plantilla_item_number" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
            <option value="">Select Item Number</option>
            <?php foreach ($positions as $pos): ?>
              <option value="<?= htmlspecialchars($pos['item_number']) ?>"
                data-title="<?= htmlspecialchars($pos['position_title']) ?>">
                <?= htmlspecialchars($pos['item_number']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Position Title input (read-only) -->
        <div class="py-4">
          <label for="position-title" class="inline-block text-sm font-normal dark:text-white mb-1">Position Title</label>
          <input type="text" id="position-title" name="position_title" placeholder="Position Title" readonly class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" required>
        </div>
        <!-- Two-column date fields -->
        <div class="py-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <input type="hidden" id="employment_details_id" name="employment_details_id" value="<?= htmlspecialchars($employment_details_id) ?>">
              <label for="dateofassumption" class="inline-block text-sm font-normal dark:text-white">Date of Assumption</label>
              <input type="date" id="dateofassumption" name="date_of_assumption" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" value="" required>
            </div>
            <div>
              <label for="dateofappointment" class="inline-block text-sm font-normal dark:text-white">Date of Appointment</label>
              <input type="date" id="dateofappointment" name="date_appointment" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" value="" required>
            </div>
          </div>
        </div>
        <div id="promote-employee-error" class="text-red-600 text-sm py-2 hidden"></div>
        <div class="py-3"></div>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <!-- Cancel button -->
        <button type="button" id="cancel-promote-btn" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white" data-hs-overlay="#hs-promote-modal">
          Cancel
        </button>
        <button
          type="submit"
          id="update-promote-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700
                 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:border-gray-400 transition"
          disabled
        >
          Promote
        </button>
      </div>
    </form>
  </div>
</div>