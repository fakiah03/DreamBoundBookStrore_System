<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

// Security: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer reference.']);
    exit();
}

// Fetch basic customer info
$stmt = $conn->prepare("SELECT fullname, customer_id_str, email FROM users WHERE id = ? AND role = 'customer'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer_result = $stmt->get_result();

if ($customer_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit();
}

$customer = $customer_result->fetch_assoc();
$stmt->close();

// Fetch order history for this customer
$orders = [];
// FIXED: Changed 'customer_id' to 'user_id' and 'order_date' to 'created_at' to match your schema
$order_stmt = $conn->prepare("SELECT id, created_at, total_amount, status FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$order_stmt->bind_param("i", $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

while ($row = $order_result->fetch_assoc()) {
    $orders[] = [
        'id'           => $row['id'],
        'order_date'   => $row['created_at'], // Kept 'order_date' as JSON key for front-end consistency
        'total_amount' => $row['total_amount'],
        'status'       => $row['status'] ?? 'N/A'
    ];
}
$order_stmt->close();

echo json_encode([
    'success'  => true,
    'customer' => [
        'fullname'        => $customer['fullname'],
        'customer_id_str' => $customer['customer_id_str'],
        'email'           => $customer['email']
    ],
    'orders' => $orders
]);
exit();