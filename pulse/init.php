<?php
// Enhanced session security configurations
ini_set('session.cookie_secure', '1'); // Ensure cookies are sent over HTTPS
ini_set('session.cookie_httponly', '1'); // Prevent JavaScript access to cookies
ini_set('session.use_strict_mode', '1'); // Prevent session fixation
ini_set('session.cookie_samesite', 'Strict'); // Mitigate CSRF attacks
ini_set('session.use_trans_sid', '0'); // Disable URL-based session IDs
ini_set('expose_php', '0'); // Hide PHP version in HTTP headers
header_remove('X-Powered-By'); // Remove the X-Powered-By header if already set

// Start session with stricter params
session_set_cookie_params([
  'lifetime' => 900,
  'path' => '/',
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Strict'
]);
session_start();

// Session hijacking prevention
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
} else {
    if (
        $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') ||
        $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')
    ) {
        session_unset();
        session_destroy();
        header("Location: login?security=agent_ip_mismatch");
        exit;
    }
}

// Session timeout logic
$timeoutDuration = 15 * 60; // 15 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutDuration) {
    session_unset();
    session_destroy();
    header("Location: login?timeout=true");
    exit;
}
$_SESSION['last_activity'] = time(); // Update last activity timestamp

// Check if the user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login");
    exit;
}

// Include the DB connection
require_once(__DIR__ . '/config/db_connection.php');

// Add additional security headers (CSP removed to allow all CDNs)
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
?>