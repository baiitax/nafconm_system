<?php
session_start();
require_once '../config/db.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: apply.php');
    exit;
}

// --- Configuration ---
$upload_dir = 'uploads/application_documents/';
$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
$max_file_size = 2 * 1024 * 1024; // 2 MB

// --- Input Sanitization and Validation ---
$first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
$middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING);
$last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
$date_of_birth = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
$address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
$previous_school = filter_input(INPUT_POST, 'previous_school', FILTER_SANITIZE_STRING);
$program_of_interest = filter_input(INPUT_POST, 'program_of_interest', FILTER_SANITIZE_STRING);

// Basic validation
if (empty($first_name) || empty($last_name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($phone) || empty($date_of_birth) || empty($address) || empty($program_of_interest)) {
    $_SESSION['status_message'] = "Error: Please fill in all required fields.";
    header('Location: apply.php');
    exit;
}

// Check for duplicate application by email
try {
    $stmt = $pdo->prepare("SELECT id FROM admission_applications WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['status_message'] = "Error: An application with this email address has already been submitted.";
        header('Location: apply.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['status_message'] = "Error: A database error occurred. Please try again later.";
    header('Location: apply.php');
    exit;
}

// --- File Upload Handling ---
$uploaded_files = [];
if (isset($_FILES['documents'])) {
    // Ensure the upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    foreach ($_FILES['documents']['name'] as $doc_type => $file_name) {
        if ($_FILES['documents']['error'][$doc_type] === UPLOAD_ERR_OK) {
            // Validate file type and size
            if (!in_array($_FILES['documents']['type'][$doc_type], $allowed_types)) {
                $_SESSION['status_message'] = "Error: Invalid file type for " . htmlspecialchars($doc_type) . ". Only PDF, JPG, and PNG are allowed.";
                header('Location: apply.php');
                exit;
            }
            if ($_FILES['documents']['size'][$doc_type] > $max_file_size) {
                $_SESSION['status_message'] = "Error: File " . htmlspecialchars($file_name) . " is too large. Maximum size is 2MB.";
                header('Location: apply.php');
                exit;
            }

            // Generate a unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_filename = uniqid($doc_type . '_', true) . '.' . $file_extension;
            $destination = $upload_dir . $unique_filename;

            if (move_uploaded_file($_FILES['documents']['tmp_name'][$doc_type], $destination)) {
                $uploaded_files[$doc_type] = $destination;
            } else {
                $_SESSION['status_message'] = "Error: Could not upload file for " . htmlspecialchars($doc_type) . ".";
                header('Location: apply.php');
                exit;
            }
        } else {
             $_SESSION['status_message'] = "Error: An error occurred during file upload. Please try again.";
             header('Location: apply.php');
             exit;
        }
    }
}

// Check if all required documents were uploaded
if (count($uploaded_files) < 3) { // Assuming 3 required documents
    $_SESSION['status_message'] = "Error: Please upload all required documents.";
    // Clean up any partially uploaded files
    foreach ($uploaded_files as $file_path) {
        if (file_exists($file_path)) unlink($file_path);
    }
    header('Location: apply.php');
    exit;
}

// --- Database Insertion ---
$pdo->beginTransaction();
try {
    // Step 1: Insert into admission_applications
    $stmt = $pdo->prepare(
        "INSERT INTO admission_applications (first_name, middle_name, last_name, email, phone, date_of_birth, address, previous_school, program_of_interest, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $date_of_birth, $address, $previous_school, $program_of_interest]);
    $application_id = $pdo->lastInsertId();

    // Step 2: Insert document records
    $doc_stmt = $pdo->prepare("INSERT INTO application_documents (application_id, document_type, file_path) VALUES (?, ?, ?)");
    foreach ($uploaded_files as $doc_type => $file_path) {
        $doc_stmt->execute([$application_id, $doc_type, $file_path]);
    }

    // All good, commit the transaction
    $pdo->commit();

    $_SESSION['status_message'] = "Your application has been submitted successfully! We will review it and get back to you shortly.";
    header('Location: apply.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // Clean up uploaded files on database error
    foreach ($uploaded_files as $file_path) {
        if (file_exists($file_path)) unlink($file_path);
    }
    $_SESSION['status_message'] = "Error: A critical error occurred while submitting your application. " . $e->getMessage();
    header('Location: apply.php');
    exit;
}