<?php
/**
 * NAFCONM - Standardized Header & Security Gatekeeper
 * Logic: Define -> Authenticate -> Validate Financial Scope -> Grant Access
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Path Configuration
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$path_depth = substr_count(trim($script_path, '/'), '/');
define('BASE_URL', ($path_depth >= 2) ? '../../' : '../');

require_once __DIR__ . '/../../config/db.php';

// 2. Define Access Control List (ACL) EARLY 
$public_pages = [
    'payments.php', 
    'payment.php', 
    'complete_profile.php', 
    'logout.php', 
    'payment_success.php', 
    'view_payment.php',
    'verify_payment.php',
    'index.php' // Dashboard usually allows viewing basic stats without full clearance
];

$current_page = basename($_SERVER['PHP_SELF']);

// 3. Strict Authentication Guard
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: " . BASE_URL . "login.php");
    exit;
}

// 4. Identification & Session Recovery
if (!isset($_SESSION['student_id'])) {
    $session_user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, full_name, photo_path FROM students WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $session_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $_SESSION['student_id'] = $row['id'];
        $_SESSION['full_name']  = $row['full_name'];
        $_SESSION['photo_path'] = $row['photo_path'];
    } else {
        session_destroy();
        header("location: " . BASE_URL . "login.php?error=account_not_found");
        exit;
    }
    $stmt->close();
}

$student_id = (int)$_SESSION['student_id'];

// --- 5. REAL-TIME FINANCIAL & STATE VERIFICATION ---
$access_allowed = false; 
$profile_complete = false;
$registration_scope = 'none'; // 'none', 'half' (1st semester), 'full' (both semesters)

// A. Get Active Session
$sess_q = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$active_session = $sess_q->num_rows > 0 ? $sess_q->fetch_assoc()['name'] : '2025/2026 Session';

// B. Get Student Profile & Level
$stu_q = $conn->prepare("SELECT program_id, current_level, phone, address, can_register_courses FROM students WHERE id = ? LIMIT 1");
$stu_q->bind_param("i", $student_id);
$stu_q->execute();
$student_info = $stu_q->get_result()->fetch_assoc();
$stu_q->close();

if ($student_info) {
    $program_id = (int)$student_info['program_id'];
    $current_level = $student_info['current_level'];
    $profile_complete = (!empty(trim($student_info['phone'] ?? '')) && !empty(trim($student_info['address'] ?? '')));
    $manual_override = ((int)$student_info['can_register_courses'] === 1);

    // C. Calculate Expected Fees vs Paid Fees for the Active Session
    $fee_q = $conn->prepare("SELECT SUM(amount) as total_expected FROM fee_structures WHERE program_id = ? AND level = ? AND session = ?");
    $fee_q->bind_param("iss", $program_id, $current_level, $active_session);
    $fee_q->execute();
    $expected_fee = (float)($fee_q->get_result()->fetch_assoc()['total_expected'] ?? 0);
    $fee_q->close();

    $paid_q = $conn->prepare("SELECT SUM(amount) as total_paid FROM student_payments WHERE student_id = ? AND session = ? AND status = 'successful'");
    $paid_q->bind_param("is", $student_id, $active_session);
    $paid_q->execute();
    $total_paid = (float)($paid_q->get_result()->fetch_assoc()['total_paid'] ?? 0);
    $paid_q->close();

    // D. Determine Registration Scope Algorithm
    if ($manual_override) {
        // Admin manually granted override
        $access_allowed = true;
        $registration_scope = 'full';
    } elseif ($expected_fee > 0) {
        $paid_percentage = ($total_paid / $expected_fee) * 100;
        
        if ($paid_percentage >= 99.9) {
            // 100% Paid: Full Registration (Both Semesters)
            $access_allowed = true;
            $registration_scope = 'full';
        } elseif ($paid_percentage >= 50.0) {
            // 50% - 99% Paid: Half Registration (First Semester Only)
            $access_allowed = true;
            $registration_scope = 'half';
        }
    } else {
        // If no fees are configured yet by admin, default to full access
        $access_allowed = true;
        $registration_scope = 'full';
    }
}

// Expose scope to the session so the course registration page can adapt its UI
$_SESSION['registration_scope'] = $registration_scope;


// --- 6. GATEKEEPER INTERCEPTION LOGIC ---
if (!in_array($current_page, $public_pages)) {
    
    // Check 1: Financial Clearance
    if (!$access_allowed) {
        $_SESSION['msg'] = "warning|You must pay at least 50% of your tuition to access academic modules.";
        header("Location: payments.php");
        exit;
    }

    // Check 2: Profile Completion
    if (!$profile_complete) {
        $_SESSION['msg'] = "warning|Please complete your personal bio-data to proceed.";
        header("Location: complete_profile.php");
        exit;
    }
}

// 7. Security & CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$full_name  = htmlspecialchars($_SESSION["full_name"] ?? 'Student');
$page_title = $page_title ?? 'Dashboard';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $page_title; ?> | NAFCONM Portal</title>
  
  <link rel="shortcut icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.min.css" />
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  
  <style>
    .page-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
    .body-wrapper { flex: 1; transition: all 0.3s ease; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 10px; }
    .cursor-pointer { cursor: pointer; }
  </style>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
