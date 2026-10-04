<?php
/**
 * NAFCONM - Student Profile & Settings
 * Features: Bio-data display, Secure Password Update, ID Card Trigger
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- SECURITY & AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$message = $error = "";

// --- HANDLE PASSWORD CHANGE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (password_verify($current_password, $user_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $hashed_password, $user_id);
            if ($stmt_update->execute()) {
                $message = "success|Password updated successfully. Please use your new password next time you log in.";
            } else {
                $error = "Database error updating password.";
            }
            $stmt_update->close();
        } else {
            $error = "Incorrect current password.";
        }
    }
}

// --- FETCH STUDENT PROFILE ---
$stmt = $conn->prepare("
    SELECT s.*, p.name as program_name, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN programs p ON s.program_id = p.id 
    WHERE s.user_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Error: Student profile not found.");

$page_title = "My Profile";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Profile & Settings</h3>
            <p class="text-muted small mb-0">Manage your account details and security.</p>
        </div>
        <a href="print_id.php" target="_blank" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold">
            <i class="ti ti-id-badge-2 me-2"></i> Print Student ID
        </a>
    </div>

    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check-circle me-2"></i><?= $txt ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-alert-triangle me-2"></i><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT COLUMN: Profile Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 text-center p-4 h-100">
                <div class="mb-4">
                    <?php if (!empty($student['photo_path'])): ?>
                        <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" class="rounded-circle border border-4 border-light shadow-sm" style="width: 150px; height: 150px; object-fit: cover;" alt="Profile Photo">
                    <?php else: ?>
                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 150px; height: 150px; font-size: 50px;">
                            <?= substr($student['full_name'], 0, 1) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($student['full_name']) ?></h4>
                <p class="text-primary fw-bold mb-3"><?= htmlspecialchars($student['student_id_number']) ?></p>
                <div class="d-flex justify-content-center gap-2 mb-4">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= htmlspecialchars($student['program_name']) ?></span>
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= htmlspecialchars($student['current_level'] ?? '100L') ?></span>
                </div>
                <hr class="border-dashed mb-4">
                <div class="text-start">
                    <p class="text-muted small fw-bold text-uppercase mb-1">Portal Username</p>
                    <p class="fw-medium text-dark font-monospace bg-light p-2 rounded"><?= htmlspecialchars($student['username']) ?></p>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Details & Security -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-white border-bottom p-0">
                    <ul class="nav nav-tabs nav-fill border-0" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-4 fw-bold border-0" data-bs-toggle="tab" data-bs-target="#bio">Bio-Data</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-4 fw-bold border-0" data-bs-toggle="tab" data-bs-target="#security">Security & Password</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4 p-md-5">
                    <div class="tab-content" id="profileTabsContent">
                        
                        <!-- TAB 1: Bio-Data -->
                        <div class="tab-pane fade show active" id="bio">
                            <h5 class="fw-bold text-dark mb-4">Personal Information</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Full Name</label>
                                    <p class="fw-medium text-dark mb-0"><?= htmlspecialchars($student['full_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Email Address</label>
                                    <p class="fw-medium text-dark mb-0"><?= htmlspecialchars($student['email']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Phone Number</label>
                                    <p class="fw-medium text-dark mb-0"><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Date of Birth</label>
                                    <p class="fw-medium text-dark mb-0"><?= !empty($student['date_of_birth']) ? date('F j, Y', strtotime($student['date_of_birth'])) : 'N/A' ?></p>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-dashed">
                            
                            <h5 class="fw-bold text-dark mb-4">Emergency Contact</h5>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Contact Name</label>
                                    <p class="fw-medium text-dark mb-0"><?= htmlspecialchars($student['emergency_contact_name'] ?? $student['parent_name'] ?? 'N/A') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Contact Phone</label>
                                    <p class="fw-medium text-dark mb-0"><?= htmlspecialchars($student['emergency_contact_phone'] ?? $student['parent_phone'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                            
                            <div class="alert bg-info-subtle text-info border-0 mt-5 small">
                                <i class="ti ti-info-circle me-1"></i> Bio-data is locked after admission. If you notice an error in your name or date of birth, please visit the ICT center with sworn affidavits to request a change.
                            </div>
                        </div>

                        <!-- TAB 2: Security -->
                        <div class="tab-pane fade" id="security">
                            <h5 class="fw-bold text-dark mb-4">Change Password</h5>
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-muted text-uppercase">Current Password</label>
                                    <input type="password" name="current_password" class="form-control form-control-lg bg-light border-0" required>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">New Password</label>
                                        <input type="password" name="new_password" class="form-control form-control-lg bg-light border-0" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-muted text-uppercase">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control form-control-lg bg-light border-0" required>
                                    </div>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-dark py-3 px-5 rounded-pill fw-bold shadow-sm">
                                    Update Password <i class="ti ti-lock-check ms-1"></i>
                                </button>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs .nav-link { color: #6c757d; border-bottom: 3px solid transparent !important; transition: 0.3s; }
    .nav-tabs .nav-link.active { color: #5D87FF; border-bottom: 3px solid #5D87FF !important; background: transparent; }
    .border-dashed { border-top: 1px dashed #dee2e6; }
</style>

<?php include 'includes/footer.php'; ?>