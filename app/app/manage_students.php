<?php
/**
 * NAFCONM - Student Directory CRM (Enterprise Grade)
 * Features: CSRF Protection, Live Search Engine, Password Resets, Level Promotions, SweetAlert2
 */
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 1. HANDLE PASSWORD RESET ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed. Please try again.";
    } else {
        $user_id = intval($_POST['target_user_id']);
        $new_password = trim($_POST['new_password']);
        
        if (strlen($new_password) < 6) {
            $_SESSION['msg'] = "warning|Password must be at least 6 characters.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "success|Password reset successfully for the student.";
            } else {
                $_SESSION['msg'] = "error|Failed to reset password. Please try again.";
            }
            $stmt->close();
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. HANDLE ACADEMIC PROMOTION (LEVEL UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_level'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $student_id = intval($_POST['target_student_id']);
        $new_level = htmlspecialchars(trim($_POST['new_level']));
        
        $stmt = $conn->prepare("UPDATE students SET current_level = ? WHERE id = ?");
        $stmt->bind_param("si", $new_level, $student_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Student successfully promoted to $new_level.";
        } else {
            $_SESSION['msg'] = "error|Failed to update academic level.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. FETCH STUDENT ROSTER ---
$sql = "
    SELECT s.*, p.name as program_name, u.username 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN programs p ON s.program_id = p.id 
    ORDER BY s.id DESC
";
$students = $conn->query($sql);

$page_title = "Student Directory";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    .dropdown-menu { border-radius: 12px; padding: 10px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .dropdown-item { border-radius: 8px; font-weight: 600; padding: 8px 15px; transition: 0.2s; }
    .dropdown-item:hover { background-color: #f1f5f9; color: #0f172a; }
    
    .search-container { position: relative; max-width: 350px; width: 100%; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    .search-container input { padding-left: 45px; border-radius: 50px; background-color: #f8fafc; border: 1px solid #e2e8f0; transition: 0.3s; }
    .search-container input:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); background-color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Student Directory</h3>
            <p class="text-muted small mb-0">Manage bio-data, academic levels, and portal access.</p>
        </div>
    </div>

    <!-- MAIN ROSTER CARD -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-users text-primary me-2"></i>Official Enrolled Roster</h6>
            
            <!-- Real-Time Search Bar -->
            <div class="search-container">
                <i class="ti ti-search"></i>
                <input type="text" id="rosterSearch" class="form-control" placeholder="Search Matric, Name, or Phone...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Student Profile</th>
                            <th class="py-3 fw-bold">Contact</th>
                            <th class="py-3 fw-bold text-center">Program & Level</th>
                            <th class="text-end pe-4 py-3 fw-bold">Administrative Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students->num_rows > 0): while ($s = $students->fetch_assoc()): ?>
                            <tr class="student-row">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($s['photo_path'])): ?>
                                            <img src="../uploads/admissions/<?= htmlspecialchars($s['photo_path']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border" style="width:45px; height:45px;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded-circle fw-bold fs-5 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:45px; height:45px;">
                                                <?= strtoupper(substr($s['full_name'],0,1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 student-name"><?= htmlspecialchars($s['full_name']) ?></h6>
                                            <small class="font-monospace text-primary fw-bold student-matric"><?= htmlspecialchars($s['student_id_number']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-medium text-dark student-contact"><i class="ti ti-mail text-muted me-1"></i> <?= htmlspecialchars($s['email']) ?></div>
                                    <div class="small fw-medium text-dark student-contact"><i class="ti ti-phone text-muted me-1"></i> <?= htmlspecialchars($s['phone'] ?? 'N/A') ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1 d-block mb-1 mx-auto student-program" style="max-width: 150px; text-wrap: wrap;"><?= htmlspecialchars($s['program_name'] ?? 'N/A') ?></span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace"><?= htmlspecialchars($s['current_level']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown">
                                        <button class="btn btn-outline-dark btn-sm rounded-pill fw-bold px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Manage Student
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><h6 class="dropdown-header text-uppercase small fw-bold text-primary">Portal Access</h6></li>
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#passwordModal<?= $s['user_id'] ?>"><i class="ti ti-key me-2 text-muted"></i>Reset Password</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><h6 class="dropdown-header text-uppercase small fw-bold text-success">Academic Status</h6></li>
                                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#levelModal<?= $s['id'] ?>"><i class="ti ti-stairs-up me-2 text-muted"></i>Update Level</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>

                            <!-- PASSWORD RESET MODAL -->
                            <div class="modal fade text-start" id="passwordModal<?= $s['user_id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                        <div class="modal-header bg-dark text-white p-3">
                                            <h5 class="modal-title fw-bold">Reset Password</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="target_user_id" value="<?= $s['user_id'] ?>">
                                                <div class="alert bg-warning-subtle text-dark border-0 small mb-4 rounded-3">
                                                    <i class="ti ti-info-circle me-1"></i> You are generating a forced password override for <strong><?= htmlspecialchars($s['full_name']) ?></strong>.
                                                </div>
                                                <div class="form-floating mb-3">
                                                    <input type="text" name="new_password" class="form-control fw-bold font-monospace" required minlength="6" placeholder="Password123!">
                                                    <label>Type New Password</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light p-3">
                                                <button type="submit" name="reset_password" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">Force Password Reset</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- PROMOTE LEVEL MODAL -->
                            <div class="modal fade text-start" id="levelModal<?= $s['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                        <div class="modal-header bg-dark text-white p-3">
                                            <h5 class="modal-title fw-bold">Update Academic Level</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="target_student_id" value="<?= $s['id'] ?>">
                                                <div class="form-floating mb-3">
                                                    <select name="new_level" class="form-select fw-bold" required>
                                                        <option value="100L" <?= $s['current_level'] == '100L' ? 'selected' : '' ?>>100 Level</option>
                                                        <option value="200L" <?= $s['current_level'] == '200L' ? 'selected' : '' ?>>200 Level</option>
                                                        <option value="300L" <?= $s['current_level'] == '300L' ? 'selected' : '' ?>>300 Level</option>
                                                        <option value="400L" <?= $s['current_level'] == '400L' ? 'selected' : '' ?>>400 Level</option>
                                                        <option value="Graduated" <?= $s['current_level'] == 'Graduated' ? 'selected' : '' ?>>Graduated / Alumni</option>
                                                    </select>
                                                    <label>Assign Academic Level</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light p-3">
                                                <button type="submit" name="update_level" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">Save Level Change</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endwhile; else: ?>
                            <tr id="emptyState"><td colspan="4" class="text-center py-5 text-muted"><i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50"></i>No enrolled students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 for Toast Notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Live Search Engine
    const searchInput = document.getElementById('rosterSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');

            rows.forEach(row => {
                const name = row.querySelector('.student-name').innerText.toLowerCase();
                const matric = row.querySelector('.student-matric').innerText.toLowerCase();
                const contact = row.querySelector('.student-contact').innerText.toLowerCase();
                const program = row.querySelector('.student-program').innerText.toLowerCase();
                
                if (name.includes(filter) || matric.includes(filter) || contact.includes(filter) || program.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 2. SweetAlert Notifications based on PHP Sessions
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); // Clear immediately
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

<?php include 'includes/footer.php'; ?>