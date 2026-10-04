<?php
/**
 * NAFCONM - Assignments & Submissions Hub
 * Features: Due Date Tracking, Student Roster Matching, Inline Grading
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_session_id = 3; 
$current_session_name = "2025/2026 Session"; 

// Fetch Teacher ID
$stmt_t = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt_t->bind_param("i", $user_id);
$stmt_t->execute();
$teacher_id = $stmt_t->get_result()->fetch_assoc()['id'];
$stmt_t->close();

$message = $error = "";
$view = isset($_GET['id']) ? 'submissions' : 'list';

// --- 1. HANDLE CREATE ASSIGNMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_assignment'])) {
    $course_id = intval($_POST['course_id']);
    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));
    $due_date = $_POST['due_date'];

    $stmt_ins = $conn->prepare("INSERT INTO assignments (course_id, teacher_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)");
    $stmt_ins->bind_param("iisss", $course_id, $teacher_id, $title, $description, $due_date);
    if ($stmt_ins->execute()) $message = "success|Assignment created successfully.";
    $stmt_ins->close();
}

// --- 2. HANDLE GRADING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $grade = floatval($_POST['grade']);
    $feedback = htmlspecialchars(trim($_POST['feedback']));

    $stmt_grade = $conn->prepare("UPDATE submissions SET grade = ?, feedback = ?, graded_by_teacher_id = ?, graded_at = NOW() WHERE id = ?");
    $stmt_grade->bind_param("dsii", $grade, $feedback, $teacher_id, $submission_id);
    if ($stmt_grade->execute()) $message = "success|Grade saved successfully.";
    $stmt_grade->close();
}

// Fetch Courses for Dropdown
$courses = $conn->query("SELECT c.id, c.course_code, c.course_title FROM course_teachers ct JOIN courses c ON ct.course_id = c.id WHERE ct.teacher_id = $teacher_id AND ct.session_id = $current_session_id");

$page_title = "Assignments";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
</style>

<div class="container-fluid py-4">

    <?php if ($view === 'list'): ?>
        <!-- VIEW 1: ASSIGNMENTS LIST -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-black text-dark mb-1">Homework & Assignments</h3>
                <p class="text-muted small mb-0">Manage tasks and track student submissions.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="ti ti-plus me-2"></i> Create Task
            </button>
        </div>

        <?php if ($message): list($color, $txt) = explode('|', $message); ?>
            <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check me-2"></i><?= $txt ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Course</th>
                            <th>Task Title</th>
                            <th>Due Date</th>
                            <th class="text-center">Submissions</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $assigns = $conn->query("
                            SELECT a.*, c.course_code, (SELECT COUNT(id) FROM submissions WHERE assignment_id = a.id) as sub_count 
                            FROM assignments a JOIN courses c ON a.course_id = c.id 
                            WHERE a.teacher_id = $teacher_id ORDER BY a.due_date DESC
                        ");
                        if ($assigns->num_rows > 0): while ($a = $assigns->fetch_assoc()): 
                        ?>
                            <tr>
                                <td class="ps-4"><span class="badge bg-primary-subtle text-primary border font-monospace"><?= htmlspecialchars($a['course_code']) ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($a['title']) ?></td>
                                <td class="text-danger fw-medium small"><i class="ti ti-calendar-due me-1"></i><?= date('d M Y, h:i A', strtotime($a['due_date'])) ?></td>
                                <td class="text-center"><span class="badge bg-dark rounded-pill"><?= $a['sub_count'] ?> Submitted</span></td>
                                <td class="text-end pe-4">
                                    <a href="?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">Grade Students</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No assignments created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- VIEW 2: SUBMISSIONS & GRADING -->
        <?php 
        $assign_id = intval($_GET['id']);
        $a_info = $conn->query("SELECT a.*, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = $assign_id AND a.teacher_id = $teacher_id")->fetch_assoc();
        if(!$a_info) die("Unauthorized.");
        
        $c_code_json = '"' . $a_info['course_code'] . '"';
        
        // Fetch roster + submissions
        $roster = $conn->query("
            SELECT s.id as student_id, s.full_name, s.student_id_number, sub.id as sub_id, sub.file_path, sub.submitted_at, sub.grade, sub.feedback 
            FROM students s
            JOIN course_registrations cr ON cr.student_id = s.id AND cr.session = '$current_session_name' AND cr.status = 'approved'
            LEFT JOIN submissions sub ON sub.student_id = s.id AND sub.assignment_id = $assign_id
            WHERE JSON_CONTAINS(cr.course_codes, '$c_code_json')
            ORDER BY sub.submitted_at DESC, s.student_id_number ASC
        ");
        ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="assignments.php" class="text-muted small fw-bold text-decoration-none"><i class="ti ti-arrow-left"></i> Back</a>
                <h3 class="fw-black text-dark mb-1 mt-2">Grading: <?= htmlspecialchars($a_info['title']) ?></h3>
                <p class="text-primary fw-bold font-monospace mb-0"><?= htmlspecialchars($a_info['course_code']) ?> • Due: <?= date('d M Y, h:i A', strtotime($a_info['due_date'])) ?></p>
            </div>
        </div>

        <?php if ($message): list($color, $txt) = explode('|', $message); ?>
            <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check me-2"></i><?= $txt ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Student</th>
                            <th>Status & File</th>
                            <th class="text-center">Score</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($roster->num_rows > 0): while($r = $roster->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($r['full_name']) ?></h6>
                                    <span class="font-monospace text-primary small fw-bold"><?= htmlspecialchars($r['student_id_number']) ?></span>
                                </td>
                                <td>
                                    <?php if($r['sub_id']): ?>
                                        <div class="text-success small fw-bold mb-1"><i class="ti ti-check"></i> Submitted <?= date('d M', strtotime($r['submitted_at'])) ?></div>
                                        <a href="../uploads/assignments/<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="btn btn-sm btn-light fw-bold rounded-pill"><i class="ti ti-download me-1"></i> View Work</a>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger px-2 py-1"><i class="ti ti-x"></i> Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($r['grade'] !== null): ?>
                                        <h5 class="fw-black text-dark mb-0"><?= $r['grade'] ?></h5>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic small">Ungraded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($r['sub_id']): ?>
                                        <button class="btn btn-sm btn-dark rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#gradeModal<?= $r['sub_id'] ?>">Grade Student</button>
                                        
                                        <!-- Inline DOM-Safe Modal -->
                                        <div class="modal fade text-start" id="gradeModal<?= $r['sub_id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header bg-dark text-white">
                                                        <h5 class="modal-title fw-bold">Grade: <?= htmlspecialchars($r['student_id_number']) ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <input type="hidden" name="submission_id" value="<?= $r['sub_id'] ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Score (Max 100)</label>
                                                                <input type="number" step="0.5" name="grade" class="form-control form-control-lg" value="<?= $r['grade'] ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold">Feedback / Remarks</label>
                                                                <textarea name="feedback" class="form-control" rows="3"><?= htmlspecialchars($r['feedback'] ?? '') ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" name="grade_submission" class="btn btn-primary w-100 rounded-pill fw-bold">Save Grade</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill disabled">No File</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center py-4">No students are officially registered for this course.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- CREATE ASSIGNMENT MODAL -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white p-3">
                <h5 class="modal-title fw-bold">Create Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Target Course</label>
                        <select name="course_id" class="form-select bg-light border-0" required>
                            <option value="">-- Select Course --</option>
                            <?php $courses->data_seek(0); while($c = $courses->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Task Title</label>
                        <input type="text" name="title" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Instructions / Description</label>
                        <textarea name="description" class="form-control bg-light border-0" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Submission Deadline</label>
                        <input type="datetime-local" name="due_date" class="form-control bg-light border-0" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="submit" name="create_assignment" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">Publish Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>