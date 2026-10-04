<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

// Base path for assets, adjust if your project is not in the root
define('BASE_URL', '../');

// Include the database configuration
require_once realpath(__DIR__ . '/../../config/db.php');

$full_name = htmlspecialchars($_SESSION["full_name"] ?? 'Student');
$page_title = $page_title ?? 'Student Portal'; // Page can override this
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="N-Portal Dashboard">
  <meta name="author" content="N-Portal">
  <title><?php echo htmlspecialchars($page_title); ?> - N-Portal</title>
  <link rel="shortcut icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.min.css" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
