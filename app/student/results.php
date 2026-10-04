<?php
/**
 * NAFCONM - Highly Secure Results Checker
 * Features: 2FA Matric Lock, SGPA & CGPA Calculator, Official Transcript View
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- 1. SECURITY & AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$error = "";
$show_results = false;
$selected_session_id = 0;
$selected_session_name = "";

// --- 2. FETCH STUDENT DATA ---
$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Error: Student profile not found.");
$student_id = $student['id'];

// --- 3. FETCH AVAILABLE SESSIONS ---
// Only show sessions where the student actually has enrolled courses
$sessions = $conn->query("
    SELECT DISTINCT s.id, s.name 
    FROM enrollments e 
    JOIN sessions s ON e.session_id = s.id 
    WHERE e.student_id = $student_id 
    ORDER BY s.id DESC
");

// --- 4. HANDLE SECURE RESULT REQUEST ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_results'])) {
    $input_matric = trim($_POST['matric_no']);
    $selected_session_id = intval($_POST['session_id']);

    // The Security Lock: Match input matric number against database
    if (strtoupper($input_matric) !== strtoupper($student['student_id_number'])) {
        $error = "Authentication Failed: Incorrect Matriculation Number.";
    } else {
        $show_results = true;

        // Get session name
        $stmt_sess = $conn->prepare("SELECT name FROM sessions WHERE id = ?");
        $stmt_sess->bind_param("i", $selected_session_id);
        $stmt_sess->execute();
        $sess_res = $stmt_sess->get_result()->fetch_assoc();
        $selected_session_name = $sess_res['name'] ?? 'Unknown Session';
        $stmt_sess->close();

        // Fetch Grades for the selected session
        $stmt_grades = $conn->prepare("
            SELECT c.course_code, c.course_title, c.credit_units, c.semester, 
                   e.final_grade, e.grade_point 
            FROM enrollments e 
            JOIN courses c ON e.course_id = c.id 
            WHERE e.student_id = ? AND e.session_id = ? 
            ORDER BY c.semester ASC, c.course_code ASC
        ");
        $stmt_grades->bind_param("ii", $student_id, $selected_session_id);
        $stmt_grades->execute();
        $grades_result = $stmt_grades->get_result();

        // Organize into semesters
        $semesters = [];
        $total_units_session = 0;
        $total_points_session = 0;

        while ($row = $grades_result->fetch_assoc()) {
            $sem = $row['semester'];
            if (!isset($semesters[$sem])) {
                $semesters[$sem] = [
                    'courses' => [],
                    'total_units' => 0,
                    'total_points' => 0
                ];
            }
            $semesters[$sem]['courses'][] = $row;
            
            // Only calculate GPA if a grade has been published
            if ($row['final_grade'] !== null) {
                $units = (int)$row['credit_units'];
                $gp = (float)$row['grade_point'];
                
                $semesters[$sem]['total_units'] += $units;
                $semesters[$sem]['total_points'] += ($units * $gp);
                
                $total_units_session += $units;
                $total_points_session += ($units * $gp);
            }
        }
        $stmt_grades->close();

        // Calculate Cumulative (CGPA) - Fetch ALL historical grades up to now
        $stmt_cgpa = $conn->prepare("
            SELECT c.credit_units, e.grade_point 
            FROM enrollments e 
            JOIN courses c ON e.course_id = c.id 
            WHERE e.student_id = ? AND e.final_grade IS NOT NULL
        ");
        $stmt_cgpa->bind_param("i", $student_id);
        $stmt_cgpa->execute();
        $cgpa_res = $stmt_cgpa->get_result();
        
        $cumulative_units = 0;
        $cumulative_points = 0;
        while ($c_row = $cgpa_res->fetch_assoc()) {
            $units = (int)$c_row['credit_units'];
            $gp = (float)$c_row['grade_point'];
            $cumulative_units += $units;
            $cumulative_points += ($units * $gp);
        }
        $stmt_cgpa->close();

        $cgpa = $cumulative_units > 0 ? ($cumulative_points / $cumulative_units) : 0;
        $sgpa = $total_units_session > 0 ? ($total_points_session / $total_units_session) : 0;
    }
}

$page_title = "Check Results";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .vault-card { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; border-top: 5px solid #5D87FF; }
    .transcript-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); position: relative; overflow: hidden; }
    .transcript-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 80px; color: rgba(93, 135, 255, 0.03); font-weight: 900; white-space: nowrap; pointer-events: none; z-index: 0; }
    .content-z { position: relative; z-index: 1; }
    .table-results th { background-color: #f1f5f9; color: #475569; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
    .grade-badge { width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 8px; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Academic Records</h3>
            <p class="text-muted small mb-0">Securely view your statement of results and CGPA.</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 px-4 py-3 shadow-sm mb-4 animate__animated animate__shakeX">
            <i class="ti ti-lock-off me-2"></i><?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (!$show_results): ?>
        <!-- SECURE VAULT FORM -->
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card vault-card shadow-lg rounded-4 text-center p-4 p-md-5 mt-4">
                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-4" style="width: 80px; height: 80px;">
                        <i class="ti ti-shield-lock fs-1 text-primary"></i>
                    </div>
                    <h4 class="fw-black mb-2 text-white">Secure Result Access</h4>
                    <p class="text-secondary small mb-4">Your academic records are highly sensitive. Please authenticate to break the seal and view your grades.</p>
                    
                    <form method="POST" action="">
                        <div class="mb-3 text-start">
                            <label class="form-label text-white small fw-bold text-uppercase">Academic Session</label>
                            <select name="session_id" class="form-select form-select-lg" required>
                                <option value="">-- Select Session --</option>
                                <?php if($sessions && $sessions->num_rows > 0): while($s = $sessions->fetch_assoc()): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endwhile; else: ?>
                                    <option value="" disabled>No results published yet</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-4 text-start">
                            <label class="form-label text-white small fw-bold text-uppercase">Confirm Matric Number</label>
                            <input type="text" name="matric_no" class="form-control form-control-lg text-uppercase font-monospace" placeholder="e.g. NAF/2026/001" required>
                        </div>
                        <button type="submit" name="check_results" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm">
                            Unlock Results <i class="ti ti-key ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- RESULTS DISPLAY TRANSCRIPT -->
        <div class="d-flex justify-content-end mb-3">
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold"><i class="ti ti-printer me-2"></i> Print Statement</button>
            <a href="results.php" class="btn btn-light rounded-pill px-4 ms-2 shadow-sm fw-bold"><i class="ti ti-lock me-2"></i> Lock & Exit</a>
        </div>

        <div class="transcript-box p-4 p-md-5 mb-5">
            <div class="transcript-watermark">CONFIDENTIAL</div>
            
            <div class="content-z">
                <div class="text-center border-bottom pb-4 mb-4">
                    <h2 class="fw-black text-dark mb-1">Nafisatu College of Nursing</h2>
                    <h6 class="text-muted text-uppercase letter-spacing-1">Statement of Result</h6>
                </div>

                <div class="row mb-5">
                    <div class="col-sm-6">
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted fw-bold w-25">Name:</td><td class="fw-bold text-dark"><?= htmlspecialchars($student['full_name']) ?></td></tr>
                            <tr><td class="text-muted fw-bold">Matric No:</td><td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($student['student_id_number']) ?></td></tr>
                            <tr><td class="text-muted fw-bold">Program:</td><td class="fw-bold text-dark"><?= htmlspecialchars($student['program_name']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <table class="table table-sm table-borderless">
                            <tr><td class="text-muted fw-bold w-75">Session:</td><td class="fw-bold text-dark"><?= htmlspecialchars($selected_session_name) ?></td></tr>
                            <tr><td class="text-muted fw-bold">Session GPA:</td><td class="fw-black text-danger fs-5"><?= number_format($sgpa, 2) ?></td></tr>
                            <tr><td class="text-muted fw-bold">Cumulative GPA:</td><td class="fw-black text-success fs-5"><?= number_format($cgpa, 2) ?></td></tr>
                        </table>
                    </div>
                </div>

                <?php if (empty($semesters)): ?>
                    <div class="alert alert-warning text-center py-4 rounded-3 border-0">
                        <i class="ti ti-alert-circle fs-1 d-block mb-2"></i>
                        No published grades found for this session yet. Please check back after the grading period.
                    </div>
                <?php else: ?>
                    
                    <?php foreach ($semesters as $sem => $data): ?>
                        <div class="mb-5">
                            <h5 class="fw-bold text-dark border-start border-4 border-primary ps-3 mb-3"><?= htmlspecialchars($sem) ?></h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-results align-middle">
                                    <thead>
                                        <tr>
                                            <th width="15%">Course Code</th>
                                            <th width="45%">Course Title</th>
                                            <th width="10%" class="text-center">Units (CU)</th>
                                            <th width="10%" class="text-center">Grade</th>
                                            <th width="10%" class="text-center">Points (GP)</th>
                                            <th width="10%" class="text-center">Total (CU x GP)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white">
                                        <?php foreach ($data['courses'] as $c): 
                                            $grade = $c['final_grade'];
                                            $grade_color = 'bg-light text-dark';
                                            if ($grade === 'A') $grade_color = 'bg-success-subtle text-success';
                                            if ($grade === 'B') $grade_color = 'bg-primary-subtle text-primary';
                                            if ($grade === 'C') $grade_color = 'bg-info-subtle text-info';
                                            if ($grade === 'D' || $grade === 'E') $grade_color = 'bg-warning-subtle text-warning';
                                            if ($grade === 'F') $grade_color = 'bg-danger-subtle text-danger';
                                        ?>
                                        <tr>
                                            <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($c['course_code']) ?></td>
                                            <td><?= htmlspecialchars($c['course_title']) ?></td>
                                            <td class="text-center fw-bold"><?= $c['credit_units'] ?></td>
                                            <td class="text-center">
                                                <?php if($grade): ?>
                                                    <span class="grade-badge <?= $grade_color ?>"><?= $grade ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center fw-medium"><?= $grade ? number_format($c['grade_point'], 1) : '-' ?></td>
                                            <td class="text-center fw-bold text-dark"><?= $grade ? number_format($c['credit_units'] * $c['grade_point'], 1) : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr>
                                            <th colspan="2" class="text-end">Semester Summary:</th>
                                            <th class="text-center fs-6 text-dark"><?= $data['total_units'] ?></th>
                                            <th colspan="2"></th>
                                            <th class="text-center fs-6 text-dark"><?= number_format($data['total_points'], 1) ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="6" class="text-end text-primary bg-primary-subtle">
                                                SEMESTER GPA: <?= $data['total_units'] > 0 ? number_format($data['total_points'] / $data['total_units'], 2) : '0.00' ?>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-5 text-center">
                        <p class="text-muted small">This is a system-generated statement of result and does not require a physical signature. Any alteration renders it invalid.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>