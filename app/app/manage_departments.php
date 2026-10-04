<?php
session_start();
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// --- Handle Delete (With Dependency Check) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];
    
    // Check if courses or programs are linked before deleting
    $check = $conn->prepare("SELECT (SELECT COUNT(*) FROM courses WHERE department_id = ?) + (SELECT COUNT(*) FROM programs WHERE department_id = ?) as total");
    $check->bind_param("ii", $department_id, $department_id);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();

    if ($res['total'] > 0) {
        $action_err = "Deletion blocked: This department contains active programs or courses.";
    } else {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $department_id);
        if ($stmt->execute()) {
            $action_success = "Department successfully removed from the system.";
        }
    }
}

// --- Handle Create/Edit ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_department'])) {
    $name = strtoupper(trim($_POST['name']));
    $description = trim($_POST['description']);
    $id = $_POST['department_id'] ?? null;

    if (!empty($id)) {
        $stmt = $conn->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $id);
        $action_success = $stmt->execute() ? "Department updated successfully." : "";
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        $action_success = $stmt->execute() ? "New department established." : "";
    }
}

// --- Fetch Departments with Sub-counts ---
$sql = "SELECT d.*, 
        (SELECT COUNT(*) FROM courses WHERE department_id = d.id) as course_count,
        (SELECT COUNT(*) FROM programs WHERE department_id = d.id) as program_count
        FROM departments d ORDER BY d.name ASC";
$departments = $conn->query($sql);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Faculty & Departments</h4>
            <p class="text-muted small mb-0">Organize the academic structure of the college.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="prepareCreate()">
            <i class="ti ti-plus fs-4"></i> Establish New Department
        </button>
    </div>

    <?php if ($action_success): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="ti ti-circle-check me-2 fs-5"></i> <?php echo $action_success; ?>
        </div>
    <?php endif; ?>
    <?php if ($action_err): ?>
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="ti ti-alert-triangle me-2 fs-5"></i> <?php echo $action_err; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php while($dept = $departments->fetch_assoc()): ?>
        <div class="col-md-6 col-xl-4 mb-4">
            <div class="card shadow-sm border-0 h-100 card-hover">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="p-3 bg-light-primary rounded-circle text-primary">
                            <i class="ti ti-building-community fs-7"></i>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-5"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick='prepareEdit(<?php echo json_encode($dept); ?>)' data-bs-toggle="modal" data-bs-target="#deptModal"><i class="ti ti-edit me-2"></i>Edit Details</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST">
                                        <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                        <button type="submit" name="delete_department" class="dropdown-item text-danger py-2" onclick="return confirm('Are you sure? This cannot be undone.');">
                                            <i class="ti ti-trash me-2"></i>Delete Department
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($dept['name']); ?></h5>
                    <p class="text-muted small mb-4" style="height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                        <?php echo $dept['description'] ?: 'No departmental description has been provided yet.'; ?>
                    </p>
                    
                    
                    <div class="row g-0 border-top pt-3">
                        <div class="col-6 border-end text-center">
                            <h4 class="fw-bolder text-primary mb-0"><?php echo $dept['program_count']; ?></h4>
                            <small class="text-uppercase fw-bold text-muted fs-1">Programs</small>
                        </div>
                        <div class="col-6 text-center">
                            <h4 class="fw-bolder text-info mb-0"><?php echo $dept['course_count']; ?></h4>
                            <small class="text-uppercase fw-bold text-muted fs-1">Courses</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary py-3">
                <h5 class="modal-title text-white fw-bold" id="modalLabel">Setup Faculty</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="department_id" id="deptId">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Department Name</label>
                        <input type="text" name="name" id="deptName" class="form-control form-control-lg fs-3" placeholder="e.g. Community Nursing" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Objectives / Description</label>
                        <textarea name="description" id="deptDesc" class="form-control" rows="5" placeholder="What are the goals of this department?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="save_department" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow">
                        Save Department Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareCreate() {
    document.getElementById('modalLabel').innerText = 'Establish Department';
    document.getElementById('deptId').value = '';
    document.getElementById('deptName').value = '';
    document.getElementById('deptDesc').value = '';
}
function prepareEdit(data) {
    document.getElementById('modalLabel').innerText = 'Modify Department Details';
    document.getElementById('deptId').value = data.id;
    document.getElementById('deptName').value = data.name;
    document.getElementById('deptDesc').value = data.description;
}
</script>

<?php include 'includes/footer.php'; ?>