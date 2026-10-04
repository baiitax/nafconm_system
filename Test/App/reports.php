<?php
$page_title = 'Portal Reports';


require_once '../config/db.php';

// 1. KPIs
$kpi_students = $conn->query("SELECT COUNT(id) FROM students")->fetch_row()[0] ?? 0;
$kpi_teachers = $conn->query("SELECT COUNT(id) FROM teachers")->fetch_row()[0] ?? 0;
$kpi_courses = $conn->query("SELECT COUNT(id) FROM courses")->fetch_row()[0] ?? 0;
$kpi_revenue = $conn->query("SELECT SUM(amount_paid) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0.00;

// 1a. Admissions KPIs
$kpi_total_applications = $conn->query("SELECT COUNT(id) FROM admission_applications")->fetch_row()[0] ?? 0;
$kpi_pending_applications = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$kpi_accepted_applications = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'accepted'")->fetch_row()[0] ?? 0;

// 2. Chart Data: Enrollments per Course
$enrollment_chart = ['labels' => [], 'data' => []];
$sql_enroll_chart = "SELECT c.course_code, COUNT(e.id) as enroll_count 
                     FROM courses c 
                     LEFT JOIN enrollments e ON c.id = e.course_id 
                     GROUP BY c.id 
                     ORDER BY enroll_count DESC 
                     LIMIT 10";
$result_enroll = $conn->query($sql_enroll_chart);
while ($row = $result_enroll->fetch_assoc()) {
    $enrollment_chart['labels'][] = $row['course_code'];
    $enrollment_chart['data'][] = $row['enroll_count'];
}

// 3. Chart Data: Revenue per Month (Last 12 months)
$revenue_chart = ['labels' => [], 'data' => []];
$sql_revenue_chart = "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount_paid) as total 
                      FROM payments 
                      WHERE status = 'approved' AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                      GROUP BY month ORDER BY month ASC";
$result_revenue = $conn->query($sql_revenue_chart);
while ($row = $result_revenue->fetch_assoc()) {
    $revenue_chart['labels'][] = date("M Y", strtotime($row['month'] . "-01"));
    $revenue_chart['data'][] = (float)$row['total'];
}

// 4. Table Data: Course Performance Summary
$course_performance = [];
$sql_course_perf = "SELECT 
                        c.course_name, c.course_code, t.full_name as teacher_name,
                        COUNT(DISTINCT e.student_id) as student_count,
                        (SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE a.course_id = c.id) as avg_grade
                    FROM courses c
                    LEFT JOIN teachers t ON c.teacher_id = t.id
                    LEFT JOIN enrollments e ON c.id = e.course_id
                    GROUP BY c.id
                    ORDER BY c.course_name";
$result_course_perf = $conn->query($sql_course_perf);
while ($row = $result_course_perf->fetch_assoc()) {
    $course_performance[] = $row;
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Assesment Report</h5>
<div class="d-flex justify-content-end mb-3 d-print-none">
    <button onclick="window.print();" class="btn btn-primary"><i class="ti ti-printer me-1"></i>Print Report</button>
</div>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-4"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title fw-semibold">Total Students</h5><h4 class="fw-bold"><?php echo $kpi_students; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title fw-semibold">Total Teachers</h5><h4 class="fw-bold"><?php echo $kpi_teachers; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title fw-semibold">Total Courses</h5><h4 class="fw-bold"><?php echo $kpi_courses; ?></h4></div></div></div>
</div>
<div class="row">
    <!-- Admission KPIs -->
    <div class="col-lg-4"><div class="card bg-light-info"><div class="card-body"><h5 class="card-title fw-semibold">Total Applications</h5><h4 class="fw-bold"><?php echo $kpi_total_applications; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-secondary"><div class="card-body"><h5 class="card-title fw-semibold">Pending Applications</h5><h4 class="fw-bold"><?php echo $kpi_pending_applications; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-danger"><div class="card-body"><h5 class="card-title fw-semibold">Accepted Applications</h5><h4 class="fw-bold"><?php echo $kpi_accepted_applications; ?></h4></div></div></div>
</div>

<div class="row">
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Top 10 Enrolled Courses</h5>
                <?php if (!empty($enrollment_chart['labels'])): ?><div id="enrollment-chart"></div><?php else: ?><div class="alert alert-info">No enrollment data.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Monthly Revenue (Last 12 Months)</h5>
                <?php if (!empty($revenue_chart['labels'])): ?><div id="revenue-chart"></div><?php else: ?><div class="alert alert-info">No revenue data.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Course Performance Summary</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light"><tr><th>Course</th><th>Code</th><th>Teacher</th><th>Students</th><th>Avg. Grade</th></tr></thead>
                <tbody>
                    <?php foreach ($course_performance as $course): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($course['student_count']); ?></td>
                            <td><span class="badge bg-primary rounded-3 fw-semibold"><?php echo number_format($course['avg_grade'] ?? 0, 2); ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enrollment Chart
    <?php if (!empty($enrollment_chart['labels'])): ?>
    var enrollmentOptions = {
        series: [{ name: 'Enrollments', data: <?php echo json_encode($enrollment_chart['data']); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: true } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
        dataLabels: { enabled: true, formatter: function (val) { return val; } },
        xaxis: { categories: <?php echo json_encode($enrollment_chart['labels']); ?>, title: { text: 'Number of Students' } },
        tooltip: { y: { formatter: function (val) { return val + " students" } } }
    };
    var enrollmentChart = new ApexCharts(document.querySelector("#enrollment-chart"), enrollmentOptions);
    enrollmentChart.render();
    <?php endif; ?>

    // Revenue Chart
    <?php if (!empty($revenue_chart['labels'])): ?>
    var revenueOptions = {
        series: [{ name: 'Revenue', data: <?php echo json_encode($revenue_chart['data']); ?> }],
        chart: { type: 'area', height: 350, toolbar: { show: true }, zoom: { enabled: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
        xaxis: { type: 'category', categories: <?php echo json_encode($revenue_chart['labels']); ?> },
        tooltip: { theme: 'light', x: { format: 'MMM yyyy' }, y: { formatter: function(val) { return "Ksh " + val.toFixed(2); } } },
    };
    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>