<?php
require_once('init.php');

// ==============================
// ACCESS CONTROL SECTION
// ==============================
$userid = $_SESSION['userid'];
$user_category = isset($_SESSION['category']) ? $_SESSION['category'] : '';

// Determine which profile is being viewed
$profile_userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : $userid;

// Always allow if viewing own profile
$access_granted = ($profile_userid === $userid);

// If not own profile, apply further access checks
if (!$access_granted) {
    if ($user_category === 'MINISTER' || $user_category === 'HR') {
        // MINISTER/HR can view any profile
        $access_granted = true;
    } elseif ($user_category === 'AAO') {
        // AAO can view only employees in the same office
        // Get AAO's office from plantilla_position
        $stmt = $pdo->prepare("SELECT office FROM plantilla_position WHERE userid = :userid LIMIT 1");
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->execute();
        $aaoRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $aaoOffice = $aaoRow ? $aaoRow['office'] : null;

        if ($aaoOffice !== null) {
            // Get profile user's office
            $stmt = $pdo->prepare(
                "SELECT pp.office
                 FROM employment_details ed
                 INNER JOIN plantilla_position pp ON ed.position_id = pp.id
                 WHERE ed.userid = :profile_userid AND ed.edstatus = 1 LIMIT 1"
            );
            $stmt->bindParam(':profile_userid', $profile_userid, PDO::PARAM_INT);
            $stmt->execute();
            $profileRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $profileOffice = $profileRow ? $profileRow['office'] : null;

            if ($profileOffice !== null && $profileOffice === $aaoOffice) {
                $access_granted = true;
            }
        }
    } else {
        // Any user can view employees they supervise or manage
        // Check if the logged-in user is the supervisor of the profile
        $stmt = $pdo->prepare(
            "SELECT 1 FROM employment_details WHERE userid = :profile_userid AND supervisor = :userid AND edstatus = 1 LIMIT 1"
        );
        $stmt->bindParam(':profile_userid', $profile_userid, PDO::PARAM_INT);
        $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $access_granted = true;
        } else {
            // Check if the logged-in user is the manager of the profile
            $stmt = $pdo->prepare(
                "SELECT 1 FROM employment_details WHERE userid = :profile_userid AND manager = :userid AND edstatus = 1 LIMIT 1"
            );
            $stmt->bindParam(':profile_userid', $profile_userid, PDO::PARAM_INT);
            $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetch()) {
                $access_granted = true;
            }
        }
    }
}

// If not granted, force logout
if (!$access_granted) {
    session_unset();
    session_destroy();
    header("Location: login");
    exit;
}

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

// ==============================
// USER CONTEXT
// ==============================
$profile_userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : $_SESSION['userid'];

// ==============================
// TAB 1: PERSONAL & CONTACT INFO
// ==============================
$stmt = $pdo->prepare("SELECT id, empno, fullname, last_name, first_name, middle_name, suffix, gender, birthdate, citizenship, civilstatus, religion, tribe, telephoneno, mobilenumber, emailaddress, height, weight, blood_type, date_orig_appt, status FROM employee WHERE id = :userid");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

$firstname = $suffix = $middlename = $lastname = $dateofbirth = $dateOrigAppt = $gender = $mobile = $telephonenumber = $emailaddress = $citizenship = $civilstatus = $religion = $tribe = $bloodtype = $height = $weight = "";

if ($profile_user) {
    $firstname        = strtoupper($profile_user['first_name']);
    $suffix           = strtoupper($profile_user['suffix']);
    $middlename       = strtoupper($profile_user['middle_name']);
    $lastname         = strtoupper($profile_user['last_name']);
    
    if (!empty($profile_user['birthdate']) && $profile_user['birthdate'] !== "0000-00-00") {
        $date = DateTime::createFromFormat('Y-m-d', $profile_user['birthdate']);
        $dateofbirth = $date ? strtoupper($date->format('F d, Y')) : '';
    }

    if (!empty($profile_user['date_orig_appt']) && $profile_user['date_orig_appt'] !== "0000-00-00") {
        $dateAppt = DateTime::createFromFormat('Y-m-d', $profile_user['date_orig_appt']);
        $dateOrigAppt = $dateAppt ? strtoupper($dateAppt->format('F d, Y')) : '';
    }

    $gender           = strtoupper($profile_user['gender']);
    $mobile           = strtoupper($profile_user['mobilenumber']);
    $telephonenumber  = strtoupper($profile_user['telephoneno']);
    $emailaddress     = strtoupper($profile_user['emailaddress']);
    $citizenship      = strtoupper($profile_user['citizenship']);
    $civilstatus      = strtoupper($profile_user['civilstatus']);
    $religion         = strtoupper($profile_user['religion']);
    $tribe            = strtoupper($profile_user['tribe']);
    $bloodtype        = strtoupper($profile_user['blood_type']);
    $height           = strtoupper($profile_user['height']);
    $weight           = strtoupper($profile_user['weight']);
}

// Employment Dates
$stmt = $pdo->prepare("SELECT date_of_assumption, date_appointment FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$employment = $stmt->fetch(PDO::FETCH_ASSOC);

$dateOfAssumption = $dateAppointment = "";

if ($employment) {
    if (!empty($employment['date_of_assumption']) && $employment['date_of_assumption'] !== "0000-00-00") {
        $dateAssumption = DateTime::createFromFormat('Y-m-d', $employment['date_of_assumption']);
        $dateOfAssumption = $dateAssumption ? strtoupper($dateAssumption->format('F d, Y')) : '';
    }

    if (!empty($employment['date_appointment']) && $employment['date_appointment'] !== "0000-00-00") {
        $dateAppt = DateTime::createFromFormat('Y-m-d', $employment['date_appointment']);
        $dateAppointment = $dateAppt ? strtoupper($dateAppt->format('F d, Y')) : '';
    }
}

// Address Info
$stmt = $pdo->prepare("SELECT birth_place, residential_add, permanent_add FROM employee_address WHERE userid = :userid LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$address = $stmt->fetch(PDO::FETCH_ASSOC);

$placeofbirth = $residentialaddress = $permanentaddress = "";
if ($address) {
    $placeofbirth       = strtoupper($address['birth_place']);
    $residentialaddress = strtoupper($address['residential_add']);
    $permanentaddress   = strtoupper($address['permanent_add']);
}

// Statutory Benefits
$stmt = $pdo->prepare("SELECT gsis_number, pagibig_number, philhealth_number, tin, sss_number FROM statutory_benefits WHERE userid = :userid LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$benefits = $stmt->fetch(PDO::FETCH_ASSOC);

$gsis = $pagibig = $philhealth = $sss = $tin = "";
if ($benefits) {
    $gsis       = strtoupper($benefits['gsis_number']);
    $pagibig    = strtoupper($benefits['pagibig_number']);
    $philhealth = strtoupper($benefits['philhealth_number']);
    $sss        = strtoupper($benefits['sss_number']);
    $tin        = strtoupper($benefits['tin']);
}

// Government Identification
$stmt = $pdo->prepare("SELECT identification_type, identification_no, date_or_placeofissuance FROM government_identification WHERE userid = :userid LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$govid = $stmt->fetch(PDO::FETCH_ASSOC);

$idtype = $idno = $validity = "";
if ($govid) {
    $idtype   = strtoupper($govid['identification_type']);
    $idno     = strtoupper($govid['identification_no']);
    $validity = strtoupper($govid['date_or_placeofissuance']);
}

// ==============================
// ADDITIONAL: PROFILE HEADER & EMPLOYMENT, LEAVE CREDITS, USER INFO (DO NOT ALTER)
// ==============================
$profile_initials = "U";
$profile_fullname = "Unknown User";

if ($profile_user) {
    $fn_initial = !empty($profile_user['first_name']) ? strtoupper(substr($profile_user['first_name'], 0, 1)) : '';
    $ln_initial = !empty($profile_user['last_name']) ? strtoupper(substr($profile_user['last_name'], 0, 1)) : '';
    $profile_initials = $fn_initial . $ln_initial;

    $middle = '';
    if (!empty($profile_user['middle_name'])) {
        $middle = strtoupper(substr(trim($profile_user['middle_name']), 0, 1)) . '. ';
    }
    $suffix_disp = !empty($profile_user['suffix']) ? ' ' . $profile_user['suffix'] : '';
    $full_name_raw = trim(
        $profile_user['first_name'] . ' ' . $middle . $profile_user['last_name'] . $suffix_disp
    );
    $profile_fullname = ucwords(strtolower($full_name_raw));
}

// Employment details and leave credits
$stmt = $pdo->prepare("SELECT * FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$employment = $stmt->fetch(PDO::FETCH_ASSOC);

$position_title = $office = $org_unit = $cost_structure = $salary_grade = $item_number = "";
$area_assignment = $step = $monthly_salary = "";

if ($employment) {
    $position_id = $employment['position_id'];
    $stmt2 = $pdo->prepare("SELECT * FROM plantilla_position WHERE id = :position_id LIMIT 1");
    $stmt2->bindParam(':position_id', $position_id, PDO::PARAM_INT);
    $stmt2->execute();
    $plantilla = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($plantilla) {
        $position_title = $plantilla['position_title'];
        $office = $plantilla['office'];
        $org_unit = $plantilla['org_unit'];
        $cost_structure = $plantilla['cost_structure'];
        $salary_grade = $plantilla['salary_grade'];
        $item_number = $plantilla['item_number']; // New line to get item_number
    }

    $area_assignment = $employment['area_of_assignment'];
    $step = $employment['step'];
    $monthly_salary = $employment['monthly_salary'];
    if (is_numeric($monthly_salary)) {
        $monthly_salary = number_format($monthly_salary);
    }
}

$stmt = $pdo->prepare("SELECT vacationleave, sickleave FROM credit_leave WHERE userid = :userid LIMIT 1");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$leave = $stmt->fetch(PDO::FETCH_ASSOC);

$vacation_leave = $leave ? $leave['vacationleave'] : "0";
$sick_leave = $leave ? $leave['sickleave'] : "0";

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
?>

<!DOCTYPE html>
<html lang="en">
<head>  

  <!-- Title -->
  <title> HRIS | Profile </title>
  
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
          Profile
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
      <div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-gray-100 [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
        <nav class="hs-accordion-group p-3 w-full flex flex-col flex-wrap" data-hs-accordion-always-open>
          <ul class="flex flex-col space-y-1">
            <li>
              <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
              <a class="flex items-center gap-x-3.5 py-2 px-2.5 bg-gray-100 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="dashboard">
                  <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                      <polyline points="9 22 9 12 15 12 15 22" />
                  </svg>
                  Dashboard
              </a>
              <?php endif; ?>
            </li>

            <li>
              <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
              <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="employeelist">
                  <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>                        
                  Employee List
              </a>
              <?php endif; ?>
            </li>

            <li>
              <?php if (
                isset($_SESSION['level'], $_SESSION['category']) &&
                $_SESSION['level'] === 'ADMINISTRATOR' &&
                in_array($_SESSION['category'], ['HR', 'AAO', 'MINISTER'])
              ): ?>
              <li class="hs-accordion" id="projects-accordion">
                <button type="button" class="hs-accordion-toggle w-full text-start flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" aria-expanded="true" aria-controls="projects-accordion-child">
                  <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                  Employee Leave

                  <svg class="hs-accordion-active:block ms-auto hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m18 15-6-6-6 6" />
                  </svg>

                  <svg class="hs-accordion-active:hidden ms-auto block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6" />
                  </svg>
                </button>

                <div id="projects-accordion-child" class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300 hidden" role="region" aria-labelledby="projects-accordion">
                  <ul class="ps-8 pt-1 space-y-1">
                    <li>
                      <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="allleave">
                        Employee Applications
                      </a>
                    </li>
                    <li>
                      <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="leavecredit">
                        Employee Leave Credits
                      </a>
                    </li>
                    <li> 
                      <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="employeecreditlog">
                        Employee Credit Logs
                      </a>
                    </li>
                  </ul>
                </div>
              </li>    
            <?php endif; ?>
          </li>

            <?php if (isset($_SESSION['level']) && $_SESSION['level'] !== 'EMPLOYEE'): ?>
                <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200  dark:text-white dark:after:border-neutral-600"></div>
            <?php endif; ?>

            <li class="hs-accordion" id="projects-accordion">
              <button type="button" class="hs-accordion-toggle w-full text-start flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" aria-expanded="true" aria-controls="projects-accordion-child">
                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect width="20" height="14" x="2" y="7" rx="2" ry="2" />
                  <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                </svg>
                My Leaves

                <svg class="hs-accordion-active:block ms-auto hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m18 15-6-6-6 6" />
                </svg>

                <svg class="hs-accordion-active:hidden ms-auto block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m6 9 6 6 6-6" />
                </svg>
              </button>

              <div id="projects-accordion-child" class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300 hidden" role="region" aria-labelledby="projects-accordion">
                <ul class="ps-8 pt-1 space-y-1">
                  <li>
                    <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="leaveform">
                      Apply Leave
                    </a>
                  </li>
                  <li>
                    <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="myapplications">
                      My Applications
                    </a>
                  </li>
                  <li> 
                    <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-neutral-200" href="creditlogs">
                      Credit Logs
                    </a>
                  </li>
                </ul>
              </div>
            </li>

            
          </ul>
        </nav>
      </div>
      <!-- End Content -->
    </div>
  </div>
  <!-- End Sidebar -->

  <!-- Content -->
  <div class="w-full lg:ps-64">
    <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
      <!-- Start Profile with Tabs -->

<!-- Card -->
<div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4">
  <!-- Profile Section -->
  <div class="flex items-center justify-between gap-x-4">
    <div class="flex items-center gap-x-4">
      <span class="inline-flex items-center justify-center w-16 h-16 font-semibold rounded-full border border-gray-200 bg-white text-gray-800 shadow-2xs dark:bg-neutral-900 dark:border-neutral-700 dark:text-white">
        <?= $profile_initials ?>
      </span>

      <div class="flex flex-col">
        <h1 class="text-lg font-medium text-gray-800 dark:text-neutral-200">
          <?= htmlspecialchars($profile_fullname) ?>
        </h1>
        <p class="text-sm text-gray-600 dark:text-neutral-400">
          <?= htmlspecialchars($item_number) ?>
        </p>
      </div>
    </div>

         <?php
          // Only show dropdown if level is ADMINISTRATOR and category is AAO or HR
          $show_admin_dropdown = isset($_SESSION['level'], $_SESSION['category'])
            && $_SESSION['level'] === 'ADMINISTRATOR'
            && in_array($_SESSION['category'], ['AAO', 'HR']);
        ?>

        <?php if ($show_admin_dropdown): ?>
        <!-- Dropdown -->
        <div class="hs-dropdown relative inline-flex">
          <button id="hs-dropdown-profile-trigger" type="button" class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
            <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="1"/>
              <circle cx="12" cy="5" r="1"/>
              <circle cx="12" cy="19" r="1"/>
            </svg>
          </button>

          <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-profile-trigger">
            <div class="p-1 space-y-0.5">
              <?php
                // Determine if we are viewing own profile (no userid param) or someone else's
                $is_own_profile = !isset($_GET['userid']) || intval($_GET['userid']) === intval($_SESSION['userid']);
                $edit_profile_href = $is_own_profile
                    ? 'editprofile'
                    : 'editprofile?userid=' . urlencode($profile_userid);
              ?>
              <a class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700"
                 href="<?= htmlspecialchars($edit_profile_href) ?>">
                Edit Profile
              </a>
              <a
              id="admin-option-link"
              href="#"
              data-hs-overlay="#hs-medium-modal"
              class="flex items-center gap-x-3.5 py-2 px-3 rounded-lg text-sm text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-300 dark:focus:bg-neutral-700"
            >
              Admin Option
            </a>
            </div>
          </div>
        </div>
        <!-- End Dropdown -->

        <!-- Modal -->
        <div id="hs-medium-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-medium-modal-label">
          <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
            <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70">
              <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
                <h3 id="hs-medium-modal-label" class="font-bold text-gray-800 dark:text-white">
                  Modal title
                </h3>
                <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-medium-modal">
                  <span class="sr-only">Close</span>
                  <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"></path>
                    <path d="m6 6 12 12"></path>
                  </svg>
                </button>
              </div>
              <div class="p-4 overflow-y-auto">
                <p class="mt-1 text-gray-800 dark:text-neutral-400">
                  This is a wider card with supporting text below as a natural lead-in to additional content.
                </p>
              </div>
              <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
                <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-medium-modal">
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

        <?php endif; ?>

  </div>
  <!-- End Profile Section -->
</div>
<!-- End Card -->

<!-- Card -->
<div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4">
  <!-- Parent Container (Ensuring Full Alignment) -->
  <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 my-1 sm:my-2 text-left">

    <!-- Employment Details -->
    <div class="mb-5 pb-5 flex justify-start items-center border-b border-gray-200 dark:border-neutral-700">
      <div>
        <h2 class="text-lg font-semibold text-gray-800 dark:text-neutral-200">Employment Details</h2>
      </div>
    </div>

    <!-- Employment Data Grid -->
    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <div class="grid space-y-3">
          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Position:</dt>
            <dd class="font-normal text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($position_title) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Office:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($office) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Organizational Unit:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($org_unit) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Area of Assignment:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($area_assignment) ?></dd>
          </dl>
        </div>
      </div>

      <div>
        <div class="grid space-y-3">
          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Salary Grade:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($salary_grade) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Step:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($step) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Monthly Salary:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($monthly_salary) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm mb-2">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Cost Structure:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($cost_structure) ?></dd>
          </dl>
        </div>
      </div>
    </div>

    <!-- Separator -->
    <div class="py-4 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200  dark:text-white dark:after:border-neutral-600"></div>

    <!-- Employment Data Grid -->
    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <div class="grid space-y-3">

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Orig. Date Appt.:</dt>
            <dd class="font-normal text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($dateOrigAppt) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Curr. Date Appt.:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($dateAppointment) ?></dd>
          </dl>

          <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
            <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">Curr. Date Assump.:</dt>
            <dd class="font-normal text-gray-700 dark:text-neutral-200"><?= htmlspecialchars($dateOfAssumption) ?></dd>
          </dl>

        </div>
      </div>

      <div>
        <div class="grid space-y-3">
        </div>
      </div>
    </div>

    <!-- Leave Credits Separator -->
    <div class="py-4 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">
      Leave Credits
    </div>

    <!-- Updated Statistics Section (with extra space below) -->
    <div class="w-full mb-4"> <!-- Added mb-4 to push the section down -->
      <div class="grid grid-cols-2 gap-4">
        <!-- Card -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4">
          <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">Vacation Leave Balance</p>
          <h3 class="text-xl sm:text-2xl font-medium text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($vacation_leave) ?></h3>
          <span class="flex items-center gap-x-1 text-green-600">
          </span>
        </div>

        <!-- Card -->
        <div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-800 dark:border-neutral-700 p-4">
          <p class="text-xs uppercase text-gray-500 dark:text-neutral-500">Sick Leave Balance</p>
          <h3 class="text-xl sm:text-2xl font-medium text-gray-800 dark:text-neutral-200"><?= htmlspecialchars($sick_leave) ?></h3>
        </div>
      </div>
    </div>
    <!-- End Updated Statistics Section -->

  </div>
</div>



<!-- Card Container -->
<div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-800 p-4">
  <!-- Tabs Header -->
  <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 my-1 sm:my-2 text-left">

    <!-- Mobile-friendly dropdown -->
    <select id="tab-select" class="sm:hidden py-3 px-4 pe-9 block w-full border-gray-200 rounded-lg text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" aria-label="Tabs">
      <option value="#tab-1">Overview</option>
      <option value="#tab-2">Family</option>
      <option value="#tab-12">Education</option>
      <option value="#tab-3">Eligibility</option>
      <option value="#tab-4">Experience</option>
      <option value="#tab-5">Learnings</option>
      <option value="#tab-6">Skills</option>
      <option value="#tab-7">Non-Academic</option>
      <option value="#tab-8">Membership</option>
      <option value="#tab-9">Disclosure</option>
      <option value="#tab-10">References</option>
      <option value="#tab-11">Emergency</option>
    </select>

    <!-- Desktop Tabs -->
    <div class="hidden sm:block border-b border-gray-200 dark:border-neutral-700 overflow-x-auto">
      <nav class="flex gap-x-1" aria-label="Tabs" role="tablist" data-hs-tab-select="#tab-select">
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500 active" id="tab-item-1" aria-selected="true" data-hs-tab="#tab-1" aria-controls="tab-1" role="tab">
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        Overview</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-2" aria-selected="false" data-hs-tab="#tab-2" aria-controls="tab-2" role="tab">Family</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-12" aria-selected="false" data-hs-tab="#tab-12" aria-controls="tab-11" role="tab">Education</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-3" aria-selected="false" data-hs-tab="#tab-3" aria-controls="tab-3" role="tab">Eligibility</button>        
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-4" aria-selected="false" data-hs-tab="#tab-4" aria-controls="tab-4" role="tab">Experience</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-5" aria-selected="false" data-hs-tab="#tab-5" aria-controls="tab-5" role="tab">Learnings</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-6" aria-selected="false" data-hs-tab="#tab-6" aria-controls="tab-6" role="tab">Skills</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-7" aria-selected="false" data-hs-tab="#tab-7" aria-controls="tab-7" role="tab">Non-Academic</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-8" aria-selected="false" data-hs-tab="#tab-8" aria-controls="tab-8" role="tab">Membership</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-9" aria-selected="false" data-hs-tab="#tab-9" aria-controls="tab-9" role="tab">Disclosure</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-10" aria-selected="false" data-hs-tab="#tab-10" aria-controls="tab-10" role="tab">References</button>
        <button type="button" class="hs-tab-active:font-semibold hs-tab-active:border-blue-600 hs-tab-active:text-blue-600 py-4 px-2 inline-flex items-center gap-x-2 border-b-2 border-transparent text-sm whitespace-nowrap text-gray-500 hover:text-blue-600 focus:outline-hidden focus:text-blue-600 disabled:opacity-50 disabled:pointer-events-none dark:text-neutral-400 dark:hover:text-blue-500" id="tab-item-11" aria-selected="false" data-hs-tab="#tab-11" aria-controls="tab-11" role="tab">Emergency</button>
      </nav>
    </div>
  </div>

  <!-- Tab Content -->
  <div class="mt-3 max-w-[85rem] text-left">
    <!-- Content per tab -->

    <!-- Tab 1 -->
    <div id="tab-1" role="tabpanel" aria-labelledby="tab-item-1">
      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
        <!-- Grid -->
        <div class="mb-5 pb-5 flex justify-between items-center border-b border-gray-200 dark:border-neutral-700">
          <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Overview</h2>
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
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  First Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($firstname) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Suffix:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                <?= htmlspecialchars($suffix) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Middle Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($middlename) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Last Name:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($lastname) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Date of Birth:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($dateofbirth) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Gender:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($gender) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Mobile Number:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($mobile) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Telephone Number:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($telephonenumber) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Email Address:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars(strtolower($emailaddress)) ?>
                </dd>
              </dl>

            </div>
          </div>
          <!-- Col -->

          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Citizenship:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($citizenship) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Civil Status:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($civilstatus) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Religion:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($religion) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Tribe:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($tribe) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Blood Type:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($bloodtype) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Height:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($height) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Weight:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($weight) ?>
                </dd>
              </dl>

            </div>
          </div>
          <!-- Col -->
        </div>
        <!-- End Grid -->

      </div>
      <!-- End Invoice -->

      <div class="py-1 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Address</div>

      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
        <!-- Grid -->        
        <!-- End Grid -->

        <!-- Grid -->
        <div class="grid md:grid-cols-1 gap-3">
          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Place of Birth:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($placeofbirth) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Residential Address:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($residentialaddress) ?>
                </dd>
              </dl>  

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Permanent Address:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($permanentaddress) ?> 
                </dd>
              </dl>     

            </div>
          </div>
        </div>
        <!-- End Grid -->

      </div>
      <!-- End Invoice -->

      <div class="py-1 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Statutory Benefits</div>

      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
        <!-- Grid -->        
        <!-- End Grid -->

        <!-- Grid -->
        <div class="grid md:grid-cols-2 gap-3">
          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  GSIS No:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($gsis) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Pag-ibig No:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($pagibig) ?> 
                </dd>
              </dl>  

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Philhealth:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($philhealth) ?>
                </dd>
              </dl>     

            </div>
          </div>
          <!-- Col -->

          <div>
            <div class="grid space-y-3">

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  SSS No:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($sss) ?>
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Tin:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($tin) ?> 
                </dd>
              </dl>  

            </div>
          </div>
          <!-- Col -->
        </div>
        <!-- End Grid -->

      </div>
      <!-- End Invoice -->

      <div class="py-1 flex items-center text-sm text-gray-500 before:flex-1 before:border-t before:border-gray-200 before:me-6 after:flex-1 after:border-t after:border-gray-200 after:ms-6">Valid ID</div>

      <!-- Invoice -->
      <div class="max-w-[85rem] px-4 sm:px-6 lg:px-8 mx-auto my-4 sm:my-10">
        <!-- Grid -->        
        <!-- End Grid -->

        <!-- Grid -->
        <div class="grid md:grid-cols-1 gap-3">
          <div>
            <div class="grid space-y-3">
              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  ID Type:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($idtype) ?> 
                </dd>
              </dl>

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  ID No:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($idno) ?> 
                </dd>
              </dl>  

              <dl class="flex flex-col sm:flex-row gap-x-3 text-sm">
                <dt class="min-w-36 max-w-50 text-gray-500 dark:text-neutral-500">
                  Date/Place of Issuance:
                </dt>
                <dd class="font-normal text-gray-700 dark:text-neutral-200">
                  <?= htmlspecialchars($validity) ?> 
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
    <!-- End Tab 1 -->

    <!-- Tab 2 -->
    <div id="tab-2" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-2">        

    </div>
    <!-- End Tab 2 -->

    <!-- Tab 12 -->
    <div id="tab-12" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-12">
      
    </div>
    <!-- End Table -->

    <!-- Tab 3 -->
    <div id="tab-3" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-3">
      

    </div>
    <!-- End Tab 3 -->

    <!-- Tab 4 -->
    <div id="tab-4" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-4">
      
    </div>
    <!-- End Tab 4 -->

    <!-- Tab 5 -->
    <div id="tab-5" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-5">
      
    </div>
    <!-- End Tab 5 -->

    <!-- Tab 6 -->
    <div id="tab-6" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-6">
      
    </div>
    <!-- End Tab 6 -->

    <!-- Tab 7 -->
    <div id="tab-7" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-7">
      
    </div>
    <!-- End Tab 7 -->


    <!-- Tab 8 -->
    <div id="tab-8" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-8">
      
    </div>
    <!-- End Tab 8 -->

    <!-- Tab 9 -->
    <div id="tab-9" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-9">
      
    </div>
    <!-- End Tab 9 -->

    <!-- Tab 10 -->
    <div id="tab-10" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-10">
      
    </div>
    <!-- End Tab 10 -->

    <!-- Tab 11 -->
    <div id="tab-11" class="hidden text-left" role="tabpanel" aria-labelledby="tab-item-11">
      
    </div>
    <!-- End Tab 11 -->

</div>
</div>
<!-- End of Card Container -->


  <!-- Required plugins -->
  <script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script>
// Standalone vanilla JS for tab switching and lazy loading (no Preline or external JS dependencies)
document.addEventListener('DOMContentLoaded', function () {
  // Elements
  const tabSelect = document.getElementById('tab-select');
  const tabButtons = document.querySelectorAll('[role="tab"][data-hs-tab]');
  const tabPanels = document.querySelectorAll('[role="tabpanel"]');

  // Track loaded tabs
  const loadedTabs = { "tab-1": true };

  // Helper to activate a tab by its panel id (e.g. '#tab-1')
  function activateTab(tabId) {
    // Hide all panels and remove active state from all tabs
    tabPanels.forEach(panel => {
      panel.classList.add('hidden');
      panel.setAttribute('aria-hidden', 'true');
    });

    tabButtons.forEach(btn => {
      btn.classList.remove('active');
      btn.setAttribute('aria-selected', 'false');
      btn.tabIndex = -1;
    });

    // Show the selected panel
    const panel = document.querySelector(tabId);
    if (panel) {
      panel.classList.remove('hidden');
      panel.setAttribute('aria-hidden', 'false');
      // Lazy load content for tab 2-12
      const tabNum = tabId.replace('#tab-', '');
      if (tabNum >= 2 && tabNum <= 12 && !loadedTabs[tabId.slice(1)]) {
        // Show loader
        panel.innerHTML = '<div class="tab-loader text-center py-8">Loading...</div>';
        // Get userid from PHP (rendered as a JS variable)
        const userid = <?= json_encode($profile_userid) ?>;
        fetch('get_tab_data?tab=' + tabNum + '&userid=' + encodeURIComponent(userid))
          .then(resp => {
            if (!resp.ok) throw new Error('Network response was not ok');
            return resp.text();
          })
          .then(html => {
            panel.innerHTML = html;
            loadedTabs[tabId.slice(1)] = true;
          })
          .catch(err => {
            panel.innerHTML = '<div class="tab-error text-center py-8 text-red-500">Failed to load data. Please try again.</div>';
          });
      }
    }

    // Activate the selected tab button
    const btn = Array.from(tabButtons).find(b => b.getAttribute('data-hs-tab') === tabId);
    if (btn) {
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');
      btn.tabIndex = 0;
    }

    // Sync dropdown (mobile)
    if (tabSelect && tabSelect.value !== tabId) {
      tabSelect.value = tabId;
    }
  }

  // Desktop tab button clicks
  tabButtons.forEach(btn => {
    btn.addEventListener('click', function () {
      const tabId = btn.getAttribute('data-hs-tab');
      activateTab(tabId);
    });
    // Keyboard navigation
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const btns = Array.from(tabButtons);
        let idx = btns.indexOf(document.activeElement);
        idx = e.key === 'ArrowRight' ? (idx + 1) % btns.length : (idx - 1 + btns.length) % btns.length;
        btns[idx].focus();
      }
    });
  });

  // Mobile dropdown change
  if (tabSelect) {
    tabSelect.addEventListener('change', function () {
      activateTab(tabSelect.value);
    });
  }

  // Set initial active tab (first .active or default to first tab)
  let initialTab = Array.from(tabButtons).find(b => b.classList.contains('active'))?.getAttribute('data-hs-tab')
    || (tabButtons[0] && tabButtons[0].getAttribute('data-hs-tab'));
  if (initialTab) activateTab(initialTab);
});
</script>
  

  </body>
</html>

