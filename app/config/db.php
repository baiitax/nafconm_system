<?php
require_once __DIR__ . '/../../config/env.php';

/* Prevent direct file access */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    die('Direct access not allowed');
}

define('DB_SERVER', app_env_required('APP_DB_HOST'));
define('DB_USERNAME', app_env_required('APP_DB_USER'));
define('DB_PASSWORD', app_env_required('APP_DB_PASSWORD', true));
define('DB_NAME', app_env_required('APP_DB_NAME'));
 
/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 
// Check connection
if($conn->connect_error){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

/**
 * Global Activity Logger
 * Logs student/admin actions into the activity_logs table
 */
if (!function_exists('log_activity')) {
    function log_activity($user_id, $action, $details = null) {
        global $conn;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        // We use a try-catch or silent check to ensure logging never breaks the main app
        $sql = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issss", $user_id, $action, $details, $ip, $agent);
            if (!$stmt->execute()) {
                // Log to server error log instead of crashing the UI
                error_log("Logging system failed: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}
?>