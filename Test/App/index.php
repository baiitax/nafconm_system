<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// --- Fetch Dashboard Data ---

// 1. User Counts for KPIs
$student_count = $conn->query("SELECT COUNT(id) FROM students")->fetch_row()[0] ?? 0;
$teacher_count = $conn->query("SELECT COUNT(id) FROM teachers")->fetch_row()[0] ?? 0;
$staff_count = $conn->query("SELECT COUNT(id) FROM staffs")->fetch_row()[0] ?? 0;
$superadmin_count = $conn->query("SELECT COUNT(id) FROM superadmins")->fetch_row()[0] ?? 0;
$user_count = $student_count + $teacher_count + $staff_count + $superadmin_count;

// Other KPIs
$course_count = $conn->query("SELECT COUNT(id) FROM courses")->fetch_row()[0] ?? 0;
$announcement_count = $conn->query("SELECT COUNT(id) FROM announcements")->fetch_row()[0] ?? 0;

// Hostel Occupancy
$total_beds = $conn->query("SELECT COUNT(id) FROM beds")->fetch_row()[0] ?? 0;
$booked_beds = $conn->query("SELECT COUNT(id) FROM hostel_bookings WHERE status = 'booked'")->fetch_row()[0] ?? 0;
$hostel_occupancy = ($total_beds > 0) ? ($booked_beds / $total_beds) * 100 : 0;

// 2. Data for Chart (excluding superadmins from visual)
$chart_data = [
    'labels' => ['Students', 'Teachers', 'Staff'],
    'series' => [$student_count, $teacher_count, $staff_count]
];

// 3. Payment Analytics
$pending_payments_count = $conn->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$total_revenue = $conn->query("SELECT SUM(amount_paid) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0.00;

// 4. Data for Payments Chart (Revenue per month for the last 6 months)
$payments_chart_data = [
    'labels' => [],
    'series' => []
];
$payments_sql = "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount_paid) as total 
                 FROM payments 
                 WHERE status = 'approved' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY month ORDER BY month ASC";
if($payments_result = $conn->query($payments_sql)) {
    while($row = $payments_result->fetch_assoc()) {
        $payments_chart_data['labels'][] = date("M Y", strtotime($row['month'] . "-01"));
        $payments_chart_data['series'][] = (float)$row['total'];
    }
}

// 5. Recent Activity Logs
$recent_activities = [];
$sql_activities = "SELECT al.action, al.created_at, u.username, u.user_type
                      FROM activity_logs al
                      JOIN users u ON al.user_id = u.id
                      ORDER BY al.created_at DESC
                      LIMIT 5";
if ($result = $conn->query($sql_activities)) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="body-wrapper mt-0">
    <div class="container-fluid">
        <h5 class="card-title fw-semibold mb-4">Superadmin Dashboard</h5>
        <p class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</p>
        
        <!-- KPIs -->
        <div class="row">
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-primary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Students</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $student_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-primary rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-school fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Teachers</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $teacher_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-success rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-user-check fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-warning overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Staff</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $staff_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-warning rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-user-circle fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-danger overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Courses</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $course_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-danger rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-book fs-6"></i></div></div></div></div></div></div></div>
        </div>

        <!-- Payment KPIs -->
        <div class="row">
            <div class="col-sm-8 col-xl-6"><div class="card bg-light-info overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Revenue</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3">₦ <?php echo number_format($total_revenue, 2); ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-info rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-cash fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-secondary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Pending Payments</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $pending_payments_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-secondary rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-clock fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Hostel Occupancy</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo number_format($hostel_occupancy, 1); ?>%</h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-success rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-home-check fs-6"></i></div></div></div></div></div></div></div>
        </div>

        
        <!-- Chart and Announcements -->
        <div class="row">
            <div class="col-lg-7 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="d-sm-flex d-block align-items-center justify-content-between mb-9">
                            <div class="mb-3 mb-sm-0">
                                <h5 class="card-title fw-semibold">User Distribution</h5>
                                <p class="card-subtitle">Overview of user roles in the portal</p>
                            </div>
                        </div>
                        <div id="user-distribution-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4">Recent Portal Activity</h5>
                        <?php if (!empty($recent_activities)): ?>
                            <ul class="list-group list-group-flush">
                            <?php foreach ($recent_activities as $activity): ?>
                                <li class="list-group-item px-0">
                                    <p class="mb-0 fs-3"><?php echo htmlspecialchars($activity['action']); ?></p>
                                    <small class="text-muted">By <?php echo htmlspecialchars($activity['username']); ?> (<?php echo htmlspecialchars(ucfirst($activity['user_type'])); ?>) on <?php echo htmlspecialchars(date("M d, Y, g:i a", strtotime($activity['created_at']))); ?></small>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info" role="alert">No recent activity.</div>
                        <?php endif; ?>
                        <a href="activity_logs.php" class="btn btn-outline-primary mt-3">View Full Log</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Chart -->
        <div class="row">
            <div class="col-lg-12 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="d-sm-flex d-block align-items-center justify-content-between mb-9">
                            <div class="mb-3 mb-sm-0"><h5 class="card-title fw-semibold">Revenue Analytics</h5><p class="card-subtitle">Approved payments over the last 6 months</p></div>
                        </div>
                        <div id="revenue-chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (array_sum($chart_data['series']) > 0): ?>
    var chartData = {
        series: <?php echo json_encode($chart_data['series']); ?>,
        labels: <?php echo json_encode($chart_data['labels']); ?>
    };

    var options = {
        chart: {
            type: 'donut',
            height: 350,
            toolbar: {
                show: true
            }
        },
        series: chartData.series,
        labels: chartData.labels,
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
                legend: {
                    position: 'bottom'
                }
            }
        }],
        dataLabels: {
            enabled: true
        },
        legend: {
            position: 'right',
            offsetY: 0,
            height: 230,
        }
    };

    var chart = new ApexCharts(document.querySelector("#user-distribution-chart"), options);
    chart.render();
    <?php else: ?>
    document.querySelector("#user-distribution-chart").innerHTML = '<div class="alert alert-info">No user data to display.</div>';
    <?php endif; ?>

    <?php if (!empty($payments_chart_data['series'])): ?>
    var revenueChartData = {
        series: [{
            name: "Revenue",
            data: <?php echo json_encode($payments_chart_data['series']); ?>
        }],
        labels: <?php echo json_encode($payments_chart_data['labels']); ?>
    };

    var revenueOptions = {
        chart: {
            type: 'bar',
            height: 350,
            toolbar: { show: true }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: revenueChartData.labels },
        yaxis: { title: { text: 'Ksh' } },
        fill: { opacity: 1 },
        tooltip: { theme: 'light',
            y: { formatter: function (val) { return "Ksh " + val.toFixed(2) } }
        }
    };

    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();
    <?php else: ?>
    document.querySelector("#revenue-chart").innerHTML = '<div class="alert alert-info">No revenue data to display for the last 6 months.</div>';
    <?php endif; ?>
});
</script>
<?php
include 'includes/footer.php';
?>
