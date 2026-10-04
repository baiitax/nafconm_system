<?php
/**
 * NAFCONM - Admin Course-Teacher Allocation
 * Features: UPSERT Logic, Session Context, Fast Dropdowns
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$message = $error = "";
$current_session_id = 3; // From your DB dump, Session 3 is "2025/2026 Session"

// --- HANDLE ASSIGNMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_teacher'])) {
    $course_id = intval($_POST['course_id']);
    $teacher_id = intval($_POST['teacher_id']);
    
    // UPSERT: If assignment exists for this session, update it. If not, insert it.
    $stmt_check = $conn->prepare("SELECT id FROM course_teachers WHERE course_id = ? AND session_id = ?");
    $stmt_check->bind_param("ii", $course_id, $current_session_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();
    
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt_upd = $conn->prepare("UPDATE course_teachers SET teacher_id = ? WHERE id = ?");
        $stmt_upd->bind_param("ii", $teacher_id, $row['id']);
        $stmt_upd->execute();
        $message = "success|Instructor assignment updated successfully.";
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO course_teachers (course_id, teacher_id, session_id, role) VALUES (?, ?, ?, 'Primary')");
        $stmt_ins->bind_param("iii", $course_id, $teacher_id, $current_session_id);
        $stmt_ins->execute();
        $message = "success|Instructor officially assigned to the course.";
    }
    header("Location: assign_teachers.php");
    exit;
}

// --- FETCH DATA ---
$teachers = $conn->query("SELECT id, full_name, email FROM teachers ORDER BY full_name ASC");

// Fetch courses along with their currently assigned teacher for this session
$sql = "
    SELECT c.*, p.name as program_name, t.full_name as assigned_teacher, t.id as assigned_teacher_id 
    FROM courses c 
    LEFT JOIN programs p ON c.program_id = p.id 
    LEFT JOIN course_teachers ct ON c.id = ct.course_id AND ct.session_id = $current_session_id
    LEFT JOIN teachers t ON ct.teacher_id = t.id
    ORDER BY c.program_id ASC, c.level ASC, c.course_code ASC
";
$courses = $conn->query($sql);

$page_title = "Allocate Instructors";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Instructor Allocation</h3>
            <p class="text-muted small mb-0">Assign teachers to courses for the active session.</p>
        </div>
        <span class="badge bg-primary-subtle text-primary border px-3 py-2 fw-bold">Active Session: 2025/2026</span>
    </div>

    <?php if(isset($_SESSION['msg'])): list($color, $txt) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4 alert-dismissible">
            <?= $txt; unset($_SESSION['msg']); ?>
            <button type="button" class="btn-close mt-1" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Course Code & Title</th>
                            <th class="py-3 fw-bold text-center">Program & Level</th>
                            <th class="py-3 fw-bold">Currently Assigned To</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $courses->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <h6 class="fw-bold text-primary font-monospace mb-0"><?= htmlspecialchars($row['course_code']) ?></h6>
                                    <span class="text-dark fw-medium"><?= htmlspecialchars($row['course_title']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($row['program_name'] ?? 'Global') ?> • <?= $row['level'] ?></span>
                                </td>
                                <td>
                                    <?php if($row['assigned_teacher']): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="ti ti-user-check text-success fs-5 me-2"></i>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($row['assigned_teacher']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning px-2 py-1"><i class="ti ti-alert-circle me-1"></i> Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- Inline Form for Instant Assignment -->
                                    <form method="POST" action="" class="d-flex justify-content-end gap-2">
                                        <input type="hidden" name="course_id" value="<?= $row['id'] ?>">
                                        <select name="teacher_id" class="form-select form-select-sm border-primary-subtle" style="width: 200px;" required>
                                            <option value="">-- Select Instructor --</option>
                                            <?php 
                                            $teachers->data_seek(0);
                                            while($t = $teachers->fetch_assoc()): 
                                                $selected = ($row['assigned_teacher_id'] == $t['id']) ? 'selected' : '';
                                            ?>
                                                <option value="<?= $t['id'] ?>" <?= $selected ?>><?= htmlspecialchars($t['full_name']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="submit" name="assign_teacher" class="btn btn-sm btn-primary fw-bold px-3 rounded">Assign</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>