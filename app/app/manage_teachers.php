<?php
/**
 * NAFCONM - Faculty Management & Delegated Administration Hub
 * Features: Advanced RBAC, Explicit Revoke Capability, Orphan Data Cleanup
 */
session_start();
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch Active Academic Session
$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
$active_session = $sess_query->fetch_assoc();
$active_session_id = $active_session['id'] ?? 1;
$active_session_name = $active_session['name'] ?? 'Current Session';

// --- 1. HANDLE TEACHER ONBOARDING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_teacher'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed. Please try again.";
    } else {
        $first_name = mysqli_real_escape_string($conn, ucwords(strtolower(trim($_POST['first_name']))));
        $last_name  = mysqli_real_escape_string($conn, strtoupper(trim($_POST['last_name'])));
        $email      = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'])));
        $specialization = mysqli_real_escape_string($conn, trim($_POST['specialization']));
        
        $full_name  = $last_name . " " . $first_name;
        $year       = date('Y');
        
        // Default Password: Lastname@Year
        $raw_password = ucfirst(strtolower($last_name)) . "@" . $year;
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $conn->begin_transaction();
        try {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) throw new Exception("A user with this email address already exists.");
            $check->close();

            // Auto-Generate System ID for Username
            $count_query = $conn->query("SELECT COUNT(id) FROM users WHERE user_type = 'teacher' AND username LIKE '%$year%'");
            $count = $count_query->fetch_row()[0] + 1;
            $username = "NAF/STF/$year/" . str_pad($count, 3, '0', STR_PAD_LEFT);

            // Insert into Users Table
            $stmt_u = $conn->prepare("INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'teacher')");
            $stmt_u->bind_param("sss", $username, $email, $hashed_password);
            $stmt_u->execute();
            $new_user_id = $conn->insert_id;
            $stmt_u->close();

            // Insert into Teachers Profile
            $stmt_t = $conn->prepare("INSERT INTO teachers (user_id, full_name, email, specialization) VALUES (?, ?, ?, ?)");
            $stmt_t->bind_param("isss", $new_user_id, $full_name, $email, $specialization);
            $stmt_t->execute();
            $stmt_t->close();

            $conn->commit();
            $_SESSION['msg'] = "success|Faculty member registered! System ID: <strong>$username</strong> | Default Password: <strong>$raw_password</strong>";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "danger|" . $e->getMessage();
        }
        header("Location: manage_teachers.php");
        exit;
    }
}

// --- 2. HANDLE TEACHER EDIT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_teacher'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed.";
    } else {
        $user_id   = intval($_POST['user_id']);
        $full_name = mysqli_real_escape_string($conn, trim($_POST['edit_full_name']));
        $email     = mysqli_real_escape_string($conn, trim($_POST['edit_email']));
        $spec      = mysqli_real_escape_string($conn, trim($_POST['edit_specialization']));
        $password  = $_POST['edit_password'] ?? '';

        $conn->begin_transaction();
        try {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssi", $email, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $email, $user_id);
            }
            $stmt->execute();
            $stmt->close();

            $stmt_t = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, specialization = ? WHERE user_id = ?");
            $stmt_t->bind_param("sssi", $full_name, $email, $spec, $user_id);
            $stmt_t->execute();
            $stmt_t->close();

            $conn->commit();
            $_SESSION['msg'] = "success|Faculty profile updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "danger|Error updating profile.";
        }
    }
    header("Location: manage_teachers.php?tab=directory");
    exit;
}

// --- 3. HANDLE DELETE TEACHER (With Cascade Cleanup) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_teacher'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed.";
    } else {
        $del_id = intval($_POST['delete_user_id']);
        
        $conn->begin_transaction();
        try {
            // Clean up privileges
            $conn->query("DELETE FROM teacher_privileges WHERE user_id = $del_id");
            
            // Get teacher_id for course unassignment
            $res = $conn->query("SELECT id FROM teachers WHERE user_id = $del_id LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $t_id = $res->fetch_assoc()['id'];
                $conn->query("DELETE FROM course_teachers WHERE teacher_id = $t_id");
            }
            
            // Delete user (teachers table should cascade if FK exists, else handled manually here)
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $del_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $_SESSION['msg'] = "success|Faculty member and all associated access rights permanently deleted.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "danger|Failed to delete. Ensure no active grades are linked to this teacher.";
        }
    }
    header("Location: manage_teachers.php?tab=directory");
    exit;
}

// --- 4. HANDLE COURSE ASSIGNMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_course'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed.";
    } else {
        $teacher_id = intval($_POST['assign_teacher_id']); 
        $course_id = intval($_POST['course_id']);
        
        $check = $conn->query("SELECT id FROM course_teachers WHERE teacher_id = $teacher_id AND course_id = $course_id AND session_id = $active_session_id");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO course_teachers (teacher_id, course_id, session_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $teacher_id, $course_id, $active_session_id);
            if ($stmt->execute()) $_SESSION['msg'] = "success|Course assigned successfully for $active_session_name.";
            $stmt->close();
        } else {
            $_SESSION['msg'] = "warning|This course is already assigned to the teacher for the active session.";
        }
    }
    header("Location: manage_teachers.php?tab=directory");
    exit;
}

// --- 5. HANDLE COURSE REVOCATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['revoke_course'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed.";
    } else {
        $mapping_id = intval($_POST['mapping_id']);
        $stmt = $conn->prepare("DELETE FROM course_teachers WHERE id = ?");
        $stmt->bind_param("i", $mapping_id);
        if ($stmt->execute()) $_SESSION['msg'] = "success|Course allocation revoked.";
        $stmt->close();
    }
    header("Location: manage_teachers.php?tab=directory");
    exit;
}

// --- 6. HANDLE DELEGATED PRIVILEGES (RBAC) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_privileges'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "danger|Security validation failed.";
    } else {
        $privilege_user_id = intval($_POST['privilege_user_id']);
        $selected_privileges = $_POST['privileges'] ?? []; // Array of selected checkboxes
        
        $conn->begin_transaction();
        try {
            // First, clear all existing privileges for this user (This handles revocation automatically)
            $stmt_clear = $conn->prepare("DELETE FROM teacher_privileges WHERE user_id = ?");
            $stmt_clear->bind_param("i", $privilege_user_id);
            $stmt_clear->execute();
            $stmt_clear->close();
            
            // Then, insert newly selected ones (if any)
            if (!empty($selected_privileges)) {
                $stmt_insert = $conn->prepare("INSERT INTO teacher_privileges (user_id, privilege_name) VALUES (?, ?)");
                foreach ($selected_privileges as $priv) {
                    $clean_priv = mysqli_real_escape_string($conn, $priv);
                    $stmt_insert->bind_param("is", $privilege_user_id, $clean_priv);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
                $msg_text = "Administrative access updated successfully.";
            } else {
                $msg_text = "All administrative privileges have been revoked.";
            }
            
            $conn->commit();
            $_SESSION['msg'] = "success|$msg_text";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "danger|Failed to update privileges.";
        }
    }
    header("Location: manage_teachers.php?tab=directory");
    exit;
}

// --- 7. FETCH DATA ---
$all_courses = $conn->query("SELECT id, course_code, course_title FROM courses ORDER BY course_code ASC");

// Fetch Teachers
$sql_teachers = "
    SELECT u.id as user_id, u.username, u.email, u.created_at,
           t.id as teacher_id, t.full_name, t.specialization,
           (SELECT COUNT(*) FROM course_teachers ct WHERE ct.teacher_id = t.id AND ct.session_id = $active_session_id) as assigned_courses
    FROM users u
    JOIN teachers t ON u.id = t.user_id
    WHERE u.user_type = 'teacher' OR u.user_type = 'staff'
    ORDER BY u.created_at DESC
";
$teachers = $conn->query($sql_teachers);

// Fetch Mappings
$mappings = [];
$map_res = $conn->query("SELECT ct.id as mapping_id, ct.teacher_id, c.course_code, c.course_title FROM course_teachers ct JOIN courses c ON ct.course_id = c.id WHERE ct.session_id = $active_session_id");
while($row = $map_res->fetch_assoc()){
    $mappings[$row['teacher_id']][] = $row;
}

// Fetch Privileges
$privileges_map = [];
$priv_res = $conn->query("SELECT user_id, privilege_name FROM teacher_privileges");
while($row = $priv_res->fetch_assoc()){
    $privileges_map[$row['user_id']][] = $row['privilege_name'];
}

$active_tab = $_GET['tab'] ?? 'directory';
$page_title = "Manage Faculty";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .form-floating > label { color: #64748b; font-weight: 500; }
    .form-control, .form-select { border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none !important; }
    .form-control:focus, .form-select:focus { border-color: #5D87FF; }
    .nav-pills .nav-link { border-radius: 10px; font-weight: bold; color: #64748b; padding: 12px 24px; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: #fff; }
    .hover-lift { transition: 0.3s; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    
    /* Toggle Switch Styling */
    .form-switch .form-check-input { width: 2.5em; height: 1.25em; cursor: pointer; }
    .form-switch .form-check-input:checked { background-color: #13DEB9; border-color: #13DEB9; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Faculty & Access Control</h3>
            <p class="text-muted small mb-0">Manage instructors, assign classes, and delegate administrative modules.</p>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if(isset($_SESSION['msg'])): list($color, $text) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 p-4 shadow-sm mb-4 alert-dismissible fade show animate__animated animate__fadeInDown">
            <div class="d-flex align-items-center">
                <i class="ti <?= $color === 'success' ? 'ti-circle-check' : 'ti-alert-triangle' ?> fs-3 me-3"></i>
                <div><?= $text; ?></div>
            </div>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="alert"></button>
        </div>
    <?php unset($_SESSION['msg']); endif; ?>

    <!-- TABS NAV -->
    <ul class="nav nav-pills mb-4 gap-2" id="teacherTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'directory' ? 'active' : '' ?>" id="dir-tab" data-bs-toggle="pill" data-bs-target="#dir" type="button" role="tab"><i class="ti ti-users me-2"></i>Faculty Directory</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'hub' ? 'active' : '' ?>" id="hub-tab" data-bs-toggle="pill" data-bs-target="#hub" type="button" role="tab"><i class="ti ti-user-plus me-2"></i>Onboard New Instructor</button>
        </li>
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content">
        
        <!-- TAB 1: FACULTY DIRECTORY & ASSIGNMENTS -->
        <div class="tab-pane fade <?= $active_tab === 'directory' ? 'show active' : '' ?>" id="dir" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-briefcase text-primary me-2"></i>Registered Instructors</h5>
                    <input type="text" id="dirSearch" class="form-control bg-light border-0 w-auto" placeholder="Search faculty...">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="dirTable">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 fw-bold">Instructor / ID</th>
                                    <th class="py-3 fw-bold">Department / Access</th>
                                    <th class="py-3 fw-bold text-center">Assigned Courses</th>
                                    <th class="text-end pe-4 py-3 fw-bold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($teachers && $teachers->num_rows > 0): while($t = $teachers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 shadow-sm" style="width: 45px; height: 45px;">
                                                    <?= substr($t['full_name'], 0, 1) ?>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                                        <?= htmlspecialchars($t['full_name']) ?>
                                                    </h6>
                                                    <small class="font-monospace text-primary fw-bold"><?= htmlspecialchars($t['username']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium d-block mb-1"><?= htmlspecialchars($t['specialization'] ?: 'General Faculty') ?></span>
                                            
                                            <!-- Advanced Dynamic Privilege Badges -->
                                            <?php if (!empty($privileges_map[$t['user_id']])): ?>
                                                <div class="d-flex gap-1 mt-1 flex-wrap">
                                                <?php foreach($privileges_map[$t['user_id']] as $priv): ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill" style="font-size: 10px;">
                                                        <i class="ti ti-shield-check"></i> <?= htmlspecialchars($priv) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="small text-muted"><i class="ti ti-mail me-1"></i><?= htmlspecialchars($t['email']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($t['assigned_courses'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="ti ti-books me-1"></i><?= $t['assigned_courses'] ?> Classes</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-3 py-1 rounded-pill">No Classes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 text-nowrap">
                                            <!-- Manage Privileges Button -->
                                            <button class="btn btn-sm btn-outline-danger rounded-circle p-2 me-1 hover-lift" onclick='openPrivilegeModal(<?= $t["user_id"] ?>, "<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>", <?= json_encode($privileges_map[$t["user_id"]] ?? []) ?>)' title="Manage Administrative Access"><i class="ti ti-shield-lock"></i></button>
                                            
                                            <!-- Assign Courses Button -->
                                            <button class="btn btn-sm btn-primary rounded-pill fw-bold px-3 me-1 shadow-sm hover-lift" onclick='openAssignModal(<?= $t["teacher_id"] ?>, <?= json_encode($mappings[$t["teacher_id"]] ?? []) ?>, "<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>")'><i class="ti ti-layout-grid-add me-1"></i>Assign Courses</button>
                                            
                                            <!-- Edit Profile Button -->
                                            <button class="btn btn-sm btn-light text-dark fw-bold rounded-circle p-2 me-1 hover-lift" onclick='openEditModal(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Profile"><i class="ti ti-pencil"></i></button>
                                            
                                            <!-- Delete Button -->
                                            <button class="btn btn-sm btn-light text-danger fw-bold rounded-circle p-2 hover-lift" onclick="confirmDelete(<?= $t['user_id'] ?>, '<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>')" title="Delete Instructor"><i class="ti ti-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="ti ti-users fs-1 d-block opacity-25 mb-2"></i>No faculty members registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ONBOARD NEW TEACHER -->
        <div class="tab-pane fade <?= $active_tab === 'hub' ? 'show active' : '' ?>" id="hub" role="tabpanel">
            <div class="row justify-content-center">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-user-plus text-primary me-2"></i>Register New Instructor</h5>
                        </div>
                        <div class="card-body p-4 p-md-5">
                            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6"><div class="form-floating"><input type="text" name="first_name" class="form-control" placeholder="First" required><label>First Name <span class="text-danger">*</span></label></div></div>
                                    <div class="col-md-6"><div class="form-floating"><input type="text" name="last_name" class="form-control" placeholder="Last" required><label>Last Name (Surname) <span class="text-danger">*</span></label></div></div>
                                    <div class="col-md-12"><div class="form-floating"><input type="email" name="email" class="form-control" placeholder="Email" required><label>Official Email <span class="text-danger">*</span></label></div></div>
                                    <div class="col-12"><div class="form-floating"><input type="text" name="specialization" class="form-control" placeholder="Dept"><label>Department / Specialization</label></div></div>
                                </div>
                                <button type="submit" name="create_teacher" class="btn btn-primary btn-lg w-100 fw-black py-3 rounded-pill shadow-sm">
                                    Create Instructor Account & Generate ID <i class="ti ti-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL: DELEGATE PRIVILEGES (RBAC) -->
<div class="modal fade" id="privilegeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-danger text-white p-3 border-0">
                <h5 class="modal-title fw-bold"><i class="ti ti-shield-lock me-2"></i>Manage Admin Access</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="privilegeForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="privilege_user_id" id="privilege_user_id">
                
                <div class="modal-body p-4 bg-light">
                    <p class="text-muted small mb-4">Grant <strong id="privilegeTeacherName" class="text-dark">Teacher Name</strong> access to the following administrative modules.</p>
                    
                    <div class="list-group list-group-flush rounded-3 border bg-white mb-3">
                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="ti ti-user-check text-primary me-2"></i>Admissions Desk</h6>
                                <small class="text-muted">Process applications and admit students.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="privileges[]" value="Admission" id="priv_admission">
                            </div>
                        </div>
                        
                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="ti ti-cash-banknote text-success me-2"></i>Finance & Bursary</h6>
                                <small class="text-muted">Manage payments, tellers, and scholarships.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="privileges[]" value="Payment" id="priv_payment">
                            </div>
                        </div>

                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="ti ti-bed text-warning me-2"></i>Hostel Command</h6>
                                <small class="text-muted">Allocate beds and manage infrastructure.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="privileges[]" value="Hostel" id="priv_hostel">
                            </div>
                        </div>

                        <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="ti ti-building-bank text-info me-2"></i>Academic Setup</h6>
                                <small class="text-muted">Manage departments, programs, and records.</small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="privileges[]" value="Academic" id="priv_academic">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-white border-top-0 d-flex justify-content-between">
                    <!-- NEW: Dedicated Revoke All Button -->
                    <button type="button" class="btn btn-outline-danger fw-bold rounded-pill shadow-sm px-4" onclick="revokeAllPrivileges()">Revoke All</button>
                    <button type="submit" name="update_privileges" class="btn btn-danger rounded-pill fw-bold shadow-sm px-4">Save Access Rights</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ASSIGN COURSE -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white p-3 border-0">
                <h5 class="modal-title fw-bold">Assign Courses</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <h6 class="text-primary fw-bold mb-3" id="assignTeacherName">Teacher Name</h6>
                <div class="alert bg-warning-subtle text-dark border-0 small mb-4 rounded-3"><i class="ti ti-info-circle me-1"></i> Allocations are specific to the <strong><?= htmlspecialchars($active_session_name) ?></strong> session.</div>
                
                <form method="POST" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="assign_teacher_id" id="assign_teacher_id">
                    <div class="input-group shadow-sm rounded-3 overflow-hidden">
                        <select name="course_id" class="form-select border-0" required>
                            <option value="">-- Select Course to Assign --</option>
                            <?php $all_courses->data_seek(0); while($c = $all_courses->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?> - <?= htmlspecialchars($c['course_title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" name="assign_course" class="btn btn-primary fw-bold px-4">Assign</button>
                    </div>
                </form>

                <h6 class="fw-bold text-muted small text-uppercase mb-2">Currently Assigned Classes</h6>
                <div class="list-group list-group-flush rounded-3 border" id="assignedCoursesList"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDIT TEACHER -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white p-3 border-0">
                <h5 class="modal-title fw-bold">Edit Instructor Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-floating mb-3"><input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required><label>Full Name</label></div>
                    <div class="form-floating mb-3"><input type="email" name="edit_email" id="edit_email" class="form-control" required><label>Email Address</label></div>
                    <div class="form-floating mb-3"><input type="text" name="edit_specialization" id="edit_specialization" class="form-control"><label>Department / Specialization</label></div>
                    <hr class="my-4 text-muted">
                    <div class="form-floating mb-2"><input type="password" name="edit_password" class="form-control" placeholder="Leave blank to keep"><label>New Password (Leave blank to keep current)</label></div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="submit" name="edit_teacher" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- HIDDEN FORMS -->
<form id="revokeForm" method="POST" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="revoke_course" value="1">
    <input type="hidden" name="mapping_id" id="revoke_mapping_id">
</form>

<form id="deleteForm" method="POST" class="d-none">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="delete_teacher" value="1">
    <input type="hidden" name="delete_user_id" id="delete_user_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Open RBAC Privilege Modal
    function openPrivilegeModal(userId, teacherName, currentPrivileges) {
        document.getElementById('privilege_user_id').value = userId;
        document.getElementById('privilegeTeacherName').innerText = teacherName;
        
        // Reset all switches
        document.getElementById('priv_admission').checked = false;
        document.getElementById('priv_payment').checked = false;
        document.getElementById('priv_hostel').checked = false;
        document.getElementById('priv_academic').checked = false;
        
        // Turn on switches for held privileges
        if(currentPrivileges.includes('Admission')) document.getElementById('priv_admission').checked = true;
        if(currentPrivileges.includes('Payment')) document.getElementById('priv_payment').checked = true;
        if(currentPrivileges.includes('Hostel')) document.getElementById('priv_hostel').checked = true;
        if(currentPrivileges.includes('Academic')) document.getElementById('priv_academic').checked = true;
        
        new bootstrap.Modal(document.getElementById('privilegeModal')).show();
    }

    // New Function: Instantly Revoke All Privileges
    function revokeAllPrivileges() {
        Swal.fire({
            title: 'Revoke All Access?',
            text: "This will instantly remove all administrative privileges from this instructor.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, Revoke All'
        }).then((result) => {
            if (result.isConfirmed) {
                // Uncheck everything
                document.getElementById('priv_admission').checked = false;
                document.getElementById('priv_payment').checked = false;
                document.getElementById('priv_hostel').checked = false;
                document.getElementById('priv_academic').checked = false;
                
                // Submit form to save blank state
                let form = document.getElementById('privilegeForm');
                
                // Add hidden action to bypass normal button click check
                const hiddenAction = document.createElement('input');
                hiddenAction.type = 'hidden';
                hiddenAction.name = 'update_privileges';
                hiddenAction.value = '1';
                form.appendChild(hiddenAction);
                
                form.submit();
            }
        });
    }

    // Open Assign Course Modal
    function openAssignModal(teacherId, mappedCourses, teacherName) {
        document.getElementById('assign_teacher_id').value = teacherId;
        document.getElementById('assignTeacherName').innerText = teacherName;
        
        let listHtml = '';
        if(mappedCourses.length > 0) {
            mappedCourses.forEach(c => {
                listHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center bg-white">
                        <div>
                            <span class="badge bg-dark text-white font-monospace me-2">${c.course_code}</span>
                            <span class="small fw-bold text-dark">${c.course_title}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-circle p-1" onclick="revokeCourse(${c.mapping_id})" title="Revoke Class"><i class="ti ti-x"></i></button>
                    </div>
                `;
            });
        } else {
            listHtml = '<div class="list-group-item text-center text-muted small py-3">No classes currently assigned for this session.</div>';
        }
        document.getElementById('assignedCoursesList').innerHTML = listHtml;
        new bootstrap.Modal(document.getElementById('assignModal')).show();
    }

    // Revoke Course Assignment
    function revokeCourse(mappingId) {
        if(confirm("Are you sure you want to remove this course from the instructor's schedule?")) {
            document.getElementById('revoke_mapping_id').value = mappingId;
            document.getElementById('revokeForm').submit();
        }
    }

    // Open Edit Profile Modal
    function openEditModal(teacher) {
        document.getElementById('edit_user_id').value = teacher.user_id;
        document.getElementById('edit_full_name').value = teacher.full_name;
        document.getElementById('edit_email').value = teacher.email;
        document.getElementById('edit_specialization').value = teacher.specialization;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // Delete Teacher Confirmation
    function confirmDelete(userId, name) {
        Swal.fire({
            title: 'Delete Instructor?',
            text: `This will permanently remove ${name} and their platform access. Please unassign all their courses first.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#1e293b',
            confirmButtonText: 'Yes, delete instructor'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        });
    }

    // Directory Search
    document.getElementById('dirSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#dirTable tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
</script>

<?php include 'includes/footer.php'; ?>