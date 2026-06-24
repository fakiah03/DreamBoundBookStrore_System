<?php
session_start();
require_once '../db.php'; 

//Retrieving Data
$logs_result = $conn->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 3");

//Data Validation & Display Process (Looping)
if ($logs_result && $logs_result->num_rows > 0) {
    while ($log = $logs_result->fetch_assoc()) {
        //Data Format & Display
        $time = date('H:i:s', strtotime($log['created_at']));
        $message = htmlspecialchars($log['log_message']);
        

        echo '<div class="log-line">';
        echo '  <span class="log-time">[' . $time . ']</span> ' . $message;
        echo '</div>';
    }
} else {
    //Condition When No Logs Are Available (Fallback)
    echo '<div class="log-line"><span class="log-time">[' . date('H:i:s') . ']</span> Terminal is ready. No new logs.</div>';
}
?>