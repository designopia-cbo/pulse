<?php
require_once('../init.php');
header('Content-Type: application/json');

// Helper function to log field changes
function logFieldChange($pdo, $employee_id, $field_name, $old_value, $new_value, $updated_by) {
    // Convert null to empty string for comparison
    $old_value = $old_value ?? '';
    $new_value = $new_value ?? '';
    
    if ((string)$old_value !== (string)$new_value) {
        try {
            // Map database field names to formal display names
            $field_display_names = [
                'first_name' => 'FIRST NAME',
                'middle_name' => 'MIDDLE NAME',
                'last_name' => 'LAST NAME',
                'suffix' => 'SUFFIX',
                'gender' => 'GENDER',
                'birthdate' => 'BIRTH DATE',
                'citizenship' => 'CITIZENSHIP',
                'civilstatus' => 'CIVIL STATUS',
                'religion' => 'RELIGION',
                'tribe' => 'TRIBE',
                'telephoneno' => 'TELEPHONE NUMBER',
                'mobilenumber' => 'MOBILE NUMBER',
                'emailaddress' => 'EMAIL ADDRESS',
                'height' => 'HEIGHT',
                'weight' => 'WEIGHT',
                'blood_type' => 'BLOOD TYPE',
                'fullname' => 'FULL NAME',
                'birth_place' => 'BIRTH PLACE',
                'permanent_add' => 'PERMANENT ADDRESS',
                'residential_add' => 'RESIDENTIAL ADDRESS',
                'gsis_number' => 'GSIS NUMBER',
                'pagibig_number' => 'PAG-IBIG NUMBER',
                'philhealth_number' => 'PHILHEALTH NUMBER',
                'tin' => 'TIN',
                'sss_number' => 'SSS NUMBER',
                'identification_type' => 'IDENTIFICATION TYPE',
                'identification_no' => 'IDENTIFICATION NUMBER',
                'date_or_placeofissuance' => 'DATE OR PLACE OF ISSUANCE'
            ];
            
            // Get the formal display name or use the original field name in uppercase
            $display_field_name = $field_display_names[$field_name] ?? strtoupper(str_replace('_', ' ', $field_name));
            
            $log_stmt = $pdo->prepare("INSERT INTO employee_update_history (employee_id, field_name, old_value, new_value, updated_by, updated_at) VALUES (:employee_id, :field_name, :old_value, :new_value, :updated_by, NOW())");
            $log_stmt->execute([
                'employee_id' => $employee_id,
                'field_name' => $display_field_name,
                'old_value' => strtoupper($old_value),
                'new_value' => strtoupper($new_value),
                'updated_by' => strtoupper($updated_by)
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Audit log error: " . $e->getMessage());
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST data - handle both JSON and form data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // If JSON decode failed, use $_POST
        if (!$data) {
            $data = $_POST;
        }
        
        // Get the profile userid - prioritize POST data, fallback to session
        $profile_userid = $data['profile_userid'] ?? $_SESSION['userid'];
        
        // Validate required fields (all except suffix)
        $required_fields = [
            'last_name', 'first_name', 'middle_name', 'gender', 'birthdate',
            'citizenship', 'civilstatus', 'religion', 'tribe', 'telephoneno', 
            'mobilenumber', 'emailaddress', 'height', 'weight', 'blood_type',
            'birth_place', 'permanent_add', 'residential_add',
            'gsis_number', 'pagibig_number', 'philhealth_number', 'tin', 'sss_number',
            'identification_type', 'identification_no', 'date_or_placeofissuance'
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            echo json_encode([
                'success' => false, 
                'message' => 'The following fields are required: ' . implode(', ', $missing_fields)
            ]);
            exit;
        }
        
        // Convert all fields to uppercase (except suffix which can be empty)
        foreach ($data as $key => $value) {
            if ($key !== 'profile_userid' && $key !== 'suffix') {
                $data[$key] = strtoupper(trim($value));
            } elseif ($key === 'suffix') {
                $data[$key] = !empty(trim($value)) ? strtoupper(trim($value)) : '';
            }
        }
        
        // Get the logged-in user's name for audit logging
        $logged_in_userid = $_SESSION['userid'];
        $user_stmt = $pdo->prepare("SELECT first_name, last_name FROM employee WHERE id = :userid");
        $user_stmt->execute(['userid' => $logged_in_userid]);
        $logged_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $updated_by = trim(($logged_user['first_name'] ?? '') . ' ' . ($logged_user['last_name'] ?? ''));
        if (empty($updated_by)) {
            $updated_by = 'User ID: ' . $logged_in_userid;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current employee data for comparison
        $current_stmt = $pdo->prepare("SELECT last_name, first_name, middle_name, suffix, gender, birthdate, citizenship, civilstatus, religion, tribe, telephoneno, mobilenumber, emailaddress, height, weight, blood_type, fullname FROM employee WHERE id = :userid");
        $current_stmt->execute(['userid' => $profile_userid]);
        $current_employee = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        // 1. Update employee table
        $employee_fields = [
            'last_name', 'first_name', 'middle_name', 'suffix', 'gender', 'birthdate',
            'citizenship', 'civilstatus', 'religion', 'tribe', 'telephoneno', 
            'mobilenumber', 'emailaddress', 'height', 'weight', 'blood_type'
        ];
        
        $employee_updates = [];
        $employee_params = [];
        
        foreach ($employee_fields as $field) {
            if (isset($data[$field])) {
                $old_value = $current_employee[$field] ?? '';
                $new_value = $data[$field]; // Already converted to uppercase above
                
                // Log the change
                logFieldChange($pdo, $profile_userid, $field, $old_value, $new_value, $updated_by);
                
                $employee_updates[] = "$field = :$field";
                $employee_params[$field] = $new_value;
            }
        }
        
        // Generate fullname if any name components are being updated
        if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name']) || isset($data['suffix'])) {
            // Use new values if provided, otherwise use current database values (all already uppercase)
            $first_name = $data['first_name'] ?? strtoupper($current_employee['first_name'] ?? '');
            $middle_name = $data['middle_name'] ?? strtoupper($current_employee['middle_name'] ?? '');
            $last_name = $data['last_name'] ?? strtoupper($current_employee['last_name'] ?? '');
            $suffix = $data['suffix'] ?? strtoupper($current_employee['suffix'] ?? '');
            
            // Build fullname: FIRST_NAME MIDDLE_INITIAL. LAST_NAME SUFFIX
            $fullname_parts = [];
            
            if (!empty($first_name)) {
                $fullname_parts[] = $first_name; // Already uppercase
            }
            
            if (!empty($middle_name)) {
                $middle_initial = substr($middle_name, 0, 1);
                if ($middle_initial) {
                    $fullname_parts[] = $middle_initial . '.';
                }
            }
            
            if (!empty($last_name)) {
                $fullname_parts[] = $last_name; // Already uppercase
            }
            
            if (!empty($suffix) && trim($suffix) !== '') {
                $fullname_parts[] = $suffix; // Already uppercase
            }
            
            $fullname = implode(' ', $fullname_parts);
            
            if (!empty($fullname)) {
                $old_fullname = $current_employee['fullname'] ?? '';
                
                // Log fullname change
                logFieldChange($pdo, $profile_userid, 'fullname', $old_fullname, $fullname, $updated_by);
                
                $employee_updates[] = "fullname = :fullname";
                $employee_params['fullname'] = $fullname;
            }
        }
        
        if (!empty($employee_updates)) {
            $employee_sql = "UPDATE employee SET " . implode(', ', $employee_updates) . " WHERE id = :userid";
            $employee_params['userid'] = $profile_userid;
            $stmt = $pdo->prepare($employee_sql);
            $stmt->execute($employee_params);
        }
        
        // 2. Update employee_address table
        $address_fields = ['birth_place', 'permanent_add', 'residential_add'];
        $address_updates = [];
        $address_params = [];
        
        // Get current address data for comparison
        $current_address_stmt = $pdo->prepare("SELECT birth_place, permanent_add, residential_add FROM employee_address WHERE userid = :userid");
        $current_address_stmt->execute(['userid' => $profile_userid]);
        $current_address = $current_address_stmt->fetch(PDO::FETCH_ASSOC);
        
        foreach ($address_fields as $field) {
            if (isset($data[$field])) {
                $old_value = $current_address[$field] ?? '';
                $new_value = $data[$field]; // Already converted to uppercase above
                
                // Log the change
                logFieldChange($pdo, $profile_userid, $field, $old_value, $new_value, $updated_by);
                
                $address_updates[] = "$field = :$field";
                $address_params[$field] = $new_value;
            }
        }
        
        if (!empty($address_updates)) {
            // Check if record exists
            $check_stmt = $pdo->prepare("SELECT id FROM employee_address WHERE userid = :userid");
            $check_stmt->execute(['userid' => $profile_userid]);
            
            if ($check_stmt->fetch()) {
                // Update existing record
                $address_sql = "UPDATE employee_address SET " . implode(', ', $address_updates) . " WHERE userid = :userid";
                $address_params['userid'] = $profile_userid;
                $stmt = $pdo->prepare($address_sql);
                $stmt->execute($address_params);
            } else {
                // Insert new record
                $address_fields_str = implode(', ', array_keys($address_params));
                $address_placeholders = ':' . implode(', :', array_keys($address_params));
                $address_params['userid'] = $profile_userid;
                $insert_sql = "INSERT INTO employee_address (userid, $address_fields_str) VALUES (:userid, $address_placeholders)";
                $stmt = $pdo->prepare($insert_sql);
                $stmt->execute($address_params);
            }
        }
        
        // 3. Update statutory_benefits table
        $benefits_fields = ['gsis_number', 'pagibig_number', 'philhealth_number', 'tin', 'sss_number'];
        $benefits_updates = [];
        $benefits_params = [];
        
        // Get current benefits data for comparison
        $current_benefits_stmt = $pdo->prepare("SELECT gsis_number, pagibig_number, philhealth_number, tin, sss_number FROM statutory_benefits WHERE userid = :userid");
        $current_benefits_stmt->execute(['userid' => $profile_userid]);
        $current_benefits = $current_benefits_stmt->fetch(PDO::FETCH_ASSOC);
        
        foreach ($benefits_fields as $field) {
            if (isset($data[$field])) {
                $old_value = $current_benefits[$field] ?? '';
                $new_value = $data[$field]; // Already converted to uppercase above
                
                // Log the change
                logFieldChange($pdo, $profile_userid, $field, $old_value, $new_value, $updated_by);
                
                $benefits_updates[] = "$field = :$field";
                $benefits_params[$field] = $new_value;
            }
        }
        
        if (!empty($benefits_updates)) {
            // Check if record exists
            $check_stmt = $pdo->prepare("SELECT id FROM statutory_benefits WHERE userid = :userid");
            $check_stmt->execute(['userid' => $profile_userid]);
            
            if ($check_stmt->fetch()) {
                // Update existing record
                $benefits_sql = "UPDATE statutory_benefits SET " . implode(', ', $benefits_updates) . " WHERE userid = :userid";
                $benefits_params['userid'] = $profile_userid;
                $stmt = $pdo->prepare($benefits_sql);
                $stmt->execute($benefits_params);
            } else {
                // Insert new record
                $benefits_fields_str = implode(', ', array_keys($benefits_params));
                $benefits_placeholders = ':' . implode(', :', array_keys($benefits_params));
                $benefits_params['userid'] = $profile_userid;
                $insert_sql = "INSERT INTO statutory_benefits (userid, $benefits_fields_str) VALUES (:userid, $benefits_placeholders)";
                $stmt = $pdo->prepare($insert_sql);
                $stmt->execute($benefits_params);
            }
        }
        
        // 4. Update government_identification table
        $gov_id_fields = ['identification_type', 'identification_no', 'date_or_placeofissuance'];
        $gov_id_updates = [];
        $gov_id_params = [];
        
        // Get current government ID data for comparison
        $current_gov_id_stmt = $pdo->prepare("SELECT identification_type, identification_no, date_or_placeofissuance FROM government_identification WHERE userid = :userid");
        $current_gov_id_stmt->execute(['userid' => $profile_userid]);
        $current_gov_id = $current_gov_id_stmt->fetch(PDO::FETCH_ASSOC);
        
        foreach ($gov_id_fields as $field) {
            if (isset($data[$field])) {
                $old_value = $current_gov_id[$field] ?? '';
                $new_value = $data[$field]; // Already converted to uppercase above
                
                // Log the change
                logFieldChange($pdo, $profile_userid, $field, $old_value, $new_value, $updated_by);
                
                $gov_id_updates[] = "$field = :$field";
                $gov_id_params[$field] = $new_value;
            }
        }
        
        if (!empty($gov_id_updates)) {
            // Check if record exists
            $check_stmt = $pdo->prepare("SELECT id FROM government_identification WHERE userid = :userid");
            $check_stmt->execute(['userid' => $profile_userid]);
            
            if ($check_stmt->fetch()) {
                // Update existing record
                $gov_id_sql = "UPDATE government_identification SET " . implode(', ', $gov_id_updates) . " WHERE userid = :userid";
                $gov_id_params['userid'] = $profile_userid;
                $stmt = $pdo->prepare($gov_id_sql);
                $stmt->execute($gov_id_params);
            } else {
                // Insert new record
                $gov_id_fields_str = implode(', ', array_keys($gov_id_params));
                $gov_id_placeholders = ':' . implode(', :', array_keys($gov_id_params));
                $gov_id_params['userid'] = $profile_userid;
                $insert_sql = "INSERT INTO government_identification (userid, $gov_id_fields_str) VALUES (:userid, $gov_id_placeholders)";
                $stmt = $pdo->prepare($insert_sql);
                $stmt->execute($gov_id_params);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile updated successfully!',
            'userid' => $profile_userid
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method: ' . $_SERVER['REQUEST_METHOD'],
        'debug' => [
            'actual_method' => $_SERVER['REQUEST_METHOD'],
            'expected' => 'POST',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'post_data' => $_POST,
            'raw_input' => file_get_contents('php://input')
        ]
    ]);
}
?>