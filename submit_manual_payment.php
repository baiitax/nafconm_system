<?php
// 1. Session consistency
$cookieParams = session_get_cookie_params();
session_set_cookie_params(
    $cookieParams["lifetime"],
    '/', 
    $_SERVER['HTTP_HOST'], 
    false, 
    true
);
session_start();

require_once 'config/db.php';

// 2. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: apply.php");
    exit;
}

$app_id = intval($_POST['app_id'] ?? 0);

// 3. Security Check: Prevent users from uploading proofs for other people's applications
if (!isset($_SESSION['last_app_id']) || $_SESSION['last_app_id'] != $app_id) {
    die("<div style='padding:20px; font-family:sans-serif; color:red; border:2px solid red;'>
            <strong>Security Error:</strong> Invalid application session. Please return to the tracker and try again.
         </div>");
}

// 4. Check if file was actually uploaded
if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    die("<div style='padding:20px; font-family:sans-serif; color:red; border:2px solid red;'>
            <strong>Upload Error:</strong> No file selected or file is corrupted. <a href='payment_details.php'>Go back</a>
         </div>");
}

// 5. Secure File Upload Logic
$upload_dir = __DIR__ . "/app/uploads/proofs/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$file_name = $_FILES['payment_proof']['name'];
$file_tmp  = $_FILES['payment_proof']['tmp_name'];
$file_size = $_FILES['payment_proof']['size'];
$ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Allowed Formats & Max Size (5MB)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
$max_size = 5 * 1024 * 1024; // 5MB in bytes

if (!in_array($ext, $allowed_extensions)) {
    die("<div style='padding:20px; font-family:sans-serif; color:red; border:2px solid red;'>
            <strong>Invalid File:</strong> Only JPG, PNG, and PDF files are allowed. <a href='payment_details.php'>Go back</a>
         </div>");
}

if ($file_size > $max_size) {
    die("<div style='padding:20px; font-family:sans-serif; color:red; border:2px solid red;'>
            <strong>File Too Large:</strong> Maximum allowed size is 5MB. Please compress your image/PDF. <a href='payment_details.php'>Go back</a>
         </div>");
}

// 6. Generate a Unique File Name to prevent overwriting
$new_filename = "TELLER_" . $app_id . "_" . time() . "_" . uniqid() . "." . $ext;
$destination = $upload_dir . $new_filename;

// 7. Move File & Update Database
if (move_uploaded_file($file_tmp, $destination)) {
    
    // Prepare statement to update the application's payment status and attach the proof
    $stmt = $conn->prepare("UPDATE admission_applications SET payment_proof = ?, payment_status = 'pending_verification' WHERE id = ?");
    
    if (!$stmt) {
        unlink($destination); // Clean up the uploaded file if DB prep fails
        die("Database Error: " . $conn->error);
    }
    
    $stmt->bind_param("si", $new_filename, $app_id);
    
    if ($stmt->execute()) {
        // Success! Redirect to the Finalize page so they can see their Acknowledgment Slip
        header("Location: finalize_application.php?method=manual&id=" . $app_id);
        exit;
    } else {
        unlink($destination); // Clean up the uploaded file if DB update fails
        die("Data Update Failed: " . $stmt->error);
    }

} else {
    die("<div style='padding:20px; font-family:sans-serif; color:red; border:2px solid red;'>
            <strong>Server Error:</strong> Failed to save the uploaded file. Check folder permissions.
         </div>");
}
?>