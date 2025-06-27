<?php
require_once(__DIR__ . '/../init.php');

// Determine userid for GET (show modal)
$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;

// Handle POST (AJAX form submit for saving password)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Determine profile_userid from POST, GET, or session
    $profile_userid = null;
    if (!empty($_POST['profile_userid']) && is_numeric($_POST['profile_userid'])) {
        $profile_userid = intval($_POST['profile_userid']);
    } elseif (!empty($_GET['userid']) && is_numeric($_GET['userid'])) {
        $profile_userid = intval($_GET['userid']);
    } elseif (isset($_SESSION['userid'])) {
        $profile_userid = intval($_SESSION['userid']);
    }

    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : null;
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : null;

    // Validate fields
    if (
        $profile_userid === null ||
        $new_password === null ||
        $confirm_password === null
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required values.']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
        exit;
    }

    // Check if user row exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE userid = :userid LIMIT 1");
    $stmt->execute([':userid' => $profile_userid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Hash password using bcrypt
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        // Update password
        $update = $pdo->prepare("UPDATE users SET password = :password WHERE userid = :userid");
        $update->execute([
            ':password' => $hashed_password,
            ':userid' => $profile_userid,
        ]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
    }
    exit;
}
?>
<div id="hs-reset-password-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-reset-password-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="reset-password-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70" autocomplete="off">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-reset-password-modal-label" class="font-bold text-gray-800 dark:text-white">Reset Password</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-reset-password-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">

        <!-- New Password with toggle (full width) -->
        <div class="py-4 w-full">
          <label for="new-password" class="block text-sm mb-2 dark:text-white">New Password</label>
          <div class="relative w-full">
            <input type="password" id="new-password" name="new_password"
              class="py-2.5 sm:py-3 ps-4 pe-10 block w-full border-gray-200 rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Enter new password"
              required>
            <button type="button" tabindex="-1" class="absolute inset-y-0 end-0 flex items-center z-20 px-3 cursor-pointer text-gray-400 rounded-e-md focus:outline-hidden focus:text-blue-600 dark:text-neutral-600 dark:focus:text-blue-500 password-toggle-btn" data-target="new-password">
              <svg class="shrink-0 size-3.5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path class="eye" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                <circle class="eye" cx="12" cy="12" r="3"></circle>
                <path class="eye-off" d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                <path class="eye-off" d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                <path class="eye-off" d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                <line class="eye-off" x1="2" x2="22" y1="2" y2="22"></line>
              </svg>
            </button>
          </div>
        </div>
        <!-- Confirm Password with toggle (full width) -->
        <div class="py-4 w-full">
          <label for="confirm-password" class="block text-sm mb-2 dark:text-white">Confirm Password</label>
          <div class="relative w-full">
            <input type="password" id="confirm-password" name="confirm_password"
              class="py-2.5 sm:py-3 ps-4 pe-10 block w-full border-gray-200 rounded-lg sm:text-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600"
              placeholder="Confirm new password"
              required>
            <button type="button" tabindex="-1" class="absolute inset-y-0 end-0 flex items-center z-20 px-3 cursor-pointer text-gray-400 rounded-e-md focus:outline-hidden focus:text-blue-600 dark:text-neutral-600 dark:focus:text-blue-500 password-toggle-btn" data-target="confirm-password">
              <svg class="shrink-0 size-3.5" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path class="eye" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                <circle class="eye" cx="12" cy="12" r="3"></circle>
                <path class="eye-off" d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                <path class="eye-off" d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                <path class="eye-off" d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                <line class="eye-off" x1="2" x2="22" y1="2" y2="22"></line>
              </svg>
            </button>
          </div>
        </div>
        <div id="reset-password-error" class="text-red-600 text-sm py-2 hidden"></div>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" id="cancel-reset-password-btn" class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white" data-hs-overlay="#hs-reset-password-modal">
          Cancel
        </button>
        <button
          type="submit"
          id="submit-reset-password-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:border-gray-400 transition"
        >
          Save Password
        </button>
      </div>
    </form>
  </div>
</div>