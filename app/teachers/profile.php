<?php
/**
 * NAFCONM - Faculty Profile Manager
 * Features: Database Sync (Users + Teachers), CSRF Protection, SweetAlert2 UI, Fixed Schema Crash
 */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once '../config/db.php';

// Security & Role Check
$user_type = strtolower($_SESSION['user_type'] ?? '');
if (!isset($_SESSION["loggedin"]) || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Safely Resolve Teacher ID
$teacher_id = 0;
$stmt_t = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
if ($stmt_t) {
    $stmt_t->bind_param("i", $user_id);
    $stmt_t->execute();
    $res_t = $stmt_t->get_result();
    if ($res_t->num_rows > 0) {
        $teacher_id = (int)$res_t->fetch_assoc()['id'];
    }
    $stmt_t->close();
}

if (!$teacher_id) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Error</h2><p>Faculty profile could not be located. Please contact the administrator.</p></div>");
}

// --- 2. HANDLE PROFILE INFORMATION UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed. Please try again.";
    } else {
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $email = strtolower(trim($_POST['email']));
        $specialization = htmlspecialchars(trim($_POST['specialization']));

        if (empty($full_name) || empty($email)) {
            $_SESSION['msg'] = "warning|Full name and email are strictly required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['msg'] = "warning|Invalid email address format.";
        } else {
            // Centralized Email Check (Users Table)
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                $_SESSION['msg'] = "error|This email address is already registered to another account.";
            } else {
                $conn->begin_transaction();
                try {
                    // Update Users Table (For Auth/Login sync)
                    $stmt_u = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                    $stmt_u->bind_param("si", $email, $user_id);
                    $stmt_u->execute();
                    $stmt_u->close();

                    // Update Teachers Table (For Profile sync)
                    $stmt_t = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, specialization = ? WHERE id = ?");
                    $stmt_t->bind_param("sssi", $full_name, $email, $specialization, $teacher_id);
                    $stmt_t->execute();
                    $stmt_t->close();

                    $conn->commit();
                    $_SESSION['msg'] = "success|Profile information updated successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['msg'] = "error|System error while updating profile.";
                }
            }
            $stmt_check->close();
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. HANDLE PASSWORD CHANGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed. Please try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['msg'] = "warning|All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['msg'] = "warning|New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['msg'] = "warning|Password must be at least 6 characters long.";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($password_hash);
            
            if ($stmt->fetch() && password_verify($current_password, $password_hash)) {
                $stmt->close();
                
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $new_password_hash, $user_id);
                
                if ($stmt_update->execute()) {
                    $_SESSION['msg'] = "success|Security credentials updated successfully!";
                } else {
                    $_SESSION['msg'] = "error|Failed to update password.";
                }
                $stmt_update->close();
            } else {
                $stmt->close();
                $_SESSION['msg'] = "error|The current password you entered is incorrect.";
            }
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 4. FETCH CURRENT PROFILE DATA (BUG FIX: Removed missing photo_path) ---
$stmt = $conn->prepare("
    SELECT t.full_name, t.email, t.specialization, u.username, u.created_at 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'My Profile';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .form-floating > label { color: #64748b; font-weight: 500; }
    .form-control { border-radius: 12px; border: 1px solid #e2e8f0; }
    .form-control:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); }
    
    .profile-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .profile-card-header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 30px 20px; border-radius: 16px 16px 0 0; text-align: center; color: white; position: relative; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Account Settings</h3>
            <p class="text-muted small mb-0">Manage your personal information and security preferences.</p>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- PROFILE INFORMATION CARD -->
        <div class="col-xl-7 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="profile-card-header">
                    <div class="profile-avatar bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold fs-1 mx-auto mb-3">
                        <?= strtoupper(substr($teacher['full_name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-black mb-1 text-white"><?= htmlspecialchars($teacher['full_name']) ?></h5>
                    <p class="mb-0 opacity-75 small font-monospace"><?= htmlspecialchars($teacher['username']) ?></p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold text-dark mb-4"><i class="ti ti-user-edit text-primary me-2"></i>Personal Details</h5>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control bg-light font-monospace fw-bold" id="username" value="<?= htmlspecialchars($teacher['username']) ?>" disabled readonly>
                                    <label for="username">System ID (Immutable)</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control fw-bold" id="full_name" name="full_name" value="<?= htmlspecialchars($teacher['full_name']) ?>" required>
                                    <label for="full_name">Full Legal Name</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating mb-2">
                                    <input type="email" class="form-control fw-bold" id="email" name="email" value="<?= htmlspecialchars($teacher['email']) ?>" required>
                                    <label for="email">Official Email</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control fw-bold" id="specialization" name="specialization" value="<?= htmlspecialchars($teacher['specialization'] ?? '') ?>">
                                    <label for="specialization">Specialization / Department</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow-sm">
                            <i class="ti ti-device-floppy me-2"></i>Save Profile Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- SECURITY & PASSWORD CARD -->
        <div class="col-xl-5 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-shield-lock text-danger me-2"></i>Account Security</h5>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <div class="alert bg-warning-subtle text-dark border-0 small mb-4 rounded-3">
                        <i class="ti ti-info-circle me-1"></i> Ensure your password is at least 6 characters long and contains a mix of letters and numbers.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control fw-bold" id="current_password" name="current_password" placeholder="Current Password" required>
                            <label for="current_password">Current Password</label>
                        </div>
                        
                        <hr class="my-4 text-muted opacity-25">
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control fw-bold" id="new_password" name="new_password" placeholder="New Password" required minlength="6">
                            <label for="new_password">New Password</label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <input type="password" class="form-control fw-bold" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required minlength="6">
                            <label for="confirm_password">Confirm New Password</label>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-danger w-100 rounded-pill fw-bold py-3 shadow-sm">
                            <i class="ti ti-key me-2"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- SweetAlert2 for Notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); 
        ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $type === "success" ? "success" : ($type === "error" ? "error" : "warning") ?>',
            title: '<?= addslashes($msg) ?>'
        });
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>