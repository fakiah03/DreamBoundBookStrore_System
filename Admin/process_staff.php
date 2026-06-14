<?php
session_start();
require_once '../db.php'; 

// Security Restrictions: Ensure that only Admins can run this process
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Auth/login.php");
    exit();
}

// Security Restrictions: Ensure that only Admins can run this process
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Auth/login.php");
    exit();
}

// Check if data is submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Retrieve data from the dashboard form
    $fullname   = $_POST['staff_name'];
    $email      = $_POST['staff_email'];
    $phone      = $_POST['staff_phone'];
    $plain_pass = $_POST['staff_password'];
    $role       = $_POST['staff_role']; // Value: 'clerk' or 'manager'
    
    // 2. Password encryption/hash for security
    $hashed_pass = password_hash($plain_pass, PASSWORD_DEFAULT);
    
    // 3. Set default values for staff
    $customer_id_str = NULL; // Staff do not require a customer ID (#CUST-XXXX)
    $membership_tier = 'Regular'; // Can be set to Regular or NULL based on your database structure

    // 4. Prepare SQL SQL commands (Using Prepared Statements to avoid SQL Injection)
    $sql = "INSERT INTO users (customer_id_str, fullname, email, password, phone, role, membership_tier) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $customer_id_str, $fullname, $email, $hashed_pass, $phone, $role, $membership_tier);
    
    // 5. Run the query and return to the dashboard
    if ($stmt->execute()) {
        // Successful: Return to dashboard with success status message
        header("Location: ad_DashBoard.php?status=staff_success");
        exit();
    } else {
        // Failed: Show an error if there is a problem (e.g.: duplicate email)
        echo "Error registering staff: " . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
} else {
    // If this file is accessed directly without submitting the form, block access
    header("Location: ad_DashBoard.php");
    exit();
}
?>