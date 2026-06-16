<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php"); exit();
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $conn->query("DELETE FROM reviews WHERE id=$id");
    $conn->query("INSERT INTO system_logs (log_message) VALUES ('Admin deleted review ID: $id')");
}
header("Location: ad_DashBoard.php?review_action=deleted");
exit();
