<?php
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_msg = "";
$action_class = "";

// --- Handle Reset Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_reset'])) {
    $confirm_text = trim($_POST['reset_verification']);
    
    // Safety check: User must type "RESET 2026" (or current year) to proceed
    if ($confirm_text !== "RESET " . date('Y')) {
        $action_msg = "Verification failed. Please type the exact confirmation string.";
        $action_class = "alert-danger";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Clear Hostel Applications (Reset all to 'expired' or delete)
            // We set them to 'expired' so you keep historical records of who stayed where
            $conn->query("UPDATE hostel_applications SET status = 'expired' WHERE status = 'approved'");

            // 2. Clear Student Registration Status 
            // This forces everyone to pay fees and register again for the new session
            $conn->query("UPDATE students SET can_register_courses = 0");

            // 3. Optional: Reset Course Enrollments for the new session
            // Note: Only do this if you have archived the old ones first!
            
            $conn->commit();
            $action_msg = "Global Session Reset Successful. Hostel beds are now vacant and student registration is locked.";
            $action_class = "alert-success";
        } catch (Exception $e) {
            $conn->rollback();
            $action_msg = "System Error: Reset failed. " . $e->getMessage();
            $action_class = "alert-danger";
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card bg-light-danger border-0 shadow-none mb-4">
                <div class="card-body p-4 text-center">
                    <i class="ti ti-alert-triangle fs-9 text-danger mb-3"></i>
                    <h4 class="fw-bolder text-danger">Administrative Session Reset</h4>
                    <p class="text-dark mb-0">Prepare the portal for the new academic year. This action is irreversible.</p>
                </div>
            </div>

            <?php if ($action_msg): ?>
                <div class="alert <?= $action_class ?> border-0 shadow-sm mb-4"><?= $action_msg ?></div>
            <?php endif; ?>

            <div class="card shadow-none border">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">Reset Parameters</h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex align-items-center px-0">
                            <div class="bg-light-primary text-primary rounded p-2 me-3"><i class="ti ti-bed"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0">Clear Hostel Bed Spaces</h6>
                                <small class="text-muted">All current occupants will be moved to 'expired' status.</small>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center px-0">
                            <div class="bg-light-warning text-warning rounded p-2 me-3"><i class="ti ti-lock"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0">Lock Course Registration</h6>
                                <small class="text-muted">All students will be marked as 'Not Cleared' until new fees are paid.</small>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center px-0 border-bottom-0">
                            <div class="bg-light-info text-info rounded p-2 me-3"><i class="ti ti-refresh"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0">Session Readiness</h6>
                                <small class="text-muted">Archives previous data to maintain institutional history.</small>
                            </div>
                        </li>
                    </ul>

                    <form method="POST" onsubmit="return confirm('WARNING: You are about to reset the entire college database for the new session. Continue?');">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-danger uppercase ls-1 fs-1">Security Verification</label>
                            <p class="small text-muted mb-2">To prevent accidental resets, please type: <b class="text-dark">RESET <?= date('Y') ?></b></p>
                            <input type="text" name="reset_verification" class="form-control form-control-lg border-2" placeholder="Type here..." required autocomplete="off">
                        </div>

                        <button type="submit" name="confirm_reset" class="btn btn-danger w-100 py-3 fw-bolder shadow-sm">
                            <i class="ti ti-power me-1"></i> EXECUTE GLOBAL RESET
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="text-muted text-decoration-none small"><i class="ti ti-arrow-left"></i> Cancel and Return to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>