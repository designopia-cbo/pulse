<?php
// Enhanced session security configurations
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');
ini_set('expose_php', '0');
header_remove('X-Powered-By');

$is_https = (
    (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

// Set session cookie to expire when browser closes (lifetime=0)
// and other strict cookie settings
session_set_cookie_params([
    'lifetime' => 0, // Until browser closes
    'path' => '/',
    'domain' => '',
    'secure' => $is_https,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Session hijacking prevention
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_parts = explode('.', $_SERVER['REMOTE_ADDR']);
        if (isset($ip_parts[0], $ip_parts[1])) {
            $_SESSION['ip_prefix'] = $ip_parts[0] . '.' . $ip_parts[1];
        } else {
            // For IPv6 or unusual addresses, just use the whole address
            $_SESSION['ip_prefix'] = $_SERVER['REMOTE_ADDR'];
        }
    } else {
        $_SESSION['ip_prefix'] = '';
    }
} else {
    $current_ip_prefix = '';
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_parts = explode('.', $_SERVER['REMOTE_ADDR']);
        if (isset($ip_parts[0], $ip_parts[1])) {
            $current_ip_prefix = $ip_parts[0] . '.' . $ip_parts[1];
        } else {
            $current_ip_prefix = $_SERVER['REMOTE_ADDR'];
        }
    }
    if (
        $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') ||
        $_SESSION['ip_prefix'] !== $current_ip_prefix
    ) {
        session_unset();
        session_destroy();
        header("Location: login?security=agent_ip_mismatch");
        exit;
    }
}

// Session idle timeout (15 minutes)
$timeoutDuration = 15 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeoutDuration) {
    session_unset();
    session_destroy();
    header("Location: login?timeout=true");
    exit;
}
$_SESSION['last_activity'] = time();

// Only require login for protected pages (not on login, login_process, etc.)
$currentFile = basename($_SERVER['PHP_SELF']);
$publicPages = ['login', 'login_process', 'index']; // Add any other public pages (extensionless)

if (!in_array(pathinfo($currentFile, PATHINFO_FILENAME), $publicPages)) {
    if (!isset($_SESSION['userid'])) {
        header("Location: login");
        exit;
    }
}

// Include the DB connection
require_once(__DIR__ . '/config/db_connection.php');

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if ($is_https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
?>