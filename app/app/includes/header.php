<?php
/**
 * NAFCONM - Central Admin Header & Security Guard
 * Features: Centralized RBAC, Real-Time Sync, Threat Interception
 */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_loggedin = $_SESSION['loggedin'] ?? false;
$is_superadmin = ($user_type === 'superadmin' || $user_type === 'admin');

// --- REAL-TIME RBAC SYNC ---
// Fetch latest privileges for delegated faculty to prevent stale session lockouts
if ($is_loggedin && !$is_superadmin) {
    require_once realpath(__DIR__ . '/../../config/db.php');
    $_SESSION['privileges'] = []; // Clear old cache
    
    $stmt_p = $conn->prepare("SELECT privilege_name FROM teacher_privileges WHERE user_id = ?");
    if ($stmt_p) {
        $stmt_p->bind_param("i", $_SESSION['user_id']);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        while ($row = $res_p->fetch_assoc()) {
            $_SESSION['privileges'][] = $row['privilege_name'];
        }
        $stmt_p->close();
    }
}

$privileges = $_SESSION['privileges'] ?? [];

// General Auth Check
if (!$is_loggedin || !in_array($user_type, ['superadmin', 'admin', 'teacher', 'staff'])) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// Module Mapping Dictionary
$page_privilege_map = [
    'admission_desk.php'          => 'Admission',
    'view_application.php'        => 'Admission',
    'manage_payments.php'         => 'Payment',
    'hostel_warden.php'           => 'Hostel',
    'manage_hostels.php'          => 'Hostel',
    'hostel_report.php'           => 'Hostel',
    'manage_academics.php'        => 'Academic',
    'student_directory.php'       => 'Academic',
    'student_academic_record.php' => 'Academic'
];

$current_file = basename($_SERVER['PHP_SELF']);

// Central Access Control Logic
if (!$is_superadmin) {
    if (array_key_exists($current_file, $page_privilege_map)) {
        $required_privilege = $page_privilege_map[$current_file];
        
        // Bounce if they lack the required key
        if (!in_array($required_privilege, $privileges)) {
            header("Location: ../teacher/index.php");
            exit;
        }
    } else {
        // Bounce from unmapped pages (like the master dashboard)
        header("Location: ../teacher/index.php");
        exit;
    }
}

$page_title = $page_title ?? 'Admin Command Center';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?> - NAFCONM Admin</title>
  <link rel="stylesheet" href="../assets/css/styles.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">