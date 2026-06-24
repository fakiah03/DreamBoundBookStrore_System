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

// --- FETCH SINGLE CUSTOMER (for populating Edit modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid customer reference.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, fullname, email, phone, membership_tier, customer_id_str FROM users WHERE id = ? AND role = 'customer'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit();
    }

    echo json_encode(['success' => true, 'customer' => $result->fetch_assoc()]);
    $stmt->close();
    exit();
}

// --- SAVE CUSTOMER UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id         = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $fullname        = trim($_POST['fullname'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $membership_tier = trim($_POST['membership_tier'] ?? '');

    if ($user_id <= 0 || empty($fullname) || empty($email) || empty($phone) || empty($membership_tier)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all the required fields.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit();
    }

    // Ensure the email isn't already used by a different user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This email address is already in use by another user.']);
        exit();
    }
    $check_stmt->close();

    $update_stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, membership_tier = ? WHERE id = ? AND role = 'customer'");
    $update_stmt->bind_param("ssssi", $fullname, $email, $phone, $membership_tier, $user_id);

    if ($update_stmt->execute()) {
        $log_message = "Customer record updated: " . $fullname . " (ID #" . $user_id . ") by admin.";
        $log_stmt = $conn->prepare("INSERT INTO system_logs (log_message) VALUES (?)");
        $log_stmt->bind_param("s", $log_message);
        $log_stmt->execute();
        $log_stmt->close();

        echo json_encode(['success' => true, 'message' => 'Customer updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: failed to update customer.']);
    }

    $update_stmt->close();
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
exit();
