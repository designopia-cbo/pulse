<?php
require_once(__DIR__ . '/../init.php'); // session, agent, DB

// (You can add POST logic for actual promotion here in the future)

// Capture GET parameters for user context (optional, for future use)
$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;
?>
<!-- Modal HTML (Updated with corrected field IDs and labels) -->
<div id="hs-promote-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-promote-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="effectivity-dates-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
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
          <select id="plantilla-item-number" name="plantilla_item_number" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
            <option value="">Select Item Number</option>
            <option value="regular">Regular</option>
            <option value="probationary">Probationary</option>
            <option value="contractual">Contractual</option>
            <option value="casual">Casual</option>
          </select>
        </div>

        <!-- Position Title input (read-only) -->
        <div class="py-4">
          <label for="position-title" class="inline-block text-sm font-normal dark:text-white mb-1">Position Title</label>
          <input type="text" id="position-title" name="position_title" placeholder="Position Title" readonly class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
        </div>

        <!-- Two-column date fields -->
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
        <button type="button" id="edit-effectivity-btn" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
          Edit
        </button>
        <!-- Cancel button (hidden initially) -->
        <button type="button" id="cancel-effectivity-btn" style="display:none" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white" data-hs-overlay="#hs-promote-modal">
          Cancel
        </button>
        <!-- Update Details button (hidden initially) -->
        <button type="submit" id="update-effectivity-btn" style="display:none" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700">
          Update Details
        </button>
      </div>
    </form>
  </div>
</div>