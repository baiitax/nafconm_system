<?php
$page_title = 'Teacher Reports';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id) {
    die("Teacher not found.");
}

// 1. KPIs
$kpi_courses = $kpi_students = $kpi_assignments = $kpi_avg_grade = 0;

// KPI: Total Courses
$stmt = $conn->prepare("SELECT COUNT(id) FROM courses WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stmt->bind_result($kpi_courses);
$stmt->fetch();
$stmt->close();

// KPI: Total Unique Students
$stmt = $conn->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stmt->bind_result($kpi_students);
$stmt->fetch();
$stmt->close();

// KPI: Total Assignments
$stmt = $conn->prepare("SELECT COUNT(id) FROM assignments WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stmt->bind_result($kpi_assignments);
$stmt->fetch();
$stmt->close();

// KPI: Overall Average Grade
$stmt = $conn->prepare("SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE a.created_by_teacher_id = ? AND sc.score IS NOT NULL");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$stmt->bind_result($kpi_avg_grade);
$stmt->fetch();
$stmt->close();
$kpi_avg_grade = $kpi_avg_grade ?? 0;

// 2. Chart Data: Course Average Performance
$course_performance = ['labels' => [], 'data' => []];
$sql_course_perf = "SELECT c.course_name, AVG(sc.score / a.max_marks * 100) as avg_perc 
                    FROM courses c 
                    LEFT JOIN assessments a ON c.id = a.course_id 
                    LEFT JOIN assessment_scores sc ON a.id = sc.assessment_id 
                    WHERE c.teacher_id = ? 
                    GROUP BY c.id";
$stmt = $conn->prepare($sql_course_perf);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $course_performance['labels'][] = $row['course_name'];
    $course_performance['data'][] = round($row['avg_perc'] ?? 0, 2);
}
$stmt->close();

// 3. Table Data: Top 20 Students
$top_students = [];
$sql_top_students = "SELECT s.full_name, s.student_id_number, AVG(sc.score / a.max_marks * 100) as avg_student_perc 
                     FROM students s 
                     JOIN enrollments e ON s.id = e.student_id 
                     JOIN courses c ON e.course_id = c.id 
                     LEFT JOIN assessments a ON c.id = a.course_id 
                     LEFT JOIN assessment_scores sc ON a.id = sc.assessment_id AND sc.student_id = s.id 
                     WHERE c.teacher_id = ? AND sc.score IS NOT NULL
                     GROUP BY s.id 
                     ORDER BY avg_student_perc DESC 
                     LIMIT 20";
$stmt = $conn->prepare($sql_top_students);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $top_students[] = $row;
}
$stmt->close();

// 4. Table Data: Course Details
$course_details = [];
$sql_course_details = "SELECT 
                        c.course_name, 
                        c.course_code, 
                        COUNT(DISTINCT e.student_id) as student_count, 
                        (SELECT COUNT(id) FROM assignments WHERE course_id = c.id) as assignment_count,
                        (SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE a.course_id = c.id) as avg_grade
                       FROM courses c 
                       LEFT JOIN enrollments e ON c.id = e.course_id 
                       WHERE c.teacher_id = ? 
                       GROUP BY c.id";
$stmt = $conn->prepare($sql_course_details);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $course_details[] = $row;
}
$stmt->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>
<style>
@media print {
  body {
    background-color: #fff !important;
  }
  .sidebar, .topbar, .d-print-none {
    display: none !important;
  }
  .container-fluid, .page-wrapper, .body-wrapper {
    padding: 0 !important;
    margin: 0 !important;
  }
  .card {
    border: 1px solid #dee2e6 !important;
    box-shadow: none !important;
    page-break-inside: avoid;
  }
}
</style>
<div class="d-flex justify-content-end mb-3 d-print-none">
    <button onclick="window.print();" class="btn btn-primary">Print Report</button>
</div>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-3"><div class="card bg-light-primary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Courses</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $kpi_courses; ?></h4></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Students</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $kpi_students; ?></h4></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-warning overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Assignments</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $kpi_assignments; ?></h4></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-info overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Overall Avg. Grade</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo number_format($kpi_avg_grade, 2); ?>%</h4></div></div></div></div></div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
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
</div>

<div class="row">
    <div class="col-lg-7 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Top 20 Students (Overall)</h5>
                <?php if (!empty($top_students)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light"><tr><th>Rank</th><th>Student Name</th><th>Student ID</th><th>Average Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_students as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                        <td><?php echo number_format($student['avg_student_perc'], 2); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No student performance data available.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Course Summary</h5>
                 <?php if (!empty($course_details)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light"><tr><th>Course</th><th>Students</th><th>Assignments</th><th>Avg. Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($course_details as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['student_count']); ?></td>
                                        <td><?php echo htmlspecialchars($course['assignment_count']); ?></td>
                                        <td><?php echo number_format($course['avg_grade'] ?? 0, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No course data available.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Course Performance Chart
    <?php if (!empty($course_performance['labels'])): ?>
    var coursePerformanceOptions = {
        series: [{ name: 'Average Grade', data: <?php echo json_encode($course_performance['data']); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: false, columnWidth: '50%', borderRadius: 4 } },
        dataLabels: { enabled: true, formatter: function (val) { return val + "%"; } },
        xaxis: { categories: <?php echo json_encode($course_performance['labels']); ?> },
        yaxis: { title: { text: 'Average Grade (%)' }, min: 0, max: 100 },
        tooltip: { y: { formatter: function (val) { return val + "%" } } }
    };
    var coursePerformanceChart = new ApexCharts(document.querySelector("#course-performance-chart"), coursePerformanceOptions);
    coursePerformanceChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>