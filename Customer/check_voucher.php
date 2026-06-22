<?php
// check_voucher.php — AJAX endpoint (Customer): returns JSON {valid, type, value}
// Called from cust_payment.php when the customer clicks "Apply" on a voucher code.
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['valid' => false]);
    exit();
}

$code = strtoupper(trim($_GET['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['valid' => false]);
    exit();
}

$stmt = $conn->prepare("SELECT type, value FROM vouchers WHERE code = ? AND status = 'active' LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $v = $result->fetch_assoc();
    echo json_encode(['valid' => true, 'type' => $v['type'], 'value' => (float)$v['value']]);
} else {
    echo json_encode(['valid' => false]);
}

$stmt->close();
?>
