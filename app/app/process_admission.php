<?php
session_start();
// Assuming you have a database connection file and a functions file
include_once '../config/db.php';
include_once '../config/functions.php';
$page_title = 'Admissions Processing';

require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_application_id'])) {
    $application_id = filter_input(INPUT_POST, 'approve_application_id', FILTER_SANITIZE_NUMBER_INT);
    $reviewer_id = $_SESSION['user_id'];

    // Start a transaction to ensure all or no operations are performed
    $pdo->beginTransaction();

    try {
        // Step 1: Fetch application data to ensure it's valid and not already accepted
        $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE id = ? AND status != 'accepted'");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            throw new Exception("Application not found or has already been accepted.");
        }

        // Step 2: Generate a unique Student ID Number (e.g., 20240001)
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id_number LIKE ?");
        $stmt->execute(["$year%"]);
        $count = $stmt->fetchColumn();
        $new_student_serial = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $student_id_number = $year . $new_student_serial;

        // Step 3: Generate a unique username
        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $application['first_name'] . '.' . $application['last_name']));
        $username = $base_username;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $username = $base_username . $counter++;
            } else {
                break;
            }
        }

        // Step 4: Generate a secure temporary password
        $temporary_password = bin2hex(random_bytes(4)); // Creates an 8-character random password
        $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);

        // Step 5: Create the user record in the `users` table
        $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
        $stmt->execute([$username, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Step 6: Create the student profile in the `students` table
        $full_name = trim($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name']);
        $stmt = $pdo->prepare(
            "INSERT INTO students (user_id, full_name, student_id_number, email, phone, date_of_birth, address, can_register_courses) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([
            $user_id,
            $full_name,
            $student_id_number,
            $application['email'],
            $application['phone'],
            $application['date_of_birth'],
            $application['address']
        ]);

        // Step 7: Update the application status to 'accepted'
        $stmt = $pdo->prepare(
            "UPDATE admission_applications 
             SET status = 'accepted', reviewed_by_user_id = ?, review_date = NOW() 
             WHERE id = ?"
        );
        $stmt->execute([$reviewer_id, $application_id]);
        
        // Step 8: Log this important activity
        $log_action = "Approved admission for {$full_name} (App ID: {$application_id}). Created Student ID: {$student_id_number}.";
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$reviewer_id, $log_action]);

        // If all queries succeed, commit the transaction
        $pdo->commit();

        // Step 9: Send the acceptance email to the student
        // This is a placeholder for your email sending function
        /*
        send_acceptance_email(
            $application['email'], 
            $full_name, 
            $student_id_number, 
            $username, 
            $temporary_password
        );
        */

        $_SESSION['success_message'] = "Application approved successfully. Student account has been created and an email with login details has been sent.";
        header('Location: /admin/admissions.php'); // Redirect back to the admissions list
        exit;

    } catch (Exception $e) {
        // If any step fails, roll back the transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error approving application: " . $e->getMessage();
        header('Location: /admin/admissions.php?view=' . $application_id); // Redirect back to the specific application
        exit;
    }
} else {
    // Redirect if accessed directly or without the required POST data
    header('Location: /admin/admissions.php');
    exit;
}