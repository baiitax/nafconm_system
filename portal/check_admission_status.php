<?php
session_start();
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['email']) || !isset($_GET['token'])) {
    header('Location: ../apply.php');
    exit();
}

$email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
$token = trim($_GET['token']);

if (!$email || empty($token)) {
    $_SESSION['status_error_message'] = "Invalid email or token provided.";
    header('Location: ../apply.php?email=' . urlencode($_GET['email']) . '&token=' . urlencode($_GET['token']));
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE email = ? AND applicant_token = ?");
    $stmt->execute([$email, $token]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($application) {
        // Fetch O'Level results
        $olevel_stmt = $pdo->prepare("SELECT subject, grade FROM admission_olevel_results WHERE application_id = ? ORDER BY subject ASC");
        $olevel_stmt->execute([$application['id']]);
        $olevel_results = $olevel_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $application['olevel_results'] = $olevel_results; // Add results to the application array

        $_SESSION['application_status_data'] = $application;
        header('Location: ../admission_status.php');
        exit();
    } else {
        $_SESSION['status_error_message'] = "No application found with the provided details. Please check your email and token.";
        header('Location: ../apply.php?email=' . urlencode($email) . '&token=' . urlencode($token));
        exit();
    }

} catch (PDOException $e) {
    error_log("Status check error: " . $e->getMessage());
    $_SESSION['status_error_message'] = "A database error occurred. Please try again later.";
    header('Location: ../apply.php');
    exit();
}