<?php
// Move ini_set() to the top of the script, before session_start()
ini_set('session.cookie_secure', '1'); // Ensure cookies are sent over HTTPS
ini_set('session.cookie_httponly', '1'); // Prevent JavaScript access to cookies
ini_set('session.use_strict_mode', '1'); // Prevent session fixation

session_start(); // Start the session after setting ini configurations

// Session timeout logic
$timeoutDuration = 15 * 60; // 15 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutDuration) {
    session_unset(); // Unset session variables
    session_destroy(); // Destroy the session
    header("Location: login.php?timeout=true"); // Redirect to login with timeout message
    exit;
}
$_SESSION['last_activity'] = time(); // Update last activity timestamp

// Check if the user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit;
}

// Include the DB connection
require_once('config/db_connection.php');

// Fetch user details using session 'userid'
$userid = $_SESSION['userid'];
$stmt = $pdo->prepare("SELECT last_name, first_name FROM employee WHERE id = :userid");
$stmt->bindParam(':userid', $userid, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

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
 
 <div class="max-w-3xl flex flex-col mx-auto size-full">
  <!-- ========== HEADER ========== -->
  <header class="mb-auto flex justify-center z-50 w-full py-4">
    <nav class="px-4 sm:px-6 lg:px-8">
      <a class="flex-none text-xl font-semibold sm:text-3xl dark:text-white" href="#" aria-label="Brand"></a>
    </nav>
  </header>
  <!-- ========== END HEADER ========== -->

  <!-- ========== MAIN CONTENT ========== -->
  <main id="content">
    <div class="text-center py-10 px-4 sm:px-6 lg:px-8">
      <h1 class="block text-7xl font-bold text-gray-800 sm:text-9xl dark:text-white">404</h1>
      <p class="mt-3 text-gray-600 dark:text-neutral-400">Oops, something went wrong.</p>
      <p class="text-gray-600 dark:text-neutral-400">Sorry, we couldn't find your page.</p>
      <div class="mt-5 flex flex-col justify-center items-center gap-2 sm:flex-row sm:gap-3">
        <a class="w-full sm:w-auto py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none" href="../examples.html">
          <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back to examples
        </a>
      </div>
    </div>
  </main>
  <!-- ========== END MAIN CONTENT ========== -->

  <!-- ========== FOOTER ========== -->
  <footer class="mt-auto text-center py-5">
    <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
      <p class="text-sm text-gray-500 dark:text-neutral-500">© MSSD PULSE</p>
    </div>
  </footer>
  <!-- ========== END FOOTER ========== -->
</div>
 


  <!-- Required plugins -->
  <script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>
  

  </body>
</html>

