<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure user is logged in and is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../index.php");
    exit;
}

// Base path for assets
if (!defined('BASE_URL')) {
    define('BASE_URL', '../');
}

// Include the database configuration
require_once realpath(__DIR__ . '/../../config/db.php');

// Helper function to log user activity.
function log_activity($conn, $user_id, $action) {
    $sql = "INSERT INTO activity_logs (user_id, action) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("is", $user_id, $action);
        $stmt->execute();
        $stmt->close();
    }
}

// Regenerate CSRF token for forms if it's not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = $page_title ?? 'Teacher Portal';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title); ?> - N-Portal</title>
  <link rel="shortcut icon" type="image/png" href="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.min.css" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">