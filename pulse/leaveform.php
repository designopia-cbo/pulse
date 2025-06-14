<?php
require_once('init.php');

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
                <button type="submit" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50">Apply Leave</button>    
            </div>
        </form>
    </div>
</div>


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
                    window.location.href = "myapplications.php"; // Redirect to myapplications.php
                } else {
                    alert(data.message); // Show error message
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                alert("An error occurred while processing the leave application.");
            });
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

</body>
</html>