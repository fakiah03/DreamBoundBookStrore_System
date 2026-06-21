<?php
$host = "localhost";
$user = "root";      
$pass = "";           
$db_name = "dreambound_db";
$port = 3307; 

// Pass $port as the 5th argument below
$conn = new mysqli($host, $user, $pass, $db_name, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>