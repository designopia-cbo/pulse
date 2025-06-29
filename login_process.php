<?php
// Show errors for debugging (remove on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('init.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token. Please try again.";
        header("Location: login");
        exit;
    }

    $inputUsername = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $inputPassword = filter_var($_POST['password'], FILTER_SANITIZE_STRING);

    $stmt = $pdo->prepare("SELECT id, completename, username, password, branch, level, category, userid FROM users WHERE username = :username");
    $stmt->bindParam(':username', $inputUsername);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($inputPassword, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['userid'] = $user['userid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['completename'] = $user['completename'];
        $_SESSION['level'] = $user['level'];
        $_SESSION['category'] = $user['category'];

        if ($user['level'] === 'ADMINISTRATOR') {
            header("Location: dashboard");
        } else {
            header("Location: profile");
        }
        exit;
    } else {
        $_SESSION['error_message'] = "Invalid login credentials. Please try again.";
        header("Location: login");
        exit;
    }
} else {
    header("Location: login");
    exit;
}
?>