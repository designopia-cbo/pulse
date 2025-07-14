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

// Fetch available item numbers and positions
$stmt = $pdo->prepare(
    "SELECT `id`, `item_number`, `position_title`
     FROM `plantilla_position`
     WHERE (`userid` IS NULL OR `userid` = '' OR `userid` = 0)
       AND `pstatus` = 1
     ORDER BY `item_number`"
);
$stmt->execute();
$itemPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Gather and sanitize form data (always trim and uppercase unless noted)
        function to_upper($val) { return mb_strtoupper(trim($val), 'UTF-8'); }
        function to_lower($val) { return mb_strtolower(trim($val), 'UTF-8'); }
        function initial($val) { return $val ? mb_substr(trim($val), 0, 1, 'UTF-8') : ''; }

        $last_name = to_upper($_POST['last_name'] ?? '');
        $first_name = to_upper($_POST['first_name'] ?? '');
        $middle_name = to_upper($_POST['middle_name'] ?? '');
        $suffix = to_upper($_POST['suffix'] ?? '');
        $gender = to_upper($_POST['gender'] ?? '');
        $birthdate = $_POST['date_of_birth'] ?? null;
        $citizenship = to_upper($_POST['citizenship'] ?? '');
        $civilstatus = to_upper($_POST['civil_status'] ?? '');
        $religion = to_upper($_POST['religion'] ?? '');
        $tribe = to_upper($_POST['tribe'] ?? '');
        $telephoneno = to_upper($_POST['telephone_number'] ?? '');
        $mobilenumber = to_upper($_POST['mobile_number'] ?? '');
        $emailaddress = to_upper($_POST['email_address'] ?? '');
        $height = to_upper($_POST['height'] ?? '');
        $weight = to_upper($_POST['weight'] ?? '');
        $blood_type = to_upper($_POST['blood_type'] ?? '');
        $date_orig_appt = !empty($_POST['date_of_appointment']) ? $_POST['date_of_appointment'] : null;
        $status = 1; // or whatever logic for status

        // 2. Insert into employee table
        $fullname = trim("$first_name $middle_name $last_name $suffix");
        $stmt = $pdo->prepare("INSERT INTO employee (
            fullname, last_name, first_name, middle_name, suffix,
            gender, birthdate, citizenship, civilstatus, religion,
            tribe, telephoneno, mobilenumber, emailaddress,
            height, weight, blood_type, date_orig_appt, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE'
        )");
        $stmt->execute([
            $fullname, $last_name, $first_name, $middle_name, $suffix, $gender,
            $birthdate, $citizenship, $civilstatus, $religion, $tribe,
            $telephoneno, $mobilenumber, $emailaddress, $height,
            $weight, $blood_type, $date_orig_appt
        ]);
        $employee_id = $pdo->lastInsertId();

        // 3. Update plantilla_position table (set userid)
        $item_number = $_POST['item_number'] ?? '';
        $stmt = $pdo->prepare("UPDATE plantilla_position SET userid = ? WHERE item_number = ?");
        $stmt->execute([$employee_id, $item_number]);

        // Get plantilla_position row for this item_number
        $stmt = $pdo->prepare("SELECT id, salary_grade, office FROM plantilla_position WHERE item_number = ?");
        $stmt->execute([$item_number]);
        $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);
        $position_id = $plantilla['id'];
        $salary_grade = $plantilla['salary_grade'];
        $office = $plantilla['office'];

        // 4. Employment details
        $date_of_assumption = !empty($_POST['date_of_assumption']) ? $_POST['date_of_assumption'] : null;
        $sg = $salary_grade;
        $step = 1;
        $edstatus = 1;
        $end_date = null;
        $hr = null;
        $supervisor = null;
        $manager = null;
        $area_of_assignment = null;

        // Find salary_id and monthly_salary from salary_standardization
        $stmt = $pdo->prepare("SELECT id, ssl_amount FROM salary_standardization WHERE ssl_salary_grade = ? AND ssl_step = 1");
        $stmt->execute([$sg]);
        $salary = $stmt->fetch(PDO::FETCH_ASSOC);
        $salary_id = $salary['id'];
        $monthly_salary = $salary['ssl_amount'];

        $stmt = $pdo->prepare("INSERT INTO employment_details (userid, position_id, date_of_assumption, date_appointment, sg, step, end_date, salary_id, monthly_salary, edstatus, supervisor, manager, hr, area_of_assignment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employee_id, $position_id, $date_of_assumption, $date_orig_appt, $sg, $step, $end_date, $salary_id, $monthly_salary,
            $edstatus, $supervisor, $manager, $hr, $area_of_assignment // area_of_assignment is now null
        ]);

        // 5. Insert into credit_leave table (only userid, leave columns default to NULL)
        $stmt = $pdo->prepare("
            INSERT INTO credit_leave (
                userid, vacationleave, forceleave, sickleave, maternityleave, paternityleave,
                spleave, soloparentleave, studyleave, vawcleave, rehabilitationprivilege,
                spleavewomen, calamityleave, adoptionleave, leavewopa, others
            ) VALUES (
                ?, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
            )
        ");
        $stmt->execute([$employee_id]);

        // 6. Insert into users table (mimic credit_leave logic: unconditional insert)
        // Construct completename: FIRSTNAME M. LASTNAME SUFFIX (all uppercase)
        $middle_initial = $middle_name ? initial($middle_name) . '.' : '';
        $suffix_str = $suffix ? " $suffix" : '';
        $completename = to_upper(trim("$first_name $middle_initial $last_name$suffix_str"));

        // Construct username: first letter of first_name + '.' + last_name (all lowercase) and day from date of birth
        // Get the initial of the first name (lowercase, no spaces)
        $initial = strtolower(str_replace(' ', '', substr($first_name, 0, 1)));

        // Get the last name (lowercase, no spaces)
        $last = strtolower(str_replace(' ', '', $last_name));

        // Get the day from the birthdate (expects 'YYYY-MM-DD' format)
        $day = date('d', strtotime($birthdate));

        // Build the username (no spaces)
        $username = $initial . '.' . $last . $day;

        // Default password (as provided)
        $default_password = '$2a$12$vSdcDcGDBc9bAgcwqTX0c.v8WX3fO.wjmlOdM/HovdJd0OpEIO0qy';
        $branch = $office;
        $level = 'EMPLOYEE';
        $category = null;

        // Direct insert to table users
        $stmt = $pdo->prepare("INSERT INTO users (completename, username, password, branch, level, category, userid)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $completename, $username, $default_password, $branch, $level, $category, $employee_id
        ]);

        sleep(3); // Wait for 2 seconds
        header("Location: profile?userid=" . $employee_id);
        exit;
    } catch (Exception $e) {
        // Handle error (log it, show error message, etc.)
        $error = $e->getMessage();
        echo "Error: " . $error; // Show error for debugging
        exit;
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
        <div class="hs-dropdown [--placement:bottom-right] relative inline-flex">
          <button id="hs-dropdown-account" type="button" class="size-9.5 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent text-gray-800 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none dark:text-white" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
            <span class="shrink-0 size-9.5 flex items-center justify-center rounded-full bg-gray-200 text-gray-800 dark:bg-neutral-700 dark:text-neutral-200 font-medium text-sm">
              <?php echo htmlspecialchars($initial); ?>
            </span>
          </button>


          <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700 dark:divide-neutral-700 after:h-4 after:absolute after:-bottom-4 after:start-0 after:w-full before:h-4 before:absolute before:-top-4 before:start-0 before:w-full" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-account">
            <div class="py-3 px-5 bg-gray-100 rounded-t-lg dark:bg-neutral-700">
              <p class="text-sm text-gray-500 dark:text-neutral-500">Signed in as</p>
              <p class="text-sm font-medium text-gray-800 dark:text-neutral-200"><?php echo htmlspecialchars($fullName); ?></p>
            </div>
            <div class="p-1.5 space-y-0.5">
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="profile">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="7" r="4" />
                  <path d="M4 20c0-4 4-7 8-7s8 3 8 7" />
                </svg>
                My Profile
              </a>
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300" href="changepassword">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" 
                viewBox="0 0 24 24" fill="none" stroke="currentColor" 
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0110 0v4" />
              </svg>
              Change Password
            </a>
            <a href="logout" class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700 dark:focus:text-neutral-300">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12H21" />
                <path d="M16 6l6 6-6 6" />
                <path d="M3 12h6" />
              </svg>
              Logout
            </a>
            
          </div>
        </div>
      </div>
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
      Employee Enrollment
    </h2>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
      Manage general information.
    </p>

    <form method="post" enctype="multipart/form-data">
      <!-- Grid -->
      <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
        <!-- Item Number Dropdown -->
        <div class="sm:col-span-3">
          <label for="item_number" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Item Number
          </label>
        </div>
        <div class="sm:col-span-9">
          <select id="item_number" name="item_number" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" required>
            <option disabled selected value="">Select Item Number</option>
            <?php foreach ($itemPositions as $row): ?>
              <option value="<?= htmlspecialchars($row['item_number']) ?>" data-position-title="<?= htmlspecialchars($row['position_title']) ?>">
                <?= htmlspecialchars($row['item_number']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Position Title (readonly, styled like the other fields, disabled) -->
        <div class="sm:col-span-3">
          <label for="position_title" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Position Title
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="position_title" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Position Title" disabled>
        </div>
        <!-- End Col -->

        <!-- Date of Appointment -->
        <div class="sm:col-span-3">
          <label for="date_of_appointment" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Date of Appointment
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="date_of_appointment" name="date_of_appointment" type="date" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
        </div>
        <!-- End Col -->

        <!-- Date of Assumption -->
        <div class="sm:col-span-3">
          <label for="date_of_assumption" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Date of Assumption
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="date_of_assumption" name="date_of_assumption" type="date" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
        </div>
        <!-- End Col -->

        <!-- Separator Line -->
        <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>

        <div class="sm:col-span-3">
          <label for="last_name" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Last Name
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="last_name" name="last_name" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Last Name" required>
        </div>

        <div class="sm:col-span-3">
          <div class="inline-block">
            <label for="first_name" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
              First Name
            </label>
          </div>
        </div>
        <div class="sm:col-span-9">
          <div class="sm:flex">
            <!-- First Name input -->
            <input id="first_name" name="first_name" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter First Name" required>

            <!-- Suffix dropdown -->
            <select id="suffix" name="suffix" class="py-1.5 sm:py-2 px-3 pe-9 block w-full sm:w-auto border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg rounded-bl-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
              <option disabled selected value="">Suffix</option>
              <option>Jr.</option>
              <option>Sr.</option>
              <option>II</option>
              <option>III</option>
              <option>IV</option>
              <option>V</option>
            </select>
          </div>
        </div>

        <div class="sm:col-span-3">
          <label for="middle_name" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Middle Name
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="middle_name" name="middle_name" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Middle Name">
        </div>

        <div class="sm:col-span-3">
          <label for="date_of_birth" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Date of Birth
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="date_of_birth" name="date_of_birth" type="date" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" required>
        </div>


        <div class="sm:col-span-3">
                  <label for="af-account-gender-checkbox" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                    Gender
                  </label>
                </div>
                <!-- End Col -->
        <div class="sm:col-span-9">
          <div class="sm:flex">
            <label for="gender_male" class="flex py-2 px-3 w-full border border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
              <input type="radio" name="gender" class="shrink-0 mt-0.5 border-gray-300 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-500 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="gender_male" value="Male" checked>
              <span class="sm:text-sm text-gray-500 ms-3 dark:text-neutral-400">Male</span>
            </label>

            <label for="gender_female" class="flex py-2 px-3 w-full border border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
              <input type="radio" name="gender" class="shrink-0 mt-0.5 border-gray-300 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-500 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="gender_female" value="Female">
              <span class="sm:text-sm text-gray-500 ms-3 dark:text-neutral-400">Female</span>
            </label>
          </div>
        </div>

        <div class="sm:col-span-3">
          <label for="civil_status" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Civil Status
          </label>
        </div>
        <div class="sm:col-span-9">
          <select id="civil_status" name="civil_status" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" required>
            <option disabled selected value="">Select Civil Status</option>
            <option>Single</option>
            <option>Married</option>
            <option>Widowed</option>
            <option>Divorced</option>
            <option>Separated</option>
          </select>
        </div>

        <!-- Citizenship -->
        <div class="sm:col-span-3">
          <label for="citizenship" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Citizenship
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="citizenship" name="citizenship" type="text" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg ..." value="Filipino" required>
        </div>

        <div class="sm:col-span-3">
          <label for="religion" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Religion
          </label>
        </div>
        <div class="sm:col-span-9">
          <select id="religion" name="religion" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg ..." required>
            <option disabled selected value="">Select Religion</option>
            <option>Roman Catholic</option>
            <option>Christian (Protestant/Born Again)</option>
            <option>Iglesia ni Cristo</option>
            <option>Islam</option>
            <option>Buddhism</option>
            <option>Hinduism</option>
            <option>Judaism</option>
            <option>Others</option>
          </select>
        </div>

        <div class="sm:col-span-3">
          <label for="tribe" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Tribe
          </label>
        </div>
        <div class="sm:col-span-9">
          <select id="tribe" name="tribe" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" required>
            <option disabled selected value="">Select Tribe</option>
            <option>Aeta</option>
            <option>Apayao</option>
            <option>Badjao</option>
            <option>Bikolano</option>
            <option>Chavacano</option>
            <option>Hiligaynon</option>
            <option>Ifugao</option>
            <option>Igorot</option>
            <option>Ilocano</option>
            <option>Ibanag</option>
            <option>Iranun</option>
            <option>Kapampangan</option>
            <option>Kalinga</option>
            <option>Kankanai</option>
            <option>Maguindanao</option>
            <option>Mangyan</option>
            <option>Manobo</option>
            <option>Maranao</option>
            <option>Palaw'an</option>
            <option>Pangasinense</option>
            <option>Subanen</option>
            <option>Tagalog</option>
            <option>Tausug</option>
            <option>Tboli</option>
            <option>Visayan</option>
            <option>Waray</option>
            <option>Yakan</option>
            <option>Others</option>
          </select>
        </div>

        <div class="sm:col-span-3">
          <label for="blood_type" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Blood Type
          </label>
        </div>
        <div class="sm:col-span-9">
          <select id="blood_type" name="blood_type" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" required>
            <option disabled selected value="">Select Blood Type</option>
            <option>A+</option>
            <option>A−</option>
            <option>B+</option>
            <option>B−</option>
            <option>AB+</option>
            <option>AB−</option>
            <option>O+</option>
            <option>O−</option>
          </select>
        </div>

        <div class="sm:col-span-3">
          <label for="height" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Height
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="height" name="height" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Height (e.g. 170 cm)">
        </div>

        <div class="sm:col-span-3">
          <label for="weight" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Weight
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="weight" name="weight" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Weight (e.g. 65 kg)">
        </div>

        <div class="sm:col-span-3">
          <label for="telephone_number" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Telephone Number
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="telephone_number" name="telephone_number" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Telephone Number">
        </div>

        <div class="sm:col-span-3">
          <label for="email_address" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Email Address
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="email_address" name="email_address" type="email" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="e.g., mike@gmail.com">
        </div>

        <div class="sm:col-span-3">
          <label for="mobile_number" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
            Mobile Number
          </label>
        </div>
        <div class="sm:col-span-9">
          <input id="mobile_number" name="mobile_number" type="text" class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter Mobile Number">
        </div>

        <!-- Separator Line -->
        <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>
      </div>
      <!-- End Grid -->

      <div class="mt-1 flex justify-end gap-x-2">
        <button type="button" class="py-3 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-scale-animation-modal" data-hs-overlay="#hs-scale-animation-modal">
          Open modal
        </button>
        <button type="button" onclick="history.back()" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
          Cancel
        </button>
        <button id="saveBtn" type="submit" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
          Add Employee
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
<script src="https://preline.co/assets/js/hs-overlay.min.js"></script>


<script>
  document.getElementById('item_number').addEventListener('change', function() {
    var selected = this.options[this.selectedIndex];
    var posTitle = selected.getAttribute('data-position-title') || '';
    document.getElementById('position_title').value = posTitle;
  });
</script>

<!-- Modal -->
<div id="hs-scale-animation-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-scale-animation-modal-label">
  <div class="hs-overlay-animation-target hs-overlay-open:scale-100 hs-overlay-open:opacity-100 scale-95 opacity-0 ease-in-out transition-all duration-200 sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
    <div class="w-full flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">

      <!-- Header -->
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-scale-animation-modal-label" class="font-bold text-gray-800 dark:text-white">
          Upload Signature
        </h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-scale-animation-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="p-4 overflow-y-auto space-y-6">
        <p class="text-sm text-gray-800 dark:text-neutral-400">
          To complete your leave application, please upload your digital signature below in PNG format.
        </p>

        <div class="grid sm:grid-cols-12 gap-4 items-start">
          <!-- Label Column -->
          <div class="sm:col-span-3">
            <label for="af-submit-application-resume-cv" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
              Signature
            </label>
          </div>

          <!-- Input Column -->
          <div class="sm:col-span-9">
            <label for="af-submit-application-resume-cv" class="sr-only">Choose file</label>
            <input type="file" name="af-submit-application-resume-cv" id="af-submit-application-resume-cv" accept=".png" class="block w-full border border-gray-200 shadow-sm rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400
              file:bg-gray-50 file:border-0
              file:bg-gray-100 file:me-4
              file:py-2 file:px-4
              dark:file:bg-neutral-700 dark:file:text-neutral-400">
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-scale-animation-modal">
          Close
        </button>
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
          Save changes
        </button>
      </div>

    </div>
  </div>
</div>
<!-- End Modal -->

</body>
</html>

