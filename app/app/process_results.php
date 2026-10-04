<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action_err = $action_success = "";
$processed_results = [];

// Fetch initial data for filters
$sessions = $conn->query("SELECT * FROM sessions ORDER BY is_active DESC, start_date DESC")->fetch_all(MYSQLI_ASSOC);
$courses = $conn->query("SELECT * FROM courses ORDER BY course_code ASC")->fetch_all(MYSQLI_ASSOC);
$grading_policy = $conn->query("SELECT * FROM grading_policy ORDER BY min_score DESC")->fetch_all(MYSQLI_ASSOC);

// --- Calculation Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_results'])) {
    $session_id = (int)$_POST['session_id'];
    $course_id = (int)$_POST['course_id'];

    $conn->begin_transaction();
    try {
        // 1. Validate Assessment Weights (Must total 100%)
        $assess_stmt = $conn->prepare("SELECT id, max_marks, weight FROM assessments WHERE course_id = ? AND session_id = ?");
        $assess_stmt->bind_param("ii", $course_id, $session_id);
        $assess_stmt->execute();
        $assessments = $assess_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total_weight = array_sum(array_column($assessments, 'weight'));
        if (abs($total_weight - 1.0) > 0.001) {
            throw new Exception("Configuration Error: Assessment weights for this course sum to " . ($total_weight * 100) . "%. They must total exactly 100% to process results.");
        }

        // 2. Fetch Enrolled Students
        $enroll_stmt = $conn->prepare("SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.student_id_number FROM enrollments e JOIN students s ON e.student_id = s.id WHERE e.course_id = ? AND e.session_id = ?");
        $enroll_stmt->bind_param("ii", $course_id, $session_id);
        $enroll_stmt->execute();
        $students = $enroll_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($students)) throw new Exception("No students found enrolled in this course for the selected session.");

        // 3. Process Scores
        foreach ($students as $student) {
            $final_weighted_score = 0;

            foreach ($assessments as $asmt) {
                $score_stmt = $conn->prepare("SELECT score FROM assessment_scores WHERE assessment_id = ? AND student_id = ?");
                $score_stmt->bind_param("ii", $asmt['id'], $student['student_id']);
                $score_stmt->execute();
                $s_res = $score_stmt->get_result()->fetch_assoc();
                
                $raw_score = $s_res['score'] ?? 0;
                // Calculate contribution: (Score / Max) * Weight * 100
                $contribution = ($asmt['max_marks'] > 0) ? ($raw_score / $asmt['max_marks']) * $asmt['weight'] * 100 : 0;
                $final_weighted_score += $contribution;
            }

            // 4. Map to Grading Policy
            $letter = 'F'; $gp = 0.00;
            foreach ($grading_policy as $rule) {
                if ($final_weighted_score >= $rule['min_score'] && $final_weighted_score <= $rule['max_score']) {
                    $letter = $rule['letter_grade'];
                    $gp = $rule['grade_point'];
                    break;
                }
            }

            // 5. Commit to Enrollment Record
            $upd = $conn->prepare("UPDATE enrollments SET final_grade = ?, grade_point = ? WHERE id = ?");
            $upd->bind_param("sdi", $letter, $gp, $student['enrollment_id']);
            $upd->execute();

            $processed_results[] = [
                'name' => $student['full_name'],
                'id_no' => $student['student_id_number'],
                'score' => $final_weighted_score,
                'grade' => $letter,
                'gp' => $gp
            ];
        }

        $conn->commit();
        log_activity($conn, $user_id, "Computed final grades for Course ID: $course_id");
        $action_success = "Successfully computed results for " . count($processed_results) . " students.";

    } catch (Exception $e) {
        $conn->rollback();
        $action_err = $e->getMessage();
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Result Processing Engine</h4>
            <p class="text-muted small mb-0">Automate score aggregation and grade assignment based on institutional policy.</p>
        </div>
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
        <div class="col-lg-4">
            <div class="card shadow-none border">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="fw-bold mb-0">Step 1: Selection</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Academic Session</label>
                            <select name="session_id" class="form-select" required>
                                <option value="">Select Session...</option>
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['name'] ?> <?= $s['is_active'] ? '(Active)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Academic Course</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Select Course...</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['course_code'] ?> - <?= $c['course_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="p-3 bg-light-danger rounded-3 mb-4 border border-danger-subtle">
                            <p class="small text-danger mb-0 fw-bold"><i class="ti ti-info-circle"></i> This action will overwrite any existing grades for this course in the selected session.</p>
                        </div>
                        <button type="submit" name="process_results" class="btn btn-danger w-100 py-2 fw-bold shadow-sm">
                            <i class="ti ti-calculator me-1"></i> Compute & Save Grades
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-transparent border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Step 2: Verification</h5>
                    <?php if(!empty($processed_results)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="ti ti-printer"></i> Print Batch</button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($processed_results)): ?>
                        <div class="text-center py-5">
                            <i class="ti ti-database-off fs-9 text-muted d-block mb-2"></i>
                            <p class="text-muted">Select a course to view processed computation results.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light-subtle">
                                    <tr class="text-muted fs-2">
                                        <th class="ps-4">STUDENT</th>
                                        <th class="text-center">WEIGHTED %</th>
                                        <th class="text-center">GRADE</th>
                                        <th class="text-center">GP</th>
                                        <th class="text-end pe-4">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($processed_results as $res): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <h6 class="fw-bold mb-0"><?= $res['name'] ?></h6>
                                            <small class="text-muted"><?= $res['id_no'] ?></small>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format($res['score'], 2) ?>%</td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill px-3"><?= $res['grade'] ?></span>
                                        </td>
                                        <td class="text-center fw-bold text-dark"><?= number_format($res['gp'], 2) ?></td>
                                        <td class="text-end pe-4">
                                            <span class="text-success small fw-bold"><i class="ti ti-check"></i> Saved</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>