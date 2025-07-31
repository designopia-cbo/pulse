<?php
require_once(__DIR__ . '/../init.php'); // session, agent, DB

$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;
?>
<div id="hs-apply-leave-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-apply-leave-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 h-[calc(100%-56px)] sm:mx-auto">
    <div class="max-h-full overflow-hidden flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-apply-leave-modal-label" class="font-bold text-gray-800 dark:text-white">Apply Leave</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-apply-leave-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
    <form id="apply-leave-form" class="flex-1 overflow-y-auto p-4 space-y-4" autocomplete="off">
      <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">

      <!-- Leave Type -->
      <div>
        <label for="leave_type" class="block text-sm font-normal dark:text-white mb-1">Leave Type</label>
        <select id="leave_type" name="leave_type" class="py-1.5 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
        <option value="" disabled selected>Select leave type</option>
        <option value="VACATION LEAVE">Vacation Leave</option>
        <option value="FORCE LEAVE">Force Leave</option>
        <option value="SICK LEAVE">Sick Leave</option>
        <option value="MATERNITY LEAVE">Maternity Leave</option>
        <option value="PATERNITY LEAVE">Paternity Leave</option>
        <option value="LEAVE WITHOUT PAY">Leave Without Pay</option>
        <option value="SPL FOR WOMEN">SPL For Women</option>
        <option value="STUDY LEAVE">Study Leave</option>
        <option value="REHABILITATION PRIVILEGE">Rehabilitation Privilege</option>
        <option value="CALAMITY LEAVE">Calamity Leave</option>
        <option value="ADOPTION LEAVE">Adoption Leave</option>
        <option value="SOLO PARENT LEAVE">Solo Parent Leave</option>
        <option value="SPECIAL PRIVILEGE LEAVE">Special Privilege Leave</option>
        <option value="10-DAY VAWC LEAVE">10-Day VAWC Leave</option>
        </select>
      </div>

      <!-- Leave Details -->
      <div>
        <label for="leave_details" class="block text-sm font-normal dark:text-white mb-1">Leave Details</label>
        <select id="leave_details" name="leave_details" class="py-1.5 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
        <option value="" disabled selected>Select leave details</option>
        </select>
      </div>

      <!-- Reason -->
      <div>
        <label for="reason" class="block text-sm font-normal dark:text-white mb-1">Reason</label>
        <textarea id="reason" name="reason" rows="4" placeholder="Enter the reason for your leave" class="py-1.5 px-3 block w-full border-gray-200 shadow-2xs rounded-lg text-sm dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"></textarea>
      </div>

      <!-- Start Date -->
      <div>
        <label for="start_date" class="block text-sm font-normal dark:text-white mb-1">Start Date</label>
        <input type="date" id="start_date" name="start_date" class="py-1.5 px-3 block w-full border-gray-200 shadow-2xs rounded-lg text-sm dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
      </div>

      <!-- End Date -->
      <div>
        <label for="end_date" class="block text-sm font-normal dark:text-white mb-1">End Date</label>
        <input type="date" id="end_date" name="end_date" class="py-1.5 px-3 block w-full border-gray-200 shadow-2xs rounded-lg text-sm dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
      </div>

      <!-- Half-day Checkbox -->
      <div class="flex items-center">
        <input type="checkbox" id="one_day_leave" name="one_day_leave" class="shrink-0 mt-0.5 border-gray-200 rounded-sm text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800">
        <label for="one_day_leave" class="text-sm text-gray-500 ms-3 dark:text-neutral-400">Half-day</label>
      </div>

      <!-- Total Days -->
      <div>
        <label for="total_days" class="block text-sm font-normal dark:text-white mb-1">Total Working Days Applied</label>
        <input type="text" id="total_days" name="total_days" readonly placeholder="Automatically calculated" class="py-1.5 px-3 block w-full border-gray-200 bg-gray-100 shadow-2xs text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
      </div>

      <div id="apply-leave-error" class="text-red-600 text-sm py-2 hidden"></div>
    </form>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-apply-leave-modal">
          Close
        </button>
        <button type="submit" form="apply-leave-form" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
          Submit Application
        </button>
      </div>
    </div>
  </div>
</div>