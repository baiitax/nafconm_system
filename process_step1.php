<?php
/**
 * NAFCONM - Process Application (Step 1)
 * Features: Bulletproof Reg No Gen, Fixed ₦16,430 Fee, Strict ID Validation
 */
$cookieParams = session_get_cookie_params();
session_set_cookie_params($cookieParams["lifetime"], '/', $_SERVER['HTTP_HOST'], false, true);
session_start();
require_once 'config/db.php';

// Force error reporting into the catch block instead of a white screen of death
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // --- 1. CSRF SECURITY CHECK ---
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Security validation failed. Please refresh the page and try again.");
        }

        // --- 2. DATA CAPTURE (bind_param handles escaping automatically) ---
        $first_name = ucwords(strtolower(trim($_POST['first_name'] ?? '')));
        $last_name  = strtoupper(trim($_POST['last_name'] ?? ''));
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $phone      = trim($_POST['phone'] ?? '');
        $program_id = intval($_POST['program_id'] ?? 0);

        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($program_id)) {
            throw new Exception("All core personal details are strictly required.");
        }

        // Universal ID field (JAMB UTME or Mental Health Index Number)
        $identification_number = !empty($_POST['jamb_number']) ? strtoupper(trim($_POST['jamb_number'])) : null;
        $jamb_score  = !empty($_POST['jamb_score']) ? intval($_POST['jamb_score']) : null;

        // --- 3. PROGRAM FETCHING & VALIDATION ---
        $stmt_prog = $conn->prepare("SELECT name FROM programs WHERE id = ?");
        if (!$stmt_prog) throw new Exception("Error verifying academic program.");
        $stmt_prog->bind_param("i", $program_id);
        $stmt_prog->execute();
        $prog_res = $stmt_prog->get_result();
        
        if ($prog_res->num_rows === 0) throw new Exception("Invalid program selected.");
        $program_name = $prog_res->fetch_assoc()['name'];
        $stmt_prog->close();

        // Determine Department Code for Reg No
        $prog_code = "GEN";
        if (stripos($program_name, 'ND') !== false || stripos($program_name, 'National Diploma') !== false) {
            $prog_code = "ND";
        } elseif (stripos($program_name, 'Mental Health') !== false) {
            $prog_code = "MH";
        } elseif (stripos($program_name, 'Midwifery') !== false) {
            $prog_code = "MID";
        } else {
            $prog_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $program_name), 0, 3));
        }

        // Strict Validation: Require ID Number for ND and Mental Health
        if (($prog_code === 'ND' || $prog_code === 'MH') && empty($identification_number)) {
            throw new Exception("An Identification/UTME Number is strictly required for the selected program.");
        }

        // --- 4. GENERATE SEQUENTIAL REG NO (Username) ---
        $year = date('Y');
        
        // Safer Count Query: Matches the reg_no pattern directly to avoid schema crashes
        $like_pattern = "%/{$year}/%";
        $stmt_count = $conn->prepare("SELECT COUNT(id) FROM admission_applications WHERE program_id = ? AND reg_no LIKE ?");
        if (!$stmt_count) throw new Exception("System error calculating application sequence.");
        
        $stmt_count->bind_param("is", $program_id, $like_pattern);
        $stmt_count->execute();
        $stmt_count->bind_result($current_count);
        $stmt_count->fetch();
        $stmt_count->close();

        $next_number = str_pad($current_count + 1, 4, '0', STR_PAD_LEFT);
        $reg_no = "NAFCONM/{$prog_code}/{$year}/{$next_number}"; // e.g., NAFCONM/MH/2026/0001
        
        $invoice_no = "INV-" . $year . "-" . strtoupper(bin2hex(random_bytes(3)));
        $token      = strtoupper(bin2hex(random_bytes(4)));
        $app_fee    = 16430.00; // Hardcoded requirement

        // --- 5. SECURE FILE UPLOAD HANDLER ---
        $upload_dir = __DIR__ . "/uploads/admissions/"; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true); 

        $uploaded_files = []; 

        function processUploadSecure($fileField, $prefix, $dir, &$uploaded_files) {
            if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Missing or corrupted file upload: " . str_replace('_', ' ', $fileField));
            }

            $tmp_name = $_FILES[$fileField]['tmp_name'];
            $file_size = $_FILES[$fileField]['size'];

            if ($file_size > 2097152) throw new Exception("The file for $fileField exceeds the 2MB limit.");

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);

            $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];

            if (!array_key_exists($mime_type, $allowed_mimes)) {
                throw new Exception("Invalid file type detected. Only JPG, PNG, and PDF files are permitted.");
            }

            $ext = $allowed_mimes[$mime_type];
            $newName = $prefix . "_" . time() . "_" . bin2hex(random_bytes(3)) . "." . $ext;

            if (move_uploaded_file($tmp_name, $dir . $newName)) {
                $uploaded_files[] = $dir . $newName; 
                return $newName;
            } else {
                throw new Exception("Server error: Failed to save uploaded file.");
            }
        }

        // Execute Uploads Safely
        $photo    = processUploadSecure('photo', 'PASSPORT', $upload_dir, $uploaded_files);
        $ssce     = processUploadSecure('ssce_attachment', 'SSCE', $upload_dir, $uploaded_files);
        $indigene = processUploadSecure('indigene_attachment', 'INDIGENE', $upload_dir, $uploaded_files);
        $birth    = processUploadSecure('birth_attachment', 'BIRTH', $upload_dir, $uploaded_files);

        // --- 6. EXECUTE DATABASE TRANSACTION ---
        $conn->begin_transaction();

        $sql = "INSERT INTO admission_applications 
                (reg_no, invoice_no, first_name, last_name, email, phone, program_id, jamb_number, jamb_score, application_fee, token, photo, proof_of_payment, ssce_attachment, indigene_attachment, birth_attachment, payment_status, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 'unpaid', 'pending')";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database preparation failed. Please check table schema.");

        $stmt->bind_param("ssssssisidsssss", $reg_no, $invoice_no, $first_name, $last_name, $email, $phone, $program_id, $identification_number, $jamb_score, $app_fee, $token, $photo, $ssce, $indigene, $birth);

        if (!$stmt->execute()) {
            throw new Exception("Database insertion failed: " . $stmt->error);
        }

        $application_id = $stmt->insert_id;
        $stmt->close();

        // Insert O-Level Results Safely (Fixes array offset error)
        if (isset($_POST['subjects']) && is_array($_POST['subjects']) && isset($_POST['grades']) && is_array($_POST['grades'])) {
            $res_sql = "INSERT INTO admission_results (application_id, subject, grade) VALUES (?, ?, ?)";
            $res_stmt = $conn->prepare($res_sql);
            
            if ($res_stmt) {
                foreach ($_POST['subjects'] as $index => $sub) {
                    $sub = trim($sub);
                    $grade = trim($_POST['grades'][$index] ?? '');
                    if (!empty($sub) && !empty($grade)) {
                        $res_stmt->bind_param("iss", $application_id, $sub, $grade);
                        $res_stmt->execute();
                    }
                }
                $res_stmt->close();
            }
        }

        $conn->commit();

        $_SESSION['last_app_id'] = $application_id;
        $_SESSION['applicant_reg_no'] = $reg_no; 
        session_write_close(); 

        // Route to the invoice page
        header("Location: payment_details.php");
        exit;

    } catch (mysqli_sql_exception $e) {
        // Catch duplicate entry specifically
        if ($e->getCode() === 1062) {
            $error_msg = "An application with this Email Address or ID Number already exists in our system.";
        } else {
            $error_msg = "Database Error: " . $e->getMessage();
        }
        
        if (isset($conn) && $conn->in_transaction) $conn->rollback();
        if (!empty($uploaded_files)) { foreach ($uploaded_files as $file) { @unlink($file); } }
        
        $_SESSION['msg'] = "danger|" . $error_msg;
        header("Location: apply.php");
        exit;
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->in_transaction) $conn->rollback();
        // Erase uploaded files to prevent server bloating on failure
        if (!empty($uploaded_files)) { foreach ($uploaded_files as $file) { @unlink($file); } }
        
        $_SESSION['msg'] = "danger|" . $e->getMessage();
        header("Location: apply.php");
        exit;
    }
} else {
    header("Location: apply.php");
    exit;
}
?>
