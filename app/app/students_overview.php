<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Role-based access control
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

$page_title = 'Students Dashboard';

// --- Analytics Logic ---

// KPI: Total Enrolled Students
$kpi_total_students = $conn->query("SELECT COUNT(id) FROM students")->fetch_row()[0] ?? 0;

// KPI: Students who have cleared all session fees (can register)
$kpi_can_register = $conn->query("SELECT COUNT(id) FROM students WHERE can_register_courses = 1")->fetch_row()[0] ?? 0;

// KPI: New Students this session
$active_session_name = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'academic_session'")->fetch_row()[0] ?? '';
$kpi_session_intake = $conn->query("SELECT COUNT(s.id) FROM students s JOIN sessions sess ON s.student_id_number LIKE CONCAT(YEAR(sess.start_date), '%') WHERE sess.name = '$active_session_name'")->fetch_row()[0] ?? 0;

// Chart 1: Distribution by Department
$dept_labels = []; $dept_data = [];
$res_dept = $conn->query("SELECT d.name, COUNT(s.id) as count FROM departments d LEFT JOIN students s ON d.id = s.department_id GROUP BY d.id ORDER BY count DESC");
while($row = $res_dept->fetch_assoc()){
    $dept_labels[] = $row['name'];
    $dept_data[] = (int)$row['count'];
}

// Chart 2: Admissions Trend (Last 6 Months)
$trend_labels = []; $trend_data = [];
$res_trend = $conn->query("SELECT DATE_FORMAT(review_date, '%b %Y') as month, COUNT(id) as count 
                           FROM admission_applications WHERE status = 'accepted' 
                           AND review_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                           GROUP BY month ORDER BY review_date ASC");
while($row = $res_trend->fetch_assoc()){
    $trend_labels[] = $row['month'];
    $trend_data[] = (int)$row['count'];
}

// List: Recent Enrolments
$recent_students = $conn->query("SELECT s.full_name, s.student_id_number, p.name as program, u.created_at 
                                FROM students s 
                                JOIN users u ON s.user_id = u.id 
                                LEFT JOIN programs p ON s.program_id = p.id
                                ORDER BY u.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Student Body Overview</h4>
            <p class="text-muted small mb-0">Academic session: <span class="badge bg-light-primary text-primary fw-bold"><?php echo $active_session_name; ?></span></p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="manage_students.php" class="btn btn-outline-primary border-2 fw-bold me-2">Directory</a>
            <a href="add_student.php" class="btn btn-primary shadow-sm fw-bold"><i class="ti ti-user-plus me-1"></i> New Enrolment</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card shadow-none border h-100 p-2">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light-primary rounded-circle p-3 me-3 text-primary">
                        <i class="ti ti-users fs-7"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo number_format($kpi_total_students); ?></h4>
                        <span class="text-muted fs-2 uppercase fw-semibold">Total Students</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card shadow-none border h-100 p-2">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light-success rounded-circle p-3 me-3 text-success">
                        <i class="ti ti-discount-check fs-7"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo number_format($kpi_can_register); ?></h4>
                        <span class="text-muted fs-2 uppercase fw-semibold">Cleared for Registration</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-none border h-100 p-2 text-white bg-dark">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-primary rounded-circle p-3 me-3 text-white">
                        <i class="ti ti-user-plus fs-7"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-white"><?php echo $kpi_session_intake; ?></h4>
                        <span class="text-white opacity-75 fs-2 uppercase fw-semibold">Session Intake</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 d-flex align-items-stretch mb-4">
            <div class="card w-100 shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h5 class="fw-bold card-title">Admission Trajectory</h5>
                        <i class="ti ti-trending-up text-success fs-5"></i>
                    </div>
                    <div id="admission-trend"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-flex align-items-stretch mb-4">
            <div class="card w-100 shadow-none border">
                <div class="card-body">
                    <h5 class="fw-bold card-title mb-4">Latest Enrollments</h5>
                    <div class="vertical-timeline ps-3">
                        <?php foreach($recent_students as $s): ?>
                        <div class="mb-4 position-relative border-start ps-3 pb-1">
                            <span class="position-absolute translate-middle-x start-0 bg-primary rounded-circle border border-white border-3" style="width:12px; height:12px; margin-left:-2px; top:5px;"></span>
                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($s['full_name']); ?></h6>
                            <p class="mb-0 text-muted fs-2"><?php echo $s['program']; ?></p>
                            <small class="text-primary fw-bold fs-1"><?php echo $s['student_id_number']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="student.php" class="btn btn-light-primary w-100 py-2 mt-2 fs-3 fw-bold">View Complete List</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-none border">
                <div class="card-body">
                    <h5 class="fw-bold card-title mb-4">Department Strength</h5>
                    <div id="dept-dist"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-none border bg-light-primary-subtle">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Ready-to-Print Reports</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="students_report.php?type=registered" class="card shadow-none border p-3 text-center text-decoration-none">
                                <i class="ti ti-file-certificate fs-7 text-primary d-block mb-1"></i>
                                <span class="text-dark fw-bold fs-2">Registration List</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="students_report.php?type=fees" class="card shadow-none border p-3 text-center text-decoration-none">
                                <i class="ti ti-cash fs-7 text-success d-block mb-1"></i>
                                <span class="text-dark fw-bold fs-2">Fee Status Report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Admission Trend Area Chart
    new ApexCharts(document.querySelector("#admission-trend"), {
        series: [{ name: 'Admissions', data: <?php echo json_encode($trend_data); ?> }],
        chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
        colors: ['#5D87FF'],
        stroke: { curve: 'smooth', width: 3 },
        dataLabels: { enabled: false },
        xaxis: { categories: <?php echo json_encode($trend_labels); ?> },
        grid: { borderColor: '#f1f1f1' },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 90, 100] } }
    }).render();

    // 2. Department Distribution Bar Chart
    new ApexCharts(document.querySelector("#dept-dist"), {
        series: [{ name: 'Students', data: <?php echo json_encode($dept_data); ?> }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        colors: ['#5D87FF'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
        xaxis: { categories: <?php echo json_encode($dept_labels); ?> },
        dataLabels: { enabled: false }
    }).render();
});
</script>

<?php require_once 'includes/footer.php'; ?>