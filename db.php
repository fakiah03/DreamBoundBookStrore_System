<?php
$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "dreambound_db";

// 1. Mula-mula, cuba port 3307 (Laluan khas untuk Laragon ANDA)
$conn = @new mysqli($host, $user, $pass, $db, 3307);

// 2. Jika gagal, maksudnya sistem sedang run di XAMPP kawan anda (port 3306)
if ($conn->connect_error) {
    $conn = @new mysqli($host, $user, $pass, $db, 3306);
}

// 3. Jika kedua-duanya masih gagal, baru keluar ralat database
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>