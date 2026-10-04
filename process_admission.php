<?php
session_start();
require_once 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Data Sanitization
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $program_id = intval($_POST['program_id']);
    $payment_method = $_POST['payment_method'];
    
    // Generate a unique tracking token
    $token = strtoupper(bin2hex(random_bytes(4)));

    // 2. File Upload Configuration
    $upload_dir = "uploads/admissions/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    function uploadFile($file, $prefix, $dir) {
        if ($file['error'] === 0) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $prefix . "_" . time() . "_" . rand(1000, 9999) . "." . $ext;
            move_uploaded_file($file['tmp_name'], $dir . $filename);
            return $filename;
        }
        return null;
    }

    $photo = uploadFile($_FILES['photo'], 'IMG', $upload_dir);
    $birth_cert = uploadFile($_FILES['birth_cert'], 'BCERT', $upload_dir);
    $olevel_slip = uploadFile($_FILES['olevel_slip'], 'SLIP', $upload_dir);
    $pay_proof = isset($_FILES['payment_proof']) ? uploadFile($_FILES['payment_proof'], 'PROOF', $upload_dir) : null;

    // 3. Voucher Verification Logic
    $payment_status = 'pending';
    if ($payment_method === 'voucher') {
        $voucher_pin = mysqli_real_escape_string($conn, $_POST['voucher_pin']);
        $v_query = $conn->query("SELECT id FROM vouchers WHERE pin = '$voucher_pin' AND status = 'unused' LIMIT 1");
        
        if ($v_query->num_rows > 0) {
            $v_data = $v_query->fetch_assoc();
            $conn->query("UPDATE vouchers SET status = 'used', used_by = '$email' WHERE id = " . $v_data['id']);
            $payment_status = 'paid';
        } else {
            die("Error: Invalid or already used Voucher PIN.");
        }
    }

    // 4. Save Main Application
    $sql = "INSERT INTO admission_applications 
            (first_name, middle_name, last_name, email, phone, program_id, photo, birth_cert, olevel_slip, payment_method, payment_proof, payment_status, token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssisssssss", $first_name, $middle_name, $last_name, $email, $phone, $program_id, $photo, $birth_cert, $olevel_slip, $payment_method, $pay_proof, $payment_status, $token);

    if ($stmt->execute()) {
        $app_id = $stmt->insert_id;

        // 5. Save O-Level Results
        $subjects = $_POST['subjects'];
        $grades = $_POST['grades'];
        $res_stmt = $conn->prepare("INSERT INTO admission_results (application_id, subject, grade) VALUES (?, ?, ?)");
        
        foreach ($subjects as $index => $sub) {
            if (!empty($sub)) {
                $res_stmt->bind_param("iss", $app_id, $sub, $grades[$index]);
                $res_stmt->execute();
            }
        }

        // Redirect to Success Page
        $_SESSION['success_token'] = $token;
        header("Location: application_success.php");
    } else {
        echo "Error: " . $conn->error;
    }
}

?>