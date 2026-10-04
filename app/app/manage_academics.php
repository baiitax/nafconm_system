<?php
/**
 * NAFCONM - Academic Structure Manager
 * Features: Manage & Edit Departments, Programs, Assign HODs, Tabbed UI, CSRF Protection
 */
session_start();
require_once '../config/db.php';

// Universal Security & RBAC Guard
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_superadmin = ($user_type === 'superadmin' || $user_type === 'admin');

// Fetch Privileges to guard POST requests
if (!$is_superadmin && isset($_SESSION['user_id'])) {
    $_SESSION['privileges'] = [];
    $stmt_p = $conn->prepare("SELECT privilege_name FROM teacher_privileges WHERE user_id = ?");
    $stmt_p->bind_param("i", $_SESSION['user_id']);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    while ($row = $res_p->fetch_assoc()) { $_SESSION['privileges'][] = $row['privilege_name']; }
    $stmt_p->close();
}

// Map the current page to required privilege
$page_map = [
    'admission_desk.php' => 'Admission', 'manage_payments.php' => 'Payment', 
    'hostel_warden.php' => 'Hostel', 'manage_hostels.php' => 'Hostel', 
    'manage_academics.php' => 'Academic', 'student_academic_record.php' => 'Academic'
];
$req_privilege = $page_map[basename($_SERVER['PHP_SELF'])] ?? 'NONE';

if (!isset($_SESSION["loggedin"]) || (!$is_superadmin && !in_array($req_privilege, $_SESSION['privileges'] ?? []))) {
    header("location: ../teacher/index.php");
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 1. ADD NEW DEPARTMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_department'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $name = htmlspecialchars(trim($_POST['department_name']));

        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Department '$name' created successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to create Department. It may already exist.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. EDIT DEPARTMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_department'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $dept_id = intval($_POST['department_id']);
        $name = htmlspecialchars(trim($_POST['department_name']));

        $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $dept_id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Department renamed to '$name' successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to update department.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. ASSIGN HEAD OF DEPARTMENT (HOD) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_hod'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $dept_id = intval($_POST['department_id']);
        $teacher_id = intval($_POST['teacher_id']);

        $stmt = $conn->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $teacher_id, $dept_id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Head of Department assigned successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to assign HOD.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 4. ADD NEW PROGRAM ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_program'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $dept_id = intval($_POST['department_id']);
        $name = htmlspecialchars(trim($_POST['program_name']));

        $stmt = $conn->prepare("INSERT INTO programs (name, department_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $dept_id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Program '$name' added successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to create program.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=programs");
    exit;
}

// --- 5. EDIT PROGRAM ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_program'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $prog_id = intval($_POST['program_id']);
        $dept_id = intval($_POST['department_id']);
        $name = htmlspecialchars(trim($_POST['program_name']));

        $stmt = $conn->prepare("UPDATE programs SET name = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $dept_id, $prog_id);
        
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Program updated successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to update program.";
        }
        $stmt->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=programs");
    exit;
}

// --- 6. DELETE ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_entity'])) {
    $id = intval($_POST['entity_id']);
    $type = $_POST['entity_type'];
    
    if ($type === 'department') {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    } elseif ($type === 'program') {
        $stmt = $conn->prepare("DELETE FROM programs WHERE id = ?");
    }
    
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "success|Successfully deleted.";
    } else {
        $_SESSION['msg'] = "error|Cannot delete. Ensure no records (like students or courses) are currently attached to this entity.";
    }
    $stmt->close();
    header("Location: " . basename($_SERVER['PHP_SELF']) . ($type === 'program' ? "?tab=programs" : ""));
    exit;
}

// --- 7. FETCH DATA FOR UI ---
// Stats
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM departments) as total_depts,
        (SELECT COUNT(*) FROM programs) as total_programs,
        (SELECT COUNT(*) FROM teachers) as total_teachers,
        (SELECT COUNT(*) FROM departments WHERE hod_id IS NOT NULL) as assigned_hods
")->fetch_assoc();

// Teachers List (For HOD Dropdown)
$teachers = $conn->query("SELECT id, full_name FROM teachers ORDER BY full_name ASC");
$teachers_list = [];
if($teachers) {
    while($t = $teachers->fetch_assoc()) $teachers_list[] = $t;
}

// Departments List
$departments = $conn->query("
    SELECT d.id, d.name, d.hod_id, t.full_name as hod_name,
           (SELECT COUNT(*) FROM programs WHERE department_id = d.id) as program_count
    FROM departments d
    LEFT JOIN teachers t ON d.hod_id = t.id
    ORDER BY d.name ASC
");

// Programs List (Added p.department_id for pre-selecting in edit form)
$programs = $conn->query("
    SELECT p.id, p.name, p.department_id, d.name as department_name 
    FROM programs p
    LEFT JOIN departments d ON p.department_id = d.id
    ORDER BY d.name ASC, p.name ASC
");

$active_tab = $_GET['tab'] ?? 'departments';
$page_title = "Academic Structure";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.info { border-left-color: var(--bs-info); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.success { border-left-color: var(--bs-success); }
    
    .nav-pills .nav-link { border-radius: 50px; font-weight: 700; color: #64748b; padding: 10px 24px; margin-right: 10px; border: 1px solid transparent; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .nav-pills .nav-link:hover:not(.active) { background-color: #f8fafc; border-color: #e2e8f0; }
    
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    
    .action-btn { transition: 0.2s; }
    .action-btn:hover { background-color: #1e293b !important; color: #fff !important; border-color: #1e293b !important; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Academic Setup</h3>
            <p class="text-muted small mb-0">Manage Departments, Programs, and Assign Heads of Department (HOD).</p>
        </div>
    </div>

    <!-- KPI WIDGETS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Departments</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_depts'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-building-bank text-primary fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info text-uppercase fw-bold mb-1" style="font-size: 11px;">Active Programs</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_programs'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-info-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-books text-info fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Faculty Members</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_teachers'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-users text-warning fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Assigned HODs</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['assigned_hods'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-briefcase text-success fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS NAV -->
    <ul class="nav nav-pills mb-4" id="academicTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'departments' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#departments" type="button" role="tab"><i class="ti ti-building-bank me-2"></i>Departments & HODs</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'programs' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#programs" type="button" role="tab"><i class="ti ti-books me-2"></i>Academic Programs</button>
        </li>
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content" id="academicTabsContent">
        
        <!-- TAB 1: DEPARTMENTS & HODs -->
        <div class="tab-pane fade <?= $active_tab === 'departments' ? 'show active' : '' ?>" id="departments" role="tabpanel">
            <div class="row g-4">
                <!-- Create Dept Form -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-plus text-primary me-2"></i>Create Department</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="form-floating mb-4">
                                    <input type="text" name="department_name" class="form-control fw-bold" placeholder="e.g. Nursing Sciences" required>
                                    <label>Department Name</label>
                                </div>
                                <button type="submit" name="add_department" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow-sm">Save Department</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Departments Table -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Active Departments</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">Department</th>
                                            <th class="py-3 fw-bold">Head of Department (HOD)</th>
                                            <th class="py-3 fw-bold text-center">Programs</th>
                                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($departments && $departments->num_rows > 0): 
                                            $dept_dropdown = []; 
                                            while ($d = $departments->fetch_assoc()): 
                                                $dept_dropdown[] = $d;
                                        ?>
                                            <tr>
                                                <td class="ps-4 py-3 fw-bold text-dark"><?= htmlspecialchars($d['name']) ?></td>
                                                <td>
                                                    <?php if(!empty($d['hod_name'])): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="ti ti-user-check me-1"></i><?= htmlspecialchars($d['hod_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="ti ti-alert-circle me-1"></i>Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center font-monospace fw-bold"><?= $d['program_count'] ?></td>
                                                <td class="text-end pe-4 text-nowrap">
                                                    <button class="btn btn-outline-primary btn-sm rounded-pill fw-bold px-3 me-1" data-bs-toggle="modal" data-bs-target="#assignHODModal<?= $d['id'] ?>">Assign HOD</button>
                                                    <button type="button" class="btn btn-light text-primary btn-sm rounded-circle p-2 border action-btn me-1" data-bs-toggle="modal" data-bs-target="#editDeptModal<?= $d['id'] ?>" title="Edit Department"><i class="ti ti-edit"></i></button>
                                                    <form method="POST" class="delete-form d-inline">
                                                        <input type="hidden" name="entity_id" value="<?= $d['id'] ?>">
                                                        <input type="hidden" name="entity_type" value="department">
                                                        <button type="button" class="btn btn-light text-danger btn-sm rounded-circle p-2 border action-btn delete-btn" data-name="<?= htmlspecialchars($d['name']) ?>" title="Delete Department"><i class="ti ti-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>

                                            <!-- Edit Department Modal -->
                                            <div class="modal fade text-start" id="editDeptModal<?= $d['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                        <div class="modal-header bg-dark text-white p-3">
                                                            <h5 class="modal-title fw-bold">Edit Department</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="department_id" value="<?= $d['id'] ?>">
                                                            <div class="modal-body p-4">
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" name="department_name" class="form-control fw-bold" value="<?= htmlspecialchars($d['name']) ?>" required>
                                                                    <label>Department Name</label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light p-3">
                                                                <button type="submit" name="edit_department" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">Save Changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Assign HOD Modal -->
                                            <div class="modal fade text-start" id="assignHODModal<?= $d['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                        <div class="modal-header bg-dark text-white p-3">
                                                            <h5 class="modal-title fw-bold">Assign HOD: <?= htmlspecialchars($d['name']) ?></h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="department_id" value="<?= $d['id'] ?>">
                                                            <div class="modal-body p-4">
                                                                <div class="form-floating mb-3">
                                                                    <select name="teacher_id" class="form-select fw-bold" required>
                                                                        <option value="">-- Select a Faculty Member --</option>
                                                                        <?php foreach($teachers_list as $t): ?>
                                                                            <option value="<?= $t['id'] ?>" <?= ($d['hod_id'] == $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <label>Head of Department</label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light p-3">
                                                                <button type="submit" name="assign_hod" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">Save Assignment</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php endwhile; else: ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">No departments created yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ACADEMIC PROGRAMS -->
        <div class="tab-pane fade <?= $active_tab === 'programs' ? 'show active' : '' ?>" id="programs" role="tabpanel">
            <div class="row g-4">
                
                <!-- Create Program Form -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-books text-info me-2"></i>Add Academic Program</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="form-floating mb-3">
                                    <select name="department_id" class="form-select fw-bold" required>
                                        <option value="">-- Select Host Department --</option>
                                        <?php foreach ($dept_dropdown ?? [] as $dd): ?>
                                            <option value="<?= $dd['id'] ?>"><?= htmlspecialchars($dd['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Host Department</label>
                                </div>
                                
                                <div class="form-floating mb-4">
                                    <input type="text" name="program_name" class="form-control fw-bold" placeholder="e.g. Basic Nursing" required>
                                    <label>Program Name</label>
                                </div>
                                
                                <button type="submit" name="add_program" class="btn btn-info text-white w-100 rounded-pill fw-bold py-3 shadow-sm" <?= empty($dept_dropdown) ? 'disabled' : '' ?>>Create Program</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Programs Table -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Active Programs Inventory</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">Program Title</th>
                                            <th class="py-3 fw-bold">Host Department</th>
                                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($programs && $programs->num_rows > 0): while ($p = $programs->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 py-3 fw-bold text-dark"><i class="ti ti-certificate me-2 text-muted"></i><?= htmlspecialchars($p['name']) ?></td>
                                                <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($p['department_name'] ?? 'Unassigned') ?></span></td>
                                                <td class="text-end pe-4 text-nowrap">
                                                    <button type="button" class="btn btn-light text-primary btn-sm rounded-circle p-2 border action-btn me-1" data-bs-toggle="modal" data-bs-target="#editProgModal<?= $p['id'] ?>" title="Edit Program"><i class="ti ti-edit"></i></button>
                                                    <form method="POST" class="delete-form d-inline">
                                                        <input type="hidden" name="entity_id" value="<?= $p['id'] ?>">
                                                        <input type="hidden" name="entity_type" value="program">
                                                        <button type="button" class="btn btn-light text-danger btn-sm rounded-circle p-2 border action-btn delete-btn" data-name="<?= htmlspecialchars($p['name']) ?>" title="Delete Program"><i class="ti ti-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>

                                            <!-- Edit Program Modal -->
                                            <div class="modal fade text-start" id="editProgModal<?= $p['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                        <div class="modal-header bg-dark text-white p-3">
                                                            <h5 class="modal-title fw-bold">Edit Program</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                                            <div class="modal-body p-4">
                                                                <div class="form-floating mb-3">
                                                                    <select name="department_id" class="form-select fw-bold" required>
                                                                        <option value="">-- Select Host Department --</option>
                                                                        <?php foreach ($dept_dropdown ?? [] as $dd): ?>
                                                                            <option value="<?= $dd['id'] ?>" <?= ($p['department_id'] == $dd['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dd['name']) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                    <label>Host Department</label>
                                                                </div>
                                                                <div class="form-floating mb-3">
                                                                    <input type="text" name="program_name" class="form-control fw-bold" value="<?= htmlspecialchars($p['name']) ?>" required>
                                                                    <label>Program Name</label>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer bg-light p-3">
                                                                <button type="submit" name="edit_program" class="btn btn-info text-white w-100 rounded-pill fw-bold shadow-sm">Save Changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php endwhile; else: ?>
                                            <tr><td colspan="3" class="text-center py-5 text-muted">No programs created yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- SweetAlert2 for Toast Notifications & Confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Delete Confirmations
    const deleteBtns = document.querySelectorAll('.delete-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.delete-form');
            const itemName = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Delete ' + itemName + '?',
                html: `Are you sure? This cannot be undone. Make sure no students or courses are currently attached to this entity.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    const hiddenAction = document.createElement('input');
                    hiddenAction.type = 'hidden';
                    hiddenAction.name = 'delete_entity';
                    hiddenAction.value = '1';
                    form.appendChild(hiddenAction);
                    form.submit();
                }
            });
        });
    });

    // SweetAlert Notifications based on PHP Sessions
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

<?php include 'includes/footer.php'; ?>