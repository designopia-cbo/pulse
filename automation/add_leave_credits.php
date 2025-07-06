<?php
// /pulse/automation/add_leave_credits.php

header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../init.php'); // assumes $pdo is defined

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get current year-month (e.g. "2025-07")
$now = new DateTime();
$thisMonth = $now->format('Y-m');

// PREVENT DOUBLE GRANT: Check if already granted this month
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_credit_grant_log WHERE grant_month = :month");
$stmt->execute([':month' => $thisMonth]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Leave credits have already been granted for this month.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch all users from credit_leave
    $stmt = $pdo->query("SELECT userid, vacationleave, sickleave, spleave FROM credit_leave");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $isFirstDayOfYear = ($now->format('n') == 1 && $now->format('j') == 1);

    foreach ($users as $user) {
        $userid = $user['userid'];
        $previousVLeave = (float)$user['vacationleave'];
        $previousSLeave = (float)$user['sickleave'];
        $previousSPLeave = (float)$user['spleave'];

        $newVLeave = $previousVLeave + 1.25;
        $newSLeave = $previousSLeave + 1.25;
        $newSPLeave = $previousSPLeave;
        if ($isFirstDayOfYear) {
            $newSPLeave += 3.0;
        }

        // Update leave balances
        $updateSql = "UPDATE credit_leave SET vacationleave = :newVLeave, sickleave = :newSLeave";
        if ($isFirstDayOfYear) {
            $updateSql .= ", spleave = :newSPLeave";
        }
        $updateSql .= " WHERE userid = :userid";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->bindValue(':newVLeave', $newVLeave);
        $updateStmt->bindValue(':newSLeave', $newSLeave);
        $updateStmt->bindValue(':userid', $userid);
        if ($isFirstDayOfYear) {
            $updateStmt->bindValue(':newSPLeave', $newSPLeave);
        }
        $updateStmt->execute();

        // Prepare log statement
        $logStmt = $pdo->prepare("INSERT INTO leave_credit_log (userid, leave_type, change_type, previous_balance, changed_amount, new_balance, leave_id)
            VALUES (:userid, :leave_type, 'ADDITION', :previous_balance, :changed_amount, :new_balance, NULL)");

        // Log for Vacation Leave
        $logStmt->execute([
            ':userid' => $userid,
            ':leave_type' => 'VACATION',
            ':previous_balance' => $previousVLeave,
            ':changed_amount' => 1.25,
            ':new_balance' => $newVLeave
        ]);
        // Log for Sick Leave
        $logStmt->execute([
            ':userid' => $userid,
            ':leave_type' => 'SICK',
            ':previous_balance' => $previousSLeave,
            ':changed_amount' => 1.25,
            ':new_balance' => $newSLeave
        ]);
        // Log for SPL only if it's January 1st
        if ($isFirstDayOfYear) {
            $logStmt->execute([
                ':userid' => $userid,
                ':leave_type' => 'SPECIAL PRIVILEGE LEAVE',
                ':previous_balance' => $previousSPLeave,
                ':changed_amount' => 3.0,
                ':new_balance' => $newSPLeave
            ]);
        }
    }

    // Log this month's grant event (AFTER all updates succeed)
    $stmt = $pdo->prepare("INSERT INTO leave_credit_grant_log (grant_month) VALUES (:month)");
    $stmt->execute([':month' => $thisMonth]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Leave credits successfully added!']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>