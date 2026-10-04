<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
//     header("location: ../index.php");
//     exit;

$page_title = 'Departments Overview';
require_once 'includes/header.php';

// KPIs
$kpi_departments = $conn->query("SELECT COUNT(id) FROM departments")->fetch_row()[0] ?? 0;
$kpi_courses = $conn->query("SELECT COUNT(id) FROM courses")->fetch_row()[0] ?? 0;
$kpi_teachers = $conn->query("SELECT COUNT(DISTINCT teacher_id) FROM courses WHERE teacher_id IS NOT NULL")->fetch_row()[0] ?? 0;

// Chart Data: Courses per Department
$courses_chart = ['labels' => [], 'data' => []];
$sql_courses_chart = "SELECT d.name, COUNT(c.id) as course_count 
                     FROM departments d 
                     LEFT JOIN courses c ON d.id = c.department_id 
                     GROUP BY d.id 
                     ORDER BY course_count DESC";
$result_courses = $conn->query($sql_courses_chart);
while ($row = $result_courses->fetch_assoc()) {
    $courses_chart['labels'][] = $row['name'];
    $courses_chart['data'][] = (int)$row['course_count'];
}

// Chart Data: Students per Department
$students_chart = ['labels' => [], 'data' => []];
$sql_students_chart = "SELECT d.name, COUNT(DISTINCT e.student_id) as student_count
                       FROM departments d
                       LEFT JOIN courses c ON d.id = c.department_id
                       LEFT JOIN enrollments e ON c.id = e.course_id
                       GROUP BY d.id
                       ORDER BY student_count DESC";
$result_students = $conn->query($sql_students_chart);
while ($row = $result_students->fetch_assoc()) {
    $students_chart['labels'][] = $row['name'];
    $students_chart['data'][] = (int)$row['student_count'];
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="card-title fw-semibold">Departments Overview</h5>
    <a href="manage_departments.php" class="btn btn-primary">Manage Departments</a>
</div>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-4"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title fw-semibold">Total Departments</h5><h4 class="fw-bold"><?php echo $kpi_departments; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title fw-semibold">Total Courses</h5><h4 class="fw-bold"><?php echo $kpi_courses; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title fw-semibold">Assigned Teachers</h5><h4 class="fw-bold"><?php echo $kpi_teachers; ?></h4></div></div></div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Courses per Department</h5>
                <?php if (!empty($courses_chart['labels'])): ?><div id="courses-chart"></div><?php else: ?><div class="alert alert-info">No data available.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Students per Department</h5>
                <?php if (!empty($students_chart['labels'])): ?><div id="students-chart"></div><?php else: ?><div class="alert alert-info">No data available.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($courses_chart['labels'])): ?>
    var coursesOptions = {
        series: [{ name: 'Courses', data: <?php echo json_encode($courses_chart['data']); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: <?php echo json_encode($courses_chart['labels']); ?> },
        yaxis: { title: { text: 'Number of Courses' } },
        fill: { opacity: 1 },
        tooltip: { y: { formatter: function (val) { return val + " courses" } } }
    };
    var coursesChart = new ApexCharts(document.querySelector("#courses-chart"), coursesOptions);
    coursesChart.render();
    <?php endif; ?>

    <?php if (!empty($students_chart['labels'])): ?>
    var studentsOptions = {
        series: <?php echo json_encode($students_chart['data']); ?>,
        chart: { type: 'donut', height: 350 },
        labels: <?php echo json_encode($students_chart['labels']); ?>,
        legend: { position: 'bottom' },
        responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }]
    };
    var studentsChart = new ApexCharts(document.querySelector("#students-chart"), studentsOptions);
    studentsChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>