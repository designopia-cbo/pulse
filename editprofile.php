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
    return $dt ? $dt->format('Y-m-d') : '';
}
function compute_age($bday) {
    if (empty($bday) || $bday === '0000-00-00') return '';
    $birth = new DateTime($bday);
    $today = new DateTime();
    $age = $today->diff($birth)->y;
    return $age;
}

// ==============================
// USER CONTEXT
// ==============================
// Allow editing other user profiles by getting userid from GET, fallback to session user
$profile_userid = isset($_GET['userid']) && is_numeric($_GET['userid']) ? intval($_GET['userid']) : $_SESSION['userid'];

// Fetch fields for Tab 1: Personal & Contact Info
$stmt = $pdo->prepare("SELECT id, empno, fullname, last_name, first_name, middle_name, suffix, gender, birthdate, citizenship, civilstatus, religion, tribe, telephoneno, mobilenumber, emailaddress, height, weight, blood_type, date_orig_appt, status FROM employee WHERE id = :userid");
$stmt->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt->execute();
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

$last_name = $first_name = $suffix = $middle_name = $birthdate = $gender = $civilstatus = "";

if ($profile_user) {
    $last_name   = $profile_user['last_name'];
    $first_name  = $profile_user['first_name'];
    $suffix      = $profile_user['suffix'];
    $middle_name = $profile_user['middle_name'];
    $birthdate   = $profile_user['birthdate'];
    $gender      = $profile_user['gender'];
    $civilstatus = $profile_user['civilstatus'];
}

// Suffix dropdown options
$suffix_options = [" ", "Jr.", "Sr.", "II", "III", "IV", "V"];
if ($suffix && !in_array($suffix, $suffix_options) && !empty($suffix)) {
    array_unshift($suffix_options, $suffix); // add as first option if not already in standard list
}

// Civil status dropdown options
$civil_status_options = ["Single", "Married", "Widowed", "Divorced", "Separated"];
if ($civilstatus && !in_array($civilstatus, $civil_status_options) && !empty($civilstatus)) {
    array_unshift($civil_status_options, $civilstatus);
}

// Fetch more fields for Tab 1
$citizenship = $profile_user['citizenship'] ?? '';
$religion    = $profile_user['religion'] ?? '';
$tribe       = $profile_user['tribe'] ?? '';
$blood_type  = $profile_user['blood_type'] ?? '';

// Religion dropdown options
$religion_options = [
    "Roman Catholic",
    "Christian (Protestant/Born Again)",
    "Iglesia ni Cristo",
    "Islam",
    "Buddhism",
    "Hinduism",
    "Judaism",
    "Others"
];
if ($religion && !in_array($religion, $religion_options) && !empty($religion)) {
    array_unshift($religion_options, $religion);
}

// Tribe dropdown options
$tribe_options = [
    "Igorot",
    "Ilocano",
    "Tagalog",
    "Visayan",
    "Maranao",
    "Tausug",
    "Aeta",
    "Manobo",
    "Badjao",
    "Others"
];
if ($tribe && !in_array($tribe, $tribe_options) && !empty($tribe)) {
    array_unshift($tribe_options, $tribe);
}

// Blood type dropdown options
$blood_type_options = [
    "A+","A−","B+","B−","AB+","AB−","O+","O−"
];
if ($blood_type && !in_array($blood_type, $blood_type_options) && !empty($blood_type)) {
    array_unshift($blood_type_options, $blood_type);
}

// Fetch more fields from the database (if not already fetched above)
$height           = $profile_user['height'] ?? '';
$weight           = $profile_user['weight'] ?? '';
$telephoneno      = $profile_user['telephoneno'] ?? '';
$emailaddress     = $profile_user['emailaddress'] ?? '';
$mobilenumber     = $profile_user['mobilenumber'] ?? '';

// Fetch from employee_address table
$stmt_addr = $pdo->prepare("SELECT birth_place, permanent_add, residential_add FROM employee_address WHERE userid = :userid LIMIT 1");
$stmt_addr->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt_addr->execute();
$address = $stmt_addr->fetch(PDO::FETCH_ASSOC);

$birth_place      = $address['birth_place'] ?? '';
$permanent_add    = $address['permanent_add'] ?? '';
$residential_add  = $address['residential_add'] ?? '';

// Fetch statutory benefits
$stmt_benefits = $pdo->prepare("SELECT gsis_number, pagibig_number, philhealth_number, tin, sss_number FROM statutory_benefits WHERE userid = :userid LIMIT 1");
$stmt_benefits->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt_benefits->execute();
$benefits = $stmt_benefits->fetch(PDO::FETCH_ASSOC);

$gsis_number      = $benefits['gsis_number']      ?? '';
$pagibig_number   = $benefits['pagibig_number']   ?? '';
$philhealth_number= $benefits['philhealth_number']?? '';
$sss_number       = $benefits['sss_number']       ?? '';
$tin              = $benefits['tin']              ?? '';

// Fetch government identification
$stmt_id = $pdo->prepare("SELECT identification_type, identification_no, date_or_placeofissuance FROM government_identification WHERE userid = :userid LIMIT 1");
$stmt_id->bindParam(':userid', $profile_userid, PDO::PARAM_INT);
$stmt_id->execute();
$govid = $stmt_id->fetch(PDO::FETCH_ASSOC);

$identification_type = $govid['identification_type'] ?? '';
$identification_no   = $govid['identification_no']   ?? '';
$date_or_placeofissuance = $govid['date_or_placeofissuance'] ?? '';

// Identification type dropdown options
$id_type_options = [
  "e-Card / UMID",
  "Employee’s ID / Office Id",
  "Driver’s License",
  "Professional Regulation Commission (PRC) ID",
  "Passport",
  "Senior Citizen ID",
  "SSS ID",
  "COMELEC / Voter’s ID / COMELEC Registration Form",
  "Philippine Identification (PhilID / ePhilID)",
  "NBI Clearance",
  "Integrated Bar of the Philippines (IBP) ID",
  "Firearms License",
  "AFPSLAI ID",
  "PVAO ID",
  "AFP Beneficiary ID",
  "BIR (TIN)",
  "Pag-ibig ID",
  "Person’s With Disability (PWD) ID",
  "Solo Parent ID",
  "Pantawid Pamilya Pilipino Program (4Ps) ID *",
  "Barangay ID",
  "Philippine Postal ID",
  "Phil-health ID",
  "Other valid government-issued IDs or Documents with picture and signature"
];
if ($identification_type && !in_array($identification_type, $id_type_options) && !empty($identification_type)) {
  array_unshift($id_type_options, $identification_type);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1"> 

  <!-- Title -->
  <title> HRIS | Edit Profile </title>
  
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

        <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
        <li>          
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="dashboard">
              <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                <polyline points="9 22 9 12 15 12 15 22" />
              </svg>
              Dashboard
            </a>          
        </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['level']) && $_SESSION['level'] === 'ADMINISTRATOR'): ?>
        <li>          
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="employeelist">
              <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>                        
              Employee List
            </a>          
        </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['level'], $_SESSION['category']) && $_SESSION['level'] === 'ADMINISTRATOR' && in_array($_SESSION['category'], ['HR', 'MINISTER']) ): ?>
        <li>          
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="plantilla">
              <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M2 14h20"/>
                <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
              </svg>                        
              Plantilla
            </a>          
        </li>
        <?php endif; ?>


        <?php if (
            isset($_SESSION['level'], $_SESSION['category']) &&
            $_SESSION['level'] === 'ADMINISTRATOR' &&
            in_array($_SESSION['category'], ['HR', 'AAO', 'MINISTER'])
          ): ?>
        <li>
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
                    Credit Logs
                  </a>
                </li>
              </ul>
            </div>
          </li>         
      </li>
      <?php endif; ?>

      <?php if (isset($_SESSION['level'], $_SESSION['category']) && $_SESSION['level'] === 'ADMINISTRATOR' && in_array($_SESSION['category'], ['HR', 'MINISTER']) ): ?>
        <li>          
            <a class="flex items-center gap-x-3.5 py-2 px-2.5 text-sm text-gray-800 rounded-lg hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 dark:bg-neutral-700 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700 dark:text-white" href="salarystandardization">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
              </svg>                      
              Salary Standardization
            </a>          
        </li>
        <?php endif; ?>
        

      <div class="py-1 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200  dark:text-white dark:after:border-neutral-600"></div>


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
    <!-- Card -->
        <div class="bg-white rounded-xl p-4 sm:p-7 dark:bg-neutral-800">
          <div class="mb-8 flex justify-between items-center">
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
              Overview Details
            </h2>
            <p class="text-sm text-gray-600 dark:text-neutral-400">
              Manage general information.
            </p>
          </div>
          <button id="edit-button" type="button"
              class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
              aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
              <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 2l4 4-14 14H4v-4L18 2z"/>
                <path d="M16 4l4 4"/>
                <path d="M4 20h4"/>
              </svg>
            </button>
        </div>

          <form id="edit-profile-form" method="post" autocomplete="off">
            <!-- Grid -->
            <div class="grid sm:grid-cols-12 gap-2 sm:gap-6">
            <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">   
              
                <div class="sm:col-span-3">
                  <label for="af-account-lastname" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                    Last Name
                  </label>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-9">
                  <input 
                  id="af-account-lastname" 
                  name="last_name"
                  type="text" 
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" 
                  placeholder="Enter Last Name "
                  value="<?= htmlspecialchars($last_name) ?>">
                </div>
                <!-- End Col -->

                <div class="sm:col-span-3">
                  <div class="inline-block">
                    <label for="af-account-firstname" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                      First Name
                    </label>
                  </div>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-9">
                  <div class="sm:flex">
                    <!-- First Name input -->
                    <input 
                      id="af-account-firstname" 
                      name="first_name"
                      type="text" 
                      class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" 
                      placeholder="Enter First Name"
                      value="<?= htmlspecialchars($first_name) ?>">

                    <!-- Suffix dropdown -->
                    <select 
                      id="af-account-suffix"
                      name="suffix"
                      class="py-1.5 sm:py-2 px-3 pe-9 block w-full sm:w-auto border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg rounded-bl-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                      <option disabled <?= empty($suffix) ? 'selected' : '' ?>>Suffix</option>
                      <?php foreach ($suffix_options as $opt): ?>
                        <option <?= ($suffix === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-3">
                  <label for="af-account-middlename" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                    Middle Name
                  </label>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-9">
                  <input 
                  id="af-account-middlename" 
                  name="middle_name"
                  type="text" 
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" 
                  placeholder="Enter Middle Name"
                  value="<?= htmlspecialchars($middle_name) ?>">
                </div>
                <!-- End Col -->

                <div class="sm:col-span-3">
                  <label for="af-account-dob" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                    Date of Birth
                  </label>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-9">
                  <input 
                  id="af-account-dob" 
                  name="birthdate"
                  type="date"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  value="<?= htmlspecialchars(format_date($birthdate)) ?>">
                </div>
                <!-- End Col -->

                <div class="sm:col-span-3">
                  <label for="af-account-gender-checkbox" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                    Gender
                  </label>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-9">
                  <div class="sm:flex">
                    <label for="af-account-gender-checkbox-male" class="flex py-2 px-3 w-full border border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                      <input 
                        type="radio" 
                        name="gender" 
                        value="Male"
                        class="shrink-0 mt-0.5 border-gray-300 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-500 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" 
                        id="af-account-gender-checkbox-male"
                        <?= (strtolower($gender) === "male") ? "checked" : "" ?>
                      >
                      <span class="sm:text-sm text-gray-500 ms-3 dark:text-neutral-400">Male</span>
                    </label>

                    <label for="af-account-gender-checkbox-female" class="flex py-2 px-3 w-full border border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-lg text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                      <input 
                        type="radio" 
                        name="gender" 
                        value="Female"
                        class="shrink-0 mt-0.5 border-gray-300 rounded-full text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-500 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" 
                        id="af-account-gender-checkbox-female"
                        <?= (strtolower($gender) === "female") ? "checked" : "" ?>
                      >
                      <span class="sm:text-sm text-gray-500 ms-3 dark:text-neutral-400">Female</span>
                    </label>
                  </div>
                </div>
                <!-- End Col -->

                <div class="sm:col-span-3">
                <label for="af-account-civilstatus" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Civil Status
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <select 
                  id="af-account-civilstatus" 
                  name="civilstatus"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                >
                  <option disabled <?= empty($civilstatus) ? 'selected' : '' ?>>Select Civil Status</option>
                  <?php foreach ($civil_status_options as $opt): ?>
                    <option <?= ($civilstatus === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <!-- End Col -->

              <!-- Citizenship -->
              <div class="sm:col-span-3">
                <label for="af-account-citizenship" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Citizenship
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-citizenship" name="citizenship" type="text"
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Citizenship"
                  value="<?= htmlspecialchars($citizenship) ?>">
              </div>

              <div class="sm:col-span-3">
                <label for="af-account-religion" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Religion
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <select id="af-account-religion" name="religion"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                  <option disabled <?= empty($religion) ? 'selected' : '' ?>>Select Religion</option>
                  <?php foreach ($religion_options as $opt): ?>
                    <option <?= ($religion === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-3">
                <label for="af-account-tribe" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Tribe
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <!-- Tribe -->
                <select id="af-account-tribe" name="tribe"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                  <option disabled <?= empty($tribe) ? 'selected' : '' ?>>Select Tribe</option>
                  <?php foreach ($tribe_options as $opt): ?>
                    <option <?= ($tribe === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-3">
                <label for="af-account-bloodtype" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Blood Type
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <select id="af-account-bloodtype" name="blood_type"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                  <option disabled <?= empty($blood_type) ? 'selected' : '' ?>>Select Blood Type</option>
                  <?php foreach ($blood_type_options as $opt): ?>
                    <option <?= ($blood_type === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-3">
                <label for="af-account-height" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Height
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <input id="af-account-height" name="height" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Height (e.g. 170 cm)"
                  value="<?= htmlspecialchars($height) ?>">
              </div>
              <!-- End Col -->


              <div class="sm:col-span-3">
                <label for="af-account-weight" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Weight
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <input id="af-account-weight" name="weight" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Weight (e.g. 65 kg)"
                  value="<?= htmlspecialchars($weight) ?>">
              </div>
              <!-- End Col -->

              <!-- Telephone Number -->
              <div class="sm:col-span-3">
                <label for="af-account-telephone" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Telephone Number
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-telephone" name="telephoneno" type="text"
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Telephone Number"
                  value="<?= htmlspecialchars($telephoneno) ?>">
              </div>

              <!-- Email Address -->
              <div class="sm:col-span-3">
                <label for="af-account-email" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Email Address
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-email" name="emailaddress" type="email"
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="e.g., mike@gmail."
                  value="<?= htmlspecialchars(strtolower($emailaddress)) ?>">
              </div>              

              <!-- Mobile Number -->
              <div class="sm:col-span-3">
                <label for="af-account-mobile" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Mobile Number
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-mobile" name="mobilenumber" type="text"
                  class="py-1.5 sm:py-2 px-3 pe-11 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Mobile Number"
                  value="<?= htmlspecialchars($mobilenumber) ?>">
              </div>


              <!-- Separator Line -->
              <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>

              <div class="sm:col-span-3">
                <label for="af-account-placeofbirth" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Place of Birth
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <input id="af-account-placeofbirth" name="birth_place" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Place of Birth"
                  value="<?= htmlspecialchars($birth_place) ?>">
              </div>
              <!-- End Col -->

              <div class="sm:col-span-3">
                <label for="af-account-permanentaddress" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Permanent Address
                </label>
              </div>
              <!-- End Col -->

              <div class="sm:col-span-9">
                <input id="af-account-permanentaddress" name="permanent_add" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Permanent Address"
                  value="<?= htmlspecialchars($permanent_add) ?>">
              </div>
              <!-- End Col -->              

              <!-- Residential Address Label -->
              <div class="sm:col-span-3">
                <label for="af-account-residentialaddress" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Residential Address
                </label>
              </div>
              <!-- End Col -->

              <!-- Residential Address Input + Dropdown -->
              <div class="sm:col-span-9">
                <div class="sm:flex">
                  <!-- Residential Address Input -->
                  <input id="af-account-residentialaddress" name="residential_add" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs -mt-px -ms-px first:rounded-t-lg last:rounded-b-lg sm:first:rounded-s-lg sm:mt-0 sm:first:ms-0 sm:first:rounded-se-none sm:last:rounded-es-none sm:last:rounded-e-none sm:text-sm relative focus:z-10 focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Residential Address"
                  value="<?= htmlspecialchars($residential_add) ?>">

                  <!-- Dropdown for Address Options -->
                  <select id="residentialAddressOption" class="py-1.5 sm:py-2 px-3 pe-9 block w-full sm:w-auto border border-l-0 border-gray-200 shadow-2xs sm:text-sm relative focus:z-10 focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:focus:ring-neutral-600 rounded-e-lg">
                    <option disabled selected>Choose Option</option>
                    <option value="same">Same as Perm. Add.</option>
                    <option value="different">Different Address</option>
                  </select>
                </div>
              </div>
              <!-- End Col -->

              <!-- Script to handle dropdown logic -->
              <script>
                const permanent = document.getElementById('af-account-permanentaddress');
                const residential = document.getElementById('af-account-residentialaddress');
                const dropdown = document.getElementById('residentialAddressOption');

                dropdown.addEventListener('change', function () {
                  if (this.value === 'same') {
                    residential.value = permanent.value;
                    residential.readOnly = true;
                  } else {
                    residential.readOnly = false;
                    residential.value = '';
                  }
                });

                // Keep it synced if Permanent Address changes
                permanent.addEventListener('input', function () {
                  if (dropdown.value === 'same') {
                    residential.value = this.value;
                  }
                });
              </script>

              <!-- Separator Line -->
              <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>
              

              <!-- GSIS No. -->
              <div class="sm:col-span-3">
                <label for="af-account-gsis" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  GSIS No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-gsis" name="gsis_number" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter GSIS Number"
                  value="<?= htmlspecialchars($gsis_number) ?>">
              </div>

              <!-- Pag-ibig No. -->
              <div class="sm:col-span-3">
                <label for="af-account-pagibig" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Pag-ibig No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-pagibig" name="pagibig_number" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter Pag-ibig Number"
                  value="<?= htmlspecialchars($pagibig_number) ?>">
              </div>

              <!-- PhilHealth No. -->
              <div class="sm:col-span-3">
                <label for="af-account-philhealth" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  PhilHealth No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-philhealth" name="philhealth_number" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter PhilHealth Number"
                  value="<?= htmlspecialchars($philhealth_number) ?>">
              </div>

              <!-- SSS No. -->
              <div class="sm:col-span-3">
                <label for="af-account-sss" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  SSS No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-sss" name="sss_number" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter SSS Number"
                  value="<?= htmlspecialchars($sss_number) ?>">
              </div>

              <!-- TIN No. -->
              <div class="sm:col-span-3">
                <label for="af-account-tin" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  TIN No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-tin" name="tin" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter TIN Number"
                  value="<?= htmlspecialchars($tin) ?>">
              </div>
     
              <!-- Separator Line -->
              <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>

              <!-- ID Type -->
              <div class="sm:col-span-3">
                <label for="af-validid" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  ID Type
                </label>
              </div>
              <div class="sm:col-span-9">
                <select id="af-validid" name="identification_type"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg bg-white focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:focus:ring-neutral-600">
                  <option disabled <?= empty($identification_type) ? 'selected' : '' ?>>Select Valid ID</option>
                  <?php foreach ($id_type_options as $opt): ?>
                    <option <?= ($identification_type === $opt) ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- ID No. -->
              <div class="sm:col-span-3">
                <label for="af-account-idno" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  ID No.
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-idno" name="identification_no" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="Enter ID Number"
                  value="<?= htmlspecialchars($identification_no) ?>">
              </div>

              <!-- Date/Place of Issuance -->
              <div class="sm:col-span-3">
                <label for="af-account-issuance" class="inline-block text-sm text-gray-800 mt-2.5 dark:text-neutral-200">
                  Date/Place of Issuance
                </label>
              </div>
              <div class="sm:col-span-9">
                <input id="af-account-issuance" name="date_or_placeofissuance" type="text"
                  class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 disabled:opacity-50 disabled:pointer-events-none focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                  placeholder="e.g., 2024-01-15 / Quezon City" required
                  value="<?= htmlspecialchars($date_or_placeofissuance) ?>">
              </div>

              <!-- Separator Line -->
              <div class="sm:col-span-12"> <hr class="my-4 border-t border-gray-200 dark:border-neutral-700"> </div>
              
            </div>
            <!-- End Grid -->          


            <div class="mt-1 flex justify-end gap-x-2">
              <button id="cancel-button" type="button" style="display:none"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              Cancel
            </button>
            <button id="save-button" type="button" style="display:none"
              class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
              Save changes
            </button>
            </div>
          </form>
        </div>
        <!-- End Card --> 
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

  <!-- Include the JS at the end of your body -->
  <script src="js/editprofile.js"></script>

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
        fetch('ajax/get_edit_tab_data?tab=' + tabNum + '&userid=' + encodeURIComponent(userid))
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

<script>
// --- TAB 2: Family Background ---
document.addEventListener('DOMContentLoaded', function () {
  // Add a new child row (disabled by default, will be enabled in edit mode)
  window.getChildRowHtml = function() {
    return `
    <div class="child-row grid sm:grid-cols-12 gap-2 items-center">
      <div class="sm:col-span-6">
        <input type="text" name="child_name[]" placeholder="Child Full Name"
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      </div>
      <div class="sm:col-span-5">
        <input type="date" name="child_dob[]"
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      </div>
      <div class="sm:col-span-1">
        <button type="button" class="remove-child-btn flex justify-center items-center size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700" disabled>
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
    `;
  };

  function setFamilyFieldsEnabled(enabled) {
    const form = document.getElementById('family-form');
    if (!form) return;
    form.querySelectorAll('input,button.remove-child-btn').forEach(input => input.disabled = !enabled);
    form.querySelectorAll('.remove-child-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
    });
    const addBtn = document.getElementById('add-child-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchFamilyViewMode() {
    setFamilyFieldsEnabled(false);
    const editBtn = document.getElementById('edit-family-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-family-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-family-btn');
    if (saveBtn) saveBtn.style.display = 'none';
    const addBtn = document.getElementById('add-child-btn');
    if (addBtn) addBtn.style.display = 'none';
  }

  function switchFamilyEditMode() {
    setFamilyFieldsEnabled(true);
    const editBtn = document.getElementById('edit-family-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-family-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-family-btn');
    if (saveBtn) saveBtn.style.display = '';
    const addBtn = document.getElementById('add-child-btn');
    if (addBtn) addBtn.style.display = '';
  }

  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#add-child-btn')) {
      var container = document.getElementById('children-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getChildRowHtml());
        setFamilyFieldsEnabled(true); // Enable all fields (including new child row)
      }
      e.preventDefault();
      return;
    }
    if (e.target.closest('.remove-child-btn')) {
      var row = e.target.closest('.child-row');
      if (row) row.remove();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#edit-family-btn')) {
      switchFamilyEditMode();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#cancel-family-btn')) {
      switchFamilyViewMode();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#save-family-btn')) {
      e.preventDefault();

      const form = document.getElementById('family-form');
      const data = new FormData(form);

      // Gather children (name/dob arrays)
      const children = [];
      const names = form.querySelectorAll('input[name="child_name[]"]');
      const dobs  = form.querySelectorAll('input[name="child_dob[]"]');
      for (let i = 0; i < names.length; i++) {
        children.push({
          name: names[i].value.trim(),
          dob: dobs[i] ? dobs[i].value : ""
        });
      }

      // Build main payload
      const payload = {
        profile_userid: data.get('profile_userid'),
        f_firstname: data.get('f_firstname'),
        f_middlename: data.get('f_middlename'),
        f_surename: data.get('f_surename'),
        m_firstname: data.get('m_firstname'),
        m_middlename: data.get('m_middlename'),
        m_surename: data.get('m_surename'),
        children: children
      };

      fetch('/pulse/ajax/update_profile_tab2', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          alert('Family background updated!');
          location.reload();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.'));
        }
      })
      .catch(err => {
        alert('AJAX/network error: ' + err);
      });

      return;
    }
  });

  // On page load: view mode
  switchFamilyViewMode();
});
</script>


<script>
// ---- Eligibility Tab Edit Mode, Add/Remove, and Button Events ----

document.addEventListener('DOMContentLoaded', function() {
  // --- Helper: HTML template for a new eligibility row ---
  window.getEligibilityRowHtml = function() {
    return `
    <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center eligibility-row">
      <input type="hidden" name="eligibility_id[]" value="">
      <input type="text" name="eligibility[]" placeholder="Eligibility Type" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="rating[]" placeholder="Rating" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="exam_date[]" placeholder="Date of Examination" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="exam_place[]" placeholder="Place of Examination" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="license_no[]" placeholder="License No." class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="date" name="date_validity[]" placeholder="Date of Validity" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
        <button type="button" class="remove-eligibility-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
      <div class="sm:col-span-6 col-span-full">
        <hr class="my-4 border-t border-gray-200">
      </div>
    </div>
    `;
  };

  function setEligibilityFieldsEnabled(enabled) {
    const container = document.getElementById('eligibility-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-eligibility-row').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-eligibility-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchEligibilityViewMode() {
    setEligibilityFieldsEnabled(false);
    const editBtn = document.getElementById('edit-eligibility-button');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-eligibility-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-eligibility-btn');
    if (saveBtn) saveBtn.style.display = 'none';
  }

  function switchEligibilityEditMode() {
    setEligibilityFieldsEnabled(true);
    const editBtn = document.getElementById('edit-eligibility-button');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-eligibility-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-eligibility-btn');
    if (saveBtn) saveBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) ---
  document.body.addEventListener('click', function(e) {
    // Add eligibility row
    if (e.target.closest('#add-eligibility-btn')) {
      var container = document.getElementById('eligibility-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getEligibilityRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove eligibility row
    if (e.target.closest('.remove-eligibility-row')) {
      var row = e.target.closest('.eligibility-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-eligibility-button')) {
      switchEligibilityEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-eligibility-btn')) {
      switchEligibilityViewMode();
      e.preventDefault();
      return;
    }
  });

  // --- Save Changes Button (AJAX with user id from hidden field) ---
  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#save-eligibility-btn')) {
      e.preventDefault();

      // Disable all fields while saving
      setEligibilityFieldsEnabled(false);

      // Gather all eligibility data
      const rows = document.querySelectorAll('.eligibility-row');
      const eligibilityData = [];
      rows.forEach(row => {
        eligibilityData.push({
          id: row.querySelector('input[name="eligibility_id[]"]')?.value || '',
          eligibility_type: row.querySelector('input[name="eligibility[]"]')?.value || '',
          rating: row.querySelector('input[name="rating[]"]')?.value || '',
          date_exam: row.querySelector('input[name="exam_date[]"]')?.value || '',
          place_exam: row.querySelector('input[name="exam_place[]"]')?.value || '',
          license_no: row.querySelector('input[name="license_no[]"]')?.value || '',
          date_validity: row.querySelector('input[name="date_validity[]"]')?.value || ''
        });
      });

      // --- Get profile_userid from hidden field and send it in payload ---
      const profile_userid = document.getElementById('profile_userid')?.value || '';

      fetch('/pulse/ajax/update_profile_tab3', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          eligibility: eligibilityData
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setEligibilityFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Eligibility information saved!');
          switchEligibilityViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setEligibilityFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setEligibilityFieldsEnabled(true);
      });
      return;
    }
  });

  // --- On page load: view mode ---
  switchEligibilityViewMode();
});
</script>

<script>
// ---- Work Experience and Voluntary Works: Add/Remove Row Logic + Sectioned Edit + AJAX Save ----

document.addEventListener('DOMContentLoaded', function() {
  // --- Work Experience Row Template ---
  function getWorkExperienceRowHtml() {
    return `
      <div class="work-experience-row w-full">
        <input type="hidden" name="work_experience_id[]" value="">
        <div class="grid grid-cols-1 sm:grid-cols-9 gap-2 items-center w-full">
          <input type="date" name="work_from[]" placeholder="From"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="date" name="work_to[]" placeholder="To"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="position_title[]" placeholder="Position Title"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="department_agency[]" placeholder="Department / Agency"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="monthly_salary[]" placeholder="Monthly Salary"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="salary_grade_step[]" placeholder="SG (Format '00-0')"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="status_of_appointment[]" placeholder="Status of Appointment"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <select name="govt_service[]"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
            <option value="" selected hidden>Gov't Serv.</option>
            <option value="YES">YES</option>
            <option value="NO">NO</option>
          </select>
          <button type="button" class="remove-work-experience-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  }

  // --- Voluntary Works Row Template ---
  function getVoluntaryWorkRowHtml() {
    return `
      <div class="voluntary-work-row w-full">
        <input type="hidden" name="voluntary_work_id[]" value="">
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center w-full">
          <input type="text" name="vol_org_name_address[]" placeholder="Name & Address of Organization"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="date" name="vol_from[]" placeholder="From"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="date" name="vol_to[]" placeholder="To"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="number" name="vol_number_of_hours[]" placeholder="Number of Hours" min="0"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <input type="text" name="vol_position_nature_of_work[]" placeholder="Position / Nature of Work"
            class="w-full py-1.5 sm:py-2 px-3 border border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
          <button type="button" class="remove-voluntary-work-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  }

  // ---- Section Enable/Disable Helpers ----
  function setWorkExperienceFieldsEnabled(enabled) {
    document.querySelectorAll('.work-experience-row input:not([type="hidden"]), .work-experience-row select').forEach(input => {
      input.disabled = !enabled;
    });
    document.querySelectorAll('.remove-work-experience-row').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-work-experience-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
    // Section Save/Cancel
    const saveBtn = document.getElementById('save-work-experience-btn');
    const cancelBtn = document.getElementById('cancel-work-experience-btn');
    if (saveBtn) {
      saveBtn.style.display = enabled ? '' : 'none';
      saveBtn.disabled = !enabled;
    }
    if (cancelBtn) {
      cancelBtn.style.display = enabled ? '' : 'none';
      cancelBtn.disabled = !enabled;
    }
    // Edit button
    const editBtn = document.getElementById('edit-work-experience-btn');
    if (editBtn) {
      editBtn.style.display = enabled ? 'none' : '';
      editBtn.disabled = enabled;
    }
  }

  function setVoluntaryWorksFieldsEnabled(enabled) {
    document.querySelectorAll('.voluntary-work-row input:not([type="hidden"]), .voluntary-work-row select').forEach(input => {
      input.disabled = !enabled;
    });
    document.querySelectorAll('.remove-voluntary-work-row').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-voluntary-work-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
    // Section Save/Cancel
    const saveBtn = document.getElementById('save-voluntary-works-btn');
    const cancelBtn = document.getElementById('cancel-voluntary-works-btn');
    if (saveBtn) {
      saveBtn.style.display = enabled ? '' : 'none';
      saveBtn.disabled = !enabled;
    }
    if (cancelBtn) {
      cancelBtn.style.display = enabled ? '' : 'none';
      cancelBtn.disabled = !enabled;
    }
    // Edit button
    const editBtn = document.getElementById('edit-voluntary-works-btn');
    if (editBtn) {
      editBtn.style.display = enabled ? 'none' : '';
      editBtn.disabled = enabled;
    }
  }

  function switchWorkExperienceViewMode() {
    setWorkExperienceFieldsEnabled(false);
  }
  function switchWorkExperienceEditMode() {
    setWorkExperienceFieldsEnabled(true);
    setVoluntaryWorksFieldsEnabled(false);
  }
  function switchVoluntaryWorksViewMode() {
    setVoluntaryWorksFieldsEnabled(false);
  }
  function switchVoluntaryWorksEditMode() {
    setVoluntaryWorksFieldsEnabled(true);
    setWorkExperienceFieldsEnabled(false);
  }

  // --- Add/Remove Row Event Delegation ---
  document.body.addEventListener('click', function(e) {
    // Add Work Experience Row
    if (e.target.closest('#add-work-experience-btn')) {
      const container = document.getElementById('work-experience-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getWorkExperienceRowHtml());
      }
      e.preventDefault();
      return;
    }
    // Remove Work Experience Row
    if (e.target.closest('.remove-work-experience-row')) {
      const row = e.target.closest('.work-experience-row');
      if (row) row.remove();
      e.preventDefault();
      return;
    }
    // Add Voluntary Work Row
    if (e.target.closest('#add-voluntary-work-btn')) {
      const container = document.getElementById('voluntary-works-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getVoluntaryWorkRowHtml());
      }
      e.preventDefault();
      return;
    }
    // Remove Voluntary Work Row
    if (e.target.closest('.remove-voluntary-work-row')) {
      const row = e.target.closest('.voluntary-work-row');
      if (row) row.remove();
      e.preventDefault();
      return;
    }
    // Edit Work Experience Section
    if (e.target.closest('#edit-work-experience-btn')) {
      switchWorkExperienceEditMode();
      e.preventDefault();
      return;
    }
    // Edit Voluntary Works Section
    if (e.target.closest('#edit-voluntary-works-btn')) {
      switchVoluntaryWorksEditMode();
      e.preventDefault();
      return;
    }
    // Cancel Work Experience Section
    if (e.target.closest('#cancel-work-experience-btn')) {
      switchWorkExperienceViewMode();
      e.preventDefault();
      return;
    }
    // Cancel Voluntary Works Section
    if (e.target.closest('#cancel-voluntary-works-btn')) {
      switchVoluntaryWorksViewMode();
      e.preventDefault();
      return;
    }
    // Save Work Experience (AJAX)
    if (e.target.closest('#save-work-experience-btn')) {
      saveWorkExperienceAJAX();
      e.preventDefault();
      return;
    }
    // Save Voluntary Works (AJAX)
    if (e.target.closest('#save-voluntary-works-btn')) {
      saveVoluntaryWorksAJAX();
      e.preventDefault();
      return;
    }
  });

  // --- On page load: both view mode ---
  switchWorkExperienceViewMode();
  switchVoluntaryWorksViewMode();

  // --- AJAX Save Functions ---
  function saveWorkExperienceAJAX() {
    const container = document.getElementById('work-experience-container');
    const rows = container.querySelectorAll('.work-experience-row');
    const work_experience = [];
    // Get profile_userid from hidden field in form
    const profile_userid = document.getElementById('profile_userid') ? document.getElementById('profile_userid').value : '';

    rows.forEach(row => {
      const id = row.querySelector('input[name="work_experience_id[]"]').value;
      const w_from_date = row.querySelector('input[name="work_from[]"]').value;
      const w_to_date = row.querySelector('input[name="work_to[]"]').value;
      const position_title = row.querySelector('input[name="position_title[]"]').value.toUpperCase();
      const agency_name = row.querySelector('input[name="department_agency[]"]').value.toUpperCase();
      const monthly_salary = row.querySelector('input[name="monthly_salary[]"]').value.toUpperCase();
      const sg_step = row.querySelector('input[name="salary_grade_step[]"]').value.toUpperCase();
      const status_appt = row.querySelector('input[name="status_of_appointment[]"]').value.toUpperCase();
      const government_service = row.querySelector('select[name="govt_service[]"]').value.toUpperCase();
      work_experience.push({
        id,
        w_from_date,
        w_to_date,
        position_title,
        agency_name,
        monthly_salary,
        sg_step,
        status_appt,
        government_service
      });
    });

    fetch('ajax/update_profile_tab4', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        section: 'work_experience',
        work_experience,
        profile_userid: profile_userid // Always send the profile_userid
      })
    }).then(r => r.json())
      .then(resp => {
        if (resp.success) {
          alert('Work Experience updated successfully!');
          switchWorkExperienceViewMode();
          //window.location.reload(); // Recommended: reload to ensure fresh DB data and reset to view mode
        } else {
          alert('Failed to update Work Experience: ' + (resp.message || 'Unknown error'));
        }
      }).catch(err => {
        alert('AJAX error: ' + err);
      });
  }

  function saveVoluntaryWorksAJAX() {
    const container = document.getElementById('voluntary-works-container');
    const rows = container.querySelectorAll('.voluntary-work-row');
    const voluntary_works = [];
    // Get profile_userid from hidden field in form
    const profile_userid = document.getElementById('profile_userid') ? document.getElementById('profile_userid').value : '';

    rows.forEach(row => {
      const id = row.querySelector('input[name="voluntary_work_id[]"]').value;
      const name_org_address = row.querySelector('input[name="vol_org_name_address[]"]').value.toUpperCase();
      const v_from_date = row.querySelector('input[name="vol_from[]"]').value;
      const v_to_date = row.querySelector('input[name="vol_to[]"]').value;
      const number_hours = row.querySelector('input[name="vol_number_of_hours[]"]').value;
      const position_nature_work = row.querySelector('input[name="vol_position_nature_of_work[]"]').value.toUpperCase();

      voluntary_works.push({
        id,
        name_org_address,
        v_from_date,
        v_to_date,
        number_hours,
        position_nature_work
      });
    });

    fetch('ajax/update_profile_tab4', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        section: 'voluntary_works',
        voluntary_works,
        profile_userid: profile_userid // Always send the profile_userid
      })
    }).then(r => r.json())
      .then(resp => {
        if (resp.success) {
          alert('Voluntary Works updated successfully!');
          switchVoluntaryWorksViewMode();
          //window.location.reload(); // Recommended: reload to ensure fresh DB data and reset to view mode
        } else {
          alert('Failed to update Voluntary Works: ' + (resp.message || 'Unknown error'));
        }
      }).catch(err => {
        alert('AJAX error: ' + err);
      });
  }
});
</script>

<script>
// ---- Learning & Development Tab Edit Mode, Add/Remove, and Button Events ----

document.addEventListener('DOMContentLoaded', function() {
  // --- Helper: HTML template for a new LD row ---
  window.getLDRowHtml = function() {
    return `
    <div class="grid grid-cols-1 sm:grid-cols-6 gap-2 items-center ld-row">
      <input type="hidden" name="ld_id[]" value="">
      <input type="text" name="ld_title[]" placeholder="Title" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="date" name="ld_from[]" placeholder="From" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="date" name="ld_to[]" placeholder="To" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="number" name="ld_number_of_hours[]" placeholder="Number of Hours" min="0" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="ld_type[]" placeholder="Type of LD" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="text" name="ld_conducted_by[]" placeholder="Conducted/Sponsored By" class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
        <button type="button" class="remove-ld-row flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
      <div class="sm:col-span-6 col-span-full">
        <hr class="my-4 border-t border-gray-200">
      </div>
    </div>
    `;
  };

  function setLDFieldsEnabled(enabled) {
    const container = document.getElementById('ld-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-ld-row').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-ld-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchLDViewMode() {
    setLDFieldsEnabled(false);
    const editBtn = document.getElementById('edit-button');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-ld-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-ld-btn');
    if (saveBtn) saveBtn.style.display = 'none';
  }

  function switchLDEditMode() {
    setLDFieldsEnabled(true);
    const editBtn = document.getElementById('edit-button');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-ld-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-ld-btn');
    if (saveBtn) saveBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) ---
  document.body.addEventListener('click', function(e) {
    // Add LD row
    if (e.target.closest('#add-ld-btn')) {
      var container = document.getElementById('ld-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getLDRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove LD row
    if (e.target.closest('.remove-ld-row')) {
      var row = e.target.closest('.ld-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-button')) {
      switchLDEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-ld-btn')) {
      switchLDViewMode();
      // window.location.reload(); // <-- Remove or comment out this line!
      e.preventDefault();
      return;
    }

    // Save button
    if (e.target.closest('#save-ld-btn')) {
      e.preventDefault();

      // Disable all fields while saving
      setLDFieldsEnabled(false);

      // Gather all LD data
      const rows = document.querySelectorAll('.ld-row');
      const ldData = [];
      rows.forEach(row => {
        ldData.push({
          id: row.querySelector('input[name="ld_id[]"]')?.value || '',
          title_learning: row.querySelector('input[name="ld_title[]"]')?.value.toUpperCase() || '',
          l_from_date: row.querySelector('input[name="ld_from[]"]')?.value || '',
          l_to_date: row.querySelector('input[name="ld_to[]"]')?.value || '',
          l_hours: row.querySelector('input[name="ld_number_of_hours[]"]')?.value || '',
          type_LD: row.querySelector('input[name="ld_type[]"]')?.value.toUpperCase() || '',
          sponsor: row.querySelector('input[name="ld_conducted_by[]"]')?.value.toUpperCase() || ''
        });
      });

      // --- Get profile_userid from hidden field and send it in payload ---
      const profile_userid = document.getElementById('profile_userid')?.value || '';

      fetch('/pulse/ajax/update_profile_tab5', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          ld: ldData
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setLDFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Learning & Development information saved!');
          switchLDViewMode();
          //window.location.reload();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setLDFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setLDFieldsEnabled(true);
      });
      return;
    }
  });

  // --- On page load: view mode ---
  switchLDViewMode();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Helper: HTML for a new skill row
  window.getSkillRowHtml = function() {
    return `
    <div class="grid grid-cols-1 gap-2 items-center skill-row">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="text" name="skills[]" placeholder="Skill"
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
        <button type="button"
          class="remove-skill-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
          border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  };

  function setSkillFieldsEnabled(enabled) {
    const container = document.getElementById('skills-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-skill-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-skill-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchSkillViewMode() {
    setSkillFieldsEnabled(false);
    const editBtn = document.getElementById('edit-skill-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-skill-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-skill-btn');
    if (saveBtn) saveBtn.style.display = 'none';
  }

  function switchSkillEditMode() {
    setSkillFieldsEnabled(true);
    const editBtn = document.getElementById('edit-skill-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-skill-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-skill-btn');
    if (saveBtn) saveBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) & Buttons ---
  document.body.addEventListener('click', function(e) {
    // Add Skill row
    if (e.target.closest('#add-skill-btn')) {
      var container = document.getElementById('skills-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getSkillRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove Skill row
    if (e.target.closest('.remove-skill-btn')) {
      var row = e.target.closest('.skill-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-skill-btn')) {
      switchSkillEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-skill-btn')) {
      switchSkillViewMode();
      // Optionally, reload data from server here if you want to reset the list
      //e.preventDefault();
      return;
    }

    // Save button
    if (e.target.closest('#save-skill-btn')) {
      // ----- AJAX SAVE LOGIC -----
      const profile_userid = document.getElementById('profile_userid')?.value || '';
      const skillRows = document.querySelectorAll('#skills-container .skill-row');
      const skills = [];
      const skill_ids = [];

      skillRows.forEach(row => {
        const skillInput = row.querySelector('input[name="skills[]"]');
        const idInput = row.querySelector('input[name="skill_id[]"]');
        // Log as uppercase for all fields (including empty, as per requirements)
        skills.push(skillInput ? (skillInput.value || '').toUpperCase() : '');
        skill_ids.push(idInput ? idInput.value : '');
      });

      const payload = {
        profile_userid: profile_userid,
        skills: skills,
        skill_id: skill_ids
      };

      const saveBtn = document.getElementById('save-skill-btn');
      if(saveBtn) saveBtn.disabled = true;

      fetch('/pulse/ajax/update_profile_tab6', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          if(saveBtn) saveBtn.disabled = false;
          return;
        }
        if(json.success) {
          alert('Skills saved!');
          switchSkillViewMode();
          // Optionally, reload tab or page here to reflect updated IDs
          // location.reload();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          if(saveBtn) saveBtn.disabled = false;
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        if(saveBtn) saveBtn.disabled = false;
      });

      e.preventDefault();
      return;
    }
  });

  // On page load: view mode
  switchSkillViewMode();
});
</script>

<script>
// --- TAB 7: Non-Academic Distinctions / Recognition ---
document.addEventListener('DOMContentLoaded', function () {
  // Helper: HTML for a new distinction row
  window.getDistinctionRowHtml = function() {
    return `
    <div class="grid grid-cols-1 gap-2 items-center distinction-row">
      <input type="hidden" name="distinction_id[]" value="">
      <input type="text" name="distinctions[]" placeholder="Title"
        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <button type="button"
        class="remove-distinction-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
        border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
      <div class="col-span-full">
        <hr class="my-4 border-t border-gray-200 dark:border-neutral-700">
      </div>
    </div>
    `;
  };

  function setDistinctionFieldsEnabled(enabled) {
    const container = document.getElementById('distinction-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-distinction-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    const addBtn = document.getElementById('add-distinction-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchDistinctionViewMode() {
    setDistinctionFieldsEnabled(false);
    const editBtn = document.getElementById('edit-distinction-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-distinction-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-distinction-btn');
    if (saveBtn) saveBtn.style.display = 'none';
  }

  function switchDistinctionEditMode() {
    setDistinctionFieldsEnabled(true);
    const editBtn = document.getElementById('edit-distinction-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-distinction-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-distinction-btn');
    if (saveBtn) saveBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) & Buttons ---
  document.body.addEventListener('click', function(e) {
    // Add Distinction row
    if (e.target.closest('#add-distinction-btn')) {
      var container = document.getElementById('distinction-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getDistinctionRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove Distinction row
    if (e.target.closest('.remove-distinction-btn')) {
      var row = e.target.closest('.distinction-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-distinction-btn')) {
      switchDistinctionEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-distinction-btn')) {
      switchDistinctionViewMode();
      e.preventDefault();
      return;
    }
  });

  // --- Save Changes Button (AJAX with user id from hidden field) ---
  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#save-distinction-btn')) {
      e.preventDefault();

      // Disable all fields while saving
      setDistinctionFieldsEnabled(false);

      // Gather all distinction data
      const rows = document.querySelectorAll('.distinction-row');
      const distinctions = [];
      const distinction_ids = [];
      rows.forEach(row => {
        distinctions.push(row.querySelector('input[name="distinctions[]"]')?.value || '');
        distinction_ids.push(row.querySelector('input[name="distinction_id[]"]')?.value || '');
      });

      // Get profile_userid from hidden field
      const profile_userid = document.getElementById('profile_userid')?.value || '';

      fetch('/pulse/ajax/update_profile_tab7', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          distinctions: distinctions,
          distinction_id: distinction_ids
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setDistinctionFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Distinctions saved!');
          switchDistinctionViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setDistinctionFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setDistinctionFieldsEnabled(true);
      });
      return;
    }
  });

  // On page load: view mode
  switchDistinctionViewMode();
});
</script>

<script>
// --- TAB 8: Membership in Association/Organization ---
document.addEventListener('DOMContentLoaded', function () {
  // Helper: HTML for a new membership row
  window.getMembershipRowHtml = function() {
    return `
    <div class="grid grid-cols-1 gap-2 items-center membership-row">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="hidden" name="membership_id[]" value="">
        <input type="text" name="organization_names[]" placeholder="Organization Name"
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
        <button type="button"
          class="remove-membership-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
          border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  };

  function setMembershipFieldsEnabled(enabled) {
    const container = document.getElementById('membership-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-membership-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    // Control visibility of Add button
    const addBtn = document.getElementById('add-membership-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchMembershipViewMode() {
    setMembershipFieldsEnabled(false);
    // Edit button visible, others hidden
    const editBtn = document.getElementById('edit-membership-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-membership-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-membership-btn');
    if (saveBtn) saveBtn.style.display = 'none';
    const addBtn = document.getElementById('add-membership-btn');
    if (addBtn) addBtn.style.display = 'none';
  }

  function switchMembershipEditMode() {
    setMembershipFieldsEnabled(true);
    // Edit button hidden, others visible
    const editBtn = document.getElementById('edit-membership-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-membership-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-membership-btn');
    if (saveBtn) saveBtn.style.display = '';
    const addBtn = document.getElementById('add-membership-btn');
    if (addBtn) addBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) & Buttons ---
  document.body.addEventListener('click', function(e) {
    // Add Membership row
    if (e.target.closest('#add-membership-btn')) {
      var container = document.getElementById('membership-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getMembershipRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove Membership row
    if (e.target.closest('.remove-membership-btn')) {
      var row = e.target.closest('.membership-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-membership-btn')) {
      switchMembershipEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-membership-btn')) {
      switchMembershipViewMode();
      e.preventDefault();
      return;
    }
  });

  // --- Save Changes Button (AJAX with user id from hidden field) ---
  document.body.addEventListener('click', function(e) {
  if (e.target.closest('#save-membership-btn')) {
    e.preventDefault();

    // Gather all membership data
    const rows = document.querySelectorAll('.membership-row');
    const organization_names = [];
    const membership_ids = [];
    rows.forEach(row => {
      organization_names.push(row.querySelector('input[name="organization_names[]"]')?.value || '');
      membership_ids.push(row.querySelector('input[name="membership_id[]"]')?.value || '');
    });

    // Get profile_userid from hidden field
    const profile_userid = document.getElementById('profile_userid')?.value || '';

    // Disable fields while saving
    setMembershipFieldsEnabled(false);

      fetch('/pulse/ajax/update_profile_tab8', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          organization_names: organization_names,
          membership_id: membership_ids
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setMembershipFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Memberships saved!');
          switchMembershipViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setMembershipFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setMembershipFieldsEnabled(true);
      });
      return;
    }
  });

  // On page load: view mode (hides Add, Cancel, Save, shows Edit)
  switchMembershipViewMode();
});
</script>


<script>
// --- TAB 9: Personal Disclosure ---
document.addEventListener('DOMContentLoaded', function () {
  // List all field IDs
  const disclosureFields = [
    'q1','q2','r2','q3','r3','q4','r4_1','r4_2',
    'q5','r5','q6','r6','q7','r7','q8','r8',
    'q9','r9','q10','r10','q11','r11','q12','r12',
    'q13','r13','q14','r14'
  ];

  function setDisclosureFieldsEnabled(enabled) {
    disclosureFields.forEach(function(key) {
      var el = document.getElementById(key);
      if (el) el.disabled = !enabled;
    });
  }

  function switchDisclosureViewMode() {
    setDisclosureFieldsEnabled(false);
    const editBtn = document.getElementById('edit-disclosure-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-disclosure-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-disclosure-btn');
    if (saveBtn) saveBtn.style.display = 'none';
  }

  function switchDisclosureEditMode() {
    setDisclosureFieldsEnabled(true);
    const editBtn = document.getElementById('edit-disclosure-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-disclosure-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-disclosure-btn');
    if (saveBtn) saveBtn.style.display = '';
  }

  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#edit-disclosure-btn')) {
      switchDisclosureEditMode();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#cancel-disclosure-btn')) {
      switchDisclosureViewMode();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#save-disclosure-btn')) {
      // Gather payload
      const payload = {};
      payload.profile_userid = document.getElementById('profile_userid').value;
      disclosureFields.forEach(function(key) {
        var el = document.getElementById(key);
        if (el) payload[key] = el.value;
      });

      // Disable save to prevent double submit
      const saveBtn = document.getElementById('save-disclosure-btn');
      if (saveBtn) saveBtn.disabled = true;

      fetch('/pulse/ajax/update_profile_tab9', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          alert('Personal disclosure updated!');
          switchDisclosureViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.'));
        }
      })
      .catch(err => {
        alert('AJAX/network error: ' + err);
      })
      .finally(() => {
        if (saveBtn) saveBtn.disabled = false;
      });

      e.preventDefault();
      return;
    }
  });

  // On page load: view mode
  switchDisclosureViewMode();
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
  // List all input/select field ids for the disclosure form
  const fields = [
    'q1','q2','r2','q3','r3','q4','r4_1','r4_2',
    'q5','r5','q6','r6','q7','r7','q8','r8',
    'q9','r9','q10','r10','q11','r11','q12','r12',
    'q13','r13','q14','r14'
  ];

  // Utility for disabling or enabling all fields
  function setFieldsDisabled(disabled) {
    fields.forEach(function(key) {
      var el = document.getElementById(key);
      if (el) el.disabled = disabled;
    });
  }

  // Utility to control button visibility
  function setButtonVisibility(editVisible, cancelVisible, saveVisible) {
    const editBtn = document.getElementById('edit-disclosure-btn');
    const cancelBtn = document.getElementById('cancel-disclosure-btn');
    const saveBtn = document.getElementById('save-disclosure-btn');
    if (editBtn) editBtn.style.display = editVisible ? '' : 'none';
    if (cancelBtn) cancelBtn.style.display = cancelVisible ? '' : 'none';
    if (saveBtn) saveBtn.style.display = saveVisible ? '' : 'none';
  }

  // On page load: disable all fields and only show Edit button
  setFieldsDisabled(true);
  setButtonVisibility(true, false, false);

  // Click handler for Edit
  document.getElementById('edit-disclosure-btn').addEventListener('click', function(e) {
    setFieldsDisabled(false);
    setButtonVisibility(false, true, true);
  });

  // Click handler for Cancel
  document.getElementById('cancel-disclosure-btn').addEventListener('click', function(e) {
    setFieldsDisabled(true);
    setButtonVisibility(true, false, false);
    // Optionally, reload the page or restore initial values here if needed
  });

  // On Save, you may want to re-disable and hide buttons after successful submit via AJAX
  // (AJAX logic not included here)
});
</script>

<script>
// --- TAB 10: References ---
document.addEventListener('DOMContentLoaded', function () {
  // Helper: HTML for a new reference row
  window.getReferenceRowHtml = function() {
    return `
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center reference-row">
      <input type="hidden" name="ref_id[]" value="">
      <input type="text" name="ref_name[]" placeholder="Name"
        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="ref_address[]" placeholder="Address"
        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="text" name="ref_tel_no[]" placeholder="Tel. No."
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs sm:text-sm rounded-lg">
        <button type="button"
          class="remove-reference-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
          border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  };

  function setReferenceFieldsEnabled(enabled) {
    const container = document.getElementById('references-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-reference-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    // Control visibility of Add button
    const addBtn = document.getElementById('add-reference-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchReferenceViewMode() {
    setReferenceFieldsEnabled(false);
    // Edit button visible, others hidden
    const editBtn = document.getElementById('edit-references-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-references-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-references-btn');
    if (saveBtn) saveBtn.style.display = 'none';
    const addBtn = document.getElementById('add-reference-btn');
    if (addBtn) addBtn.style.display = 'none';
  }

  function switchReferenceEditMode() {
    setReferenceFieldsEnabled(true);
    // Edit button hidden, others visible
    const editBtn = document.getElementById('edit-references-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-references-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-references-btn');
    if (saveBtn) saveBtn.style.display = '';
    const addBtn = document.getElementById('add-reference-btn');
    if (addBtn) addBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) & Buttons ---
  document.body.addEventListener('click', function(e) {
    // Add Reference row
    if (e.target.closest('#add-reference-btn')) {
      var container = document.getElementById('references-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getReferenceRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove Reference row
    if (e.target.closest('.remove-reference-btn')) {
      var row = e.target.closest('.reference-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-references-btn')) {
      switchReferenceEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-references-btn')) {
      switchReferenceViewMode();
      e.preventDefault();
      return;
    }
  });

  // --- Save Changes Button (AJAX with user id from hidden field) ---
  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#save-references-btn')) {
      e.preventDefault();

      // Gather all reference data
      const rows = document.querySelectorAll('.reference-row');
      const ref_names = [];
      const ref_addresses = [];
      const ref_tel_nos = [];
      const ref_ids = [];
      rows.forEach(row => {
        ref_names.push(row.querySelector('input[name="ref_name[]"]')?.value || '');
        ref_addresses.push(row.querySelector('input[name="ref_address[]"]')?.value || '');
        ref_tel_nos.push(row.querySelector('input[name="ref_tel_no[]"]')?.value || '');
        ref_ids.push(row.querySelector('input[name="ref_id[]"]')?.value || '');
      });

      // Get profile_userid from hidden field
      const profile_userid = document.getElementById('profile_userid')?.value || '';

      // Disable fields while saving
      setReferenceFieldsEnabled(false);

      fetch('/pulse/ajax/update_profile_tab10', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          ref_name: ref_names,
          ref_address: ref_addresses,
          ref_tel_no: ref_tel_nos,
          ref_id: ref_ids
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setReferenceFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('References saved!');
          switchReferenceViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setReferenceFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setReferenceFieldsEnabled(true);
      });
      return;
    }
  });

  // On page load: view mode (hides Add, Cancel, Save, shows Edit)
  switchReferenceViewMode();
});
</script>

<script>
// --- TAB 11: Emergency Contact ---
document.addEventListener('DOMContentLoaded', function () {
  // Helper: HTML for a new emergency contact row
  window.getEmergencyRowHtml = function() {
    return `
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center emergency-row">
      <input type="hidden" name="e_id[]" value="">
      <input type="text" name="e_fullname[]" placeholder="Full Name"
        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <input type="text" name="e_contact_number[]" placeholder="Contact Number"
        class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
        <input type="text" name="e_relationship[]" placeholder="Relationship"
          class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg">
        <button type="button"
          class="remove-emergency-btn flex shrink-0 justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg
          border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 ml-0 sm:ml-1">
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
    `;
  };

  function setEmergencyFieldsEnabled(enabled) {
    const container = document.getElementById('emergency-container');
    if (!container) return;
    container.querySelectorAll('input').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-emergency-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });
    // Control visibility of Add button
    const addBtn = document.getElementById('add-emergency-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchEmergencyViewMode() {
    setEmergencyFieldsEnabled(false);
    const editBtn = document.getElementById('edit-emergency-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-emergency-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-emergency-btn');
    if (saveBtn) saveBtn.style.display = 'none';
    const addBtn = document.getElementById('add-emergency-btn');
    if (addBtn) addBtn.style.display = 'none';
  }

  function switchEmergencyEditMode() {
    setEmergencyFieldsEnabled(true);
    const editBtn = document.getElementById('edit-emergency-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-emergency-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-emergency-btn');
    if (saveBtn) saveBtn.style.display = '';
    const addBtn = document.getElementById('add-emergency-btn');
    if (addBtn) addBtn.style.display = '';
  }

  // --- Dynamic Row Management (Add/Remove) & Buttons ---
  document.body.addEventListener('click', function(e) {
    // Add Emergency Contact row
    if (e.target.closest('#add-emergency-btn')) {
      var container = document.getElementById('emergency-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getEmergencyRowHtml());
      }
      e.preventDefault();
      return;
    }

    // Remove Emergency Contact row
    if (e.target.closest('.remove-emergency-btn')) {
      var row = e.target.closest('.emergency-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }

    // Edit button
    if (e.target.closest('#edit-emergency-btn')) {
      switchEmergencyEditMode();
      e.preventDefault();
      return;
    }

    // Cancel button
    if (e.target.closest('#cancel-emergency-btn')) {
      switchEmergencyViewMode();
      e.preventDefault();
      return;
    }
  });

  // --- Save Changes Button (AJAX with user id from hidden field) ---
  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#save-emergency-btn')) {
      e.preventDefault();

      // Gather all emergency contact data
      const rows = document.querySelectorAll('.emergency-row');
      const e_fullname = [];
      const e_contact_number = [];
      const e_relationship = [];
      const e_id = [];
      rows.forEach(row => {
        e_fullname.push(row.querySelector('input[name="e_fullname[]"]')?.value || '');
        e_contact_number.push(row.querySelector('input[name="e_contact_number[]"]')?.value || '');
        e_relationship.push(row.querySelector('input[name="e_relationship[]"]')?.value || '');
        e_id.push(row.querySelector('input[name="e_id[]"]')?.value || '');
      });

      // Get profile_userid from hidden field
      const profile_userid = document.getElementById('profile_userid')?.value || '';

      // Disable fields while saving
      setEmergencyFieldsEnabled(false);

      fetch('/pulse/ajax/update_profile_tab11', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          e_fullname: e_fullname,
          e_contact_number: e_contact_number,
          e_relationship: e_relationship,
          e_id: e_id
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setEmergencyFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Emergency contacts saved!');
          switchEmergencyViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setEmergencyFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setEmergencyFieldsEnabled(true);
      });
      return;
    }
  });

  // On page load: view mode (hides Add, Cancel, Save, shows Edit)
  switchEmergencyViewMode();
});
</script>

<script>
// --- TAB 12: Educational Background ---
document.addEventListener('DOMContentLoaded', function () {
  window.getEducRowHtml = function() {
    return `
    <div class="educ-row space-y-2">
      <input type="hidden" name="educ_id[]" value="">
      <input type="text" name="schoolname[]" placeholder="Name of School"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <div>
        <select name="basic_degree_course[]"
          class="educ-basic-degree-course w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
          <option value="">- Select -</option>
          <option value="ELEMENTARY GRADUATE">ELEMENTARY GRADUATE</option>
          <option value="ELEMENTARY UNDERGRADUATE">ELEMENTARY UNDERGRADUATE</option>
          <option value="JUNIOR HIGHSCHOOL GRADUATE">JUNIOR HIGHSCHOOL GRADUATE</option>
          <option value="JUNIOR HIGHSCHOOL UNDERGRADUATE">JUNIOR HIGHSCHOOL UNDERGRADUATE</option>
          <option value="SENIOR HIGHSCHOOL GRADUATE">SENIOR HIGHSCHOOL GRADUATE</option>
          <option value="SENIOR HIGHSCHOOL UNDERGRADUATE">SENIOR HIGHSCHOOL UNDERGRADUATE</option>
          <option value="SECONDARY GRADUATE">SECONDARY GRADUATE</option>
          <option value="SECONDARY UNDERGRADUATE">SECONDARY UNDERGRADUATE</option>
          <option value="OTHERS">OTHERS</option>
        </select>
        <input type="text" name="basic_degree_course_other[]" placeholder="Other Course"
          class="educ-basic-degree-other w-full py-1.5 px-3 mt-1 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
          style="display:none;" disabled>
      </div>
      <input type="text" name="from_date[]" placeholder="From"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <input type="text" name="to_date[]" placeholder="To"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <input type="text" name="units_earned[]" placeholder="Units Earned"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <input type="text" name="year_grad[]" placeholder="Year Graduated"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <input type="text" name="honor[]" placeholder="Honors Received"
        class="w-full py-1.5 px-3 block border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" disabled>
      <button type="button"
        class="remove-educ-btn flex justify-center items-center gap-2 size-9.5 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:border-neutral-700"
        disabled>
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
    `;
  };

  function setEducFieldsEnabled(enabled) {
    const container = document.getElementById('educ-container');
    if (!container) return;
    container.querySelectorAll('input,select,button.remove-educ-btn').forEach(input => input.disabled = !enabled);
    container.querySelectorAll('.remove-educ-btn').forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
    });
    const addBtn = document.getElementById('add-educ-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  function switchEducViewMode() {
    setEducFieldsEnabled(false);
    const editBtn = document.getElementById('edit-educ-btn');
    if (editBtn) editBtn.style.display = '';
    const cancelBtn = document.getElementById('cancel-educ-btn');
    if (cancelBtn) cancelBtn.style.display = 'none';
    const saveBtn = document.getElementById('save-educ-btn');
    if (saveBtn) saveBtn.style.display = 'none';
    const addBtn = document.getElementById('add-educ-btn');
    if (addBtn) addBtn.style.display = 'none';
  }

  function switchEducEditMode() {
    setEducFieldsEnabled(true);
    const editBtn = document.getElementById('edit-educ-btn');
    if (editBtn) editBtn.style.display = 'none';
    const cancelBtn = document.getElementById('cancel-educ-btn');
    if (cancelBtn) cancelBtn.style.display = '';
    const saveBtn = document.getElementById('save-educ-btn');
    if (saveBtn) saveBtn.style.display = '';
    const addBtn = document.getElementById('add-educ-btn');
    if (addBtn) addBtn.style.display = '';
  }

  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#add-educ-btn')) {
      var container = document.getElementById('educ-container');
      if (container) {
        container.insertAdjacentHTML('beforeend', getEducRowHtml());
        setEducFieldsEnabled(true); // Enable all fields (including new one)
      }
      e.preventDefault();
      return;
    }
    if (e.target.closest('.remove-educ-btn')) {
      var row = e.target.closest('.educ-row');
      if (row) {
        row.remove();
      }
      e.preventDefault();
      return;
    }
    if (e.target.closest('#edit-educ-btn')) {
      switchEducEditMode();
      e.preventDefault();
      return;
    }
    if (e.target.closest('#cancel-educ-btn')) {
      switchEducViewMode();
      e.preventDefault();
      return;
    }
  });

  document.body.addEventListener('change', function(e) {
    if (e.target.matches('.educ-basic-degree-course')) {
      var select = e.target;
      var input = select.parentElement.querySelector('.educ-basic-degree-other');
      if (select.value === "OTHERS") {
        select.style.display = "none";
        input.style.display = "";
        input.value = "";
        input.disabled = false;
        input.focus();
      } else {
        input.style.display = "none";
        select.style.display = "";
        input.value = "";
        input.disabled = select.disabled;
      }
    }
  });

  document.body.addEventListener('blur', function(e) {
    if (e.target.matches('.educ-basic-degree-other')) {
      var input = e.target;
      var select = input.parentElement.querySelector('.educ-basic-degree-course');
      if (input.value.trim() === "") {
        input.style.display = "none";
        select.style.display = "";
        select.value = "";
      }
    }
  }, true);

  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#save-educ-btn')) {
      e.preventDefault();
      const rows = document.querySelectorAll('.educ-row');
      const schoolname = [];
      const basic_degree_course = [];
      const from_date = [];
      const to_date = [];
      const units_earned = [];
      const year_grad = [];
      const honor = [];
      const educ_id = [];
      rows.forEach(row => {
        schoolname.push(row.querySelector('input[name="schoolname[]"]')?.value || '');
        let select = row.querySelector('select[name="basic_degree_course[]"]');
        let other = row.querySelector('input[name="basic_degree_course_other[]"]');
        if(select && select.style.display === "none" && other && other.style.display !== "none"){
          basic_degree_course.push(other.value || '');
        } else if(select){
          basic_degree_course.push(select.value || '');
        } else {
          basic_degree_course.push('');
        }
        from_date.push(row.querySelector('input[name="from_date[]"]')?.value || '');
        to_date.push(row.querySelector('input[name="to_date[]"]')?.value || '');
        units_earned.push(row.querySelector('input[name="units_earned[]"]')?.value || '');
        year_grad.push(row.querySelector('input[name="year_grad[]"]')?.value || '');
        honor.push(row.querySelector('input[name="honor[]"]')?.value || '');
        educ_id.push(row.querySelector('input[name="educ_id[]"]')?.value || '');
      });
      const profile_userid = document.getElementById('profile_userid')?.value || '';
      setEducFieldsEnabled(false);
      fetch('/pulse/ajax/update_profile_tab12', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          profile_userid: profile_userid,
          schoolname: schoolname,
          basic_degree_course: basic_degree_course,
          from_date: from_date,
          to_date: to_date,
          units_earned: units_earned,
          year_grad: year_grad,
          honor: honor,
          educ_id: educ_id
        })
      })
      .then(async res => {
        let responseText = await res.text();
        let json;
        try {
          json = JSON.parse(responseText);
        } catch (e) {
          alert('Server returned invalid JSON:\n\n' + responseText);
          setEducFieldsEnabled(true);
          return;
        }
        if(json.success) {
          alert('Educational background saved!');
          switchEducViewMode();
        } else {
          alert('Save error: ' + (json.message || 'Failed to save changes.') + "\n\nRaw response:\n" + responseText);
          setEducFieldsEnabled(true);
        }
      })
      .catch(err => {
        alert('AJAX/network error:\n\n' + err);
        setEducFieldsEnabled(true);
      });
      return;
    }
  });

  switchEducViewMode();
});
</script>

  </body>
</html>

