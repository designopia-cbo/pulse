<?php
require_once('init.php');

// Handle signature upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_signature') {
    $userid = $_SESSION['userid'];
    
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/signatures/';
        $fileName = $userid . '.png';
        $uploadPath = $uploadDir . $fileName;
        
        // Check if the uploaded file is a PNG
        $fileInfo = getimagesize($_FILES['signature']['tmp_name']);
        if ($fileInfo !== false && $fileInfo['mime'] === 'image/png') {
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadPath)) {
                echo json_encode(['status' => 'success', 'message' => 'Signature uploaded successfully.']);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save signature file.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Please upload a valid PNG file.']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error.']);
        exit;
    }
}

// Handle AJAX request for validation and saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_and_save_leave') {
    $userid = strtoupper($_POST['userid']); // Convert to uppercase
    $leave_type = strtoupper($_POST['leave_type']); // Convert to uppercase
    $leave_details = strtoupper($_POST['leave_details']); // Convert to uppercase
    $leave_reason = strtoupper($_POST['leave_reason']); // Convert to uppercase
    $start_date = strtoupper($_POST['start_date']); // Convert to uppercase
    $end_date = strtoupper($_POST['end_date']); // Convert to uppercase
    $total_days = strtoupper($_POST['total_days']); // Convert to uppercase
    $appdate = strtoupper(date('Y-m-d')); // Convert to uppercase
    $leave_status = 1; // Leave status is always integer 1

    // Fetch credit leave data for the current user
    $stmt = $pdo->prepare("SELECT * FROM credit_leave WHERE userid = :userid");
    $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
    $stmt->execute();
    $credit_leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$credit_leave) {
        echo json_encode(['status' => 'error', 'message' => 'User leave credit data not found.']);
        exit;
    }

    // Validation logic based on leave type
    if ($leave_type === "VACATION LEAVE") {
        $remaining_balance = $credit_leave['vacationleave'] - $total_days;
        if ($remaining_balance < 5) {
            echo json_encode(['status' => 'error', 'message' => 'Not enough credit for Vacation Leave.']);
            exit;
        }
    } elseif ($leave_type === "SICK LEAVE") {
        $remaining_balance = $credit_leave['sickleave'] - $total_days;
        if ($remaining_balance < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Not enough credit for Sick Leave.']);
            exit;
        }
    } elseif ($leave_type === "SPECIAL PRIVILEGE LEAVE") {
        $remaining_balance = $credit_leave['spleave'] - $total_days;
        if ($remaining_balance < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Not enough credit for Special Privilege Leave.']);
            exit;
        }
    }

    // NEW: Fetch the user's active employment details to get supervisor, manager, hr
    $empDetailsStmt = $pdo->prepare("SELECT supervisor, manager, hr FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
    $empDetailsStmt->bindParam(':userid', $userid, PDO::PARAM_INT);
    $empDetailsStmt->execute();
    $empDetails = $empDetailsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$empDetails) {
        echo json_encode(['status' => 'error', 'message' => 'Active employment details not found for user.']);
        exit;
    }

    $supervisor = $empDetails['supervisor'];
    $manager = $empDetails['manager'];
    $hr = $empDetails['hr'];

    // Save the leave application to the emp_leave table (now with hr, supervisor, manager)
    $stmt = $pdo->prepare("
        INSERT INTO emp_leave 
        (userid, leave_type, leave_details, leave_reason, startdate, enddate, total_leave_days, leave_status, appdate, hr, supervisor, manager) 
        VALUES (:userid, :leave_type, :leave_details, :leave_reason, :startdate, :enddate, :total_days, :leave_status, :appdate, :hr, :supervisor, :manager)
    ");
    $stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
    $stmt->bindParam(':leave_type', $leave_type, PDO::PARAM_STR);
    $stmt->bindParam(':leave_details', $leave_details, PDO::PARAM_STR);
    $stmt->bindParam(':leave_reason', $leave_reason, PDO::PARAM_STR);
    $stmt->bindParam(':startdate', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':enddate', $end_date, PDO::PARAM_STR);
    $stmt->bindParam(':total_days', $total_days, PDO::PARAM_STR);
    $stmt->bindParam(':leave_status', $leave_status, PDO::PARAM_INT);
    $stmt->bindParam(':appdate', $appdate, PDO::PARAM_STR);
    $stmt->bindParam(':hr', $hr, PDO::PARAM_INT);
    $stmt->bindParam(':supervisor', $supervisor, PDO::PARAM_INT);
    $stmt->bindParam(':manager', $manager, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['status' => 'success', 'message' => 'Leave application submitted successfully.']);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>  
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">  
  <title>HRIS | Leave Form</title>
  <link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>

<body class="bg-gray-50 dark:bg-neutral-900">
<div class="max-w-2xl mx-auto my-8 p-6 bg-white shadow-md rounded-lg dark:bg-neutral-800">
    <div class="p-4 sm:p-7 overflow-y-auto">
        <div class="text-center mb-6">
            <h1 class="text-lg font-semibold text-gray-800 dark:text-neutral-200 sm:text-2xl">
                Leave Application
            </h1>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mt-2">
                Please fill out the details below to apply for leave.
            </p>
        </div>

        <form class="space-y-6" id="leave-form" method="POST">
            <!-- Leave Type -->
            <div>
                <label for="leave_type" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    Leave Type
                </label>
                <select id="leave_type" name="leave_type" class="block w-full py-3 px-4 border rounded-lg shadow-sm text-sm bg-white dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300 focus:ring-blue-500 focus:border-blue-500 appearance-none" required>
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
                <label for="leave_details" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    Leave Details
                </label>
                <select id="leave_details" name="leave_details" class="block w-full py-3 px-4 border rounded-lg shadow-sm text-sm bg-white dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300 focus:ring-blue-500 focus:border-blue-500 appearance-none" required>
                    <option value="" disabled selected>Select leave details</option>
                </select>
            </div>

            <!-- Reason -->
            <div>
                <label for="reason" id="reason-label" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    Reason
                </label>
                <textarea id="reason" name="reason" rows="4" placeholder="Enter the reason for your leave" class="block w-full rounded-lg border bg-white shadow-sm p-3 text-sm text-gray-800 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300 focus:ring-blue-500 focus:border-blue-500" required></textarea>
            </div>

            <!-- Start Date -->
            <div id="start-date-container">
                <label for="start_date" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    Start Date
                </label>
                <input type="date" id="start_date" name="start_date" class="block w-full py-3 px-4 border rounded-lg shadow-sm text-sm bg-white dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300 focus:ring-blue-500 focus:border-blue-500" />
            </div>

            <div class="flex">
              <input type="checkbox" id="one_day_leave" name="one_day_leave" class="shrink-0 mt-0.5 border-gray-200 rounded-sm text-blue-600 focus:ring-blue-500 checked:border-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="hs-default-checkbox">
              <label for="hs-default-checkbox" class="text-sm text-gray-500 ms-3 dark:text-neutral-400">Half-day</label>
            </div>

            <!-- End Date -->
            <div id="end-date-container">
                <label for="end_date" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    End Date
                </label>
                <input type="date" id="end_date" name="end_date" class="block w-full py-3 px-4 border rounded-lg shadow-sm text-sm bg-white dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-300 focus:ring-blue-500 focus:border-blue-500" />
            </div>

            <!-- Total Days -->
            <div>
                <label for="total_days" class="block text-xs uppercase font-medium text-gray-500 dark:text-neutral-400 mb-2">
                    Total Working Days Applied
                </label>
                <input type="text" id="total_days" name="total_days" readonly placeholder="Automatically calculated" class="block w-full py-3 px-4 border rounded-lg shadow-sm text-sm bg-gray-100 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" />
            </div>

            <!-- Buttons -->
            <div class="flex justify-between gap-4 mt-6">
                <button type="button" id="back-btn" class="w-full py-3 px-4 rounded-lg bg-gray-400 text-white text-sm font-medium hover:bg-gray-500 focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50" onclick="history.back()">
                    Back
                </button>
                <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50" aria-haspopup="dialog" aria-expanded="false" aria-controls="hs-scale-animation-modal">Apply Leave</button>    
            </div>
        </form>
    </div>
</div>

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
        <p class="text-sm text-gray-800 dark:text-neutral-400" style="text-align: justify;">
        To complete your leave application, please upload your digital signature below in PNG format, which will be embedded in your official Leave Application Form and used for internal documentation, approval routing, and record-keeping purposes.
        </p>

        <div class="grid sm:grid-cols-12 gap-4 items-start">
          <!-- Label Column -->
          <div class="sm:col-span-3">
            <label for="signature-upload" class="inline-block text-sm font-normal text-gray-500 mt-2.5 dark:text-neutral-500">
              E-Signature
            </label>
          </div>

          <!-- Input Column -->
          <div class="sm:col-span-9">
            <label for="signature-upload" class="sr-only">Choose file</label>
            <input type="file" name="signature-upload" id="signature-upload" accept=".png" class="block w-full border border-gray-200 shadow-sm rounded-lg sm:text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400
              file:bg-gray-50 file:border-0
              file:bg-gray-100 file:me-4
              file:py-2 file:px-4
              dark:file:bg-neutral-700 dark:file:text-neutral-400">
          </div>
        </div>

        <!-- Privacy Disclaimer -->
        <div class="mt-5 flex">
          <input type="checkbox" class="shrink-0 mt-0.5 border-gray-300 rounded-sm text-blue-600 checked:border-blue-600 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-600 dark:checked:bg-blue-500 dark:checked:border-blue-500 dark:focus:ring-offset-gray-800" id="privacy-disclaimer-check">
          <label for="privacy-disclaimer-check" class="text-sm text-gray-500 ms-2 dark:text-neutral-400">I have read and understood the above disclaimer. I agree and authorize the use of my e-signature for this leave application.</label>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700" data-hs-overlay="#hs-scale-animation-modal">
          Close
        </button>
        <button type="button" id="save-signature-btn" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" disabled>
          Save changes
        </button>
      </div>

    </div>
  </div>
</div>
<!-- End Modal -->


<script>
document.addEventListener("DOMContentLoaded", function () {
    const leaveForm = document.getElementById("leave-form");
    const sessionUserId = "<?php echo $_SESSION['userid']; ?>"; // Fetch the session userid from PHP

    leaveForm.addEventListener("submit", function (event) {
        event.preventDefault(); // Prevent the form from submitting and refreshing the page

        const leaveType = document.getElementById("leave_type").value;
        const leaveDetails = document.getElementById("leave_details").value;
        const leaveReason = document.getElementById("reason").value;
        const startDate = document.getElementById("start_date").value;
        const endDate = document.getElementById("end_date").value;
        const totalDays = document.getElementById("total_days").value;

        // Validation: Ensure required fields are filled
        if (!leaveType || !leaveDetails || !leaveReason || !startDate || !endDate || !totalDays) {
            alert("Please fill out all required fields.");
            return;
        }

        // Check if signature file exists before proceeding
        checkSignatureExists(sessionUserId, function(signatureExists) {
            if (signatureExists) {
                // Proceed with leave application submission
                submitLeaveApplication();
            } else {
                // Show signature upload modal using multiple methods
                const modal = document.querySelector('#hs-scale-animation-modal');
                
                // Method 1: Try Preline HSOverlay
                if (modal && window.HSOverlay) {
                    window.HSOverlay.open(modal);
                } else {
                    // Method 2: Manual overlay opening
                    modal.classList.remove('hidden');
                    modal.classList.add('hs-overlay-open');
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    
                    // Method 3: Trigger click on a hidden button with data-hs-overlay
                    const triggerBtn = document.createElement('button');
                    triggerBtn.setAttribute('data-hs-overlay', '#hs-scale-animation-modal');
                    triggerBtn.style.display = 'none';
                    document.body.appendChild(triggerBtn);
                    triggerBtn.click();
                    document.body.removeChild(triggerBtn);
                }
            }
        });

        function submitLeaveApplication() {
            // Send AJAX request for validation and saving
            const formData = new FormData();
            formData.append("action", "validate_and_save_leave");
            formData.append("userid", sessionUserId);
            formData.append("leave_type", leaveType);
            formData.append("leave_details", leaveDetails);
            formData.append("leave_reason", leaveReason);
            formData.append("start_date", startDate);
            formData.append("end_date", endDate);
            formData.append("total_days", totalDays);

            fetch("", {
                method: "POST",
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status === "success") {
                        alert(data.message); // Show success message
                        window.location.href = "myapplications"; // Redirect to myapplications
                    } else {
                        alert(data.message); // Show error message
                    }
                })
                .catch((error) => {
                    console.error("Error:", error);
                    alert("An error occurred while processing the leave application.");
                });
        }

        function checkSignatureExists(userid, callback) {
            // Check if signature file exists
            const img = new Image();
            img.onload = function() {
                callback(true);
            };
            img.onerror = function() {
                callback(false);
            };
            img.src = `assets/signatures/${userid}.png?${Date.now()}`; // Add timestamp to prevent caching
        }
    });
});
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const startDateField = document.getElementById("start_date");
    const endDateField = document.getElementById("end_date");
    const totalDaysField = document.getElementById("total_days");
    const oneDayLeaveCheckbox = document.getElementById("one_day_leave");

    // Event listener for the one-day leave checkbox
    oneDayLeaveCheckbox.addEventListener("change", function () {
        if (oneDayLeaveCheckbox.checked) {
            if (startDateField.value) {
                endDateField.value = startDateField.value; // Set end date to start date
                totalDaysField.value = "0.5"; // Set total days to 0.5
                endDateField.disabled = true; // Disable end date field
            } else {
                alert("Please select a start date first.");
                oneDayLeaveCheckbox.checked = false; // Uncheck the checkbox
            }
        } else {
            endDateField.value = ""; // Clear the end date
            totalDaysField.value = ""; // Clear total days
            endDateField.disabled = false; // Enable end date field
        }
    });

    // Ensure end date updates correctly when start date is changed
    startDateField.addEventListener("change", function () {
        if (oneDayLeaveCheckbox.checked) {
            endDateField.value = startDateField.value; // Update end date to match start date
        }
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const leaveTypeField = document.getElementById("leave_type");
    const leaveDetailsField = document.getElementById("leave_details");
    const reasonField = document.getElementById("reason");
    const reasonLabel = document.getElementById("reason-label");
    const startDateField = document.getElementById("start_date");
    const endDateField = document.getElementById("end_date");
    const totalDaysField = document.getElementById("total_days");

    // Utility function to calculate total days (including weekends)
    const calculateTotalDays = (start, end) => {
        const startDate = new Date(start);
        const endDate = new Date(end);
        return Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1; // Include the start date
    };

    // Utility function to calculate total working days (excluding weekends)
    const calculateWorkingDays = (start, end) => {
        const startDate = new Date(start);
        const endDate = new Date(end);
        let totalDays = 0;

        while (startDate <= endDate) {
            const dayOfWeek = startDate.getDay(); // 0 = Sunday, 6 = Saturday
            if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                totalDays++; // Count only weekdays
            }
            startDate.setDate(startDate.getDate() + 1); // Move to the next day
        }
        return totalDays;
    };

    // Update total days when start and end dates are selected
    const updateTotalDays = (includeWeekends = false, maxDays = null) => {
        if (startDateField.value && endDateField.value) {
            const totalDays = includeWeekends
                ? calculateTotalDays(startDateField.value, endDateField.value)
                : calculateWorkingDays(startDateField.value, endDateField.value);

            if (totalDays > 0) {
                if (maxDays && totalDays > maxDays) {
                    alert(`The selected leave cannot exceed ${maxDays} days.`);
                    totalDaysField.value = ""; // Clear total days if invalid
                    endDateField.value = ""; // Clear invalid end date
                } else {
                    totalDaysField.value = totalDays; // Display total days
                }
            } else {
                alert("End Date must be after Start Date.");
                totalDaysField.value = ""; // Clear total days if invalid
                endDateField.value = ""; // Clear invalid end date
            }
        }
    };

    // Clear all fields when leave_type changes
    const clearFields = () => {
        leaveDetailsField.innerHTML = ""; // Clear leave details dropdown
        reasonField.value = ""; // Clear reason text
        reasonField.readOnly = false; // Make reason editable by default
        reasonLabel.textContent = "Reason"; // Reset reason label
        startDateField.value = ""; // Clear start date
        endDateField.value = ""; // Clear end date
        totalDaysField.value = ""; // Clear total days
    };

    // Event listener for Leave Type selection
    leaveTypeField.addEventListener("change", function () {
        clearFields(); // Clear all fields before applying new conditions

        const leaveType = leaveTypeField.value;

        if (leaveType === "VACATION LEAVE") {
                leaveDetailsField.innerHTML = `
                <option value="WITHIN THE PHILIPPINES" selected>WITHIN THE PHILIPPINES</option>
                <option value="ABROAD">ABROAD</option>
            `;

            // Set default behavior for "WITHIN THE PHILIPPINES"
            reasonField.value = "N/A"; // Auto-fill the reason as "N/A"
            reasonField.readOnly = true; // Make the reason field read-only
            reasonLabel.textContent = "Reason"; // Reset the label to "Reason"

            // Attach event listener for leave details selection
            leaveDetailsField.addEventListener("change", function () {
                if (leaveDetailsField.value === "WITHIN THE PHILIPPINES") {
                    reasonField.value = "N/A"; // Auto-fill the reason
                    reasonField.readOnly = true; // Make the field read-only
                    reasonLabel.textContent = "Reason"; // Reset the label
                } else if (leaveDetailsField.value === "ABROAD") {
                    reasonField.value = ""; // Clear the field for user input
                    reasonField.readOnly = false; // Allow editing
                    reasonLabel.textContent = "SPECIFY DESTINATION ABROAD"; // Change the label
                }
            });

            // Attach event listeners for total days calculation
            startDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
            endDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
        } else if (leaveType === "SICK LEAVE") {
            leaveDetailsField.innerHTML = `
                <option value="IN HOSPITAL">IN HOSPITAL</option>
                <option value="OUT PATIENT">OUT PATIENT</option>
            `;
            reasonLabel.textContent = "Specific Illness"; // Change the label

            // Attach event listeners for total days calculation
            startDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
            endDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
        } else if (leaveType === "FORCE LEAVE") {
            leaveDetailsField.innerHTML = `<option value="FORCE LEAVE" selected>FORCE LEAVE</option>`;
            reasonField.value = "N/A";
            reasonField.readOnly = true; // Make the field read-only

            // Attach event listeners for total days calculation
            startDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
            endDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
        } else if (leaveType === "LEAVE WITHOUT PAY") {
            leaveDetailsField.innerHTML = `<option value="LEAVE WITHOUT PAY" selected>LEAVE WITHOUT PAY</option>`;
            reasonField.value = ""; // Allow the user to input a reason if necessary
            reasonField.readOnly = false; // Allow editing

            // Attach event listeners for total days calculation
            startDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
            endDateField.addEventListener("change", function () {
                updateTotalDays(false); // Exclude weekends, no limit
            });
        } else if (leaveType === "SPECIAL PRIVILEGE LEAVE") {
            leaveDetailsField.innerHTML = `<option value="SPECIAL PRIVILEGE LEAVE" selected>SPECIAL PRIVILEGE LEAVE</option>`;
            endDateField.addEventListener("change", function () {
                updateTotalDays(false, 3); // Exclude weekends, max 3 days
            });
        } else if (leaveType === "ADOPTION LEAVE") {
            leaveDetailsField.innerHTML = `<option value="ADOPTION LEAVE" selected>ADOPTION LEAVE</option>`;
            reasonLabel.textContent = "Details"; // Change the label
            reasonField.value = ""; // Allow the user to input details
            reasonField.readOnly = false; // Allow editing

            // Attach event listeners for total days calculation with a max of 60 days, including weekends
            startDateField.addEventListener("change", function () {
                updateTotalDays(true, 60); // Include weekends, max 60 days
            });
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 60); // Include weekends, max 60 days
            });
        } else if (leaveType === "CALAMITY LEAVE") {
            leaveDetailsField.innerHTML = `<option value="CALAMITY LEAVE" selected>CALAMITY LEAVE</option>`;
            reasonLabel.textContent = "Details"; // Change the label
            endDateField.addEventListener("change", function () {
                updateTotalDays(false, 5); // Exclude weekends, max 5 days
            });
        } else if (leaveType === "SPL FOR WOMEN") {
            leaveDetailsField.innerHTML = `<option value="SPECIAL PRIVILEGE LEAVE FOR WOMEN" selected>SPECIAL PRIVILEGE LEAVE FOR WOMEN</option>`;
            reasonLabel.textContent = "Specific Illness"; // Change the label
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 60); // Include weekends, max 60 days
            });
        } else if (leaveType === "MATERNITY LEAVE") {
            leaveDetailsField.innerHTML = `<option value="MATERNITY LEAVE" selected>MATERNITY LEAVE</option>`;
            reasonField.value = "N/A";
            reasonField.readOnly = true; // Make the field read-only
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 105); // Include weekends, max 105 days
            });
        } else if (leaveType === "PATERNITY LEAVE") {
            leaveDetailsField.innerHTML = `<option value="PATERNITY LEAVE" selected>PATERNITY LEAVE</option>`;
            reasonField.value = "N/A";
            reasonField.readOnly = true; // Make the field read-only
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 7); // Include weekends, max 7 days
            });
        } else if (leaveType === "STUDY LEAVE") {
            leaveDetailsField.innerHTML = `
                <option value="COMPLETION OF MASTER'S DEGREE BAR/BOARD">COMPLETION OF MASTER'S DEGREE BAR/BOARD</option>
                <option value="EXAMINATION REVIEW">EXAMINATION REVIEW</option>
            `;
            reasonField.value = "N/A";
            reasonField.readOnly = true; // Make the field read-only
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 182); // Include weekends, max 182 days (6 months)
            });
        } else if (leaveType === "REHABILITATION PRIVILEGE") {
            leaveDetailsField.innerHTML = `<option value="REHABILITATION PRIVILEGE" selected>REHABILITATION PRIVILEGE</option>`;
            reasonLabel.textContent = "Details"; // Change the label
            endDateField.addEventListener("change", function () {
                updateTotalDays(true, 182); // Include weekends, max 182 days (6 months)
            });
        } else if (leaveType === "SOLO PARENT LEAVE") {
            leaveDetailsField.innerHTML = `<option value="SOLO PARENT LEAVE" selected>SOLO PARENT LEAVE</option>`;
            reasonField.value = "N/A";
            reasonField.readOnly = true; // Make the field read-only
            endDateField.addEventListener("change", function () {
                updateTotalDays(false, 7); // Exclude weekends, max 7 working days
            });
        } else if (leaveType === "10-DAY VAWC LEAVE") {
            leaveDetailsField.innerHTML = `<option value="10-DAY VAWC LEAVE" selected>10-DAY VAWC LEAVE</option>`;
            reasonLabel.textContent = "Details"; // Change the label
            endDateField.addEventListener("change", function () {
                updateTotalDays(false, 10); // Exclude weekends, max 10 working days
            });
        }
    });
});
</script>

<script>
// Handle signature upload modal functionality
document.addEventListener("DOMContentLoaded", function () {
    const saveSignatureBtn = document.getElementById("save-signature-btn");
    const signatureUpload = document.getElementById("signature-upload");
    const privacyCheck = document.getElementById("privacy-disclaimer-check");
    const modal = document.querySelector('#hs-scale-animation-modal');
    const sessionUserId = "<?php echo $_SESSION['userid']; ?>";

    // Function to check if both conditions are met
    function checkButtonState() {
        const hasFile = signatureUpload.files.length > 0;
        const privacyChecked = privacyCheck.checked;
        
        if (hasFile && privacyChecked) {
            saveSignatureBtn.disabled = false; // Enable the Save changes button
        } else {
            saveSignatureBtn.disabled = true; // Disable the Save changes button
        }
    }

    // Handle privacy disclaimer checkbox
    privacyCheck.addEventListener("change", function () {
        checkButtonState();
    });

    // Handle file selection
    signatureUpload.addEventListener("change", function () {
        checkButtonState();
    });

    saveSignatureBtn.addEventListener("click", function () {
        const file = signatureUpload.files[0];
        
        if (!file) {
            alert("Please select a PNG file to upload.");
            return;
        }

        if (file.type !== "image/png") {
            alert("Please upload a PNG file only.");
            return;
        }

        const formData = new FormData();
        formData.append("action", "upload_signature");
        formData.append("signature", file);

        // Disable the button during upload
        saveSignatureBtn.disabled = true;
        saveSignatureBtn.textContent = "Uploading...";

        fetch("", {
            method: "POST",
            body: formData,
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === "success") {
                alert(data.message);
                // Close the modal using Preline overlay
                if (window.HSOverlay) {
                    window.HSOverlay.close(modal);
                } else {
                    // Fallback method
                    modal.classList.add('hidden');
                    modal.classList.remove('hs-overlay-open');
                    document.body.style.overflow = '';
                }
                // Reset the form
                signatureUpload.value = "";
                privacyCheck.checked = false; // Reset privacy checkbox
                // Try to submit the leave application again
                document.getElementById("leave-form").dispatchEvent(new Event('submit'));
            } else {
                alert(data.message);
            }
        })
        .catch((error) => {
            console.error("Error:", error);
            alert("An error occurred while uploading the signature.");
        })
        .finally(() => {
            // Reset button text and check conditions again
            saveSignatureBtn.textContent = "Save changes";
            checkButtonState();
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>

<script>
// Initialize Preline components when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
    // Initialize HSOverlay if available
    if (window.HSOverlay) {
        window.HSOverlay.init();
    }
});
</script>

</body>
</html>