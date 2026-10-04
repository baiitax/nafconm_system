<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

 // Role-based access control
 if (!is_logged_in() || !in_array($_SESSION['user_type'], ['superadmin', 'staff'])) {
     header('Location: /login.php');
     exit;
 }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['approve_application_id'])) {
    header('Location: /admin/admissions.php');
    exit;
}

$application_id = filter_input(INPUT_POST, 'approve_application_id', FILTER_SANITIZE_NUMBER_INT);
$reviewer_id = $_SESSION['user_id'];

$pdo->beginTransaction();

try {
    // Step 1: Fetch application data to ensure it's valid and ready for approval
    $stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE id = ? AND status NOT IN ('accepted', 'rejected')");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        throw new Exception("Application not found or has already been processed.");
    }

    // Step 2: Check if a student with this email already exists
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$application['email']]);
    if ($stmt->fetch()) {
        throw new Exception("A student account with this email address already exists.");
    }

    // Step 3: Generate a unique username
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $application['first_name']) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $application['last_name']));
    $username = $base_username;
    $counter = 1;
    $user_exists_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $user_exists_stmt->execute([$username]);
    while ($user_exists_stmt->fetch()) {
        $username = $base_username . $counter;
        $counter++;
        $user_exists_stmt->execute([$username]);
    }

    // Step 4: Generate a unique student ID number
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT student_id_number FROM students WHERE student_id_number LIKE ? ORDER BY student_id_number DESC LIMIT 1");
    $stmt->execute(["$year%"]);
    $last_id_number = $stmt->fetchColumn();
    if ($last_id_number) {
        $last_num = (int)substr($last_id_number, 4);
        $new_num = $last_num + 1;
        $student_id_number = $year . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    } else {
        $student_id_number = $year . '001';
    }

    // Step 5: Generate a temporary password
    $temporary_password = bin2hex(random_bytes(4)); // 8-character password
    $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);

    // Step 6: Create the new user record
    $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
    $stmt->execute([$username, $hashed_password]);
    $new_user_id = $pdo->lastInsertId();

    // Step 7: Create the new student record
    $full_name = trim($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name']);
    $stmt = $pdo->prepare(
        "INSERT INTO students (user_id, full_name, student_id_number, email, phone, date_of_birth, address) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $new_user_id,
        $full_name,
        $student_id_number,
        $application['email'],
        $application['phone'],
        $application['date_of_birth'],
        $application['address']
    ]);
    $new_student_id = $pdo->lastInsertId();

    // Step 8: Update the application status to 'accepted' and link to the new student
    $stmt = $pdo->prepare(
        "UPDATE admission_applications 
         SET status = 'accepted', student_id = ?, reviewed_by_user_id = ?, review_date = NOW()
         WHERE id = ?"
    );
    $stmt->execute([$new_student_id, $reviewer_id, $application_id]);

    // Step 9: Log this action
    $log_action = "Approved admission for {$full_name} (App ID: {$application_id}). Created Student ID: {$student_id_number}.";
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$reviewer_id, $log_action]);

    $pdo->commit();

    $_SESSION['success_message'] = "Application approved successfully. Student account created. Username: {$username}, Temp Password: {$temporary_password}";
    header('Location: /admin/admissions.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "Error approving application: " . $e->getMessage();
    header('Location: /admin/admissions.php?view=' . $application_id);
    exit;
}