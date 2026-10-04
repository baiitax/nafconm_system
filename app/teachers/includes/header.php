<?php
/**
 * NAFCONM - Teacher Portal Header
 * Features: Loop-Proof Authorization Guard, Root-Relative Routing
 */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Case-insensitive role check accepting 'teacher', 'staff', or 'superadmin'
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_loggedin = $_SESSION['loggedin'] ?? false;

if (!$is_loggedin || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("Location: /login.php");
    exit;
}

require_once realpath(__DIR__ . '/../../config/db.php');

function has_privilege($role) {
    return (isset($_SESSION['privileges']) && is_array($_SESSION['privileges']) && in_array($role, $_SESSION['privileges']));
}

$page_title = $page_title ?? 'Faculty Portal';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?> - NAFCONM</title>
  <link rel="stylesheet" href="../assets/css/styles.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-sidebartype="full">