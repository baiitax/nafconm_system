<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Delete Hostel Application
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_application'])) {
    $application_id_to_delete = $_POST['application_id'];
    $sql = "DELETE FROM hostel_applications WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $application_id_to_delete);
        if ($stmt->execute()) {
            $action_success = "Hostel application deleted successfully.";
        } else {
            $action_err = "Error deleting application. Please try again.";
        }
        $stmt->close();
    }
}

// Handle Update Hostel Application Status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['new_status'];

    $sql = "UPDATE hostel_applications SET status = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $new_status, $application_id);
        if ($stmt->execute()) {
            $action_success = "Application status updated successfully.";
        } else {
            $action_err = "Error updating application status. Please try again.";
        }
        $stmt->close();
    }
}

// Fetch all hostel applications for display
$applications = [];
$sql = "SELECT ha.id, ha.student_id, ha.application_date, ha.status, ha.room_preference,
               s.full_name as student_name, s.student_id_number
        FROM hostel_applications ha
        JOIN students s ON ha.student_id = s.id
        ORDER BY ha.application_date DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}
