<?php
// Do NOT start session or require init.php here!
// Assume parent page already includes init.php and session is active.

// Use unique variable names to avoid conflicts with profile.php.
$dropdown_userid = $_SESSION['userid'] ?? null;
$dropdown_category = $_SESSION['category'] ?? '';
$dropdown_level = $_SESSION['level'] ?? '';
$dropdown_fullName = "Unknown User";
$dropdown_initial = "U";
if ($dropdown_userid) {
  $dropdown_stmt = $pdo->prepare("SELECT fullname FROM employee WHERE id = :userid");
  $dropdown_stmt->bindParam(':userid', $dropdown_userid, PDO::PARAM_INT);
  $dropdown_stmt->execute();
  $dropdown_user = $dropdown_stmt->fetch(PDO::FETCH_ASSOC);
  if ($dropdown_user) {
    $dropdown_fullName = ucwords(strtolower($dropdown_user['fullname']));
    $dropdown_initial = strtoupper(substr($dropdown_fullName, 0, 1));
  }
}

// Profile image logic
$dropdown_profile_userid = $dropdown_userid;
$dropdown_profile_image_path = "assets/prof_img/{$dropdown_profile_userid}.jpg";

// Who can edit: self, or ADMINISTRATOR with HR or SUPERADMIN
$dropdown_can_edit_image = false;
if ($dropdown_profile_userid === $_SESSION['userid']) {
  $dropdown_can_edit_image = true;
} elseif ($dropdown_level === 'ADMINISTRATOR' && in_array($dropdown_category, ['HR', 'SUPERADMIN'])) {
  $dropdown_can_edit_image = true;
}

// For initials if no image
$dropdown_profile_initials = $dropdown_initial;
?>

<div class="hs-dropdown [--placement:bottom-right] relative inline-flex">
  <button id="hs-dropdown-account" type="button" class="size-9.5 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent text-gray-800 focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none dark:text-white" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
    <?php
      if (file_exists($dropdown_profile_image_path)) {
        // Display profile image - NOT clickable (no modal attributes)
        echo '<img class="inline-block shrink-0 size-9.5 rounded-full border border-gray-200 shadow-2xs object-cover object-center" src="' . $dropdown_profile_image_path . '" alt="Profile Image">';
      } else {
        // No image, show initials - NOT clickable (no modal attributes)
        echo '<span class="inline-flex items-center justify-center size-9.5 font-semibold rounded-full border border-gray-200 bg-white text-gray-800 shadow-2xs dark:bg-neutral-900 dark:border-neutral-700 dark:text-white">';
        echo htmlspecialchars($dropdown_profile_initials);
        echo '</span>';
      }
    ?>
  </button>

  <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-60 bg-white shadow-md rounded-lg mt-2 dark:bg-neutral-800 dark:border dark:border-neutral-700 dark:divide-neutral-700 after:h-4 after:absolute after:-bottom-4 after:start-0 after:w-full before:h-4 before:absolute before:-top-4 before:start-0 before:w-full" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-account">
    <div class="py-3 px-5 bg-gray-100 rounded-t-lg dark:bg-neutral-700">
      <p class="text-sm text-gray-500 dark:text-neutral-500">Signed in as</p>
      <p class="text-sm font-medium text-gray-800 dark:text-neutral-200"><?php echo htmlspecialchars($dropdown_fullName); ?></p>
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