<?php
// Move ini_set() to the top of the file
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

session_start(); // Start session after the ini_set() adjustments

// Include the DB connection
require_once('config/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // Sanitize and validate inputs
    $inputUsername = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $inputPassword = filter_var($_POST['password'], FILTER_SANITIZE_STRING);

    // Query database for the user, now including 'category'
    $stmt = $pdo->prepare("SELECT id, completename, username, password, branch, level, category, userid FROM users WHERE username = :username");
    $stmt->bindParam(':username', $inputUsername);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($inputPassword, $user['password'])) {
        // Regenerate session ID
        session_regenerate_id(true);

        // Save user data in session, including 'category'
        $_SESSION['userid'] = $user['userid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['completename'] = $user['completename'];
        $_SESSION['level'] = $user['level'];
        $_SESSION['category'] = $user['category'];

        // Redirect based on the user's level
        if ($user['level'] === 'ADMINISTRATOR') {
            header("Location: dashboard.php");
        } else {
            header("Location: profile.php");
        }
        exit;
    } else {
        // Invalid credentials
        $_SESSION['error_message'] = "Invalid login credentials. Please try again.";
        header("Location: login.php");
        exit;
    }
}
?>