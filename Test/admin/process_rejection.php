<?php
session_start();
// Assuming you have a database connection file and a functions file
include_once '../config/db.php';
include_once '../config/functions.php';
include_once '../includes/header.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_application_id'])) {
    $application_id = filter_input(INPUT_POST, 'reject_application_id', FILTER_SANITIZE_NUMBER_INT);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
    $reviewer_id = $_SESSION['user_id'];

    try {
        // Fetch application data to ensure it's valid and not already processed
        $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE id = ? AND status NOT IN ('accepted', 'rejected')");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            throw new Exception("Application not found or has already been processed.");
        }

        // Update the application status to 'rejected'
        $stmt = $pdo->prepare(
            "UPDATE admission_applications 
             SET status = 'rejected', reviewed_by_user_id = ?, review_date = NOW(), review_remarks = ?
             WHERE id = ?"
        );
        $stmt->execute([$reviewer_id, $remarks, $application_id]);
        
        // Log this action
        $full_name = trim($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name']);
        $log_action = "Rejected admission for {$full_name} (App ID: {$application_id}).";
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$reviewer_id, $log_action]);

        // Optionally, send a rejection email
        // send_rejection_email($application['email'], $full_name, $remarks);

        $_SESSION['success_message'] = "Application has been rejected.";
        header('Location: /admin/admissions.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error rejecting application: " . $e->getMessage();
        header('Location: /admin/admissions.php?view=' . $application_id);
        exit;
    }
} else {
    header('Location: /admin/admissions.php');
    exit;
}