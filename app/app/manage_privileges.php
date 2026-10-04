<?php
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// --- Handle Role Assignment ---
if (isset($_POST['assign_role'])) {
    $user_id = intval($_POST['user_id']);
    $role = $_POST['role_name'];

    $stmt = $conn->prepare("INSERT IGNORE INTO staff_roles (user_id, role_name) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $role);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Privilege granted successfully.";
    }
    header("Location: manage_privileges.php");
    exit;
}

// --- Handle Unassigning ---
if (isset($_GET['unassign'])) {
    $id = intval($_GET['unassign']);
    $conn->query("DELETE FROM staff_roles WHERE id = $id");
    $_SESSION['msg'] = "Administrative privilege revoked.";
    header("Location: manage_privileges.php");
    exit;
}

// Fetch Teachers who have User Accounts
$teachers = $conn->query("SELECT t.id, t.full_name, u.id as user_id FROM teachers t JOIN users u ON t.user_id = u.id ORDER BY t.full_name ASC");

// Fetch Current Role Assignments
$assignments = $conn->query("SELECT sr.id, sr.role_name, sr.assigned_at, t.full_name, u.username 
                             FROM staff_roles sr 
                             JOIN users u ON sr.user_id = u.id 
                             JOIN teachers t ON t.user_id = u.id 
                             ORDER BY sr.role_name ASC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0">Administrative Privilege Manager</h4>
            <p class="text-muted small mb-0">Delegate specific portal responsibilities to teaching staff.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow-none border">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold mb-0">New Assignment</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Teacher</label>
                            <select name="user_id" class="form-select border-2" required>
                                <option value="">-- Choose Instructor --</option>
                                <?php while($t = $teachers->fetch_assoc()): ?>
                                    <option value="<?= $t['user_id'] ?>"><?= $t['full_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Assign Administrative Role</label>
                            <select name="role_name" class="form-select border-2" required>
                                <option value="Admission">Admission Officer</option>
                                <option value="Student">Student Affairs Officer</option>
                                <option value="Payment">Bursary / Payment Officer</option>
                                <option value="Hostel">Hostel Warden</option>
                                <option value="Academic">Academic Registrar</option>
                            </select>
                            <div class="form-text text-info">This grants the teacher access to the selected dashboard.</div>
                        </div>
                        <button type="submit" name="assign_role" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            Authorize Access
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-none border">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold mb-0">Active Administrative Staff</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light-subtle text-uppercase fs-2">
                                <tr>
                                    <th class="ps-4">Staff Member</th>
                                    <th>Assigned Role</th>
                                    <th>Granted On</th>
                                    <th class="text-end pe-4">Revoke</th>
                                </tr>
                            </thead>
                            <tbody class="fs-3">
                                <?php if($assignments->num_rows > 0): ?>
                                    <?php while($row = $assignments->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <h6 class="fw-bold mb-0 text-dark"><?= $row['full_name'] ?></h6>
                                            <small class="text-muted">@<?= $row['username'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light-primary text-primary px-3 border border-primary-subtle">
                                                <?= $row['role_name'] ?>
                                            </span>
                                        </td>
                                        <td><?= date('d M, Y', strtotime($row['assigned_at'])) ?></td>
                                        <td class="text-end pe-4">
                                            <a href="?unassign=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke all administrative access for this role?')">
                                                <i class="ti ti-user-x me-1"></i> Unassign
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-5">No administrative roles assigned yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>