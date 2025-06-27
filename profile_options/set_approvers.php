<?php
require_once(__DIR__ . '/../init.php');

// Determine userid for GET (show modal)
$userid = $_SESSION['userid'];
$profile_userid = (isset($_GET['userid']) && is_numeric($_GET['userid'])) ? intval($_GET['userid']) : $userid;

// Fetch ADMINISTRATOR users for dropdowns
$admins = [];
$admins_map = []; // userid => completename
$stmt = $pdo->prepare("SELECT userid, completename FROM users WHERE level = 'ADMINISTRATOR' ORDER BY completename ASC");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $admins[] = [
        'userid' => $row['userid'],
        'completename' => $row['completename']
    ];
    $admins_map[$row['userid']] = $row['completename'];
}

// --- Handle POST: Save Approvers ---
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

    $approver_1 = isset($_POST['approver_1']) ? $_POST['approver_1'] : null;
    $approver_2 = isset($_POST['approver_2']) ? $_POST['approver_2'] : null;
    $approver_3 = isset($_POST['approver_3']) ? $_POST['approver_3'] : null;

    // Validate
    if (
        $profile_userid === null ||
        $approver_1 === null ||
        $approver_2 === null ||
        $approver_3 === null
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required values.']);
        exit;
    }

    // Only allow if all approvers are among available admins (sanity check)
    if (
        !array_key_exists($approver_1, $admins_map) ||
        !array_key_exists($approver_2, $admins_map) ||
        !array_key_exists($approver_3, $admins_map)
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'One or more approvers are invalid.']);
        exit;
    }

    // Update employment_details for this userid and edstatus=1
    $stmt = $pdo->prepare("UPDATE employment_details SET hr = :hr, supervisor = :supervisor, manager = :manager WHERE userid = :userid AND edstatus = 1");
    $stmt->execute([
        ':hr' => $approver_1,
        ':supervisor' => $approver_2,
        ':manager' => $approver_3,
        ':userid' => $profile_userid,
    ]);
    echo json_encode(['success' => true]);
    exit;
}

// Default: no preselection
$approver1_userid = '';
$approver2_userid = '';
$approver3_userid = '';

// Find the employment_details row for this user (edstatus = 1)
$stmt = $pdo->prepare("SELECT hr, supervisor, manager FROM employment_details WHERE userid = :userid AND edstatus = 1 LIMIT 1");
$stmt->execute([':userid' => $profile_userid]);
$employment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employment) {
    $approver1_userid = $employment['hr'];
    $approver2_userid = $employment['supervisor'];
    $approver3_userid = $employment['manager'];
}
?>
<div id="hs-set-approvers-modal" class="hs-overlay hidden size-full fixed top-0 start-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" role="dialog" tabindex="-1" aria-labelledby="hs-set-approvers-modal-label">
  <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all md:max-w-2xl md:w-full m-3 md:mx-auto">
    <form id="set-approvers-form" class="flex flex-col bg-white border border-gray-200 shadow-2xs rounded-xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700 dark:shadow-neutral-700/70" autocomplete="off">
      <div class="flex justify-between items-center py-3 px-4 border-b border-gray-200 dark:border-neutral-700">
        <h3 id="hs-set-approvers-modal-label" class="font-bold text-gray-800 dark:text-white">Set Approvers</h3>
        <button type="button" class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden focus:bg-gray-200 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-400 dark:focus:bg-neutral-600" aria-label="Close" data-hs-overlay="#hs-set-approvers-modal">
          <span class="sr-only">Close</span>
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </button>
      </div>
      <div class="p-4 overflow-y-auto">
        <input type="hidden" name="profile_userid" id="profile_userid" value="<?= htmlspecialchars($profile_userid) ?>">
        <!-- Approver 1 (dropdown) -->
        <div class="py-4">
          <label for="approver-1" class="inline-block text-sm font-normal dark:text-white mb-1">Human Resource</label>
          <select id="approver-1" name="approver_1"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            disabled
          >
            <option value="">Select HR</option>
            <?php foreach ($admins as $admin): ?>
              <option value="<?= htmlspecialchars($admin['userid']) ?>" <?= ($admin['userid'] == $approver1_userid) ? 'selected' : '' ?>>
                <?= htmlspecialchars($admin['completename']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Approver 2 (dropdown) -->
        <div class="py-4">
          <label for="approver-2" class="inline-block text-sm font-normal dark:text-white mb-1">Immediate Supervisor</label>
          <select id="approver-2" name="approver_2"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            disabled
          >
            <option value="">Select Supervisor</option>
            <?php foreach ($admins as $admin): ?>
              <option value="<?= htmlspecialchars($admin['userid']) ?>" <?= ($admin['userid'] == $approver2_userid) ? 'selected' : '' ?>>
                <?= htmlspecialchars($admin['completename']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Approver 3 (dropdown) -->
        <div class="py-4">
          <label for="approver-3" class="inline-block text-sm font-normal dark:text-white mb-1">Approving Officer</label>
          <select id="approver-3" name="approver_3"
            class="py-1.5 sm:py-2 px-3 block w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none shadow-2xs sm:text-sm rounded-lg dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400"
            disabled
          >
            <option value="">Select Manager</option>
            <?php foreach ($admins as $admin): ?>
              <option value="<?= htmlspecialchars($admin['userid']) ?>" <?= ($admin['userid'] == $approver3_userid) ? 'selected' : '' ?>>
                <?= htmlspecialchars($admin['completename']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="set-approvers-error" class="text-red-600 text-sm py-2 hidden"></div>
      </div>
      <div class="flex justify-end items-center gap-x-2 py-3 px-4 border-t border-gray-200 dark:border-neutral-700">
        <button type="button" id="edit-approvers-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
        >
          Edit
        </button>
        <button type="button" id="cancel-set-approvers-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white"
          style="display:none;"
          data-hs-overlay="#hs-set-approvers-modal"
        >
          Cancel
        </button>
        <button
          type="submit"
          id="submit-set-approvers-btn"
          class="py-1.5 sm:py-2 px-3 inline-flex items-center text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white dark:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 disabled:border-gray-400 transition"
          style="display:none;"
        >
          Save Approvers
        </button>
      </div>
    </form>
  </div>
</div>