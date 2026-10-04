<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Teachers Overview';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// --- Analytics Queries ---

// KPI: Total Teachers
$total_teachers = $conn->query("SELECT COUNT(id) FROM teachers")->fetch_row()[0] ?? 0;

// KPI: Unassigned Courses
$unassigned_courses_count = $conn->query("SELECT COUNT(id) FROM courses WHERE teacher_id IS NULL")->fetch_row()[0] ?? 0;

// KPI: Total Departments with at least one teacher
$total_departments_with_teachers = $conn->query("SELECT COUNT(DISTINCT department_id) FROM teachers WHERE department_id IS NOT NULL")->fetch_row()[0] ?? 0;

// KPI: Average courses per teacher
$avg_courses_query = $conn->query("SELECT AVG(course_count) FROM (SELECT COUNT(id) as course_count FROM courses WHERE teacher_id IS NOT NULL GROUP BY teacher_id) as teacher_course_counts");
$avg_courses_per_teacher = $avg_courses_query ? ($avg_courses_query->fetch_row()[0] ?? 0) : 0;


// Chart: Teachers per Department
$teachers_by_dept = [];
$sql_dept = "SELECT d.name, COUNT(t.id) as teacher_count
             FROM departments d
             LEFT JOIN teachers t ON d.id = t.department_id
             GROUP BY d.id
             ORDER BY teacher_count DESC";
$result_dept = $conn->query($sql_dept);
while ($row = $result_dept->fetch_assoc()) {
    $teachers_by_dept['labels'][] = $row['name'];
    $teachers_by_dept['data'][] = (int)$row['teacher_count'];
}

// List: Unassigned Courses
$unassigned_courses = $conn->query("SELECT course_name, course_code FROM courses WHERE teacher_id IS NULL ORDER BY course_name LIMIT 10")->fetch_all(MYSQLI_ASSOC);


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <!-- KPIs -->
    <div class="row">
        <div class="col-md-3"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title">Total Teachers</h5><h4 class="fw-bold"><?php echo $total_teachers; ?></h4></div></div></div>
        <div class="col-md-3"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title">Unassigned Courses</h5><h4 class="fw-bold"><?php echo $unassigned_courses_count; ?></h4></div></div></div>
        <div class="col-md-3"><div class="card bg-light-info"><div class="card-body"><h5 class="card-title">Active Departments</h5><h4 class="fw-bold"><?php echo $total_departments_with_teachers; ?></h4></div></div></div>
        <div class="col-md-3"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title">Avg. Courses/Teacher</h5><h4 class="fw-bold"><?php echo number_format($avg_courses_per_teacher, 1); ?></h4></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8 d-flex align-items-stretch">
            <div class="card w-100">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Teachers per Department</h5>
                    <?php if (!empty($teachers_by_dept['labels'])): ?><div id="dept-chart"></div><?php else: ?><div class="alert alert-info">No department data available.</div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-flex align-items-stretch">
            <div class="card w-100">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Unassigned Courses</h5>
                    <?php if (!empty($unassigned_courses)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($unassigned_courses as $course): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                         <?php if ($unassigned_courses_count > 10): ?>
                            <a href="manage_courses.php" class="btn btn-sm btn-outline-primary mt-2">View All...</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>All courses have an assigned teacher. Well done!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Teachers by Department Chart
    <?php if (!empty($teachers_by_dept['labels'])): ?>
    var deptOptions = {
        series: [{ name: 'Teachers', data: <?php echo json_encode($teachers_by_dept['data']); ?> }],
        chart: { type: 'bar', height: 350 },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: <?php echo json_encode($teachers_by_dept['labels']); ?> },
        yaxis: { title: { text: 'Number of Teachers' } },
        fill: { opacity: 1 },
        tooltip: { y: { formatter: function(val) { return val + " teachers" } } }
    };
    var deptChart = new ApexCharts(document.querySelector("#dept-chart"), deptOptions);
    deptChart.render();
    <?php endif; ?>
});
</script>