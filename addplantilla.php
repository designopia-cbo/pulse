<?php
require_once('init.php');

// Restrict access: Only ADMINISTRATOR level and HR or SUPERADMIN category
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || ($_SESSION['category'] !== 'HR' && $_SESSION['category'] !== 'SUPERADMIN')
) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

// Fetch user details using session 'userid'
$userid = $_SESSION['userid'];
$category = isset($_SESSION['category']) ? $_SESSION['category'] : '';
$stmt = $pdo->prepare("SELECT fullname FROM employee WHERE id = :userid");
$stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
  $fullName = ucwords(strtolower($user['fullname']));
  $initial = strtoupper(substr($fullName, 0, 1));
} else {
  $fullName = "Unknown User";
  $initial = "U";
}

// Fetch unique, uppercase values for dropdowns
function fetchUniqueDropdownValues($pdo, $column) {
    $stmt = $pdo->prepare("SELECT DISTINCT UPPER($column) AS val FROM plantilla_position WHERE $column IS NOT NULL AND $column != ''");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    sort($results, SORT_STRING);
    return $results;
}

$org_units = fetchUniqueDropdownValues($pdo, 'org_unit');
$offices = fetchUniqueDropdownValues($pdo, 'office');
$cost_structures = fetchUniqueDropdownValues($pdo, 'cost_structure');

$error = null;

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Helper: convert to uppercase and trim
        function to_upper($val) { return mb_strtoupper(trim($val), 'UTF-8'); }

        $item_number = to_upper($_POST['item_number'] ?? '');
        $position_title = to_upper($_POST['position_title'] ?? '');
        $salary_grade = to_upper($_POST['salary_grade'] ?? '');
        $org_unit = to_upper($_POST['organizational_unit'] ?? '');
        $office = to_upper($_POST['office'] ?? '');
        $cost_structure = to_upper($_POST['cost_structure'] ?? '');

        // Classification value mapping
        $classification_map = [
            'PERMANENT' => 'P',
            'COTERMINOUS' => 'CT',
            'COTERMINOUS WITH THE INCUMBENT' => 'CTI'
        ];
        $classification_input = to_upper($_POST['classification'] ?? '');
        $classification = '';
        foreach ($classification_map as $key => $value) {
            if ($classification_input === $key) {
                $classification = $value;
                break;
            }
        }
        if ($classification === '') {
            throw new Exception("Invalid classification selected.");
        }

        // Check for duplicate Item Number
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_position WHERE item_number = :item_number");
        $dupStmt->bindParam(':item_number', $item_number);
        $dupStmt->execute();
        $exists = $dupStmt->fetchColumn();

        if ($exists > 0) {
            // Set error message for frontend
            $error = "The Item Number <strong>" . htmlspecialchars($item_number) . "</strong> already exists.";
        } else {
            // Set userid to NULL, pstatus to 1
            $userid_val = null;
            $pstatus = 1;

            // Prepare and execute the insert
            $stmt = $pdo->prepare("INSERT INTO plantilla_position 
                (userid, item_number, position_title, salary_grade, org_unit, office, cost_structure, classification, pstatus) 
                VALUES (:userid, :item_number, :position_title, :salary_grade, :org_unit, :office, :cost_structure, :classification, :pstatus)");
            $stmt->bindParam(':userid', $userid_val, PDO::PARAM_NULL);
            $stmt->bindParam(':item_number', $item_number);
            $stmt->bindParam(':position_title', $position_title);
            $stmt->bindParam(':salary_grade', $salary_grade);
            $stmt->bindParam(':org_unit', $org_unit);
            $stmt->bindParam(':office', $office);
            $stmt->bindParam(':cost_structure', $cost_structure);
            $stmt->bindParam(':classification', $classification);
            $stmt->bindParam(':pstatus', $pstatus, PDO::PARAM_INT);
            $stmt->execute();

            // Success message or redirect
            header("Location: plantilla");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

  <!DOCTYPE html>
  <html lang="en">
  <head>  
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

<!-- Title -->
<title> HRIS | Add Employee</title>

<!-- CSS Preline -->
<link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>

<body class="bg-gray-50 dark:bg-neutral-900">
<!-- ========== HEADER ========== -->
<header class="sticky top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-48 w-full bg-white border-b border-gray-200 text-sm py-2.5 lg:ps-65 dark:bg-neutral-800 dark:border-neutral-700">
  <nav class="px-4 sm:px-6 flex basis-full items-center w-full mx-auto">
    <div class="me-5 lg:me-0 lg:hidden flex items-center">
      <!-- Logo -->
      <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline">
        <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline" style="color: #155dfc; font-weight: bold; text-decoration: none;">
          MSSD PULSE
        </a>
      </a>
      <!-- End Logo -->
    </div>

    <div class="w-full flex items-center justify-end ms-auto md:justify-between gap-x-1 md:gap-x-3">

      <div class="hidden md:block">
        
      </div>

      <div class="flex flex-row items-center justify-end gap-1">
        

        <button type="button" class="size-9.5 relative inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span class="sr-only">Notifications</span>
        </button>

        <!-- Dropdown -->
        <?php include __DIR__ . '/includes/header_dropdown.php'; ?>
        <!-- End Dropdown -->
    </div>
  </div>
</nav>
</header>
<!-- ========== END HEADER ========== -->

<!-- ========== MAIN CONTENT ========== -->
<!-- Breadcrumb -->
<div class="sticky top-0 inset-x-0 z-20 bg-white border-y border-gray-200 px-4 sm:px-6 lg:px-8 lg:hidden dark:bg-neutral-800 dark:border-neutral-700">
  <div class="flex items-center py-2">
    <!-- Navigation Toggle -->
    <button type="button" class="size-8 flex justify-center items-center gap-x-2 border border-gray-200 text-gray-800 hover:text-gray-500 rounded-lg focus:outline-hidden focus:text-gray-500 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700 dark:text-neutral-200 dark:hover:text-neutral-500 dark:focus:text-neutral-500" aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-application-sidebar" aria-label="Toggle navigation" data-hs-overlay="#hs-application-sidebar">
      <span class="sr-only">Toggle Navigation</span>
      <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect width="18" height="18" x="3" y="3" rx="2" />
        <path d="M15 3v18" />
        <path d="m8 9 3 3-3 3" />
      </svg>
    </button>
    <!-- End Navigation Toggle -->

    <!-- Breadcrumb -->
    <ol class="ms-3 flex items-center whitespace-nowrap">
      <li class="flex items-center text-sm text-gray-800 dark:text-neutral-400">
        HRIS
        <svg class="shrink-0 mx-3 overflow-visible size-2.5 text-gray-400 dark:text-neutral-500" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M5 1L10.6869 7.16086C10.8637 7.35239 10.8637 7.64761 10.6869 7.83914L5 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
      </li>
      <li class="text-sm font-semibold text-gray-800 truncate dark:text-neutral-400" aria-current="page">
        Add Employee
      </li>
    </ol>
    <!-- End Breadcrumb -->
  </div>
</div>
<!-- End Breadcrumb -->

<!-- Sidebar -->
<div id="hs-application-sidebar" class="hs-overlay  [--auto-close:lg]
hs-overlay-open:translate-x-0
-translate-x-full transition-all duration-300 transform
w-65 h-full
hidden
fixed inset-y-0 start-0 z-60
bg-white border-e border-gray-200
lg:block lg:translate-x-0 lg:end-auto lg:bottom-0
dark:bg-neutral-800 dark:border-neutral-700" role="dialog" tabindex="-1" aria-label="Sidebar">
<div class="relative flex flex-col h-full max-h-full">
  <div class="px-6 pt-4 flex justify-center items-center">
    <!-- Logo -->
    <a class="flex-none rounded-xl text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline">
      <a class="flex-none rounded-md text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80" href="#" aria-label="Preline" style="color: #155dfc; font-weight: bold; text-decoration: none;">
        MSSD PULSE
      </a>
    </a>
    <!-- End Logo -->
  </div>
  <!-- Content -->
  <?php include 'includes/sidebar.php'; ?>
<!-- End Content -->
</div>
</div>
<!-- End Sidebar -->

<!-- Content -->
<div class="w-full lg:ps-64">
  <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">      
   
   <!-- Card -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
      <div class="p-10">
        <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
          Add Plantilla Position
        </h2>
        <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
          All fields in the form are required.
        </p>

        <?php if (!empty($error)): ?>
          <div class="bg-red-50 border-s-4 border-red-500 p-4 dark:bg-red-800/30 mb-6" role="alert" tabindex="-1" aria-labelledby="hs-bordered-red-style-label">
            <div class="flex">
              <div class="shrink-0">
                <!-- Icon -->
                <span class="inline-flex justify-center items-center size-8 rounded-full border-4 border-red-100 bg-red-200 text-red-800 dark:border-red-900 dark:bg-red-800 dark:text-red-400">
                  <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                  </svg>
                </span>
                <!-- End Icon -->
              </div>
              <div class="ms-3">
                <h3 id="hs-bordered-red-style-label" class="text-gray-800 font-semibold dark:text-white">
                  Error!
                </h3>
                <p class="text-sm text-gray-700 dark:text-neutral-400">
                  <?= $error ?>
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <!-- Grid -->
          <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">

            <div class="sm:col-span-3">
              <label for="item_number" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Item Number
              </label>
            </div>
            <div class="sm:col-span-9">
              <input id="item_number" name="item_number" type="text"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                placeholder="Enter Item Number" required value="<?= isset($_POST['item_number']) ? htmlspecialchars($_POST['item_number']) : '' ?>">
            </div>

            <div class="sm:col-span-3">
              <label for="position_title" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Position Title
              </label>
            </div>
            <div class="sm:col-span-9">
              <input id="position_title" name="position_title" type="text"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                placeholder="Enter Position Title" required value="<?= isset($_POST['position_title']) ? htmlspecialchars($_POST['position_title']) : '' ?>">
            </div>

            <div class="sm:col-span-3">
              <label for="salary_grade" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Salary Grade
              </label>
            </div>
            <div class="sm:col-span-9">
              <input id="salary_grade" name="salary_grade" type="number" min="0"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                placeholder="Enter Salary Grade" required value="<?= isset($_POST['salary_grade']) ? htmlspecialchars($_POST['salary_grade']) : '' ?>">
            </div>

            <!-- Organizational Unit -->
            <div class="sm:col-span-3">
              <label for="organizational_unit" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Organizational Unit
              </label>
            </div>
            <div class="sm:col-span-9">
              <select id="organizational_unit" name="organizational_unit"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                onchange="convertField('organizational_unit')" required>
                <option disabled value="" <?= !isset($_POST['organizational_unit']) || $_POST['organizational_unit'] === '' ? 'selected' : '' ?>>Select Organizational Unit</option>
                <?php foreach ($org_units as $val): ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= (isset($_POST['organizational_unit']) && $_POST['organizational_unit'] == $val ? 'selected' : '') ?>><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= (isset($_POST['organizational_unit']) && $_POST['organizational_unit'] == 'other' ? 'selected' : '') ?>>OTHERS</option>
              </select>
            </div>

            <!-- Office -->
            <div class="sm:col-span-3">
              <label for="office" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Office
              </label>
            </div>
            <div class="sm:col-span-9">
              <select id="office" name="office"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                onchange="convertField('office')" required>
                <option disabled value="" <?= !isset($_POST['office']) || $_POST['office'] === '' ? 'selected' : '' ?>>Select Office</option>
                <?php foreach ($offices as $val): ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= (isset($_POST['office']) && $_POST['office'] == $val ? 'selected' : '') ?>><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= (isset($_POST['office']) && $_POST['office'] == 'other' ? 'selected' : '') ?>>OTHERS</option>
              </select>
            </div>

            <!-- Cost Structure -->
            <div class="sm:col-span-3">
              <label for="cost_structure" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Cost Structure
              </label>
            </div>
            <div class="sm:col-span-9">
              <select id="cost_structure" name="cost_structure"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg"
                onchange="convertField('cost_structure')" required>
                <option disabled value="" <?= !isset($_POST['cost_structure']) || $_POST['cost_structure'] === '' ? 'selected' : '' ?>>Select Cost Structure</option>
                <?php foreach ($cost_structures as $val): ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= (isset($_POST['cost_structure']) && $_POST['cost_structure'] == $val ? 'selected' : '') ?>><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
                <option value="other" <?= (isset($_POST['cost_structure']) && $_POST['cost_structure'] == 'other' ? 'selected' : '') ?>>OTHERS</option>
              </select>
            </div>

            <script>
              function convertField(fieldId) {
                var dropdown = document.getElementById(fieldId);

                if (dropdown.value === "other") {
                  var inputField = document.createElement("input");
                  inputField.type = "text";
                  inputField.id = fieldId;
                  inputField.name = fieldId;
                  inputField.placeholder = "Enter " + fieldId.replace('_', ' ');
                  inputField.className = dropdown.className;
                  inputField.required = true;

                  dropdown.parentNode.replaceChild(inputField, dropdown);

                  // Automatically focus on the newly created input field
                  inputField.focus();

                  // Add an event listener to revert back if no value is entered
                  inputField.addEventListener("blur", function () {
                    if (!inputField.value.trim()) {
                      revertToDropdown(inputField, fieldId);
                    }
                  });
                }
              }

              function revertToDropdown(inputField, fieldId) {
                // Get the original PHP array for the dropdown
                let options = [];
                <?php if ($org_units): ?>
                if (fieldId === 'organizational_unit') options = <?php echo json_encode($org_units); ?>;
                <?php endif; ?>
                <?php if ($offices): ?>
                if (fieldId === 'office') options = <?php echo json_encode($offices); ?>;
                <?php endif; ?>
                <?php if ($cost_structures): ?>
                if (fieldId === 'cost_structure') options = <?php echo json_encode($cost_structures); ?>;
                <?php endif; ?>

                var dropdown = document.createElement("select");
                dropdown.id = fieldId;
                dropdown.name = fieldId;
                dropdown.className = inputField.className;
                dropdown.required = true;
                dropdown.onchange = function () { convertField(fieldId); };

                var placeholder = "Select " + fieldId.replace('_', ' ');
                var optionPlaceholder = document.createElement("option");
                optionPlaceholder.textContent = placeholder.toUpperCase();
                optionPlaceholder.value = "";
                optionPlaceholder.disabled = true;
                optionPlaceholder.selected = true;
                dropdown.appendChild(optionPlaceholder);

                options.forEach(function(val) {
                  var option = document.createElement("option");
                  option.textContent = val;
                  option.value = val;
                  dropdown.appendChild(option);
                });

                var optionOther = document.createElement("option");
                optionOther.textContent = "OTHERS";
                optionOther.value = "other";
                dropdown.appendChild(optionOther);

                inputField.parentNode.replaceChild(dropdown, inputField);
              }
            </script>

            <div class="sm:col-span-3">
              <label for="classification" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                Classification
              </label>
            </div>
            <div class="sm:col-span-9">
              <select id="classification" name="classification"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg" required>
                <option disabled value="" <?= !isset($_POST['classification']) || $_POST['classification'] === '' ? 'selected' : '' ?>>Select Classification</option>
                <option <?= (isset($_POST['classification']) && $_POST['classification'] == 'PERMANENT' ? 'selected' : '') ?>>PERMANENT</option>
                <option <?= (isset($_POST['classification']) && $_POST['classification'] == 'COTERMINOUS' ? 'selected' : '') ?>>COTERMINOUS</option>
                <option <?= (isset($_POST['classification']) && $_POST['classification'] == 'COTERMINOUS WITH THE INCUMBENT' ? 'selected' : '') ?>>COTERMINOUS WITH THE INCUMBENT</option>
              </select>
            </div>

            <!-- Separator Line -->
            <div class="sm:col-span-12">
              <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
            </div>
          </div>
          <!-- End Grid -->

          <div class="mt-1 flex justify-end gap-x-2">
            <button type="button" onclick="history.back()"
              class="py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50">
              Cancel
            </button>
            <button id="saveBtn" type="submit"
              class="py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700">
              Add Plantilla
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- End Card -->

</div>
</div>
<!-- End Content -->

<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script>
  document.getElementById('item_number').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    var posTitle = selected.getAttribute('data-position-title') || '';
    document.getElementById('position_title').value = posTitle;
  });
</script>

<script src="/pulse/js/secure.js"></script>

</body>
</html>

