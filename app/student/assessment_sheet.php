<?php
/**
 * NAFCONM - Student Assessment Sheet
 * Features: Real-time Grade Mapping, Visual Progress Bars, & Performance Summary
 */
$page_title = 'Assessment Sheet';
require_once 'includes/header.php';

$student_id = (int)$_SESSION['student_id'];
$enrolled_courses = [];
$assessments = [];
$scores = [];
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// 1. Fetch enrolled courses for active session
$sql_courses = "SELECT c.id, c.course_name, c.course_code 
                FROM courses c 
                JOIN enrollments e ON c.id = e.course_id 
                JOIN sessions s ON e.session_id = s.id 
                WHERE e.student_id = ? AND s.is_active = 1 
                ORDER BY c.course_code ASC";

if ($stmt = $conn->prepare($sql_courses)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $enrolled_courses[] = $row;
    $stmt->close();
}

// 2. Fetch specific assessment data if course is selected
if ($selected_course_id) {
    // Join with grading policy to get dynamic letter grades for each score
    $sql_assessments = "SELECT a.id, a.name, a.max_marks, sc.score, 
                        (sc.score / a.max_marks * 100) as percentage,
                        gp.letter_grade
                        FROM assessments a 
                        LEFT JOIN assessment_scores sc ON a.id = sc.assessment_id AND sc.student_id = ?
                        LEFT JOIN grading_policy gp ON (sc.score / a.max_marks * 100) BETWEEN gp.min_score AND gp.max_score
                        WHERE a.course_id = ? 
                        AND a.session_id = (SELECT id FROM sessions WHERE is_active = 1) 
                        ORDER BY a.created_at ASC";
    
    if ($stmt = $conn->prepare($sql_assessments)) {
        $stmt->bind_param("ii", $student_id, $selected_course_id);
        $stmt->execute();
        $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Academic Performance</h3>
            <p class="text-muted mb-0">Monitor your continuous assessment and examination scores.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-uppercase small text-muted mb-3">Select Module</h6>
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
                        <select class="form-select border-2 py-2 mb-3" id="course_id" name="course_id" onchange="this.form.submit()">
                            <option value="">-- Select Enrolled Course --</option>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <option value="<?= $course['id']; ?>" <?= ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    
                    <div class="alert bg-primary-subtle text-primary border-0 small rounded-3 mt-2">
                        <i class="ti ti-info-circle me-1"></i> Selection will automatically refresh your assessment sheet below.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($selected_course_id && !empty($assessments)): 
                // Calculate Course Overview
                $total_max = array_sum(array_column($assessments, 'max_marks'));
                $total_obt = array_sum(array_column($assessments, 'score'));
                $overall_pct = ($total_max > 0) ? ($total_obt / $total_max * 100) : 0;
            ?>
                <div class="card border-0 shadow-sm rounded-4 bg-primary text-white mb-4">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="text-white-50 small fw-bold text-uppercase mb-1">Course Standings</h5>
                            <h2 class="fw-black mb-0"><?= number_format($overall_pct, 1) ?>%</h2>
                        </div>
                        <div class="text-end">
                            <a href="print_assessment_sheet.php?course_id=<?= $selected_course_id; ?>" target="_blank" class="btn btn-white btn-sm fw-bold rounded-pill px-3 shadow-sm">
                                <i class="ti ti-printer me-1"></i> Print PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Assessment Task</th>
                                    <th class="text-center">Max</th>
                                    <th class="text-center">Score</th>
                                    <th>Performance</th>
                                    <th class="text-center pe-4">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assessments as $a): 
                                    $pct = (float)$a['percentage'];
                                    $bar_color = ($pct >= 70) ? 'bg-success' : (($pct >= 50) ? 'bg-primary' : 'bg-danger');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($a['name']) ?></h6>
                                        <small class="text-muted">Continuous Assessment</small>
                                    </td>
                                    <td class="text-center fw-bold"><?= $a['max_marks'] ?></td>
                                    <td class="text-center">
                                        <span class="fw-black text-dark fs-5"><?= $a['score'] ?? '—' ?></span>
                                    </td>
                                    <td style="width: 200px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress w-100 me-2" style="height: 6px;">
                                                <div class="progress-bar <?= $bar_color ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                                            </div>
                                            <small class="fw-bold"><?= round($pct) ?>%</small>
                                        </div>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light-<?= str_replace('bg-', '', $bar_color) ?> text-<?= str_replace('bg-', '', $bar_color) ?> fw-bold px-3 py-2 rounded-pill">
                                            <?= $a['letter_grade'] ?? 'N/A' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light-subtle">
                                <tr class="fw-black text-dark">
                                    <td class="ps-4">TOTAL AGGREGATE</td>
                                    <td class="text-center"><?= $total_max ?></td>
                                    <td class="text-center text-primary"><?= $total_obt ?></td>
                                    <td colspan="2" class="text-end pe-4 small text-muted">Accumulated for Semester</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            <?php elseif ($selected_course_id): ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                    <i class="ti ti-database-x fs-1 text-muted opacity-25 mb-3"></i>
                    <h5 class="fw-bold">No Records Found</h5>
                    <p class="text-muted">Assessment data for this module hasn't been uploaded by the lecturer yet.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center border-dashed">
                    <i class="ti ti-arrow-left fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark">Ready to view scores?</h5>
                    <p class="text-muted">Select a course from the list on the left to display your assessment history.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .border-dashed { border: 2px dashed #dee2e6 !important; background: transparent; }
    .btn-white { background: white; color: var(--bs-primary); }
    .btn-white:hover { background: #f8f9fa; color: var(--bs-primary); }
    .bg-light-success { background-color: rgba(25, 135, 84, 0.1); }
    .bg-light-primary { background-color: rgba(93, 135, 255, 0.1); }
    .bg-light-danger { background-color: rgba(220, 53, 69, 0.1); }
</style>

<?php require_once 'includes/footer.php'; ?>