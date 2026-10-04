<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php'; // For log_activity

$page_title = 'Online Payments';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Online Payments</h5>
        <a href="payments_overview.php" class="btn btn-outline-primary">Back to Overview</a>
    </div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ti ti-tool fs-8 text-primary mb-4"></i>
                <h5 class="card-title fw-semibold mb-3">Online Payments Under Maintenance</h5>
                <p class="mb-0">We are currently working on integrating a seamless online payment gateway.</p>
                <p>This feature will be available soon. Thank you for your patience.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>