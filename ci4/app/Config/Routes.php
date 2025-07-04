<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Default route
$routes->get('/', 'AuthController::login');

// Authentication routes
$routes->group('', function ($routes) {
    $routes->get('login', 'AuthController::login');
    $routes->post('login', 'AuthController::loginProcess');
    $routes->get('logout', 'AuthController::logout');
    $routes->get('change-password', 'AuthController::changePassword');
    $routes->post('change-password', 'AuthController::changePasswordProcess');
});

// Dashboard routes
$routes->group('', ['filter' => 'auth'], function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('profile', 'ProfileController::index');
    $routes->get('profile/edit', 'ProfileController::edit');
    $routes->post('profile/edit', 'ProfileController::edit');
});

// Leave management routes
$routes->group('leave', ['filter' => 'auth'], function ($routes) {
    $routes->get('applications', 'LeaveController::myApplications');
    $routes->get('apply', 'LeaveController::apply');
    $routes->post('apply', 'LeaveController::processApplication');
    $routes->get('view/(:num)', 'LeaveController::view/$1');
    $routes->get('all', 'LeaveController::allLeaves');
    $routes->post('approve/(:num)', 'LeaveController::approve/$1');
    $routes->post('reject/(:num)', 'LeaveController::reject/$1');
});

// Employee management routes
$routes->group('employee', ['filter' => 'auth'], function ($routes) {
    $routes->get('list', 'EmployeeController::list');
    $routes->get('add', 'EmployeeController::add');
    $routes->post('add', 'EmployeeController::processAdd');
    $routes->get('edit/(:num)', 'EmployeeController::edit/$1');
    $routes->post('edit/(:num)', 'EmployeeController::processEdit/$1');
});

// Plantilla management routes
$routes->group('plantilla', ['filter' => 'auth'], function ($routes) {
    $routes->get('/', 'PlantillaController::index');
    $routes->get('add', 'PlantillaController::add');
    $routes->post('add', 'PlantillaController::processAdd');
    $routes->get('edit/(:num)', 'PlantillaController::edit/$1');
    $routes->post('edit/(:num)', 'PlantillaController::processEdit/$1');
});

// AJAX routes
$routes->group('ajax', ['filter' => 'auth'], function ($routes) {
    $routes->post('profile/update', 'ProfileController::updateAjax');
    $routes->post('leave/details', 'LeaveController::getDetailsAjax');
    $routes->post('employee/search', 'EmployeeController::searchAjax');
});

// Reports routes
$routes->group('reports', ['filter' => 'auth'], function ($routes) {
    $routes->get('audit', 'ReportController::auditLog');
    $routes->get('credit', 'ReportController::creditLogs');
    $routes->get('tardiness', 'ReportController::tardiness');
});

// Admin routes
$routes->group('admin', ['filter' => 'auth|admin'], function ($routes) {
    $routes->get('users', 'AdminController::users');
    $routes->get('system', 'AdminController::system');
    $routes->get('backup', 'AdminController::backup');
});

// API routes (for AJAX endpoints)
$routes->group('api', ['filter' => 'auth'], function ($routes) {
    $routes->post('upload-tardiness', 'ApiController::uploadTardiness');
    $routes->post('generate-leave-pdf', 'ApiController::generateLeavePdf');
    $routes->post('chart-data', 'ApiController::getChartData');
});

// Legacy route mappings (to maintain compatibility)
$routes->group('legacy', function ($routes) {
    $routes->get('login_process', 'AuthController::loginProcess');
    $routes->get('myapplications', 'LeaveController::myApplications');
    $routes->get('employeelist', 'EmployeeController::list');
    $routes->get('plantilla', 'PlantillaController::index');
    $routes->get('viewleave/(:num)', 'LeaveController::view/$1');
    $routes->get('allleave', 'LeaveController::allLeaves');
    $routes->get('addemployee', 'EmployeeController::add');
    $routes->get('addplantilla', 'PlantillaController::add');
    $routes->get('auditlog', 'ReportController::auditLog');
    $routes->get('creditlogs', 'ReportController::creditLogs');
    $routes->get('uploadtardiness', 'ReportController::tardiness');
    $routes->get('changepassword', 'AuthController::changePassword');
    $routes->get('editprofile', 'ProfileController::edit');
});

// Error pages
$routes->get('404', 'ErrorController::show404');
$routes->get('error', 'ErrorController::general');
