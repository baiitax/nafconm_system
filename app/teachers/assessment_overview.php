<?php
$page_title = 'Assessment Overview';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$error = '';

// Fetch teacher's courses
$teacher_courses = [];
$sql_courses = "SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name";
if ($stmt = $conn->prepare($sql_courses)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teacher_courses[] = $row;
    }
    $stmt->close();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$assessment_averages = [];
$student_performance = [];
$top_students = [];
$selected_course_name = '';

if ($course_id) {
    // Get selected course name
    foreach ($teacher_courses as $course) {
        if ($course['id'] == $course_id) {
            $selected_course_name = $course['course_name'] . ' (' . $course['course_code'] . ')';
            break;
        }
    }

    // 1. Fetch Assessment Averages for the first chart
    $sql_avg = "SELECT a.name AS assessment_name, AVG(sc.score) AS average_score, a.max_marks
                FROM assessments a
                JOIN assessment_scores sc ON a.id = sc.assessment_id
                WHERE a.course_id = ? AND sc.score IS NOT NULL
                GROUP BY a.id, a.name, a.max_marks
                ORDER BY a.created_at";
    if ($stmt_avg = $conn->prepare($sql_avg)) {
        $stmt_avg->bind_param("i", $course_id);
        $stmt_avg->execute();
        $result_avg = $stmt_avg->get_result();
        while ($row = $result_avg->fetch_assoc()) {
            $assessment_averages[] = $row;
        }
        $stmt_avg->close();
    } else {
        $error = "Error preparing statement for averages: " . $conn->error;
    }

    // 2. Fetch Student Performance data for the second chart and the table
    $sql_perf = "SELECT
                    s.full_name, s.student_id_number,
                    SUM(sc.score) AS total_score,
                    SUM(a.max_marks) AS total_max_marks
                FROM students s
                JOIN enrollments e ON s.id = e.student_id
                LEFT JOIN assessment_scores sc ON s.id = sc.student_id
                LEFT JOIN assessments a ON sc.assessment_id = a.id AND a.course_id = e.course_id
                WHERE e.course_id = ?
                GROUP BY s.id, s.full_name, s.student_id_number
                HAVING SUM(sc.score) IS NOT NULL
                ORDER BY total_score DESC";

    if ($stmt_perf = $conn->prepare($sql_perf)) {
        $stmt_perf->bind_param("i", $course_id);
        $stmt_perf->execute();
        $result_perf = $stmt_perf->get_result();
        while ($row = $result_perf->fetch_assoc()) {
            $student_performance[] = $row;
        }
        $stmt_perf->close();
    } else {
        $error = "Error preparing statement for performance: " . $conn->error;
    }

    // Get top 10 students from the performance data
    $top_students = array_slice($student_performance, 0, 10);
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Assessment Overview</h5>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3 mb-4 align-items-end">
                    <div class="col-md-5">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select class="form-select" id="course_id" name="course_id" onchange="this.form.submit()">
                            <option value="">-- Choose a course --</option>
                            <?php foreach ($teacher_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php if ($course_id == $course['id']) echo 'selected'; ?>><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if ($course_id && $selected_course_name): ?>
                    <h5 class="card-title fw-semibold my-4">Reports for <?php echo htmlspecialchars($selected_course_name); ?></h5>
                    <div class="row">
                        <div class="col-lg-6 d-flex align-items-stretch">
                            <div class="card w-100">
                                <div class="card-body">
                                    <h5 class="card-title fw-semibold mb-4">Assessment Averages</h5>
                                    <?php if (!empty($assessment_averages)): ?>
                                        <div id="assessment-averages-chart"></div>
                                    <?php else: ?>
                                        <div class="alert alert-info">No assessment data available to generate this chart.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 d-flex align-items-stretch">
                            <div class="card w-100">
                                <div class="card-body">
                                    <h5 class="card-title fw-semibold mb-4">Student Performance Distribution</h5>
                                     <?php if (!empty($student_performance)): ?>
                                        <div id="student-performance-chart"></div>
                                    <?php else: ?>
                                        <div class="alert alert-info">No student performance data available to generate this chart.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title fw-semibold mb-4">Top 10 Performing Students</h5>
                            <?php if (!empty($top_students)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rank</th>
                                                <th>Student Name</th>
                                                <th>Student ID</th>
                                                <th>Total Score</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_students as $index => $student): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($student['total_score'], 2)); ?> / <?php echo htmlspecialchars(number_format($student['total_max_marks'], 2)); ?></td>
                                                    <td>
                                                        <?php
                                                        $percentage = ($student['total_max_marks'] > 0) ? ($student['total_score'] / $student['total_max_marks']) * 100 : 0;
                                                        echo htmlspecialchars(number_format($percentage, 2)) . '%';
                                                        ?>
                                                    </td>
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

                <?php elseif ($course_id): ?>
                     <div class="alert alert-warning">Selected course not found or you do not have permission to view it.</div>
                <?php else: ?>
                    <div class="alert alert-info">Please select a course to view the overview.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($course_id && !empty($assessment_averages) && !empty($student_performance)): ?>
<script src="<?php echo BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Assessment Averages Chart
    var assessmentAveragesOptions = {
        series: [{ name: 'Average Score', data: <?php echo json_encode(array_column($assessment_averages, 'average_score')); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(2); } },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: <?php echo json_encode(array_column($assessment_averages, 'assessment_name')); ?>, title: { text: 'Assessments' } },
        yaxis: { title: { text: 'Average Score' } },
        fill: { opacity: 1 },
        tooltip: { y: { formatter: function (val) { return val.toFixed(2) + " marks" } } }
    };
    var assessmentAveragesChart = new ApexCharts(document.querySelector("#assessment-averages-chart"), assessmentAveragesOptions);
    assessmentAveragesChart.render();

    // 2. Student Performance Chart
    var studentPerformanceOptions = {
        series: [{ name: 'Total Score', data: <?php echo json_encode(array_column($student_performance, 'total_score')); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: true, dataLabels: { position: 'top' } } },
        dataLabels: {
            enabled: true, offsetX: 30, style: { colors: ['#333'] },
            formatter: function (val, opts) {
                const max = <?php echo json_encode(array_column($student_performance, 'total_max_marks')); ?>[opts.dataPointIndex];
                const perc = max > 0 ? (val / max * 100).toFixed(1) + '%' : 'N/A';
                return val.toFixed(1) + ' (' + perc + ')';
            }
        },
        xaxis: { categories: <?php echo json_encode(array_column($student_performance, 'full_name')); ?>, title: { text: 'Total Score' } },
        yaxis: { labels: { show: true, trim: true, maxWidth: 120 } },
        tooltip: { x: { formatter: function(val, { dataPointIndex, w }) { return w.globals.labels[dataPointIndex]; } }, y: { title: { formatter: function (seriesName) { return seriesName; } } } }
    };
    var studentPerformanceChart = new ApexCharts(document.querySelector("#student-performance-chart"), studentPerformanceOptions);
    studentPerformanceChart.render();
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>