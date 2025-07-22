<?php
require_once('init.php');

// Restrict access: Only ADMINISTRATOR level and HR or SUPERADMIN category
if (
    !isset($_SESSION['level']) || $_SESSION['level'] !== 'ADMINISTRATOR' ||
    !isset($_SESSION['category']) || 
    ($_SESSION['category'] !== 'HR' && $_SESSION['category'] !== 'SUPERADMIN')
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
  
  <?php include 'includes/sidebar.php'; ?>
  
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
      Upload Timekeeping Logs
    </h2>
    <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
      Upload the .xls file containing the logs of employee time in and out.
    </p>

    <form method="post" enctype="multipart/form-data" id="tardiness-upload-form">
      <!-- File Upload Section -->
      <div class="grid sm:grid-cols-12 gap-2 sm:gap-4 py-8 first:pt-0 last:pb-0 border-t first:border-transparent border-gray-200 dark:border-neutral-700 dark:first:border-transparent">
        <div class="sm:col-span-3">
          <label for="tardiness_file" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
            Time Keeping File (.xls)
          </label>
        </div>
        <div class="sm:col-span-9">
          <label for="tardiness_file" class="sr-only">Choose file</label>
          <input type="file" name="tardiness_file" id="tardiness_file"
            class="block w-full border border-gray-200 rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400
            file:bg-gray-50 file:border-0
            file:bg-gray-100 file:me-4
            file:py-2 file:px-4
            dark:file:bg-neutral-700 dark:file:text-neutral-400"
            required>
        </div>
      </div>
      <!-- End File Upload Section -->

      <div class="mt-1 flex justify-end gap-x-2">
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
          Cancel
        </button>
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
          Save changes
        </button>
      </div>
      
    </form>

  </div>
</div>
<!-- End Card -->

<!-- Card -->
<div class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl dark:bg-neutral-900 dark:border-neutral-700 dark:shadow-neutral-700/70">
  <!-- Select (Mobile only) -->
  <div class="sm:hidden">
    <label for="hs-card-nav" class="sr-only">Select a nav</label>
    <select id="hs-card-nav" class="block w-full border-t-0 border-x-0 border-gray-300 rounded-t-xl text-center focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400">
      <option value="logs" selected>Logs</option>
      <option value="tardiness">Tardiness</option>
    </select>
  </div>
  <!-- End Select -->

  <!-- Nav (Device only) -->
  <div class="hidden sm:block">
    <nav class="relative z-0 flex border-b border-gray-200 rounded-xl divide-x divide-gray-200 dark:border-neutral-700 dark:divide-neutral-700">
      <button type="button"
              class="tab-link group relative min-w-0 flex-1 bg-white py-4 px-4 text-sm font-medium text-center rounded-ss-xl focus:outline-none dark:bg-neutral-900 dark:border-b-blue-500"
              data-tab="logs"
              aria-current="true">
        Logs
      </button>
      <button type="button"
              class="tab-link group relative min-w-0 flex-1 bg-white py-4 px-4 text-sm font-medium text-center rounded-se-xl focus:outline-none dark:bg-neutral-900"
              data-tab="tardiness">
        Tardiness
      </button>
    </nav>
  </div>
  <!-- End Nav -->

  <!-- Tab Panels -->
<div class="p-4 text-left md:py-7 md:px-5">
<!-- Logs Panel -->
<div id="tab-panel-logs" class="tab-panel">
  <div class="bg-white border border-white rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
    <div class="p-10">
      <h3 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
        Logs
      </h3>
      <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
        With supporting text below as a natural lead-in to additional content.
      </p>

      <form method="post" id="logs-form">
        <!-- Inclusive Date Fields -->
        <div class="mb-4 flex flex-col sm:flex-row gap-4">
          <div class="flex-1">
            <label for="logs-date-from" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">
              From
            </label>
            <input type="date" id="logs-date-from" name="logs_date_from"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
          </div>
          <div class="flex-1">
            <label for="logs-date-to" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">
              To
            </label>
            <input type="date" id="logs-date-to" name="logs_date_to"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
          </div>
        </div>
        <!-- End Inclusive Date Fields -->

        <!-- Separator -->
        <div class="mb-4 py-0 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200 dark:text-white dark:after:border-neutral-600"></div>
        <!-- End Separator -->

        <!-- Search Box -->
        <div class="mb-4 flex-1 relative max-w-full">
          <label for="logs-employee-search" class="sr-only">Search</label>
          <input type="text" id="logs-employee-search" name="logs_employee_search"
            class="py-1.5 sm:py-2 px-3 ps-9 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
            placeholder="Search for employee" autocomplete="off">
          <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3">
            <svg class="size-4 text-gray-400 dark:text-neutral-500" xmlns="http://www.w3.org/2000/svg"
              width="24" height="24" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="m21 21-4.3-4.3"></path>
            </svg>
          </div>
          <!-- Suggestions Dropdown -->
          <ul id="employee-suggestions" class="absolute z-10 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto hidden dark:bg-neutral-900 dark:border-neutral-700"></ul>
        </div>
        <!-- End Search Box -->        

        <!-- Edit Logs Button -->
        <div class="mb-4">
          <a href=""
            class="w-9 h-9 py-1 px-4 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 font-medium bg-white text-gray-700 shadow-2xs align-middle hover:bg-gray-50 focus:outline-none focus:ring-0 transition-all text-sm dark:bg-neutral-900 dark:hover:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:hover:text-white dark:focus:ring-offset-gray-800"
            title="Edit Logs">
            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 3a2.828 2.828 0 0 1 4 4L7 21H3v-4L17 3z"/>
            </svg>
          </a>
        </div>
        <!-- End Edit Logs Button -->

        <!-- Time Picker Fields -->
        <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 mb-6">
          <!-- Date -->
          <div class="flex flex-col">
            <label for="logs-date" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Date</label>
            <input type="text"
              id="logs-date"
              name="logs_date"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="1 - Monday">
          </div>
          <!-- Time In (AM) -->
          <div class="flex flex-col">
            <label for="logs-time-in-am" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Time In (AM)</label>
            <input type="time"
              id="logs-time-in-am"
              name="logs_time_in_am"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Time In">
          </div>
          <!-- Time Out (AM) -->
          <div class="flex flex-col">
            <label for="logs-time-out-am" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Time Out (AM)</label>
            <input type="time"
              id="logs-time-out-am"
              name="logs_time_out_am"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Time Out">
          </div>
          <!-- Time In (PM) -->
          <div class="flex flex-col">
            <label for="logs-time-in-pm" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Time In (PM)</label>
            <input type="time"
              id="logs-time-in-pm"
              name="logs_time_in_pm"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Time In">
          </div>
          <!-- Time Out (PM) -->
          <div class="flex flex-col">
            <label for="logs-time-out-pm" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Time Out (PM)</label>
            <input type="time"
              id="logs-time-out-pm"
              name="logs_time_out_pm"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Time Out">
          </div>
          <!-- Total -->
          <div class="flex flex-col">
            <label for="logs-total-tardiness" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Total T/U in Minutes</label>
            <input type="text"
              id="logs-total-tardiness"
              name="logs_total_tardiness"
              class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="36" readonly>
          </div>
        </div>
        <!-- End Time Picker Fields -->

        <div class="mt-1 flex justify-end gap-x-2">
          <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
            Cancel
          </button>
          <button type="submit" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Save changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

  <!-- Tardiness Panel -->
  <div id="tab-panel-tardiness" class="tab-panel hidden">
    <div class="bg-white border border-white rounded-xl shadow-2xs overflow-hidden dark:bg-neutral-800 dark:border-neutral-700">
      <div class="p-10">
        <h3 class="text-xl font-bold text-gray-800 dark:text-neutral-200">
          Tardiness
        </h3>
        <p class="text-sm text-gray-600 dark:text-neutral-400 mb-8">
          With supporting text below as a natural lead-in to additional content.
        </p>

        <form method="post" id="tardiness-form">
          <!-- Inclusive Date Fields -->
          <div class="mb-4 flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
              <label for="inclusive-date-from" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">
                From
              </label>
              <input type="date" id="inclusive-date-from" name="inclusive_date_from"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
            </div>
            <div class="flex-1">
              <label for="inclusive-date-to" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">
                To
              </label>
              <input type="date" id="inclusive-date-to" name="inclusive_date_to"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
            </div>
          </div>
          <!-- End Inclusive Date Fields -->

          <!-- Separator -->
          <div class="mb-4 py-0 flex items-center text-sm text-gray-800 after:flex-1 after:border-t after:border-gray-200 dark:text-white dark:after:border-neutral-600"></div>
          <!-- End Separator -->

          <!-- Time Picker Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <!-- Employee Name -->
            <div class="flex flex-col">
              <label for="employee-id" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Employee Name</label>
              <input type="text"
                id="employee-id"
                name="employee_id"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                placeholder="Dela Cruz, Juan T.">
            </div>
            <!-- Total -->
            <div class="flex flex-col">
              <label for="total-tardiness" class="block text-sm font-normal text-gray-500 mb-1 dark:text-neutral-500">Total T/U in Minutes</label>
              <input type="text"
                id="total-tardiness"
                name="total_tardiness"
                class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 shadow-2xs rounded-lg sm:text-sm relative focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
                placeholder="36" readonly>
            </div>
          </div>
          <!-- End Time Picker Fields -->

          <div class="mt-1 flex justify-end gap-x-2">
            <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none focus:outline-hidden focus:bg-gray-50 dark:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              Cancel
            </button>
            <button type="submit" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
              Save changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- End Card -->

</div>
</div>
<!-- End Content -->

<!-- Required plugins -->
<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script src="/pulse/js/secure.js"></script>

<!-- Script to handle tab switching -->
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const tabLinks = document.querySelectorAll(".tab-link");
    const selectNav = document.getElementById("hs-card-nav");
    const panels = document.querySelectorAll(".tab-panel");

    function activateTab(tabName) {
      panels.forEach(panel => {
        panel.classList.toggle("hidden", panel.id !== `tab-panel-${tabName}`);
      });

      tabLinks.forEach(link => {
        const isActive = link.dataset.tab === tabName;
        link.classList.toggle("text-blue-600", isActive);
        link.classList.toggle("border-b-blue-600", isActive);
        link.classList.toggle("text-gray-500", !isActive);
        link.classList.toggle("border-b-transparent", !isActive);
      });

      selectNav.value = tabName;
    }

    tabLinks.forEach(link => {
      link.addEventListener("click", () => {
        activateTab(link.dataset.tab);
      });
    });

    selectNav.addEventListener("change", (e) => {
      activateTab(e.target.value);
    });

    // Initialize default tab
    activateTab("logs");
  });
</script>

<script>
        document.addEventListener("DOMContentLoaded", function() {
          const searchInput = document.getElementById("logs-employee-search");
          const suggestionsBox = document.getElementById("employee-suggestions");

          let debounceTimeout;

          searchInput.addEventListener("input", function() {
            const query = this.value.trim();
            clearTimeout(debounceTimeout);

            if (query.length < 2) {
              suggestionsBox.innerHTML = "";
              suggestionsBox.style.display = "none";
              return;
            }

            debounceTimeout = setTimeout(() => {
              fetch(`/pulse/ajax/search_employee.php?q=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            suggestionsBox.innerHTML = "";
            if (data.length > 0) {
              data.forEach(emp => {
                const li = document.createElement("li");
                li.textContent = emp.fullname;
                li.className = "px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-800";
                li.addEventListener("mousedown", function(e) {
            searchInput.value = emp.fullname;
            suggestionsBox.innerHTML = "";
            suggestionsBox.style.display = "none";
                });
                suggestionsBox.appendChild(li);
              });
              suggestionsBox.style.display = "block";
            } else {
              suggestionsBox.style.display = "none";
            }
          });
            }, 200);
          });

          // Hide suggestions on blur
          searchInput.addEventListener("blur", function() {
            setTimeout(() => {
              suggestionsBox.style.display = "none";
            }, 100);
          });
        });
        </script>

</body>
</html>

