<?php
/**
 * Logs an activity to the database.
 */
function log_activity($conn, $user_id, $action) {
    $sql = "INSERT INTO activity_logs (user_id, action) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("is", $user_id, $action);
        $stmt->execute();
        $stmt->close();
    }
}
?>