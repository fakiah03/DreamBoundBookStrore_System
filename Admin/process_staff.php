<?php
session_start();
require_once '../db.php'; // Sila pastikan jalan fail database anda betul

// Sekatan Keselamatan: Pastikan hanya Admin sahaja yang boleh jalankan proses ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Auth/login.php");
    exit();
}

// Semak jika data dihantar melalui borang POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Ambil data dari borang dashboard
    $fullname   = $_POST['staff_name'];
    $email      = $_POST['staff_email'];
    $phone      = $_POST['staff_phone'];
    $plain_pass = $_POST['staff_password'];
    $role       = $_POST['staff_role']; // Nilai: 'clerk' atau 'manager'
    
    // 2. Enkripsi/Hash kata laluan untuk keselamatan
    $hashed_pass = password_hash($plain_pass, PASSWORD_DEFAULT);
    
    // 3. Tetapkan nilai lalai (default) untuk kakitangan
    $customer_id_str = NULL; // Kakitangan tidak memerlukan ID pelanggan (#CUST-XXXX)
    $membership_tier = 'Regular'; // Boleh diletakkan Regular atau NULL mengikut struktur db anda

    // 4. Sediakan arahan SQL SQL (Menggunakan Prepared Statement untuk elak SQL Injection)
    $sql = "INSERT INTO users (customer_id_str, fullname, email, password, phone, role, membership_tier) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $customer_id_str, $fullname, $email, $hashed_pass, $phone, $role, $membership_tier);
    
    // 5. Jalankan query dan kembalikan ke dashboard
    if ($stmt->execute()) {
        // Berjaya: Kembali ke dashboard dengan mesej status sukses
        header("Location: ad_DashBoard.php?status=staff_success");
        exit();
    } else {
        // Gagal: Paparkan ralat jika ada masalah (cth: e-mel bertindih)
        echo "Ralat pendaftaran kakitangan: " . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
} else {
    // Jika fail ini diakses secara terus tanpa hantar borang, sekat akses
    header("Location: ad_DashBoard.php");
    exit();
}
?>