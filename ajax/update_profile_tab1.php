<?php
// update_profile_tab1.php

session_start();
date_default_timezone_set('Asia/Manila'); // Set to your timezone if needed

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Accept profile_userid from the POST data (the profile being edited)
$profile_userid = isset($_POST['profile_userid']) && is_numeric($_POST['profile_userid']) ? intval($_POST['profile_userid']) : $_SESSION['userid'];

// Include the DB connection
require_once('../config/db_connection.php');


// Helper function to get POST and uppercase
function post($key, $default = '') {
    return isset($_POST[$key]) ? mb_strtoupper(trim($_POST[$key]), 'UTF-8') : $default;
}

// Helper function to fetch fullname of updater
function get_full_name($pdo, $userid) {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM employee WHERE id = :id");
    $stmt->execute(['id' => $userid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        return ucwords(strtolower($user['first_name'] . ' ' . $user['last_name']));
    }
    return "Unknown User";
}

// Helper function for field name formatting
function format_field_name($field) {
    return mb_strtoupper(str_replace('_', ' ', $field), 'UTF-8');
}

// Helper function to build FULLNAME
function build_fullname($last_name, $first_name, $suffix, $middle_name) {
    $parts = [];
    $last_name = trim($last_name);
    $first_name = trim($first_name);
    $suffix = trim($suffix);
    $middle_name = trim($middle_name);

    if ($last_name !== "") {
        $parts[] = $last_name . ",";
    }
    if ($first_name !== "") {
        $parts[] = $first_name;
    }
    if ($suffix !== "") {
        $parts[] = $suffix;
    }
    if ($middle_name !== "") {
        $parts[] = $middle_name;
    }
    $fullname = implode(' ', $parts);
    return mb_strtoupper($fullname, 'UTF-8');
}

// ---- Step 1: Gather fields ----
// Employee
$employee_fields = [
    'last_name','first_name','middle_name','suffix','gender','birthdate','citizenship','civilstatus','religion','tribe',
    'telephoneno','mobilenumber','emailaddress','height','weight','blood_type'
];
$emp = [];
foreach ($employee_fields as $f) { $emp[$f] = post($f); }

// Address
$address_fields = ['birth_place','permanent_add','residential_add'];
$addr = [];
foreach ($address_fields as $f) { $addr[$f] = post($f); }

// Statutory benefits
$benefit_fields = ['gsis_number','pagibig_number','philhealth_number','sss_number','tin'];
$benef = [];
foreach ($benefit_fields as $f) { $benef[$f] = post($f); }

// Government ID
$id_fields = ['identification_type','identification_no','date_or_placeofissuance'];
$gid = [];
foreach ($id_fields as $f) { $gid[$f] = post($f); }

// ---- Step 2: Fetch Old Values ----
$old_employee = [];
$stmt = $pdo->prepare("SELECT * FROM employee WHERE id = :userid");
$stmt->execute(['userid' => $profile_userid]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($employee_fields as $f) {
        // Always compare uppercase for consistency
        $old_employee[$f] = isset($row[$f]) ? mb_strtoupper(trim($row[$f]), 'UTF-8') : '';
    }
    $old_employee['fullname'] = isset($row['fullname']) ? trim($row['fullname']) : '';
}
$old_address = [];
$stmt = $pdo->prepare("SELECT * FROM employee_address WHERE userid = :userid LIMIT 1");
$stmt->execute(['userid' => $profile_userid]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($address_fields as $f) {
        $old_address[$f] = isset($row[$f]) ? mb_strtoupper(trim($row[$f]), 'UTF-8') : '';
    }
}
$old_benefit = [];
$stmt = $pdo->prepare("SELECT * FROM statutory_benefits WHERE userid = :userid LIMIT 1");
$stmt->execute(['userid' => $profile_userid]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($benefit_fields as $f) {
        $old_benefit[$f] = isset($row[$f]) ? mb_strtoupper(trim($row[$f]), 'UTF-8') : '';
    }
}
$old_gid = [];
$stmt = $pdo->prepare("SELECT * FROM government_identification WHERE userid = :userid LIMIT 1");
$stmt->execute(['userid' => $profile_userid]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($id_fields as $f) {
        $old_gid[$f] = isset($row[$f]) ? mb_strtoupper(trim($row[$f]), 'UTF-8') : '';
    }
}

// ---- Step 3: Compare and Log Changes ----
$log_entries = [];
$updated_by = get_full_name($pdo, $_SESSION['userid']);
$now = date('Y-m-d H:i:s');

// Track if fullname needs updating
$fullname_needs_update = false;

// Employee
foreach ($employee_fields as $f) {
    $old = $old_employee[$f] ?? '';
    $new = $emp[$f];
    if ($old !== $new) {
        $log_entries[] = [
            'employee_id' => $profile_userid,
            'field_name'  => format_field_name($f),
            'old_value'   => $old,
            'new_value'   => $new,
            'updated_by'  => $updated_by,
            'updated_at'  => $now
        ];
        // If one of the fullname fields changed, flag for update
        if (in_array($f, ['last_name', 'first_name', 'middle_name', 'suffix'])) {
            $fullname_needs_update = true;
        }
    }
}
// Address
foreach ($address_fields as $f) {
    $old = $old_address[$f] ?? '';
    $new = $addr[$f];
    if ($old !== $new) {
        $log_entries[] = [
            'employee_id' => $profile_userid,
            'field_name'  => format_field_name($f),
            'old_value'   => $old,
            'new_value'   => $new,
            'updated_by'  => $updated_by,
            'updated_at'  => $now
        ];
    }
}
// Statutory Benefits
foreach ($benefit_fields as $f) {
    $old = $old_benefit[$f] ?? '';
    $new = $benef[$f];
    if ($old !== $new) {
        $log_entries[] = [
            'employee_id' => $profile_userid,
            'field_name'  => format_field_name($f),
            'old_value'   => $old,
            'new_value'   => $new,
            'updated_by'  => $updated_by,
            'updated_at'  => $now
        ];
    }
}
// Government ID
foreach ($id_fields as $f) {
    $old = $old_gid[$f] ?? '';
    $new = $gid[$f];
    if ($old !== $new) {
        $log_entries[] = [
            'employee_id' => $profile_userid,
            'field_name'  => format_field_name($f),
            'old_value'   => $old,
            'new_value'   => $new,
            'updated_by'  => $updated_by,
            'updated_at'  => $now
        ];
    }
}

// ---- Step 4: Perform Update in a Transaction ----
try {
    $pdo->beginTransaction();

    // If any fullname-related field changed, set the new fullname value
    if ($fullname_needs_update) {
        $new_fullname = build_fullname($emp['last_name'], $emp['first_name'], $emp['suffix'], $emp['middle_name']);
        $emp['fullname'] = $new_fullname;
    } else {
        // Keep the old fullname if not changed
        $emp['fullname'] = $old_employee['fullname'];
    }

    // Employee
    $sql = "UPDATE employee SET
      last_name = :last_name,
      first_name = :first_name,
      middle_name = :middle_name,
      suffix = :suffix,
      gender = :gender,
      birthdate = :birthdate,
      citizenship = :citizenship,
      civilstatus = :civilstatus,
      religion = :religion,
      tribe = :tribe,
      telephoneno = :telephoneno,
      mobilenumber = :mobilenumber,
      emailaddress = :emailaddress,
      height = :height,
      weight = :weight,
      blood_type = :blood_type,
      fullname = :fullname
      WHERE id = :userid";
    $emp['userid'] = $profile_userid;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($emp);

    // Address
    $stmt = $pdo->prepare("SELECT id FROM employee_address WHERE userid = :userid");
    $stmt->execute(['userid' => $profile_userid]);
    if ($stmt->fetchColumn()) {
        $sql = "UPDATE employee_address SET
            birth_place = :birth_place,
            permanent_add = :permanent_add,
            residential_add = :residential_add
            WHERE userid = :userid";
    } else {
        $sql = "INSERT INTO employee_address (userid, birth_place, permanent_add, residential_add)
                VALUES (:userid, :birth_place, :permanent_add, :residential_add)";
    }
    $addr['userid'] = $profile_userid;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($addr);

    // Statutory benefits
    $stmt = $pdo->prepare("SELECT id FROM statutory_benefits WHERE userid = :userid");
    $stmt->execute(['userid' => $profile_userid]);
    if ($stmt->fetchColumn()) {
        $sql = "UPDATE statutory_benefits SET
            gsis_number = :gsis_number,
            pagibig_number = :pagibig_number,
            philhealth_number = :philhealth_number,
            sss_number = :sss_number,
            tin = :tin
            WHERE userid = :userid";
    } else {
        $sql = "INSERT INTO statutory_benefits (userid, gsis_number, pagibig_number, philhealth_number, sss_number, tin)
                VALUES (:userid, :gsis_number, :pagibig_number, :philhealth_number, :sss_number, :tin)";
    }
    $benef['userid'] = $profile_userid;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($benef);

    // Government ID
    $stmt = $pdo->prepare("SELECT id FROM government_identification WHERE userid = :userid");
    $stmt->execute(['userid' => $profile_userid]);
    if ($stmt->fetchColumn()) {
        $sql = "UPDATE government_identification SET
            identification_type = :identification_type,
            identification_no = :identification_no,
            date_or_placeofissuance = :date_or_placeofissuance
            WHERE userid = :userid";
    } else {
        $sql = "INSERT INTO government_identification (userid, identification_type, identification_no, date_or_placeofissuance)
                VALUES (:userid, :identification_type, :identification_no, :date_or_placeofissuance)";
    }
    $gid['userid'] = $profile_userid;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($gid);

    // ---- Step 5: Insert log entries ----
    $log_sql = "INSERT INTO employee_update_history 
        (employee_id, field_name, old_value, new_value, updated_by, updated_at)
        VALUES (:employee_id, :field_name, :old_value, :new_value, :updated_by, :updated_at)";

    $log_stmt = $pdo->prepare($log_sql);
    foreach ($log_entries as $entry) {
        $log_stmt->execute($entry);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}