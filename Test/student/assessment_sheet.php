<?php
$page_title = 'Assessment Sheet';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

$student_id = $_SESSION['student_id'];
$enrolled_courses = [];
$assessments = [];
$scores = [];
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Fetch student's enrolled courses for the active session
$sql_courses = "SELECT c.id, c.course_name, c.course_code FROM courses c JOIN enrollments e ON c.id = e.course_id JOIN sessions s ON e.session_id = s.id WHERE e.student_id = ? AND s.is_active = 1 ORDER BY c.course_name";
if ($stmt = $conn->prepare($sql_courses)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $enrolled_courses[] = $row;
    $stmt->close();
}

// If a course is selected, fetch its assessments and scores
if ($selected_course_id) {
    $sql_assessments = "SELECT id, name, max_marks FROM assessments WHERE course_id = ? AND session_id = (SELECT id FROM sessions WHERE is_active = 1) ORDER BY created_at";
    if ($stmt = $conn->prepare($sql_assessments)) {
        $stmt->bind_param("i", $selected_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    }

    $sql_scores = "SELECT assessment_id, score FROM assessment_scores WHERE student_id = ? AND assessment_id IN (SELECT id FROM assessments WHERE course_id = ?)";
    if ($stmt = $conn->prepare($sql_scores)) {
        $stmt->bind_param("ii", $student_id, $selected_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $scores[$row['assessment_id']] = $row['score'];
        $stmt->close();
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">My Assessment Sheet</h5>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3 mb-4 align-items-end">
                    <div class="col-md-5">
                        <label for="course_id" class="form-label">Select a Course to View</label>
                        <select class="form-select" id="course_id" name="course_id" onchange="this.form.submit()">
                            <option value="">-- Choose a course --</option>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php if ($selected_course_id == $course['id']) echo 'selected'; ?>><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selected_course_id && !empty($assessments)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6>Results for: <?php echo htmlspecialchars(array_values(array_filter($enrolled_courses, function($c) use ($selected_course_id) { return $c['id'] == $selected_course_id; }))[0]['course_name']); ?></h6>
                        <a href="print_assessment_sheet.php?course_id=<?php echo $selected_course_id; ?>" target="_blank" class="btn btn-primary">Print Sheet</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light"><tr><th>#</th><th>Assessment</th><th>Max Marks</th><th>Your Score</th></tr></thead>
                            <tbody>
                                <?php $total_max = $total_score = 0; foreach ($assessments as $index => $assessment): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                                        <td><?php echo htmlspecialchars($assessment['max_marks']); $total_max += $assessment['max_marks']; ?></td>
                                        <td><?php echo htmlspecialchars($scores[$assessment['id']] ?? 'N/A'); $total_score += ($scores[$assessment['id']] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr><th colspan="2" class="text-end">Total:</th><th><?php echo htmlspecialchars($total_max); ?></th><th><?php echo htmlspecialchars($total_score); ?></th></tr></tfoot>
                        </table>
                    </div>
                <?php elseif ($selected_course_id): ?>
                    <div class="alert alert-info">No assessments have been recorded for this course yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>