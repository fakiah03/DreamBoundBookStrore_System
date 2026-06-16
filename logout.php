<?php
session_start();
require_once 'db.php'; 

// 1. Log the user logout activity in system logs prior to session destruction.
if (isset($_SESSION['fullname'])) {
    $nama = $_SESSION['fullname'];
    $role = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Pengguna';
    
    $log_msg = "$role $nama logged out of the system.";
    $conn->query("INSERT INTO system_logs (log_message) VALUES ('$log_msg')");
}

// 2. Clear all session data.
$_SESSION = array(); 

// If a session cookie is used, also remove the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Fully terminate and destroy the session.
session_destroy();

// Navigation flow after logout (redirect).
// Redirect the user to the login page or the main index page.
echo "<script>
    alert('You have successfully logged out.');
    window.location.href = 'Auth/login.php'; 
</script>";
exit();
?>