<?php
// process_voucher.php — Admin: Add or deactivate vouchers from the Dashboard
session_start();
require_once '../db.php';

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD NEW VOUCHER
    if (isset($_POST['add_voucher'])) {
        $code  = strtoupper(trim($_POST['voucher_code']));
        $type  = ($_POST['voucher_type'] === 'flat') ? 'flat' : 'percentage';
        $value = floatval($_POST['voucher_value']);

        if (empty($code) || $value <= 0) {
            header("Location: ad_DashBoard.php?voucher_error=invalid");
            exit();
        }

        // Check duplicate
        $check_stmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $check = $check_stmt->get_result();
        $check_stmt->close();

        if ($check && $check->num_rows > 0) {
            header("Location: ad_DashBoard.php?voucher_error=duplicate");
            exit();
        }

        $ins_stmt = $conn->prepare("INSERT INTO vouchers (code, type, value, status) VALUES (?, ?, ?, 'active')");
        $ins_stmt->bind_param("ssd", $code, $type, $value);
        $ins_stmt->execute();
        $ins_stmt->close();

        $log_stmt = $conn->prepare("INSERT INTO system_logs (log_message) VALUES (?)");
        $log_msg = "Admin added voucher: $code ($type, $value)";
        $log_stmt->bind_param("s", $log_msg);
        $log_stmt->execute();
        $log_stmt->close();

        header("Location: ad_DashBoard.php?voucher_success=1");
        exit();
    }

    // DEACTIVATE VOUCHER
    if (isset($_POST['deactivate_voucher'])) {
        $id = intval($_POST['voucher_id']);

        $deact_stmt = $conn->prepare("UPDATE vouchers SET status = 'inactive' WHERE id = ?");
        $deact_stmt->bind_param("i", $id);
        $deact_stmt->execute();
        $deact_stmt->close();

        $log_stmt = $conn->prepare("INSERT INTO system_logs (log_message) VALUES (?)");
        $log_msg = "Admin deactivated voucher ID: $id";
        $log_stmt->bind_param("s", $log_msg);
        $log_stmt->execute();
        $log_stmt->close();

        header("Location: ad_DashBoard.php?voucher_success=deactivated");
        exit();
    }
}

// Fallback
header("Location: ad_DashBoard.php");
exit();
?>
