<?php
session_start();

// --- 1. Strict Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php"); // Updated path to root
    exit;
}

$page_title = 'Complete Your Profile';
require_once 'includes/header.php'; // This file contains your DB connection ($conn)

// Resolve IDs from session safely
$student_id = intval($_SESSION['student_id'] ?? 0);
$user_id = intval($_SESSION['user_id'] ?? 0);

$message = $error = '';
$redirect_url = "index.php"; 

// Handle Profile and Password Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_profile'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        // Sanitize inputs
        $phone = trim($_POST['phone']);
        $dob = trim($_POST['date_of_birth']);
        $address = trim($_POST['address']);
        $emergency_name = trim($_POST['emergency_contact_name']);
        $emergency_phone = trim($_POST['emergency_contact_phone']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($phone) || empty($address) || empty($emergency_name) || empty($emergency_phone)) {
            $error = "Please fill in all required profile fields (*).";
        } elseif (!empty($new_password) && ($new_password !== $confirm_password)) {
            $error = "The new passwords do not match.";
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $conn->begin_transaction();
            try {
                // Update student's profile - Matching your DB schema exactly
                $sql_profile = "UPDATE students SET phone = ?, date_of_birth = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?";
                $stmt_profile = $conn->prepare($sql_profile);
                $stmt_profile->bind_param("sssssi", $phone, $dob, $address, $emergency_name, $emergency_phone, $student_id);
                
                if (!$stmt_profile->execute()) {
                    throw new Exception("Profile update failed: " . $stmt_profile->error);
                }
                $stmt_profile->close();

                // Update password if provided
                if (!empty($new_password)) {
                    $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_pass->bind_param("si", $hashed_pass, $user_id);
                    if (!$stmt_pass->execute()) {
                        throw new Exception("Password update failed.");
                    }
                    $stmt_pass->close();
                }

                $conn->commit();
                
                // CRITICAL: Update session flags so header.php recognizes the completion immediately
                $_SESSION['profile_completed'] = true; 
                
                $message = "Profile successfully updated!";

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch current data
$student_data = [];
$sql_fetch = "SELECT phone, date_of_birth, address, emergency_contact_name, emergency_contact_phone FROM students WHERE id = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $student_id);
    $stmt_fetch->execute();
    $student_data = $stmt_fetch->get_result()->fetch_assoc();
    $stmt_fetch->close();
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    .input-group-text { background-color: #f8f9fa; border-right: none; }
    .form-control { border-left: none; }
    .form-control:focus { border-color: #dee2e6; box-shadow: none; }
    .input-group:focus-within { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); border-radius: 0.375rem; }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            
            <div class="text-center mb-4">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                    <i class="ti ti-user-edit fs-1"></i>
                </div>
                <h3 class="fw-bolder text-dark">Complete Your Profile</h3>
                <p class="text-muted">Fill in your details to gain full access to the portal.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 p-md-5">
                    
                    <?php if ($message): ?>
                        <div class="text-center py-5">
                            <i class="ti ti-circle-check-filled text-success" style="font-size: 5rem;"></i>
                            <h4 class="fw-bold mt-4 text-dark"><?= $message ?></h4>
                            <p class="text-muted">Redirecting to dashboard...</p>
                            <script>setTimeout(() => { window.location.href = 'index.php'; }, 2000);</script>
                        </div>
                    <?php else: ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center mb-4 shadow-sm">
                                <i class="ti ti-alert-triangle-filled fs-4 me-2"></i>
                                <div><?= htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            
                            <h6 class="fw-bold text-uppercase text-primary mb-3 border-bottom pb-2">Personal Details</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-phone"></i></span>
                                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($student_data['phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Date of Birth</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-calendar"></i></span>
                                        <input type="date" class="form-control" name="date_of_birth" value="<?= htmlspecialchars($student_data['date_of_birth'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Residential Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-map-pin"></i></span>
                                        <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($student_data['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-uppercase text-danger mb-3 border-bottom pb-2 mt-4">Emergency Contact</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Contact Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-user"></i></span>
                                        <input type="text" class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars($student_data['emergency_contact_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Contact Phone <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-phone-call"></i></span>
                                        <input type="tel" class="form-control" name="emergency_contact_phone" value="<?= htmlspecialchars($student_data['emergency_contact_phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-uppercase text-success mb-3 border-bottom pb-2 mt-4">Security</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-key"></i></span>
                                        <input type="password" class="form-control border-end-0" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                                        <span class="input-group-text bg-white" onclick="toggleVisibility('new_password', 'icon_new')" style="cursor:pointer;"><i class="ti ti-eye-off" id="icon_new"></i></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Confirm Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ti ti-shield-check"></i></span>
                                        <input type="password" class="form-control border-end-0" id="confirm_password" name="confirm_password">
                                        <span class="input-group-text bg-white" onclick="toggleVisibility('confirm_password', 'icon_confirm')" style="cursor:pointer;"><i class="ti ti-eye-off" id="icon_confirm"></i></span>
                                    </div>
                                    <div id="password_match_message" class="form-text mt-1"></div>
                                </div>
                            </div>

                            <button type="submit" name="complete_profile" id="submitBtn" class="btn btn-primary w-100 py-3 fw-bold rounded-pill fs-5 mt-4">
                                Save Profile & Access Dashboard
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleVisibility(id, iconId) {
        const input = document.getElementById(id);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("ti-eye-off", "ti-eye");
        } else {
            input.type = "password";
            icon.classList.replace("ti-eye", "ti-eye-off");
        }
    }

    const nPass = document.getElementById('new_password');
    const cPass = document.getElementById('confirm_password');
    const msg = document.getElementById('password_match_message');
    const btn = document.getElementById('submitBtn');

    function check() {
        if (nPass.value === '' && cPass.value === '') {
            msg.innerHTML = ''; btn.disabled = false; return;
        }
        if (nPass.value === cPass.value) {
            if (nPass.value.length < 6) {
                msg.innerHTML = '<span class="text-danger">Too short (min 6)</span>'; btn.disabled = true;
            } else {
                msg.innerHTML = '<span class="text-success">Passwords match</span>'; btn.disabled = false;
            }
        } else {
            msg.innerHTML = '<span class="text-danger">Passwords do not match</span>'; btn.disabled = true;
        }
    }
    nPass.addEventListener('keyup', check);
    cPass.addEventListener('keyup', check);
</script>

<?php require_once 'includes/footer.php'; ?>