<?php
/**
 * NAFCONM - Admin Course Management
 * Features: Perfect Schema Sync, Core/Elective Toggle, Secure SQL Execution
 */
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_msg = "";
$action_class = "";

// --- 1. HANDLE CREATE & UPDATE LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_course'])) {
    
    $course_id    = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $title        = trim($_POST['title']);
    $code         = strtoupper(trim($_POST['code']));
    $program_id   = (int)$_POST['program_id'];
    $level        = trim($_POST['level']);
    $semester     = trim($_POST['semester']);
    $credits      = (int)$_POST['credits'];
    $is_core      = isset($_POST['is_core']) ? 1 : 0;

    if ($course_id > 0) {
        // UPDATE EXISTING COURSE
        $stmt = $conn->prepare("UPDATE courses SET course_title=?, course_code=?, program_id=?, level=?, semester=?, credit_units=?, is_core=? WHERE id=?");
        $stmt->bind_param("ssissiii", $title, $code, $program_id, $level, $semester, $credits, $is_core, $course_id);
        $action_text = "updated";
    } else {
        // INSERT NEW COURSE
        $stmt = $conn->prepare("INSERT INTO courses (course_title, course_code, program_id, level, semester, credit_units, is_core) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissii", $title, $code, $program_id, $level, $semester, $credits, $is_core);
        $action_text = "created";
    }

    if ($stmt->execute()) {
        $action_msg = "Course <strong>$code</strong> successfully $action_text.";
        $action_class = "alert-success";
    } else {
        $action_msg = "Database Error: " . $stmt->error;
        $action_class = "alert-danger";
    }
    $stmt->close();
}

// --- 2. HANDLE DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // In a real app, you would check if this course is currently inside a student's JSON array in course_registrations before deleting
    $conn->query("DELETE FROM courses WHERE id = $id");
    $action_msg = "Course removed from the catalog successfully.";
    $action_class = "alert-success";
}

// --- 3. FETCH DATA ---
$sql = "SELECT c.*, p.name as program_name 
        FROM courses c 
        LEFT JOIN programs p ON c.program_id = p.id 
        ORDER BY c.program_id ASC, c.level ASC, c.semester ASC, c.course_code ASC";
$courses = $conn->query($sql);

// Fetch dropdown data
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black mb-1 text-dark">Course Curriculum</h3>
            <p class="text-muted small mb-0">Manage academic units, program mapping, and core/elective status.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 py-2 fw-bold d-flex align-items-center shadow-sm mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="prepareCreate()">
            <i class="ti ti-plus fs-5 me-2"></i> Add New Course
        </button>
    </div>

    <?php if ($action_msg): ?>
        <div class="alert <?php echo $action_class; ?> alert-dismissible fade show border-0 shadow-sm rounded-4 p-3 animate__animated animate__fadeIn" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti <?php echo ($action_class === 'alert-success') ? 'ti-circle-check text-success' : 'ti-alert-triangle text-danger'; ?> fs-2 me-3"></i>
                <div class="fw-medium"><?php echo $action_msg; ?></div>
            </div>
            <button type="button" class="btn-close mt-1" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="text-muted text-uppercase small fw-bold">
                            <th class="ps-4 py-3">Code & Title</th>
                            <th>Academic Program</th>
                            <th class="text-center">Level & Semester</th>
                            <th class="text-center">Units</th>
                            <th class="text-center">Type</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($courses->num_rows > 0): ?>
                            <?php while($row = $courses->fetch_assoc()): ?>
                            <tr class="transition-all course-row">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="p-2 bg-primary-subtle text-primary rounded-3 me-3 fw-black border border-primary-subtle" style="min-width: 80px; text-align: center;">
                                            <?php echo htmlspecialchars($row['course_code']); ?>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['course_title']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($row['program_name'] ?? 'Global'); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <?php echo $row['level']; ?> • <?php echo $row['semester']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-black text-primary fs-5"><?php echo $row['credit_units']; ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if($row['is_core'] == 1): ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 border border-danger-subtle">Core</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 border border-info-subtle">Elective</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-1" 
                                            onclick='prepareEdit(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                            data-bs-toggle="modal" data-bs-target="#courseModal">
                                        <i class="ti ti-edit"></i> Edit
                                    </button>
                                    
                                    <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-danger rounded-circle p-2" onclick="return confirm('Are you sure you want to permanently delete this course?')" title="Delete Course">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="ti ti-book-off fs-1 text-muted"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">No Courses Found</h5>
                                    <p class="text-muted">The curriculum catalog is currently empty.</p>
                                    <button class="btn btn-primary rounded-pill px-4 mt-2" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="prepareCreate()">Add First Course</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-bottom p-4">
                <h5 class="modal-title fw-black text-dark d-flex align-items-center" id="modalLabel">
                    <i class="ti ti-book text-primary me-2 fs-4"></i> Setup Academic Course
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="hidden" name="course_id" id="course_id">
                
                <div class="modal-body p-4">
                    <h6 class="fw-bold text-uppercase text-muted small mb-3">General Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold text-dark">Full Course Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control form-control-lg bg-light border-0" placeholder="e.g. Foundation of Nursing" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Course Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="code" class="form-control form-control-lg bg-light border-0 text-uppercase" placeholder="e.g. NUR 111" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-dark">Host Program <span class="text-danger">*</span></label>
                            <select name="program_id" id="program_id" class="form-select form-select-lg bg-light border-0" required>
                                <option value="">Select Program...</option>
                                <?php 
                                $programs->data_seek(0);
                                while($p = $programs->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <h6 class="fw-bold text-uppercase text-muted small mb-3">Academic Matrix</h6>
                    <div class="row g-3 mb-4 bg-light rounded-4 p-3 border">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Level</label>
                            <select name="level" id="level" class="form-select">
                                <option value="100L">100L</option>
                                <option value="200L">200L</option>
                                <option value="300L">300L</option>
                                <option value="400L">400L</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Semester</label>
                            <select name="semester" id="semester" class="form-select">
                                <option value="First Semester">First Semester</option>
                                <option value="Second Semester">Second Semester</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark">Credit Units</label>
                            <input type="number" name="credits" id="credits" class="form-control" value="2" min="1" max="10" required>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                                <input class="form-check-input ms-0 mt-1" type="checkbox" name="is_core" id="is_core" value="1" checked style="width: 2.5em; height: 1.25em;">
                                <label class="form-check-label ms-5 fw-bold text-danger" for="is_core">Mandatory Core Course</label>
                                <small class="d-block ms-5 text-muted" style="font-size: 11px;">If enabled, students cannot un-select this course during registration.</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-top p-4 bg-light">
                    <button type="button" class="btn btn-outline-dark rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_course" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="ti ti-device-floppy me-1"></i> Save Course to Catalog
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .transition-all { transition: all 0.2s ease; }
    .course-row:hover { background-color: rgba(93, 135, 255, 0.05) !important; }
    .form-control:focus, .form-select:focus { box-shadow: none; border-color: #5D87FF; background-color: #fff !important; }
</style>

<script>
// UX: Resets the modal for creating a new course
function prepareCreate() {
    document.getElementById('modalLabel').innerHTML = '<i class="ti ti-book text-primary me-2 fs-4"></i> Setup Academic Course';
    document.getElementById('course_id').value = '';
    document.querySelector('form').reset();
    document.getElementById('is_core').checked = true; // Default to core
}

// UX: Populates the modal for editing an existing course safely
function prepareEdit(data) {
    document.getElementById('modalLabel').innerHTML = '<i class="ti ti-edit text-primary me-2 fs-4"></i> Modify Course: ' + data.course_code;
    document.getElementById('course_id').value = data.id;
    document.getElementById('title').value = data.course_title;
    document.getElementById('code').value = data.course_code;
    document.getElementById('program_id').value = data.program_id;
    document.getElementById('level').value = data.level;
    document.getElementById('semester').value = data.semester;
    document.getElementById('credits').value = data.credit_units;
    document.getElementById('is_core').checked = (data.is_core == 1);
}
</script>

<?php include 'includes/footer.php'; ?>