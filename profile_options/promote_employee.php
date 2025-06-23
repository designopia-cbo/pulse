<?php
require_once(__DIR__ . '/../init.php'); // session, agent, DB

// Capture GET parameters for user context
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