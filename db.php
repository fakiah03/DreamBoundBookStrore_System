<?php
$host = "127.0.0.1"; // Guna IP ini lebih stabil untuk Laragon & XAMPP
$user = "root";
$pass = ""; 
$db   = "dreambound_db";

// 1. Cuba sambung menggunakan port default Laragon (3306) dahulu
$conn = @new mysqli($host, $user, $pass, $db, 3306);

// 2. Jika gagal (mungkin di PC kawan yang guna XAMPP port 3307), sistem akan automatik cuba port 3307
if ($conn->connect_error) {
    $conn = @new mysqli($host, $user, $pass, $db, 3307);
}

// 3. Jika kedua-duanya masih gagal, baru paparkan ralat
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>