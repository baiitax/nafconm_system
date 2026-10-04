<?php
session_start();
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// --- 1. Handle Delete Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_enrollment'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->bind_param("i", $enrollment_id);
    if ($stmt->execute()) {
        $action_success = "Enrollment record removed successfully.";
    } else {
        $action_err = "System Error: Could not delete record.";
    }
}

// --- 2. Handle Create/Update Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_enrollment'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $session_id = $_POST['session_id'];
    $enroll_id = $_POST['enrollment_id'] ?? null;

    // Check for duplicate (Student cannot take same course in same session twice)
    $check_sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND session_id = ?";
    if($enroll_id) $check_sql .= " AND id != $enroll_id";
    
    $check = $conn->prepare($check_sql);
    $check->bind_param("iii", $student_id, $course_id, $session_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $action_err = "Duplicate Alert: Student is already registered for this course in the selected session.";
    } else {
        if ($enroll_id) {
            $stmt = $conn->prepare("UPDATE enrollments SET student_id=?, course_id=?, session_id=? WHERE id=?");
            $stmt->bind_param("iiii", $student_id, $course_id, $session_id, $enroll_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, session_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $student_id, $course_id, $session_id);
        }
        $action_success = $stmt->execute() ? "Enrollment successfully synchronized." : "Operation failed.";
    }
}

// --- 3. Fetch Data ---
$sql = "SELECT e.*, s.full_name, s.student_id_number, c.course_name, c.course_code, sess.name as session_name 
        FROM enrollments e 
        JOIN students s ON e.student_id = s.id 
        JOIN courses c ON e.course_id = c.id 
        JOIN sessions sess ON e.session_id = sess.id 
        ORDER BY e.enrollment_date DESC";
$enrollments = $conn->query($sql);

$all_students = $conn->query("SELECT id, full_name, student_id_number FROM students ORDER BY full_name ASC");
$all_courses = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_code ASC");
$all_sessions = $conn->query("SELECT id, name FROM sessions ORDER BY id DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0">Course Enrollments</h4>
            <p class="text-muted small mb-0">Manage student-course relationships for the current session.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#enrollModal" onclick="prepareCreate()">
            <i class="ti ti-user-plus fs-4"></i> New Registration
        </button>
    </div>

    <?php if ($action_success): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
            <i class="ti ti-circle-check me-2 fs-5"></i> <?php echo $action_success; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-none border">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle">
                        <tr class="text-muted fs-2 text-uppercase ls-1">
                            <th class="ps-4">Student Info</th>
                            <th>Course Assigned</th>
                            <th>Academic Session</th>
                            <th>Enrollment Date</th>
                            <th class="text-end pe-4">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="fs-3">
                        <?php while($row = $enrollments->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <h6 class="fw-bold mb-0"><?php echo $row['full_name']; ?></h6>
                                <small class="text-muted"><?php echo $row['student_id_number']; ?></small>
                            </td>
                            <td>
                                <span class="fw-bold text-primary"><?php echo $row['course_code']; ?></span><br>
                                <small class="text-muted"><?php echo $row['course_name']; ?></small>
                            </td>
                            <td><span class="badge bg-light-info text-info border border-info-subtle"><?php echo $row['session_name']; ?></span></td>
                            <td><?php echo date('d M, Y', strtotime($row['enrollment_date'])); ?></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-light-warning text-warning" onclick='prepareEdit(<?php echo json_encode($row); ?>)' data-bs-toggle="modal" data-bs-target="#enrollModal">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Remove student from this course?');">
                                        <input type="hidden" name="enrollment_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_enrollment" class="btn btn-sm btn-light-danger text-danger">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark py-3">
                <h5 class="modal-title text-white fw-bold" id="modalLabel">Course Enrollment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="enrollment_id" id="enrollment_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Student</label>
                        <select name="student_id" id="student_id" class="form-select select2-basic" required>
                            <option value="">Choose Student...</option>
                            <?php $all_students->data_seek(0); while($s = $all_students->fetch_assoc()): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['student_id_number'] . " - " . $s['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Academic Course</label>
                        <select name="course_id" id="course_id" class="form-select" required>
                            <option value="">Choose Course...</option>
                            <?php $all_courses->data_seek(0); while($c = $all_courses->fetch_assoc()): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['course_code'] . " - " . $c['course_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold">Target Session</label>
                        <select name="session_id" id="session_id" class="form-select" required>
                            <?php $all_sessions->data_seek(0); while($sess = $all_sessions->fetch_assoc()): ?>
                                <option value="<?php echo $sess['id']; ?>"><?php echo $sess['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="save_enrollment" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow">
                        Confirm Enrollment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareCreate() {
    document.getElementById('modalLabel').innerText = 'New Course Registration';
    document.getElementById('enrollment_id').value = '';
    document.querySelector('form').reset();
}

function prepareEdit(data) {
    document.getElementById('modalLabel').innerText = 'Modify Enrollment Details';
    document.getElementById('enrollment_id').value = data.id;
    document.getElementById('student_id').value = data.student_id;
    document.getElementById('course_id').value = data.course_id;
    document.getElementById('session_id').value = data.session_id;
}
</script>

<?php include 'includes/footer.php'; ?>