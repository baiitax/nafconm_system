<?php
/**
 * NAFCONM - Secure Gradebook Computation Engine
 * Features: Smart JSON Roster Parsing, Auto GPA Calculation, Spreadsheet UI
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$current_session_id = 3; 
$current_session_name = "2025/2026"; 
$message = $error = "";

if (!isset($_GET['course_id'])) {
    header("location: index.php");
    exit;
}
$course_id = intval($_GET['course_id']);

// 1. Verify Teacher owns this course
$stmt_verify = $conn->prepare("SELECT t.id FROM teachers t JOIN course_teachers ct ON t.id = ct.teacher_id WHERE t.user_id = ? AND ct.course_id = ? AND ct.session_id = ?");
$stmt_verify->bind_param("iii", $user_id, $course_id, $current_session_id);
$stmt_verify->execute();
if ($stmt_verify->get_result()->num_rows === 0) die("Unauthorized access to this gradebook.");
$stmt_verify->close();

// 2. Fetch Course Info
$course = $conn->query("SELECT * FROM courses WHERE id = $course_id")->fetch_assoc();
$course_code_json = '"' . $course['course_code'] . '"'; // Formatting for JSON_CONTAINS lookup

// 3. Fetch Grading Policy (e.g. 70-100 = A = 5.0)
$policies = [];
$policy_res = $conn->query("SELECT * FROM grading_policy ORDER BY min_score DESC");
while ($p = $policy_res->fetch_assoc()) {
    $policies[] = $p;
}

// --- HANDLE GRADE SAVING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grades'])) {
    $scores = $_POST['scores']; // Array of student_id => raw_score
    
    $stmt_ins = $conn->prepare("INSERT INTO enrollments (student_id, course_id, session_id, raw_score, final_grade, grade_point) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE raw_score = VALUES(raw_score), final_grade = VALUES(final_grade), grade_point = VALUES(grade_point)");
    
    $updated_count = 0;
    foreach ($scores as $std_id => $raw_score) {
        if ($raw_score === '' || $raw_score < 0 || $raw_score > 100) continue; // Skip empty or invalid
        
        $score = (float)$raw_score;
        $letter_grade = 'F';
        $grade_point = 0.00;
        
        // Auto-Compute Grade based on grading_policy table
        foreach ($policies as $p) {
            if ($score >= $p['min_score'] && $score <= $p['max_score']) {
                $letter_grade = $p['letter_grade'];
                $grade_point = $p['grade_point'];
                break;
            }
        }
        
        $stmt_ins->bind_param("iiidsd", $std_id, $course_id, $current_session_id, $score, $letter_grade, $grade_point);
        if ($stmt_ins->execute()) $updated_count++;
    }
    $message = "success|Successfully published grades for $updated_count students.";
}

// --- FETCH ROSTER (The Magic JSON Parse) ---
// This intelligently joins the `course_registrations` JSON array with the `enrollments` table
$sql = "
    SELECT s.id as student_id, s.student_id_number, s.full_name, e.raw_score, e.final_grade 
    FROM students s
    JOIN course_registrations cr ON cr.student_id = s.id AND cr.session = '$current_session_name' AND cr.status = 'approved'
    LEFT JOIN enrollments e ON e.student_id = s.id AND e.course_id = $course_id AND e.session_id = $current_session_id
    WHERE JSON_CONTAINS(cr.course_codes, '$course_code_json')
    ORDER BY s.student_id_number ASC
";
$roster = $conn->query($sql);

$page_title = "Gradebook: " . $course['course_code'];
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .grade-input { width: 100px; text-align: center; font-weight: bold; font-size: 16px; border: 2px solid #e9ecef; border-radius: 8px; }
    .grade-input:focus { border-color: #5D87FF; outline: none; box-shadow: 0 0 0 3px rgba(93, 135, 255, 0.2); }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <a href="index.php" class="text-muted small fw-bold text-decoration-none"><i class="ti ti-arrow-left"></i> Back to Dashboard</a>
            <h3 class="fw-black text-dark mb-1 mt-2"><?= htmlspecialchars($course['course_title']) ?></h3>
            <p class="text-primary fw-bold font-monospace mb-0"><?= htmlspecialchars($course['course_code']) ?> • <?= $course['credit_units'] ?> Units</p>
        </div>
        <button type="submit" form="gradeForm" name="save_grades" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">
            <i class="ti ti-device-floppy me-2"></i> Publish Grades
        </button>
    </div>

    <?php if($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4">
            <i class="ti ti-check-circle me-2"></i><?= $txt ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="ti ti-users me-2"></i>Official Class Roster</h6>
            <span class="badge bg-light text-dark"><?= $roster->num_rows ?> Enrolled Students</span>
        </div>
        
        <form id="gradeForm" method="POST" action="">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3 fw-bold" width="10%">S/N</th>
                                <th class="py-3 fw-bold" width="20%">Matric Number</th>
                                <th class="py-3 fw-bold" width="40%">Student Full Name</th>
                                <th class="py-3 fw-bold text-center" width="15%">Raw Score (100)</th>
                                <th class="pe-4 py-3 fw-bold text-center" width="15%">Final Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($roster->num_rows > 0): $count = 1; while($r = $roster->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 text-muted fw-bold"><?= $count++ ?></td>
                                    <td><span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($r['student_id_number']) ?></span></td>
                                    <td><span class="fw-medium text-dark"><?= htmlspecialchars($r['full_name']) ?></span></td>
                                    
                                    <td class="text-center">
                                        <input type="number" step="0.01" min="0" max="100" 
                                               name="scores[<?= $r['student_id'] ?>]" 
                                               class="form-control grade-input mx-auto" 
                                               value="<?= isset($r['raw_score']) ? (float)$r['raw_score'] : '' ?>" 
                                               placeholder="--">
                                    </td>
                                    
                                    <td class="text-center pe-4">
                                        <?php if($r['final_grade']): 
                                            $color = 'bg-secondary';
                                            if($r['final_grade'] == 'A') $color = 'bg-success';
                                            if($r['final_grade'] == 'B' || $r['final_grade'] == 'C') $color = 'bg-primary';
                                            if($r['final_grade'] == 'D' || $r['final_grade'] == 'E') $color = 'bg-warning text-dark';
                                            if($r['final_grade'] == 'F') $color = 'bg-danger';
                                        ?>
                                            <span class="badge <?= $color ?> px-3 py-2 fs-6 rounded-3 shadow-sm"><?= $r['final_grade'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No students have officially registered for this course yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if($roster->num_rows > 0): ?>
            <div class="card-footer bg-light p-3 text-end border-top">
                <button type="submit" name="save_grades" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                    Publish Grades to Student Portal
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>