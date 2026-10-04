<?php
/**
 * Logs an activity to the database.
 */
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = '') {
        global $conn;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

?>