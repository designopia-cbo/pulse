<?php
require_once('init.php');

// Get which tab and userid
$tab = isset($_GET['tab']) ? intval($_GET['tab']) : 0;
$profile_userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : $_SESSION['userid'];

// ==============================
// HELPER FUNCTIONS
// ==============================
function proper_case($str) {
    return ucwords(strtolower(trim($str)));
}
function format_date($date) {
    if (empty($date) || $date === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('F d, Y') : '';
}
function compute_age($bday) {
    if (empty($bday) || $bday === '0000-00-00') return '';
    $birth = new DateTime($bday);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    return $age;
}
function format_salary($salary) {
    $clean = preg_replace('/[^\d.]/', '', $salary);
    $clean = floatval($clean);
    $clean = round($clean);
    return number_format($clean, 0, '.', ',');
}

// Output
ob_start();
switch ($tab) {
    case 2: // FAMILY BACKGROUND
        // Parents
        $stmt = $pdo->prepare("SELECT f_surename, f_firstname, f_middlename, m_surename, m_firstname, m_middlename FROM parents_name WHERE userid = :userid LIMIT 1");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $parents = $stmt->fetch(PDO::FETCH_ASSOC);

        $fathersfirstname = $fathersmiddlename = $fatherslastname = "";
        $mothersfirstname = $mothersmiddlename = $motherslastname = "";
        if ($parents) {
            $fathersfirstname   = strtoupper($parents['f_firstname']);
            $fathersmiddlename  = strtoupper($parents['f_middlename']);
            $fatherslastname    = strtoupper($parents['f_surename']);
            $mothersfirstname   = strtoupper($parents['m_firstname']);
            $mothersmiddlename  = strtoupper($parents['m_middlename']);
            $motherslastname    = strtoupper($parents['m_surename']);
        }
        
        // Spouse
        $stmt = $pdo->prepare("SELECT s_surname, s_firstname, s_middlename, occupation, employer_or_business, business_add, s_telno FROM spouse_details WHERE userid = :userid LIMIT 1");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $spouse = $stmt->fetch(PDO::FETCH_ASSOC);

        $spousefirstname = $spousemiddlename = $spouselastname = $spouseoccupation = $spouseemployer = $spousebusinessadd = $spousetelno = "";
        if ($spouse) {
            $spousefirstname     = strtoupper($spouse['s_firstname']);
            $spousemiddlename    = strtoupper($spouse['s_middlename']);
            $spouselastname      = strtoupper($spouse['s_surname']);
            $spouseoccupation    = strtoupper($spouse['occupation']);
            $spouseemployer      = strtoupper($spouse['employer_or_business']);
            $spousebusinessadd   = strtoupper($spouse['business_add']);
            $spousetelno         = strtoupper($spouse['s_telno']);
        }
        // Children
        $stmt = $pdo->prepare("SELECT c_fullname, c_bday FROM children WHERE userid = :userid ORDER BY c_bday ASC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output HTML (customize as needed)
        ?>
        <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
        <!-- Grid -->
        <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Family Background</h2>
          </div>
          <!-- Col -->
          <!-- Col -->
        </div>
        <!-- End Grid -->

        <!-- Grid -->
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500 font-semibold">
                  Father's Name
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">

                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  First Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($fathersfirstname) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Middle Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($fathersmiddlename) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Last Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($fatherslastname) ?>
                </dd>
              </dl>

            </div>
          </div>
          <!-- Col -->

          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500 font-semibold">
                  Mother's Name
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">

                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  First Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($mothersfirstname) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Middle Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($mothersmiddlename) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Last Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($motherslastname) ?>
                </dd>
              </dl>

            </div>
          </div>
          <!-- Col -->
        </div>
        <!-- End Grid -->

      </div>
      <!-- End Invoice -->

      <div class="py-1 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Spouse Information</div>      <hr class="border-8 border-white">

      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10"> 

        <!-- Grid -->
        <div class="grid md:grid-cols-2 gap-3">
          <!-- Left: Spouse Name -->
          <div>
            <div class="grid space-y-3">              

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  First Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spousefirstname) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Middle Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spousemiddlename) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Last Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spouselastname) ?>
                </dd>
              </dl>
            </div>
          </div>
          <!-- Col (left) -->

          <!-- Right: Other Spouse Info -->
          <div>
            <div class="grid space-y-3">
              

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Occupation:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spouseoccupation) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Employer/Bussiness:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spouseemployer) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Business Address:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spousebusinessadd) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Tel Number:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($spousetelno) ?>
                </dd>
              </dl>
            </div>
          </div>
          <!-- Col (right) -->
        </div>
        <!-- End Grid -->
      </div>
      <!-- End Invoice -->

      <div class="py-1 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Children</div>

      <hr class="border-8 border-white">

      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">       

        <!-- Grid -->
        <div class="grid md:grid-cols-1 gap-3">
         <!-- Table Section -->
         <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
          <!-- Table -->
          <div class="-m-1.5 overflow-x-auto">
            <div class="p-1.5 min-w-full inline-block align-middle">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                <thead class="bg-gray-50 dark:bg-neutral-800">
                  <tr>
                    <th scope="col" class="px-6 py-3 text-start">
                      <div class="flex items-center gap-x-2">
                        <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Name</span>
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-3 text-start">
                      <div class="flex items-center gap-x-2">
                        <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Date of Birth</span>
                      </div>
                    </th>
                    <th scope="col" class="px-6 py-3 text-start">
                      <div class="flex items-center gap-x-2">
                        <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Age</span>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                  <?php if (!empty($children)): ?>
                    <?php foreach ($children as $child): ?>
                      <tr>
                        <td class="size-px whitespace-nowrap">
                          <div class="px-6 py-3">
                            <div class="flex items-center gap-x-3">
                              <span class="block text-sm font-normal text-gray-800 dark:text-neutral-200">
                                <?= htmlspecialchars(strtoupper($child['c_fullname'])) ?>
                              </span>
                            </div>
                          </div>
                        </td>
                        <td class="size-px whitespace-nowrap">
                          <div class="px-6 py-3">
                            <span class="text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(htmlspecialchars(date("M d, Y", strtotime($child['c_bday'])))) ?>
                            </span>
                          </div>
                        </td>
                        <td class="size-px whitespace-nowrap">
                          <div class="px-6 py-3">
                            <span class="text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(compute_age($child['c_bday'])) ?>
                            </span>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="3" class="text-center py-4 text-gray-400 dark:text-neutral-500">No children records found.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <!-- End Table -->
      </div>
      <!-- End Table Section -->
    </div>
    <!-- End Grid -->

  </div>
  <!-- End Invoice -->   
        <?php
        break;
    case 3: // ELIGIBILITY
        $stmt = $pdo->prepare("SELECT eligibility_type, rating, date_exam, place_exam, license_number, date_validity FROM eligibility WHERE userid = :userid ORDER BY date_exam DESC, id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $eligibilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Invoice -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Grid -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Eligibility</h2>
              </div>
              <!-- Col -->
              <!-- Col -->
            </div>
            <!-- End Grid -->

            <!-- Grid -->
            <div class="grid md:grid-cols-1 gap-3">
              <!-- Table Section -->
              <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
                <!-- Table -->
                <div class="-m-1.5 overflow-x-auto">
                  <div class="p-1.5 min-w-full inline-block align-middle">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                      <thead class="bg-gray-50 dark:bg-neutral-800">
                        <tr>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Eligibility</span>
                            </div>
                          </th>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Rating</span>
                            </div>
                          </th>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Date of Exam</span>
                            </div>
                          </th>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Place of Exam</span>
                            </div>
                          </th>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">License Number</span>
                            </div>
                          </th>
                          <th scope="col" class="px-6 py-3 text-start">
                            <div class="flex items-center gap-x-2">
                              <span class="text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Validity</span>
                            </div>
                          </th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                        <?php if (!empty($eligibilities)): ?>
                          <?php foreach ($eligibilities as $row): ?>
                            <tr>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <div class="flex items-center gap-x-3">
                                    <span class="block text-sm font-normal text-gray-800 dark:text-neutral-200">
                                      <?= htmlspecialchars(strtoupper($row['eligibility_type'])) ?>
                                    </span>
                                  </div>
                                </div>
                              </td>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <span class="text-sm text-gray-500 dark:text-neutral-500">
                                    <?= htmlspecialchars(strtoupper($row['rating'])) ?>
                                  </span>
                                </div>
                              </td>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <span class="text-sm text-gray-500 dark:text-neutral-500">
                                    <?= htmlspecialchars(strtoupper($row['date_exam'])) ?>
                                  </span>
                                </div>
                              </td>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <span class="text-sm text-gray-500 dark:text-neutral-500">
                                    <?= htmlspecialchars(strtoupper($row['place_exam'])) ?>
                                  </span>
                                </div>
                              </td>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <span class="text-sm text-gray-500 dark:text-neutral-500">
                                    <?= htmlspecialchars(strtoupper($row['license_number'])) ?>
                                  </span>
                                </div>
                              </td>
                              <td class="size-px whitespace-nowrap">
                                <div class="px-6 py-3">
                                  <span class="text-sm text-gray-500 dark:text-neutral-500">
                                    <?= !empty($row['date_validity']) ? strtoupper(date('M d, Y', strtotime($row['date_validity']))) : '' ?>
                                  </span>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="6" class="text-center py-4 text-gray-400 dark:text-neutral-500">No eligibility records found.</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <!-- End Table -->
              </div>
              <!-- End Table Section -->
            </div>
            <!-- End Grid -->
          </div>    
        <?php
        break;
    case 4: // WORK EXPERIENCE & VOLUNTARY WORK
        // Work experiences
        $stmt = $pdo->prepare("
          (
            SELECT w_from_date, w_to_date, position_title, agency_name, monthly_salary
            FROM work_experience_mssd
            WHERE userid = :userid
          )
          UNION ALL
          (
            SELECT w_from_date, w_to_date, position_title, agency_name, monthly_salary
            FROM work_experience
            WHERE userid = :userid
          )
          ORDER BY w_from_date DESC
        ");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $work_experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Voluntary works
        $stmt = $pdo->prepare("
          SELECT name_org_address, v_from_date, v_to_date, number_hours, position_nature_work
          FROM voluntary_works
          WHERE userid = :userid
          ORDER BY v_from_date DESC
        ");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $voluntary_works = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table 1 -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Work Experience</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">From Date</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">To Date</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Position Title</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Agency Name</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Monthly Salary</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($work_experiences)): ?>
                        <?php foreach ($work_experiences as $work): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(date('M d, Y', strtotime($work['w_from_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(empty($work['w_to_date']) || $work['w_to_date'] === '0000-00-00' ? 'To Present' : date('M d, Y', strtotime($work['w_to_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($work['position_title'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($work['agency_name'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(format_salary($work['monthly_salary'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="5" class="text-center py-4 text-gray-400 dark:text-neutral-500">No work experience records found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Table 2 -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Voluntary Work</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Agency Name</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Start Date</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">End Date</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Hours Worked</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Position/Nature of Work</th>
                      </tr>
                    </thead>
                      <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($voluntary_works)): ?>
                        <?php foreach ($voluntary_works as $vol): ?>
                          <tr>

                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($vol['name_org_address'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(date('M d, Y', strtotime($vol['v_from_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(empty($vol['v_to_date']) || $vol['v_to_date'] === '0000-00-00' ? 'To Present' : date('M d, Y', strtotime($vol['v_to_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars($vol['number_hours']) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($vol['position_nature_work'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="5" class="text-center py-4 text-gray-400 dark:text-neutral-500">No voluntary work records found.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php
        break;
    case 5: // LEARNING & DEVELOPMENT
        $stmt = $pdo->prepare("SELECT title_learning, l_from_date, l_to_date, l_hours, type_LD, sponsor FROM learning_development WHERE userid = :userid ORDER BY l_from_date DESC, id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $learning_development = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Learning and Development</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Title</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">From</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">To</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Total Hours</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Type</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Sponsored By</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($learning_development)): ?>
                        <?php foreach ($learning_development as $ld): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($ld['title_learning'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(date('M d, Y', strtotime($ld['l_from_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= strtoupper(date('M d, Y', strtotime($ld['l_to_date']))) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars($ld['l_hours']) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($ld['type_LD'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($ld['sponsor'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="6" class="text-center py-4 text-gray-400 dark:text-neutral-500">
                            No learning and development records found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            <!-- End Table Container -->
          </div>
        <?php
        break;
    case 6: // SPECIAL SKILLS
        $stmt = $pdo->prepare("SELECT specific_skills FROM special_skills WHERE userid = :userid ORDER BY id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $special_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Special Skills and Hobbies</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Skills</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($special_skills)): ?>
                        <?php foreach ($special_skills as $skill): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                              <?= htmlspecialchars(strtoupper($skill['specific_skills'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td class="text-center py-4 text-gray-400 dark:text-neutral-500">
                            No special skills found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php
        break;
    case 7: // NON-ACADEMIC DISTINCTIONS
        $stmt = $pdo->prepare("SELECT n_nacademic_title FROM non_academic_distinctions WHERE userid = :userid ORDER BY id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $non_academic_distinctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
        <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
          <!-- Header -->
          <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Non-Academic Distinction/Recognition</h2>
          </div>
          <!-- Table Container -->
          <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
            <div class="-m-1.5 overflow-x-auto">
              <div class="p-1.5 min-w-full inline-block align-middle">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                  <thead class="bg-gray-50 dark:bg-neutral-800">
                    <tr>
                      <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Title</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                    <?php if (!empty($non_academic_distinctions)): ?>
                      <?php foreach ($non_academic_distinctions as $distinction): ?>
                        <tr>
                          <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                            <?= htmlspecialchars(strtoupper($distinction['n_nacademic_title'])) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td class="text-center py-4 text-gray-400 dark:text-neutral-500">
                          No non-academic distinctions found.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <?php
        break;
    case 8: // MEMBERSHIP
        $stmt = $pdo->prepare("SELECT association FROM membership WHERE userid = :userid ORDER BY id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Membership in Association/Organization</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Name</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($memberships)): ?>
                        <?php foreach ($memberships as $membership): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                              <?= htmlspecialchars(strtoupper($membership['association'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td class="text-center py-4 text-gray-400 dark:text-neutral-500">
                            No memberships found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php
        break;
    case 9: // PERSONAL DISCLOSURE
        $stmt = $pdo->prepare("
            SELECT q1, q2, r2, q3, r3, q4, r4_1, r4_2, q5, r5, q6, r6, q7, r7, q8, r8, q9, r9, q10, r10, q11, r11, q12, r12, q13, r13, q14, r14
            FROM personal_disclosure
            WHERE userid = :userid
            LIMIT 1
        ");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $personal_disclosure = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$personal_disclosure) {
            $personal_disclosure = [
                'q1' => '', 'q2' => '', 'r2' => '', 'q3' => '', 'r3' => '', 'q4' => '', 'r4_1' => '', 'r4_2' => '',
                'q5' => '', 'r5' => '', 'q6' => '', 'r6' => '', 'q7' => '', 'r7' => '', 'q8' => '', 'r8' => '',
                'q9' => '', 'r9' => '', 'q10' => '', 'r10' => '', 'q11' => '', 'r11' => '', 'q12' => '', 'r12' => '',
                'q13' => '', 'r13' => '', 'q14' => '', 'r14' => ''
            ];
        }
        foreach ($personal_disclosure as $k => $v) {
            $personal_disclosure[$k] = strtoupper($v);
        }
        ?>
        <!-- Invoice -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Grid -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-300 dark:border-neutral-700">
              <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Personal Disclosure</h2>
              </div>
              <!-- Col -->
              <!-- Col -->
            </div>
            <!-- End Grid -->

            <!-- Grid -->
            <div class="grid md:grid-cols-1 gap-3">
              <div>
                <div class="grid space-y-3">

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q1:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Are you related by consanguinity or affinity to the appointing or recommending authority, or to the chief of bureau or office or to the person who has immediate supervision over you in the Office, Bureau or Department where you will be apppointed,</span>
                      <address class="not-italic font-normal">
                        <br>
                        a. within the third degree?<br> 
                        <br>
                        <?= htmlspecialchars($personal_disclosure['q1']) ?><br>
                        <br>
                        b. within the fourth degree (for Local Government Unit - Career Employees)?<br> 
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q2']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r2']) ?></span>
                        <br>                                        
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>


                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q2:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">a. Have you ever been found guilty of any administrative offense?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q3']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r3']) ?></span>
                        <br>
                        <br>
                        b. Have you been criminally charged before any court?<br> 
                        <br>
                        <?= htmlspecialchars($personal_disclosure['q4']) ?><br>
                        <br>
                        Date Filled: <?= !empty($personal_disclosure['r4_1']) && $personal_disclosure['r4_1'] !== '0000-00-00' ? htmlspecialchars($personal_disclosure['r4_1']) : '' ?><br>
                        Status: <?= htmlspecialchars($personal_disclosure['r4_2']) ?><br>                                         
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q3:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q5']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r5']) ?></span>
                        <br>                                         
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q4:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q6']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r6']) ?></span>
                        <br>                                         
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q5:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">a. Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q7']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r7']) ?></span>
                        <br>   
                        <br>
                        b. Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?<br>                         
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q8']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r8']) ?></span>
                        <br>                                      
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q6:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Have you acquired the status of an immigrant or permanent resident of another country?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q9']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r9']) ?></span>
                        <br>                                         
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q7:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Pursuant to: (a) Indigenous People's Act (RA 8371); (b) Magna Carta for Disabled Persons (RA 7277); and (c) Solo Parents Welfare Act of 2000 (RA 8972), please answer the following items:</span>
                      <address class="not-italic font-normal">
                        <br>                    
                        a. Are you a member of any indigenous group?<br>
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q10']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r10']) ?></span>
                        <br>        
                        <br>             
                        b. Are you a person with disability?<br>
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q11']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r11']) ?></span>
                        <br>  
                        <br>                    
                        c. Are you a solo parent?<br>
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q12']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r12']) ?></span>
                        <br>
                        <br>                                       
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q8:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Are you the son or daughter of a Mujahideen/Mujahidat?</span>
                      <address class="not-italic font-normal">
                        <br>                      
                        <?= htmlspecialchars($personal_disclosure['q13']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r13']) ?></span>
                        <br>                                         
                      </address>
                    </dd>
                  </dl>

                  <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-300  dark:text-white dark:after:border-neutral-600"></div>

                  <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                    <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                      Q9:
                    </dt>
                    <dd class="font-medium text-gray-800 dark:text-neutral-200">
                      <span class="block font-normal">Are you a Mujahideen/Mujahidat?</span>
                      <address class="not-italic font-normal">
                        <br>
                        <?= htmlspecialchars($personal_disclosure['q14']) ?> <span style="margin-left: 50px;">More Details: <?= htmlspecialchars($personal_disclosure['r14']) ?></span>
                        <br>                                         
                      </address>
                    </dd>
                  </dl>

                </div>
              </div>
              <!-- Col -->
            </div>
            <!-- End Grid -->

          </div>
          <!-- End Invoice -->

        </div>
        <!-- End Invoice -->
        <?php
        break;
    case 10: // REFERENCES
        $stmt = $pdo->prepare("SELECT r_fullname, r_address, r_contactno FROM references_name WHERE userid = :userid ORDER BY id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">References</h2>
            </div>

            <!-- Table Container -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Name</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Address</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Tel No.</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($references)): ?>
                        <?php foreach ($references as $ref): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                              <?= htmlspecialchars(strtoupper($ref['r_fullname'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($ref['r_address'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($ref['r_contactno'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="3" class="text-center py-4 text-gray-400 dark:text-neutral-500">
                            No references found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php
        break;
        case 11: // EMERGENCY CONTACT
        $stmt = $pdo->prepare("SELECT e_fullname, e_contact_number, e_relationship FROM emergency_contact WHERE userid = :userid ORDER BY id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
        <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
          <!-- Header -->
          <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Emergency Contacts</h2>
          </div>
          <!-- Table Container -->
          <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
            <div class="-m-1.5 overflow-x-auto">
              <div class="p-1.5 min-w-full inline-block align-middle">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                  <thead class="bg-gray-50 dark:bg-neutral-800">
                    <tr>
                      <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Name</th>
                      <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Contact Number</th>
                      <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Relationship</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                    <?php if (!empty($emergency_contacts)): ?>
                      <?php foreach ($emergency_contacts as $contact): ?>
                        <tr>
                          <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                            <?= htmlspecialchars(strtoupper($contact['e_fullname'])) ?>
                          </td>
                          <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                            <?= htmlspecialchars(strtoupper($contact['e_contact_number'])) ?>
                          </td>
                          <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                            <?= htmlspecialchars(strtoupper($contact['e_relationship'])) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center py-4 text-gray-400 dark:text-neutral-500">
                          No emergency contact found.
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <?php
        break;
    case 12: // EDUCATIONAL BACKGROUND
        $stmt = $pdo->prepare("SELECT schoolname, basic_degree_course, from_date, to_date, units_earned, year_grad, honor FROM educational_background WHERE userid = :userid ORDER BY from_date DESC, to_date DESC, id DESC");
        $stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
        $stmt->execute();
        $educational_background = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!-- Table Section -->
          <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
            <!-- Header -->
            <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
              <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Educational Background</h2>
            </div>

            
            <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-900 dark:border-neutral-700">
              <div class="-m-1.5 overflow-x-auto">
                <div class="p-1.5 min-w-full inline-block align-middle">
                  <table class="min-w-full divide-y divide-gray-200 dark:divide-neutral-700">
                    <thead class="bg-gray-50 dark:bg-neutral-800">
                      <tr>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">School Name</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Degree/Course</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">From</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">To</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Units Earned(if not Grad)</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Year Graduated</th>
                        <th class="px-6 py-3 text-start text-xs font-semibold uppercase text-gray-800 dark:text-neutral-200">Honor</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-neutral-700">
                      <?php if (!empty($educational_background)): ?>
                        <?php foreach ($educational_background as $edu): ?>
                          <tr>
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-normal text-gray-800 dark:text-neutral-200">
                              <?= htmlspecialchars(strtoupper($edu['schoolname'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['basic_degree_course'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['from_date'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['to_date'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['units_earned'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['year_grad'])) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-500">
                              <?= htmlspecialchars(strtoupper($edu['honor'])) ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="7" class="text-center py-4 text-gray-400 dark:text-neutral-500">
                            No educational background found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php
        break;
    default:
        echo "Invalid tab.";
}
$output = ob_get_clean();
echo $output;
?>