<?php
require_once('init.php');

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $old_password = isset($_POST['old_password']) ? trim($_POST['old_password']) : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // Check for empty fields
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } else {
        // Fetch user's hashed password from DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE userid = :userid LIMIT 1");
        $stmt->bindParam(':userid', $_SESSION['userid'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $error_message = "User not found.";
        } elseif (!password_verify($old_password, $row['password'])) {
            $error_message = "Old password is incorrect.";
        } elseif ($old_password === $new_password) {
            $error_message = "New password must be different from old password.";
        } else {
            // Check password strength (at least 1 uppercase, 1 number, 1 special, 8 chars)
            if (!preg_match('/[A-Z]/', $new_password) ||
                !preg_match('/[0-9]/', $new_password) ||
                !preg_match('/[&!@]/', $new_password) ||
                strlen($new_password) < 8
            ) {
                $error_message = "Password must be at least 8 characters and contain an uppercase letter, a number, and a special character (&!@).";
            } else {
                // Hash new password and update
                $hashed_new = password_hash($new_password, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE users SET password = :newpwd WHERE userid = :userid");
                $upd->bindParam(':newpwd', $hashed_new);
                $upd->bindParam(':userid', $_SESSION['userid'], PDO::PARAM_INT);
                if ($upd->execute()) {
                    $success_message = "Password changed successfully.";
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'profile.php';
                        }, 2500);
                    </script>";
                } else {
                    $error_message = "Error updating password. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRIS | Change Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/preline@latest/dist/preline.css">
    <link rel="stylesheet" href="https://preline.co/assets/css/main.min.css">
</head>
<body class="bg-gray-100 dark:bg-neutral-900 flex items-center justify-center h-screen">

<div class="mt-7 bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-neutral-900 dark:border-neutral-700 p-6 sm:p-7 w-96">

    <!-- Header Row -->
    <div class="grid grid-cols-3 items-center mb-4">
        <!-- Left: Dropdown -->
        <div class="flex justify-start">
            <div class="hs-dropdown relative inline-flex">
                <button id="hs-dropdown-custom-icon-trigger" type="button"
                    class="hs-dropdown-toggle flex justify-center items-center size-9 text-sm font-semibold rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800"
                    aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown"
                    onclick="history.back()"
                    <?php if (!empty($success_message)) echo 'style="display:none"'; ?>
                >
                    <svg class="flex-none size-4 text-gray-600 dark:text-neutral-500"
                        xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24">
                        <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>
        <!-- Center: Title -->
        <div class="text-center">
            <h1 class="block text-xl font-bold text-gray-800 dark:text-white"></h1>
        </div>
        <!-- Right: Spacer (to balance layout) -->
        <div></div>
    </div>

    <!-- Description (hide if success) -->
    <p id="change-password-desc" class="text-center text-sm text-gray-600 dark:text-neutral-400" <?php if (!empty($success_message)) echo 'style="display:none"'; ?>>
        Change your password to enhance your account protection.
    </p>

    <!-- Error Message -->
    <?php if (!empty($error_message)): ?>
        <p class="text-red-600 text-sm mt-2 text-center"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <!-- Success Message Custom Alert -->
    <?php if (!empty($success_message)): ?>
        <div class="space-y-5">
            <div id="dismiss-alert" class="hs-removing:translate-x-5 hs-removing:opacity-0 transition duration-300 bg-teal-50 border border-teal-200 text-sm text-teal-800 rounded-lg p-4 dark:bg-teal-800/10 dark:border-teal-900 dark:text-teal-500" role="alert" tabindex="-1" aria-labelledby="hs-dismiss-button-label">
                <div class="flex">
                    <div class="shrink-0">
                        <svg class="shrink-0 size-4 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div class="ms-2">
                        <h3 id="hs-dismiss-button-label" class="text-sm font-medium">
                            Password has been successfully changed.
                        </h3>
                    </div>
                    <div class="ps-3 ms-auto">
                        <div class="-mx-1.5 -my-1.5">
                            <button type="button" class="inline-flex bg-teal-50 rounded-lg p-1.5 text-teal-500 hover:bg-teal-100 focus:outline-hidden focus:bg-teal-100 dark:bg-transparent dark:text-teal-600 dark:hover:bg-teal-800/50 dark:focus:bg-teal-800/50" data-hs-remove-element="#dismiss-alert">
                                <span class="sr-only">Dismiss</span>
                                <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($success_message)): ?>
    <div class="mt-5">
        <!-- Form -->
        <form method="post" autocomplete="off">
            <div class="grid gap-y-4">
                <!-- Old Password Field -->
                <div>
                    <input type="password" id="old_password" name="old_password" class="py-2.5 sm:py-3 px-4 block w-full border-gray-200 rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400" placeholder="Enter old password" required>
                </div>

                <hr class="border-white">

                <!-- New Password -->
                <div class="max-w-sm">
                    <div class="flex">
                        <div class="relative flex-1">
                            <input type="password" id="new_password" name="new_password" class="py-2.5 sm:py-3 px-4 block w-full border-gray-200 rounded-md sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Enter new password" required>
                            <div id="hs-strong-password-api" class="hidden absolute z-10 w-full bg-white shadow-md rounded-lg p-4 dark:bg-teal-800/30 dark:border dark:border-neutral-700">
                                <div id="hs-strong-password-api-in-popover" data-hs-strong-password='{
                                    "target": "#new_password",
                                    "hints": "#hs-strong-password-api",
                                    "stripClasses": "hs-strong-password:opacity-100 hs-strong-password-accepted:bg-teal-500 h-2 flex-auto rounded-full bg-blue-500 opacity-50 mx-1",
                                    "mode": "popover",
                                    "checksExclude": ["lowercase", "min-length"],
                                    "specialCharactersSet": "&!@"
                                }' class="flex mt-2 -mx-1">
                                </div>

                                <h4 class="mt-3 text-sm font-semibold text-gray-800 dark:text-white">
                                    Your password must contain:
                                </h4>

                                <ul class="space-y-1 text-sm text-gray-500 dark:text-neutral-500">
                                    <li data-hs-strong-password-hints-rule-text="uppercase" class="hs-strong-password-active:text-teal-500 flex items-center gap-x-2">
                                        <span class="hidden" data-check="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </span>
                                        <span data-uncheck="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 6 6 18"></path>
                                                <path d="m6 6 12 12"></path>
                                            </svg>
                                        </span>
                                        Should contain uppercase.
                                    </li>
                                    <li data-hs-strong-password-hints-rule-text="numbers" class="hs-strong-password-active:text-teal-500 flex items-center gap-x-2">
                                        <span class="hidden" data-check="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </span>
                                        <span data-uncheck="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 6 6 18"></path>
                                                <path d="m6 6 12 12"></path>
                                            </svg>
                                        </span>
                                        Should contain numbers.
                                    </li>
                                    <li data-hs-strong-password-hints-rule-text="special-characters" class="hs-strong-password-active:text-teal-500 flex items-center gap-x-2">
                                        <span class="hidden" data-check="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </span>
                                        <span data-uncheck="">
                                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 6 6 18"></path>
                                                <path d="m6 6 12 12"></path>
                                            </svg>
                                        </span>
                                        Should contain special characters (available chars: &!@).
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End New Password -->

                <!-- Confirm Password -->
                <div class="max-w-sm">
                    <div class="flex">
                        <div class="relative flex-1">
                            <input type="password" id="confirm_password" name="confirm_password" class="py-2.5 sm:py-3 px-4 block w-full border-gray-200 rounded-md sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" placeholder="Re-enter new password" required>
                        </div>
                    </div>
                </div>
                <!-- End Confirm Password -->

                <!-- Submit Button -->
                <button type="submit" id="changePasswordBtn" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50" disabled>Change Password</button>
            </div>
        </form>
        <!-- End Form -->
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>
<script>
// Password strength and match check for button enable/disable
document.addEventListener("DOMContentLoaded", function() {
    const newPwd = document.getElementById('new_password');
    const confirmPwd = document.getElementById('confirm_password');
    const btn = document.getElementById('changePasswordBtn');

    function validatePassword() {
        const val = newPwd.value;
        const confirmVal = confirmPwd.value;
        const hasUpper = /[A-Z]/.test(val);
        const hasNumber = /[0-9]/.test(val);
        const hasSpecial = /[&!@]/.test(val);
        const hasLength = val.length >= 8;
        const match = val === confirmVal && val !== '';

        // Border coloring (as before)
        if (!val && !confirmVal) {
            newPwd.classList.remove('border-teal-500', 'border-red-500');
            confirmPwd.classList.remove('border-teal-500', 'border-red-500');
        } else if (val && confirmVal) {
            if (match) {
                newPwd.classList.remove('border-red-500');
                confirmPwd.classList.remove('border-red-500');
                newPwd.classList.add('border-teal-500');
                confirmPwd.classList.add('border-teal-500');
            } else {
                newPwd.classList.remove('border-teal-500');
                confirmPwd.classList.remove('border-teal-500');
                newPwd.classList.add('border-red-500');
                confirmPwd.classList.add('border-red-500');
            }
        } else {
            newPwd.classList.remove('border-teal-500', 'border-red-500');
            confirmPwd.classList.remove('border-teal-500', 'border-red-500');
        }

        // Button enable/disable logic
        if (hasUpper && hasNumber && hasSpecial && hasLength && match) {
            btn.disabled = false;
        } else {
            btn.disabled = true;
        }
    }

    newPwd.addEventListener('input', validatePassword);
    confirmPwd.addEventListener('input', validatePassword);
});
</script>
</body>
</html>