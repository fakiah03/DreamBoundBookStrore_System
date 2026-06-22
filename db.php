<?php
$host = "localhost";
$user = "root";
$pass = ""; 
$db   = "dreambound_db";

$conn = @new mysqli($host, $user, $pass, $db, 3307);

if ($conn->connect_error) {
    $conn = @new mysqli($host, $user, $pass, $db, 3306);
}

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>