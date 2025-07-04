<?php
/**
 * Bridge File for CodeIgniter 4 Migration
 * 
 * This file allows the legacy PHP system and CodeIgniter 4 to coexist
 * during the migration process. It redirects legacy URLs to the new
 * CodeIgniter 4 equivalents when they are available.
 */

// Define legacy to CI4 route mappings
$legacyRoutes = [
    'login.php' => 'ci4/public/login',
    'login_process.php' => 'ci4/public/login',
    'dashboard.php' => 'ci4/public/dashboard',
    'profile.php' => 'ci4/public/profile',
    'plantilla.php' => 'ci4/public/plantilla',
    'myapplications.php' => 'ci4/public/leave/applications',
    'allleave.php' => 'ci4/public/leave/all',
    'changepassword.php' => 'ci4/public/change-password',
    'logout.php' => 'ci4/public/logout',
];

// Get the current request
$currentFile = basename($_SERVER['PHP_SELF']);
$requestUri = $_SERVER['REQUEST_URI'];

// Check if we have a mapping for this file
if (isset($legacyRoutes[$currentFile])) {
    $newUrl = $legacyRoutes[$currentFile];
    
    // Preserve query parameters
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    if (!empty($queryString)) {
        $newUrl .= '?' . $queryString;
    }
    
    // Redirect to the new CodeIgniter 4 route
    header("Location: /" . $newUrl);
    exit;
}

// If no mapping exists, continue with the legacy system
// This allows gradual migration without breaking existing functionality
?>