<?php
require_once(__DIR__ . '/../init.php');


// Get which tab and userid
$tab = isset($_GET['tab']) ? intval($_GET['tab']) : 0;
$profile_userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : $_SESSION['userid'];


// Output
ob_start();
switch ($tab) {

        case 2: // FAMILY BACKGROUND
        $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));

        // Get parents
        $stmt = $pdo->prepare("SELECT * FROM parents_name WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get spouse
        $stmt = $pdo->prepare("SELECT * FROM spouse_details WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $spouse = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get children
        $stmt = $pdo->prepare("SELECT * FROM children WHERE userid = ?");
        $stmt->execute([$profile_userid]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
          <div class="mb-8 flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">Family Background</h2>
              <p class="text-sm text-gray-600 dark:text-neutral-400">Manage family background information.</p>
            </div>
            <button id="edit-family-btn" type="button"
              class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
              aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
              <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
                width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
                <path d="M16 4l4 4"/>
                <path d="M4 20h4"/>
              </svg>
            </button>
          </div>
          <form id="family-form">
            <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
            <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
              <!-- Father's Name -->
              <div class="col-span-full">
                <label class="block text-sm font-normal text-gray-700 dark:text-neutral-300 mb-2">Father's Name</label>
                <input type="text" name="f_firstname" id="father-firstname"
                  value="<?= htmlspecialchars($parent['f_firstname'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter First Name" disabled>
                <input type="text" name="f_middlename" id="father-middlename"
                  value="<?= htmlspecialchars($parent['f_middlename'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Middle Name" disabled>
                <input type="text" name="f_surename" id="father-lastname"
                  value="<?= htmlspecialchars($parent['f_surename'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Last Name" disabled>
              </div>
              <div class="col-span-full">
                <hr class="my-2 border-t border-gray-200 dark:border-neutral-700">
              </div>
              <!-- Mother's Name -->
              <div class="col-span-full">
                <label class="block text-sm font-normal text-gray-700 dark:text-neutral-300 mb-2">Mother's Name</label>
                <input type="text" name="m_firstname" id="mother-firstname"
                  value="<?= htmlspecialchars($parent['m_firstname'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter First Name" disabled>
                <input type="text" name="m_middlename" id="mother-middlename"
                  value="<?= htmlspecialchars($parent['m_middlename'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Middle Name" disabled>
                <input type="text" name="m_surename" id="mother-lastname"
                  value="<?= htmlspecialchars($parent['m_surename'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Last Name" disabled>
              </div>
              <div class="col-span-full">
                <hr class="my-2 border-t border-gray-200 dark:border-neutral-700">
              </div>
              <!-- Spouse's Name -->
              <div class="col-span-full">
                <label class="block text-sm font-normal text-gray-700 dark:text-neutral-300 mb-2">Spouse's Name</label>
                <input type="text" name="s_firstname" id="spouse-firstname"
                  value="<?= htmlspecialchars($spouse['s_firstname'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter First Name" disabled>
                <input type="text" name="s_middlename" id="spouse-middlename"
                  value="<?= htmlspecialchars($spouse['s_middlename'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Middle Name" disabled>
                <input type="text" name="s_surname" id="spouse-lastname"
                  value="<?= htmlspecialchars($spouse['s_surname'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Last Name" disabled>
              </div>
              
              <!-- Spouse's Additional Details -->
              <div class="col-span-full">
                <label class="block text-sm font-normal text-gray-700 dark:text-neutral-300 mb-2">Spouse's Additional Information</label>
                <input type="text" name="occupation" id="spouse-occupation"
                  value="<?= htmlspecialchars($spouse['occupation'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Occupation" disabled>
                <input type="text" name="employer_or_business" id="spouse-employer"
                  value="<?= htmlspecialchars($spouse['employer_or_business'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Employer/Business Name" disabled>
                <input type="text" name="business_add" id="spouse-business-address"
                  value="<?= htmlspecialchars($spouse['business_add'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Business Address" disabled>
                <input type="text" name="s_telno" id="spouse-telephone"
                  value="<?= htmlspecialchars($spouse['s_telno'] ?? '') ?>"
                  class="mb-3 py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Telephone Number" disabled>
              </div>
              <div class="col-span-full">
                <hr class="my-2 border-t border-gray-200 dark:border-neutral-700">
              </div>
              <!-- Children -->
              <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-neutral-300 mb-1">Children</label>
              </div>
              <div class="col-span-full">
                <div id="children-container" class="space-y-3">
                  <?php foreach($children as $child): ?>
                    <div class="child-row grid sm:grid-cols-12 gap-2 items-center">
                      <div class="sm:col-span-6">
                        <input type="text" name="child_name[]" value="<?= htmlspecialchars($child['c_fullname']) ?>" placeholder="Child Full Name"
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
                      </div>
                      <div class="sm:col-span-5">
                        <input type="date" name="child_dob[]" value="<?= htmlspecialchars($child['c_bday']) ?>"
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
                      </div>
                      <div class="sm:col-span-1">
                        <button type="button" class="remove-child-btn flex justify-center items-center size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700" disabled style="display:none;">
                          <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                            <path d="M10 11v6"></path>
                            <path d="M14 11v6"></path>
                            <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                          </svg>
                        </button>
                      </div>
                      <div class="sm:col-span-12">
                        <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <!-- Add Child Button -->
                <p class="mt-3">
                  <button type="button" id="add-child-btn" style="display:none;"
                    class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-blue-500">
                    <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/>
                      <path d="M8 12h8"/>
                      <path d="M12 8v8"/>
                    </svg>
                    Add Child
                  </button>
                </p>
              </div>
              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>
            </div>
            <div class="mt-1 flex justify-end gap-x-2">
              <button type="button" id="cancel-family-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
                Cancel
              </button>
              <button type="submit" id="save-family-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                Save changes
              </button>
            </div>
          </form>
        </div>
        <?php
        break;

        case 3: // ELIGIBILITY
    // Fetch eligibility rows for the given user
    $stmt = $pdo->prepare("SELECT `id`, `eligibility_type`, `rating`, `date_exam`, `place_exam`, `license_number`, `date_validity`, `ra_status`, `ra_type` 
                           FROM `eligibility` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $eligibilityRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch unique ra_type values for dropdown options
    $stmt_ra_types = $pdo->prepare("SELECT DISTINCT `ra_type` FROM `eligibility` WHERE `ra_type` IS NOT NULL AND `ra_type` != '' ORDER BY `ra_type`");
    $stmt_ra_types->execute();
    $ra_type_options = $stmt_ra_types->fetchAll(PDO::FETCH_COLUMN);
    ?>
    <script>
      window.GLOBAL_RA_TYPE_OPTIONS = <?= json_encode($ra_type_options) ?>;
    </script>
    
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
          Eligibility
        </h2>
        <p class="text-sm text-gray-600 dark:text-neutral-400">
          Manage eligibility information.
        </p>
      </div>
      <button id="edit-eligibility-button" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
    </div>

      <form>
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
          <div class="col-span-full">
            <div id="eligibility-container" class="space-y-3">
              <?php if ($eligibilityRows && count($eligibilityRows) > 0): ?>
                <?php foreach ($eligibilityRows as $row_index => $row): ?>
                  <div class="eligibility-row">
                    <!-- Hidden ID field -->
                    <input type="hidden" name="eligibility_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                    
                    <!-- First row: 4 fields on large screens -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 mb-3">
                      <!-- Eligibility Type -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">Eligibility Type</label>
                        <input type="text" name="eligibility[]" placeholder="Eligibility Type" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['eligibility_type']) ?>"
                          disabled>
                      </div>

                      <!-- Rating -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">Rating</label>
                        <input type="text" name="rating[]" placeholder="Rating" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['rating']) ?>"
                          disabled>
                      </div>

                      <!-- Date of Examination -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">Date of Examination</label>
                        <input type="text" name="exam_date[]" placeholder="Date of Examination" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['date_exam']) ?>"
                          disabled>
                      </div>

                      <!-- Place of Examination -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">Place of Examination</label>
                        <input type="text" name="exam_place[]" placeholder="Place of Examination" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['place_exam']) ?>"
                          disabled>
                      </div>
                    </div>

                    <!-- Second row: 4 fields on large screens -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 mb-3">
                      <!-- License Number -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">License No.</label>
                        <input type="text" name="license_no[]" placeholder="License No." required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['license_number']) ?>"
                          disabled>
                      </div>

                      <!-- Date of Validity -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">Date of Validity</label>
                        <input type="date" name="date_validity[]" placeholder="Date of Validity" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          value="<?= htmlspecialchars($row['date_validity']) ?>"
                          disabled>
                      </div>

                      <!-- RA Status -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">RA Status</label>
                        <select name="ra_status[]" required
                          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                          focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                          dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                          dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                          disabled>
                          <option value="">RA Status</option>
                          <option value="YES" <?= (isset($row['ra_status']) && strtoupper($row['ra_status']) === 'YES') ? 'selected' : '' ?>>YES</option>
                          <option value="NO" <?= (isset($row['ra_status']) && strtoupper($row['ra_status']) === 'NO') ? 'selected' : '' ?>>NO</option>
                        </select>
                      </div>

                      <!-- RA Type -->
                      <div class="col-span-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1 sm:hidden">RA Type</label>
                        <?php 
                        // Check if ra_type value exists in the dropdown options
                        $ra_type_value = $row['ra_type'] ?? '';
                        $is_custom_ra_type = !empty($ra_type_value) && !in_array($ra_type_value, $ra_type_options);
                        ?>
                        
                        <?php if ($is_custom_ra_type): ?>
                          <!-- Show as input field if it's a custom value -->
                          <input type="text" name="ra_type[]" 
                            id="ra_type_<?= $row_index ?>"
                            value="<?= htmlspecialchars($ra_type_value) ?>"
                            placeholder="Enter RA Type" required
                            class="ra-type-input py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                            focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                            dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                            dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                            onblur="if (!this.value.trim()) revertRATypeToDropdown(this, 'ra_type_<?= $row_index ?>', <?= json_encode($ra_type_options) ?>)"
                            disabled>
                        <?php else: ?>
                          <!-- Show as dropdown -->
                          <select name="ra_type[]" 
                            id="ra_type_<?= $row_index ?>"
                            class="ra-type-dropdown py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs text-sm rounded-lg
                            focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none
                            dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400
                            dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                            onchange="convertRATypeField('ra_type_<?= $row_index ?>', <?= json_encode($ra_type_options) ?>)"
                            disabled required>
                            <option value="" disabled <?= empty($row['ra_type']) ? 'selected' : '' ?>>Select RA Type</option>
                            <?php foreach ($ra_type_options as $option): ?>
                              <option value="<?= htmlspecialchars($option) ?>" <?= (isset($row['ra_type']) && $row['ra_type'] === $option) ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                            <?php endforeach; ?>
                            <option value="other">OTHERS</option>
                          </select>
                        <?php endif; ?>
                      </div>
                    </div>

                    <!-- Remove button - below the fields -->
                    <div class="flex justify-end mb-4">
                      <button type="button" class="remove-eligibility-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
                        border border-gray-200 bg-white text-gray-800 hover:bg-gray-100
                        focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700"
                        style="display:none" disabled>
                        <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                          viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                          stroke-linecap="round" stroke-linejoin="round">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                          <path d="M10 11v6"></path>
                          <path d="M14 11v6"></path>
                          <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                        </svg>
                      </button>
                    </div>

                    <!-- Separator -->
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Add Eligibility Button -->
            <p class="mt-3">
              <button type="button" id="add-eligibility-btn"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500"
                style="display:none" disabled>
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Eligibility
              </button>
            </p>
          </div>

          <!-- Separator Line -->
          <div class="col-span-full">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-eligibility-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
            style="display:none">
            Cancel
          </button>
          <button type="button" id="save-eligibility-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
            style="display:none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    
    <?php
    break;

    
    case 4: // WORK EXPERIENCE & VOLUNTARY WORK

    // --- Fetch Work Experience rows for the given user
    $stmtW = $pdo->prepare("SELECT `id`, `userid`, `w_from_date`, `w_to_date`, `position_title`, `agency_name`, `monthly_salary`, `sg_step`, `status_appt`, `government_service`, `adjustment_type` FROM `work_experience` WHERE `userid` = ?");
    $stmtW->execute([$profile_userid]);
    $workRows = $stmtW->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch Voluntary Works rows for the given user
    $stmtV = $pdo->prepare("SELECT `id`, `userid`, `name_org_address`, `v_from_date`, `v_to_date`, `number_hours`, `position_nature_work` FROM `voluntary_works` WHERE `userid` = ?");
    $stmtV->execute([$profile_userid]);
    $volRows = $stmtV->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Combined Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <form>
        <!-- Work Experience Section -->
        <div class="mb-8 flex justify-between items-center">
          <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200 inline-flex items-center space-x-2">
              <span>Work Experience</span>
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              Manage work experience outside of MSSD BARMM.
            </p>
          </div>
          <button id="edit-work-experience-btn" type="button"
            class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
            <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
              width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
              <path d="M16 4l4 4"/>
              <path d="M4 20h4"/>
            </svg>
          </button>
        </div>

        <div class="w-full">
          <div id="work-experience-container" class="flex flex-col gap-y-3 w-full">
            <?php if ($workRows && count($workRows) > 0): ?>
              <?php foreach ($workRows as $row): ?>
                <div class="work-experience-row w-full">
                  <input type="hidden" name="work_experience_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                  <div class="grid grid-cols-1 sm:grid-cols-9 gap-2 items-center w-full">
                    <input type="date" name="work_from[]" placeholder="From"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['w_from_date']) ?>" disabled>
                    <input type="date" name="work_to[]" placeholder="To"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['w_to_date']) ?>" disabled>
                    <input type="text" name="position_title[]" placeholder="Position Title"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['position_title']) ?>" disabled>
                    <input type="text" name="department_agency[]" placeholder="Department / Agency"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['agency_name']) ?>" disabled>
                    <input type="text" name="monthly_salary[]" placeholder="Monthly Salary"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['monthly_salary']) ?>" disabled>
                    <input type="text" name="salary_grade_step[]" placeholder="SG (Format '00-0')"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['sg_step']) ?>" disabled>
                    <input type="text" name="status_of_appointment[]" placeholder="Status of Appointment"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['status_appt']) ?>" disabled>
                    <select name="govt_service[]"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                      <option value="" <?= $row['government_service']=="" ? "selected" : "" ?> hidden>Gov't Serv.</option>
                      <option value="YES" <?= $row['government_service']=="YES" ? "selected" : "" ?>>YES</option>
                      <option value="NO" <?= $row['government_service']=="NO" ? "selected" : "" ?>>NO</option>
                    </select>
                    <button type="button" class="remove-work-experience-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      tabindex="-1" style="display:none" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div>
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <!-- Add Work Experience Button -->
          <p class="mt-3">
            <button type="button" id="add-work-experience-btn" style="display:none"
              class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
              focus:outline-hidden focus:underline font-medium dark:text-blue-500">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 12h8"/>
                <path d="M12 8v8"/>
              </svg>
              Add Work Experience
            </button>
          </p>
          <!-- Save/Cancel for Work Experience -->
          <div class="mt-3 flex justify-end gap-x-2">
            <button type="button" id="cancel-work-experience-btn"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
              style="display:none">
              Cancel
            </button>
            <button type="button" id="save-work-experience-btn"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
              style="display:none">
              Save changes
            </button>
          </div>
        </div>
        <div class="sm:col-span-12">
          <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
        </div>

        <!-- Voluntary Works Section -->
        <div class="mb-8 flex justify-between items-center">
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200 inline-flex items-center space-x-2">
              <span>Voluntary Works</span>
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              Manage voluntary works.
            </p>
          </div>
          <button id="edit-voluntary-works-btn" type="button"
            class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
            aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
            <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
              <path d="M16 4l4 4"/>
              <path d="M4 20h4"/>
            </svg>
          </button>
        </div>

        <div class="w-full">
          <div id="voluntary-works-container" class="flex flex-col gap-y-3 w-full">
            <?php if ($volRows && count($volRows) > 0): ?>
              <?php foreach ($volRows as $row): ?>
                <div class="voluntary-work-row w-full">
                  <input type="hidden" name="voluntary_work_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                  <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center w-full">
                    <input type="text" name="vol_org_name_address[]" placeholder="Name & Address of Organization"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['name_org_address']) ?>" disabled>
                    <input type="date" name="vol_from[]" placeholder="From"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['v_from_date']) ?>" disabled>
                    <input type="date" name="vol_to[]" placeholder="To"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['v_to_date']) ?>" disabled>
                    <input type="number" name="vol_number_of_hours[]" placeholder="Number of Hours" min="0"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none  shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['number_hours']) ?>" disabled>
                    <input type="text" name="vol_position_nature_of_work[]" placeholder="Position / Nature of Work"
                      class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      value="<?= htmlspecialchars($row['position_nature_work']) ?>" disabled>
                    <button type="button" class="remove-voluntary-work-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      tabindex="-1" style="display:none" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div>
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <!-- Add Voluntary Work Button -->
          <p class="mt-3">
            <button type="button" id="add-voluntary-work-btn" style="display:none"
              class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
              focus:outline-hidden focus:underline font-medium dark:text-blue-500">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 12h8"/>
                <path d="M12 8v8"/>
              </svg>
              Add Voluntary Work
            </button>
          </p>
          <!-- Save/Cancel for Voluntary Works -->
          <div class="mt-3 flex justify-end gap-x-2">
            <button type="button" id="cancel-voluntary-works-btn"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
              style="display:none">
              Cancel
            </button>
            <button type="button" id="save-voluntary-works-btn"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
              style="display:none">
              Save changes
            </button>
          </div>
        </div>
        <div class="sm:col-span-12">
          <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
        </div>
      </form>
    </div>
    <!-- End Card -->
    <script src="editprofile_tab4.js"></script>
    <?php
    break;

    case 5: // LEARNING & DEVELOPMENT
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `title_learning`, `l_from_date`, `l_to_date`, `l_hours`, `type_LD`, `sponsor` FROM `learning_development` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $ldRows = $stmt->fetchAll(PDO::FETCH_ASSOC);    
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Learning and Development
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage learning and development trainings, seminars, or workshops attended.
          </p>
        </div>
        <button type="button" id="edit-button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="ld-container" class="space-y-3">
              <?php foreach ($ldRows as $row): ?>
                <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center ld-row">
                  <input type="hidden" name="ld_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                  <input type="text" name="ld_title[]" placeholder="Title"
                    value="<?= htmlspecialchars($row['title_learning']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="date" name="ld_from[]" placeholder="From"
                    value="<?= htmlspecialchars($row['l_from_date']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="date" name="ld_to[]" placeholder="To"
                    value="<?= htmlspecialchars($row['l_to_date']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="number" name="ld_number_of_hours[]" placeholder="Number of Hours" min="0"
                    value="<?= htmlspecialchars($row['l_hours']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="text" name="ld_type[]" placeholder="Type of LD"
                    value="<?= htmlspecialchars($row['type_LD']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="text" name="ld_conducted_by[]" placeholder="Conducted/Sponsored By"
                      value="<?= htmlspecialchars($row['sponsor']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button" class="remove-ld-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1" style="display:none" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="sm:col-span-6 col-span-full">
                    <hr class="my-4 border-t border-gray-200">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add LD Button -->
            <p class="mt-3">
              <button type="button" id="add-ld-btn"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500" style="display:none">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Learning & Development
              </button>
            </p>
          </div>
          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-ld-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" style="display:none">
            Cancel
          </button>
          <button type="submit" id="save-ld-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" style="display:none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    <?php
    break;

    case 6: // SPECIAL SKILLS
    // Get the user id from session, GET, or wherever you store it
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));
    // Fetch special skills for this user
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `specific_skills` FROM `special_skills` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $skillsRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Skills
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage skills information.
          </p>
        </div>
        <!-- Edit Button (visible by default) -->
        <button id="edit-skill-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Edit">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="skills-container" class="space-y-3">
              <?php foreach ($skillsRows as $row): ?>
                <div class="grid grid-cols-1 gap-2 items-center skill-row">
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="hidden" name="skill_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                    <input type="text" name="skills[]" placeholder="Skill"
                      value="<?= htmlspecialchars($row['specific_skills']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button"
                      class="remove-skill-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      style="display:none" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="col-span-full">
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add Skill Button (hidden by default) -->
            <p class="mt-3">
              <button type="button" id="add-skill-btn"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500"
                style="display:none;">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Skill
              </button>
            </p>
          </div>
          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-skill-btn"
            class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
            style="display:none;">
            Cancel
          </button>
          <button type="submit" id="save-skill-btn"
            class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
            style="display:none;">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    <?php
    break;

    case 7: // NON-ACADEMIC DISTINCTIONS

    // Get the user id from session, GET, or wherever you store it
    $profile_userid = isset($profile_userid) ? $profile_userid : (
        isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (
            isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0
        )
    );

    // Fetch distinctions for this user
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `n_nacademic_title` FROM `non_academic_distinctions` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $distinctionRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Non-Academic Distinctions / Recognition
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            List your non-academic distinctions or recognition received.
          </p>
        </div>
        <!-- Edit Button (visible by default) -->
        <button id="edit-distinction-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Edit">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="distinction-container" class="space-y-3">
              <?php foreach ($distinctionRows as $row): ?>
                <div class="grid grid-cols-1 gap-2 items-center distinction-row">
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="hidden" name="distinction_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                    <input type="text" name="distinctions[]" placeholder="Title"
                      value="<?= htmlspecialchars($row['n_nacademic_title']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button"
                      class="remove-distinction-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      style="display:none;" disabled tabindex="-1">
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="col-span-full">
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add Title Button (hidden by default) -->
            <p class="mt-3">
              <button type="button" id="add-distinction-btn"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500"
                style="display:none;">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Title
              </button>
            </p>
          </div>
          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-distinction-btn"
            class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
            style="display:none;">
            Cancel
          </button>
          <button type="submit" id="save-distinction-btn"
            class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none"
            style="display:none;">
            Save changes
          </button>
        </div>
      </form>
    </div>
       <!-- End Card -->
    <?php
    break;

    case 8: // MEMBERSHIP
    // Get the user id from session, GET, or wherever you store it
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));

    // Fetch memberships for this user
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `association` FROM `membership` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Membership in Association/Organization
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage information on memberships in associations or organizations.
          </p>
        </div>
        <button id="edit-membership-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="membership-container" class="space-y-3">
              <?php foreach ($memberships as $row): ?>
                <div class="grid grid-cols-1 gap-2 items-center membership-row">
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="hidden" name="membership_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                    <input type="text" name="organization_names[]" placeholder="Organization Name"
                      value="<?= htmlspecialchars($row['association']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button"
                      class="remove-membership-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
                      border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      style="display:none;" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="col-span-full">
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add Organization Button -->
            <p class="mt-3">
              <button type="button" id="add-membership-btn" style="display:none;"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Organization
              </button>
            </p>
          </div>          

          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

            <div class="mt-1 flex justify-end gap-x-2">
              <button type="button" id="cancel-membership-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
                Cancel
              </button>
              <button type="submit" id="save-membership-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
                Save changes
              </button>
            </div>
          </form>
        </div>
        <!-- End Card --> 
    <?php
    break;

    case 9: // PERSONAL DISCLOSURE
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));

    // Fetch the personal disclosure data
    $stmt = $pdo->prepare("SELECT * FROM personal_disclosure WHERE userid = ?");
    $stmt->execute([$profile_userid]);
    $disclosure = $stmt->fetch(PDO::FETCH_ASSOC);

    function sel($a, $b) { return (strtoupper($a) === strtoupper($b)) ? 'selected' : ''; }
    function val($arr, $key) { return htmlspecialchars($arr[$key] ?? ''); }

    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Personal Disclosure
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage personal disclosure information.
          </p>
        </div>
        <button id="edit-disclosure-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form id="personal-disclosure-form">
        <input type="hidden" id="profile_userid" name="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="membership-container" class="space-y-3"></div>
            <p class="mt-3">

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 1 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #1</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be apppointed,
                </label>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  a. within the third degree?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q1" name="q1" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q1'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q1'] ?? '', 'NO') ?>>NO</option>
                  </select>
                </div>
              </div>

              <!-- Question 1.2 -->
              <div class="space-y-2">
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  b. within the fourth degree (for Local Government Unit - Career Employees)?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q2" name="q2" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q2'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q2'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r2" name="r2" type="text" disabled  value="<?= val($disclosure, 'r2') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 2 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #2</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  a. Have you ever been found guilty of any administrative offense?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q3" name="q3" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q3'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q3'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r3" name="r3" type="text" disabled  value="<?= val($disclosure, 'r3') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>

                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  b. Have you been criminally charged before any court?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q4" name="q4" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q4'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q4'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r4_1" name="r4_1" type="date" disabled  value="<?= val($disclosure, 'r4_1') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="Date Filed:">
                  <input id="r4_2" name="r4_2" type="text" disabled  value="<?= val($disclosure, 'r4_2') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="Status:">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 3 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #3</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q5" name="q5" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q5'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q5'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r5" name="r5" type="text" disabled  value="<?= val($disclosure, 'r5') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 4 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #4</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q6" name="q6" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q6'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q6'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r6" name="r6" type="text" disabled  value="<?= val($disclosure, 'r6') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 5 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #5</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  a. Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q7" name="q7" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q7'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q7'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r7" name="r7" type="text" disabled  value="<?= val($disclosure, 'r7') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  b. Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q8" name="q8" disabled
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q8'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q8'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r8" name="r8" type="text" disabled value="<?= val($disclosure, 'r8') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 6 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #6</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Have you acquired the status of an immigrant or permanent resident of another country?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q9" name="q9" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q9'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q9'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r9" name="r9" type="text" disabled  value="<?= val($disclosure, 'r9') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 7 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #7</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Pursuant to: (a) Indigenous People's Act (RA 8371); (b) Magna Carta for Disabled Persons (RA 7277); and (c) Solo Parents Welfare Act of 2000 (RA 8972), please answer the following items:
                </label>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  a. Are you a member of any indigenous group?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q10" name="q10" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q10'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q10'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r10" name="r10" type="text" disabled  value="<?= val($disclosure, 'r10') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <!-- Question 7.1 -->
              <div class="space-y-2">
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  b. Are you a person with disability?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q11" name="q11" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q11'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q11'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r11" name="r11" type="text" disabled value="<?= val($disclosure, 'r11') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <!-- Question 7.2 -->
              <div class="space-y-2">
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  c. Are you a solo parent?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q12" name="q12" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q12'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q12'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r12" name="r12" type="text" disabled  value="<?= val($disclosure, 'r12') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 8 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #8</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Are you the son or daughter of a Mujahideen/Mujahidat?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q13" name="q13" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q13'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q13'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r13" name="r13" type="text" disabled  value="<?= val($disclosure, 'r13') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

              <div class="col-span-full">
                <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
              </div>

              <!-- Question 9 -->
              <div class="space-y-2">
                <p class="text-sm text-gray-600 dark:text-neutral-400">Question #9</p>
                <label class="inline-block text-sm font-normal text-gray-800 mt-2.5 dark:text-neutral-200">
                  Are you a Mujahideen/Mujahidat?
                </label>
                <div class="flex flex-col sm:flex-row gap-2">
                  <select id="q14" name="q14" disabled 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                    <option value="YES" <?= sel($disclosure['q14'] ?? '', 'YES') ?>>YES</option>
                    <option value="NO" <?= sel($disclosure['q14'] ?? '', 'NO') ?>>NO</option>
                  </select>
                  <input id="r14" name="r14" type="text" disabled value="<?= val($disclosure, 'r14') ?>" 
                    class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                    placeholder="More Details">
                </div>
              </div>

            </p>
          </div>
          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-disclosure-btn" style="display: none" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Cancel
          </button>
          <button type="submit" id="save-disclosure-btn" style="display: none" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    <?php
    break;

    case 10: // REFERENCES
    // Get the user id from session, GET, or wherever you store it
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));

    // Fetch references for this user
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `r_fullname`, `r_address`, `r_contactno` FROM `references_name` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            References
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage references.
          </p>
        </div>
        <button id="edit-references-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="references-container" class="space-y-3">
              <?php foreach ($references as $row): ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center reference-row">
                  <input type="hidden" name="ref_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                  <input type="text" name="ref_name[]" placeholder="Name"
                    value="<?= htmlspecialchars($row['r_fullname']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="text" name="ref_address[]" placeholder="Address"
                    value="<?= htmlspecialchars($row['r_address']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="text" name="ref_tel_no[]" placeholder="Tel. No."
                      value="<?= htmlspecialchars($row['r_contactno']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button"
                      class="remove-reference-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
                      border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      style="display:none;" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="sm:col-span-3 col-span-full">
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add Reference Button -->
            <p class="mt-3">
              <button type="button" id="add-reference-btn" style="display:none;"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Reference
              </button>
            </p>
          </div>

          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-references-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Cancel
          </button>
          <button type="submit" id="save-references-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    <?php
    break;

    case 11: // EMERGENCY CONTACT
    // Get the user id from session, GET, or wherever you store it
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));

    // Fetch emergency contacts for this user
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `e_fullname`, `e_contact_number`, `e_relationship` FROM `emergency_contact` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!-- Card -->
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
            Emergency Contact
          </h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">
            Manage your emergency contact(s).
          </p>
        </div>
        <button id="edit-emergency-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Grid -->
        <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
          <div class="col-span-full">
            <div id="emergency-container" class="space-y-3">
              <?php foreach ($contacts as $row): ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center emergency-row">
                  <input type="hidden" name="e_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                  <input type="text" name="e_fullname[]" placeholder="Full Name"
                    value="<?= htmlspecialchars($row['e_fullname']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <input type="text" name="e_contact_number[]" placeholder="Contact Number"
                    value="<?= htmlspecialchars($row['e_contact_number']) ?>"
                    class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                    disabled>
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="text" name="e_relationship[]" placeholder="Relationship"
                      value="<?= htmlspecialchars($row['e_relationship']) ?>"
                      class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg"
                      disabled>
                    <button type="button"
                      class="remove-emergency-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
                      border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1"
                      style="display:none;" disabled>
                      <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                  <div class="sm:col-span-3 col-span-full">
                    <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <!-- Add Emergency Contact Button -->
            <p class="mt-3">
              <button type="button" id="add-emergency-btn" style="display:none;"
                class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline
                focus:outline-hidden focus:underline font-medium dark:text-blue-500">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 12h8"/>
                  <path d="M12 8v8"/>
                </svg>
                Add Emergency Contact
              </button>
            </p>
          </div>

          <div class="sm:col-span-12">
            <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
          </div>
        </div>
        <!-- End Grid -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-emergency-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Cancel
          </button>
          <button type="submit" id="save-emergency-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <!-- End Card -->
    <?php
    break;

   case 12: // EDUCATIONAL BACKGROUND
    $profile_userid = isset($profile_userid) ? $profile_userid : (isset($_GET['profile_userid']) ? $_GET['profile_userid'] : (isset($_SESSION['profile_userid']) ? $_SESSION['profile_userid'] : 0));
    $stmt = $pdo->prepare("SELECT `id`, `userid`, `schoolname`, `basic_degree_course`, `from_date`, `to_date`, `units_earned`, `year_grad`, `honor`, `level` FROM `educational_background` WHERE `userid` = ?");
    $stmt->execute([$profile_userid]);
    $educs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">Educational Background</h2>
          <p class="text-sm text-gray-600 dark:text-neutral-400">Manage your educational background.</p>
        </div>
        <button id="edit-educ-btn" type="button"
          class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
          aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
          <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
            <path d="M16 4l4 4"/>
            <path d="M4 20h4"/>
          </svg>
        </button>
      </div>
      <form>
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <div class="col-span-full">
          <div id="educ-container" class="space-y-6">
            <?php foreach ($educs as $row): 
              $isOther = !in_array($row['basic_degree_course'], [
                "ELEMENTARY GRADUATE","ELEMENTARY UNDERGRADUATE",
                "JUNIOR HIGHSCHOOL GRADUATE", "JUNIOR HIGHSCHOOL UNDERGRADUATE",
                "SENIOR HIGHSCHOOL GRADUATE", "SENIOR HIGHSCHOOL UNDERGRADUATE",
                "SECONDARY GRADUATE","SECONDARY UNDERGRADUATE","OTHERS"
              ]);
            ?>
              <div class="educ-row space-y-2">
                <input type="hidden" name="educ_id[]" value="<?= htmlspecialchars($row['id']) ?>">
                <input type="text" name="schoolname[]" placeholder="Name of School"
                  value="<?= htmlspecialchars($row['schoolname']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <select name="basic_degree_course[]" class="educ-basic-degree-course w-full py-1.5 px-3 block border-gray-200 sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600 disabled:opacity-50 disabled:pointer-events-none" <?= $isOther ? 'style="display:none;"' : '' ?> disabled>
                  <option value="">- Select -</option>
                  <option value="ELEMENTARY GRADUATE" <?= $row['basic_degree_course']=="ELEMENTARY GRADUATE"?'selected':''; ?>>ELEMENTARY GRADUATE</option>
                  <option value="ELEMENTARY UNDERGRADUATE" <?= $row['basic_degree_course']=="ELEMENTARY UNDERGRADUATE"?'selected':''; ?>>ELEMENTARY UNDERGRADUATE</option>
                  <option value="JUNIOR HIGHSCHOOL GRADUATE" <?= $row['basic_degree_course']=="JUNIOR HIGHSCHOOL GRADUATE"?'selected':''; ?>>JUNIOR HIGHSCHOOL GRADUATE</option>
                  <option value="JUNIOR HIGHSCHOOL UNDERGRADUATE" <?= $row['basic_degree_course']=="JUNIOR HIGHSCHOOL UNDERGRADUATE"?'selected':''; ?>>JUNIOR HIGHSCHOOL UNDERGRADUATE</option>
                  <option value="SENIOR HIGHSCHOOL GRADUATE" <?= $row['basic_degree_course']=="SENIOR HIGHSCHOOL GRADUATE"?'selected':''; ?>>SENIOR HIGHSCHOOL GRADUATE</option>
                  <option value="SENIOR HIGHSCHOOL UNDERGRADUATE" <?= $row['basic_degree_course']=="SENIOR HIGHSCHOOL UNDERGRADUATE"?'selected':''; ?>>SENIOR HIGHSCHOOL UNDERGRADUATE</option>
                  <option value="SECONDARY GRADUATE" <?= $row['basic_degree_course']=="SECONDARY GRADUATE"?'selected':''; ?>>SECONDARY GRADUATE</option>
                  <option value="SECONDARY UNDERGRADUATE" <?= $row['basic_degree_course']=="SECONDARY UNDERGRADUATE"?'selected':''; ?>>SECONDARY UNDERGRADUATE</option>
                  <option value="OTHERS" <?= $row['basic_degree_course']=="OTHERS"?'selected':''; ?>>OTHERS</option>
                </select>
                <input type="text" name="basic_degree_course_other[]" placeholder="Other Course"
                  value="<?= $isOther ? htmlspecialchars($row['basic_degree_course']) : '' ?>"
                  class="educ-basic-degree-other w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  style="<?= $isOther ? '' : 'display:none;' ?>" disabled>
                <input type="text" name="from_date[]" placeholder="From"
                  value="<?= htmlspecialchars($row['from_date']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <input type="text" name="to_date[]" placeholder="To"
                  value="<?= htmlspecialchars($row['to_date']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <input type="text" name="units_earned[]" placeholder="Units Earned"
                  value="<?= htmlspecialchars($row['units_earned']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <input type="text" name="year_grad[]" placeholder="Year Graduated"
                  value="<?= htmlspecialchars($row['year_grad']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <input type="text" name="honor[]" placeholder="Honors Received"
                  value="<?= htmlspecialchars($row['honor']) ?>"
                  class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  disabled>
                <button type="button"
                  class="remove-educ-btn flex justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700"
                  style="display:none;" disabled>
                  <svg class="shrink-0 size-3" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                    <path d="M17 6V4a2 2 0 0 0-2-2h-6a2 2 0 0 0-2 2v2"></path>
                  </svg>
                </button>
                <hr class="my-2 border-t border-gray-200 dark:border-neutral-700">
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mt-3">
            <button type="button" id="add-educ-btn" style="display:none;"
              class="inline-flex items-center gap-x-1 text-sm text-blue-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-blue-500">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 12h8"/>
                <path d="M12 8v8"/>
              </svg>
              Add Educational Background
            </button>
          </p>
        </div>
        <div class="sm:col-span-12">
          <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
        </div>
        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" id="cancel-educ-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Cancel
          </button>
          <button type="submit" id="save-educ-btn" style="display:none;" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Save changes
          </button>
        </div>
      </form>
    </div>
    <?php
    break;

    default:
        echo "Invalid tab.";
}
$output = ob_get_clean();
echo $output;
?>