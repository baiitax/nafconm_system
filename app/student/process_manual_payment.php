<?php
session_start();
require_once '../config/db.php';

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_manual'])) {
    
    $student_id = (int)$_SESSION['student_id'];
    
    // Ensure fee_id and amount exist in the POST request
    if (!isset($_POST['fee_id']) || !isset($_POST['amount'])) {
        die("Error: Please select a fee category before submitting.");
    }

    $fee_id = (int)$_POST['fee_id'];
    $amount = (float)$_POST['amount'];
    
    // 1. Verify File Exists and No Upload Errors
    if (!isset($_FILES['evidence']) || $_FILES['evidence']['error'] !== UPLOAD_ERR_OK) {
        die("Error: No file uploaded or file size exceeds server limits. Please go back and select a valid receipt image/PDF.");
    }

    // 2. Setup Directory
    $target_dir = "../uploads/receipts/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // 3. Rename File Securely
    $file_ext = strtolower(pathinfo($_FILES["evidence"]["name"], PATHINFO_EXTENSION));
    
    // Security check: Only allow images and PDFs
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($file_ext, $allowed_extensions)) {
        die("Error: Invalid file type. Only JPG, PNG, and PDF are allowed.");
    }

    $new_filename = "MANUAL_" . time() . "_STU" . $student_id . "." . $file_ext;
    $target_file = $target_dir . $new_filename;

    // 4. Move File
    if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $target_file)) {
        
        // 5. Insert into Database
        $query = "INSERT INTO payments (student_id, fee_id, amount_paid, payment_method, status, proof_file, session_id) 
                  SELECT ?, ?, ?, 'manual', 'pending', ?, id 
                  FROM sessions WHERE is_active = 1 LIMIT 1";
        
        $stmt = $conn->prepare($query);

        if ($stmt) {
            $stmt->bind_param("iids", $student_id, $fee_id, $amount, $new_filename);
            
            if ($stmt->execute()) {
                // Success! Redirect to overview page
                header("Location: payment_overview.php?success=manual_submitted");
                exit;
            } else {
                die("Execute failed: " . $stmt->error);
            }
        } else {
            die("Database Error: " . $conn->error);
        }
    } else {
        die("Failed to save the uploaded file. Contact the administrator to check folder permissions.");
    }
} else {
    // If someone visits this URL directly without submitting the form
    header("Location: make_payment.php");
    exit;
}