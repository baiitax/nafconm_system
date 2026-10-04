<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_teacher'])) {
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $dept_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $specialization = trim($_POST['specialization']);

    $conn->begin_transaction();

    try {
        if ($teacher_id) {
            // --- UPDATE FLOW ---
            $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $user_id = $stmt->get_result()->fetch_assoc()['user_id'];

            // Update user record
            $stmt_u = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt_u->bind_param("si", $email, $user_id);
            $stmt_u->execute();

            // Update teacher record
            $stmt_t = $conn->prepare("UPDATE teachers SET full_name = ?, phone = ?, department_id = ?, specialization = ? WHERE id = ?");
            $stmt_t->bind_param("ssisi", $full_name, $phone, $dept_id, $specialization, $teacher_id);
            $stmt_t->execute();

            $_SESSION['msg'] = "Instructor profile updated successfully.";
        } else {
            // --- CREATE FLOW ---
            // Check email uniqueness
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) throw new Exception("Email is already registered.");

            // Create User account (Password defaults to phone number)
            $username = strtolower(explode(' ', $full_name)[0]) . rand(100, 999);
            $password = password_hash($phone, PASSWORD_DEFAULT);
            
            $stmt_u = $conn->prepare("INSERT INTO users (username, password, email, user_type) VALUES (?, ?, ?, 'teacher')");
            $stmt_u->bind_param("sss", $username, $password, $email);
            $stmt_u->execute();
            $new_user_id = $conn->insert_id;

            // Create Teacher profile
            $stmt_t = $conn->prepare("INSERT INTO teachers (user_id, full_name, phone, department_id, specialization) VALUES (?, ?, ?, ?, ?)");
            $stmt_t->bind_param("issis", $new_user_id, $full_name, $phone, $dept_id, $specialization);
            $stmt_t->execute();

            $_SESSION['msg'] = "Instructor created. Login: $username / Pass: $phone";
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("location: manage_teachers.php");
    exit;
}