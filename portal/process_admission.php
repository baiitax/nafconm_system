<?php
session_start();
require_once '../config/db_connection.php';

/**
 * Handles the submission of the admission application form.
 */

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: ../admissions.php');
    exit();
}

// --- 1. Input Sanitization and Validation ---
$errors = [];
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? null);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$dob = trim($_POST['date_of_birth'] ?? '');
$address = trim($_POST['address'] ?? '');
$previousSchool = trim($_POST['previous_school'] ?? '');
$programOfInterest = trim($_POST['program_of_interest'] ?? '');
$parentName = trim($_POST['parent_name'] ?? '');
$parentPhone = trim($_POST['parent_phone'] ?? '');
$parentEmail = filter_var(trim($_POST['parent_email'] ?? ''), FILTER_SANITIZE_EMAIL);

// Basic validation
if (empty($firstName)) $errors[] = "First name is required.";
if (empty($lastName)) $errors[] = "Last name is required.";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
if (empty($phone)) $errors[] = "Phone number is required.";
if (empty($dob)) $errors[] = "Date of birth is required.";
if (empty($programOfInterest)) $errors[] = "Program of interest is required.";

// O-Level results processing
$olevel_results = [];
if (isset($_POST['subjects']) && is_array($_POST['subjects'])) {
    foreach ($_POST['subjects'] as $index => $subject) {
        $grade = $_POST['grades'][$index] ?? '';
        if (!empty($subject) && !empty($grade)) {
            $olevel_results[] = ['subject' => htmlspecialchars($subject), 'grade' => htmlspecialchars($grade)];
        }
    }
}
if (empty($olevel_results)) {
    $errors[] = "At least one O'Level result is required.";
}

// --- 2. File Upload Handling ---
$uploaded_documents = [];
$upload_dir = '../uploads/admissions/docs/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$allowed_files = [
    'birth_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
    'olevel_certificate' => ['application/pdf', 'image/jpeg', 'image/png'],
    'photo' => ['image/jpeg', 'image/png']
];

foreach ($allowed_files as $doc_type => $allowed_types) {
    if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$doc_type];
        $file_type = mime_content_type($file['tmp_name']);

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type for $doc_type. Please upload a PDF, JPG, or PNG.";
            continue;
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = "File for $doc_type is too large (max 5MB).";
            continue;
        }

        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid($doc_type . '_', true) . '.' . $file_extension;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $uploaded_documents[] = [
                'type' => $doc_type,
                'path' => 'uploads/admissions/docs/' . $new_filename
            ];
        } else {
            $errors[] = "Failed to upload file for $doc_type.";
        }
    } else {
        // Make some documents required
        if ($doc_type === 'birth_certificate' || $doc_type === 'photo') {
             $errors[] = "A $doc_type is required.";
        }
    }
}

// If there are errors, redirect back to the form
if (!empty($errors)) {
    $_SESSION['admission_error_message'] = implode('<br>', $errors);
    $_SESSION['form_data'] = $_POST;
    header('Location: ../admissions.php');
    exit();
}

// --- 3. Database Insertion ---
$applicant_token = bin2hex(random_bytes(16)) . time();

try {
    $pdo->beginTransaction();

    // Insert into the main application table (without olevel_results)
    $sql = "INSERT INTO admission_applications (first_name, last_name, middle_name, email, phone, date_of_birth, address, previous_school, program_of_interest, parent_name, parent_phone, parent_email, applicant_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$firstName, $lastName, $middleName, $email, $phone, $dob, $address, $previousSchool, $programOfInterest, $parentName, $parentPhone, $parentEmail, $applicant_token]);

    $application_id = $pdo->lastInsertId();

    // Insert O'Level results into the new dedicated table
    if ($application_id && !empty($olevel_results)) {
        $sql_olevel = "INSERT INTO admission_olevel_results (application_id, subject, grade) VALUES (?, ?, ?)";
        $stmt_olevel = $pdo->prepare($sql_olevel);
        foreach ($olevel_results as $result) {
            $stmt_olevel->execute([$application_id, $result['subject'], $result['grade']]);
        }
    }

    if (!empty($uploaded_documents)) {
        $sql_docs = "INSERT INTO application_documents (application_id, document_type, file_path) VALUES (?, ?, ?)";
        $stmt_docs = $pdo->prepare($sql_docs);
        foreach ($uploaded_documents as $doc) {
            $stmt_docs->execute([$application_id, $doc['type'], $doc['path']]);
        }
    }

    $pdo->commit();

    unset($_SESSION['admission_error_message'], $_SESSION['form_data']);
    $_SESSION['admission_success_data'] = ['name' => "$firstName $lastName", 'email' => $email, 'token' => $applicant_token];
    header('Location: ../admission_success.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    
    foreach ($uploaded_documents as $doc) {
        if (file_exists('../' . $doc['path'])) unlink('../' . $doc['path']);
    }

    error_log("Admission application error: " . $e->getMessage());
    $_SESSION['admission_error_message'] = "A database error occurred. Please try again.";
    header('Location: ../admissions.php');
    exit();
}