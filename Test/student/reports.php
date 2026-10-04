<?php
$page_title = 'My Reports';
require_once 'includes/header.php';

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    die("Student not found.");
}

$active_session_id = null;
$sql_session = "SELECT id FROM sessions WHERE is_active = 1 LIMIT 1";
$result_session = $conn->query($sql_session);
if ($result_session->num_rows > 0) {
    $active_session_id = $result_session->fetch_assoc()['id'];
}

// 1. KPIs
$kpi_courses = $kpi_credits = $kpi_avg_grade = 0;

if ($active_session_id) {
    // KPI: Enrolled Courses & Credit Units for active session
    $stmt = $conn->prepare("SELECT COUNT(c.id), SUM(c.credit_units) FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? AND e.session_id = ?");
    $stmt->bind_param("ii", $student_id, $active_session_id);
    $stmt->execute();
    $stmt->bind_result($kpi_courses, $kpi_credits);
    $stmt->fetch();
    $stmt->close();
}

// KPI: Overall Average Grade (all time)
$stmt = $conn->prepare("SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE sc.student_id = ? AND sc.score IS NOT NULL AND a.max_marks > 0");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($kpi_avg_grade);
$stmt->fetch();
$stmt->close();
$kpi_avg_grade = $kpi_avg_grade ?? 0;

// 2. Chart Data: Course Average Performance
$course_performance = ['labels' => [], 'data' => []];
$sql_course_perf = "SELECT c.course_name, c.course_code, AVG(sc.score / a.max_marks * 100) as avg_perc 
                    FROM courses c 
                    JOIN assessments a ON c.id = a.course_id 
                    JOIN assessment_scores sc ON a.id = sc.assessment_id 
                    WHERE sc.student_id = ? AND a.max_marks > 0
                    GROUP BY c.id";
$stmt = $conn->prepare($sql_course_perf);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $course_performance['labels'][] = $row['course_code'];
    $course_performance['data'][] = round($row['avg_perc'] ?? 0, 2);
}
$stmt->close();

// 3. Table Data: Detailed Assessment Report for active session
$detailed_report = [];
if ($active_session_id) {
    $sql_details = "SELECT c.course_name, c.course_code, a.name as assessment_name, a.max_marks, sc.score 
                    FROM assessment_scores sc 
                    JOIN assessments a ON sc.assessment_id = a.id 
                    JOIN courses c ON a.course_id = c.id 
                    WHERE sc.student_id = ? AND a.session_id = ?
                    ORDER BY c.course_name, a.created_at";
    $stmt = $conn->prepare($sql_details);
    $stmt->bind_param("ii", $student_id, $active_session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $detailed_report[] = $row;
    }
    $stmt->close();
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-4"><div class="card bg-light-primary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Enrolled Courses</h5><h4 class="fw-semibold mb-3"><?php echo $kpi_courses; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Credit Units</h5><h4 class="fw-semibold mb-3"><?php echo $kpi_credits ?? 0; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-info overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Overall Avg. Grade</h5><h4 class="fw-semibold mb-3"><?php echo number_format($kpi_avg_grade, 2); ?>%</h4></div></div></div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Course Average Performance (%)</h5>
                <?php if (!empty($course_performance['labels'])): ?>
                    <div id="course-performance-chart"></div>
                <?php else: ?>
                    <div class="alert alert-info">No assessment data available to generate this chart.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Detailed Report (Current Session)</h5>
                <?php if (!empty($detailed_report)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light"><tr><th>Course</th><th>Assessment</th><th>Score</th></tr></thead>
                            <tbody>
                                <?php foreach ($detailed_report as $report): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($report['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($report['assessment_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['score']); ?> / <?php echo htmlspecialchars($report['max_marks']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No detailed assessment records found for the current session.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($course_performance['labels'])): ?>
    var coursePerformanceOptions = {
        series: [{ name: 'Average Grade', data: <?php echo json_encode($course_performance['data']); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + "%"; } },
        xaxis: { categories: <?php echo json_encode($course_performance['labels']); ?>, title: { text: 'Average Grade (%)' }, min: 0, max: 100 },
        tooltip: { y: { formatter: function (val) { return val.toFixed(2) + "%" } } }
    };
    var coursePerformanceChart = new ApexCharts(document.querySelector("#course-performance-chart"), coursePerformanceOptions);
    coursePerformanceChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>