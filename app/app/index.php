<?php
/**
 * NAFCONM - Admin Master Dashboard
 * Features: High-Level KPIs, Schema-Synced Analytics, Real-time Pulse
 */
session_start();
require_once '../config/db.php';

// --- Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// --- 1. OPTIMIZED DATA AGGREGATION ---

// User Distribution
$user_counts = $conn->query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
$dist = ['student' => 0, 'teacher' => 0, 'staff' => 0, 'superadmin' => 0];
if ($user_counts) {
    while($row = $user_counts->fetch_assoc()) {
        if(isset($dist[$row['user_type']])) $dist[$row['user_type']] = $row['count'];
    }
}

// Academic KPIs
$course_count = $conn->query("SELECT COUNT(id) FROM courses")->fetch_row()[0] ?? 0;
$active_programs = $conn->query("SELECT COUNT(id) FROM programs")->fetch_row()[0] ?? 0;

// Financial KPIs (Synced to the new `student_payments` table)
$total_revenue = $conn->query("SELECT SUM(amount) FROM student_payments WHERE status IN ('successful', 'approved', 'paid')")->fetch_row()[0] ?? 0;
$pending_payments = $conn->query("SELECT COUNT(id) FROM student_payments WHERE status = 'pending'")->fetch_row()[0] ?? 0;

// Admissions KPIs (Synced to the 'accepted' status)
$pending_apps = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$admitted_apps = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'accepted'")->fetch_row()[0] ?? 0;

// Revenue Chart Data (Last 6 Months from `student_payments`)
$months = []; 
$revenue_series = [];
$rev_query = "
    SELECT DATE_FORMAT(payment_date, '%b %Y') as m, SUM(amount) as amt 
    FROM student_payments 
    WHERE status IN ('successful', 'approved', 'paid') 
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), m 
    ORDER BY MAX(payment_date) ASC 
    LIMIT 6
";
$rev_res = $conn->query($rev_query);
if ($rev_res) {
    while($r = $rev_res->fetch_assoc()){
        $months[] = $r['m'];
        $revenue_series[] = (float)$r['amt'];
    }
}

// Activity Logs
$logs = $conn->query("
    SELECT al.*, u.username 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 5
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .kpi-card { transition: all 0.3s ease; border: 1px solid rgba(0,0,0,0.05); }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
    .icon-box { width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    .log-item { border-left: 3px solid transparent; transition: all 0.2s; }
    .log-item:hover { background-color: #f8f9fa; border-left-color: var(--bs-primary); }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex flex-column flex-md-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-bold text-dark mb-1">Administrative Overview</h3>
            <p class="text-muted small mb-0">NAFCONM Portal Status: <span class="badge bg-success-subtle text-success fw-bold ms-1"><i class="ti ti-circle-check me-1"></i>Live & Operational</span></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark fw-bold rounded-pill px-4 shadow-sm" onclick="window.location.reload();">
                <i class="ti ti-refresh me-2"></i> Refresh Data
            </button>
        </div>
    </div>

    <?php if($pending_payments > 0 || $pending_apps > 0): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center justify-content-between p-4 mb-4">
            <div class="d-flex align-items-center">
                <div class="bg-white rounded-circle p-2 me-3 shadow-sm text-warning"><i class="ti ti-alert-triangle fs-4"></i></div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Urgent Actions Required</h6>
                    <span class="text-muted small">You have <strong><?= $pending_payments; ?></strong> unverified fee payments and <strong><?= $pending_apps; ?></strong> new applications waiting for review.</span>
                </div>
            </div>
            <div class="d-none d-lg-flex gap-2">
                <?php if($pending_apps > 0): ?><a href="admissions_overview.php" class="btn btn-dark btn-sm rounded-pill px-4 fw-bold">Review Admissions</a><?php endif; ?>
                <?php if($pending_payments > 0): ?><a href="student_payments.php" class="btn btn-outline-dark btn-sm rounded-pill px-4 fw-bold border">Check Ledger</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        
        <!-- Total Revenue KPI -->
        <div class="col-xl-3 col-sm-6">
            <div class="card kpi-card shadow-sm h-100 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted small fw-bold text-uppercase mb-1">Total Revenue</p>
                            <h3 class="fw-bolder text-dark mb-0">₦<?= number_format($total_revenue / 1000000, 2); ?>M</h3>
                        </div>
                        <div class="icon-box bg-primary-subtle text-primary"><i class="ti ti-currency-naira fs-2"></i></div>
                    </div>
                    <div class="d-flex align-items-center mt-3 border-top pt-3">
                        <span class="badge bg-light-success text-success fw-bold me-2"><i class="ti ti-trending-up me-1"></i>Verified</span>
                        <span class="text-muted small fw-medium">All-time collections</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Students KPI -->
        <div class="col-xl-3 col-sm-6">
            <div class="card kpi-card shadow-sm h-100 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted small fw-bold text-uppercase mb-1">Active Students</p>
                            <h3 class="fw-bolder text-dark mb-0"><?= number_format($dist['student']); ?></h3>
                        </div>
                        <div class="icon-box bg-info-subtle text-info"><i class="ti ti-users fs-2"></i></div>
                    </div>
                    <div class="d-flex align-items-center mt-3 border-top pt-3">
                        <span class="text-muted small fw-medium">Across <strong class="text-dark"><?= $active_programs ?></strong> active programs</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Admitted KPI -->
        <div class="col-xl-3 col-sm-6">
            <div class="card kpi-card shadow-sm h-100 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted small fw-bold text-uppercase mb-1">Total Admitted</p>
                            <h3 class="fw-bolder text-dark mb-0"><?= number_format($admitted_apps); ?></h3>
                        </div>
                        <div class="icon-box bg-success-subtle text-success"><i class="ti ti-school fs-2"></i></div>
                    </div>
                    <div class="d-flex align-items-center mt-3 border-top pt-3">
                        <a href="manage_students.php" class="text-decoration-none text-success small fw-bold">View Student Directory <i class="ti ti-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Faculty & Staff KPI -->
        <div class="col-xl-3 col-sm-6">
            <div class="card kpi-card shadow-sm h-100 rounded-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <p class="text-muted small fw-bold text-uppercase mb-1">Faculty & Staff</p>
                            <h3 class="fw-bolder text-dark mb-0"><?= number_format($dist['teacher'] + $dist['staff']); ?></h3>
                        </div>
                        <div class="icon-box bg-warning-subtle text-warning"><i class="ti ti-briefcase fs-2"></i></div>
                    </div>
                    <div class="d-flex align-items-center mt-3 border-top pt-3">
                        <span class="text-muted small fw-medium">Managing <strong class="text-dark"><?= $course_count ?></strong> courses</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4">
        
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Financial Analytics</h6>
                        <small class="text-muted">Monthly collection of approved fees</small>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php if(!empty($months)): ?>
                        <div id="revenue-growth-chart" style="min-height: 320px;"></div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted"><i class="ti ti-chart-area-line fs-1 d-block mb-2"></i>No recent revenue data.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Demographics Chart -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">User Demographics</h6>
                </div>
                <div class="card-body p-4">
                    <div id="user-dist-pie" class="d-flex justify-content-center mb-3"></div>
                </div>
            </div>
        </div>

    </div>

    <div class="row g-4 mt-1">
        
        <!-- Activity Logs -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Recent System Activity</h6>
                    <a href="activity_logs.php" class="btn btn-sm btn-light fw-bold rounded-pill">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if($logs && $logs->num_rows > 0): while($log = $logs->fetch_assoc()): ?>
                            <div class="list-group-item log-item p-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($log['action']); ?></h6>
                                    <small class="text-muted"><i class="ti ti-clock me-1"></i><?= date('d M, Y - h:i A', strtotime($log['created_at'])); ?></small>
                                </div>
                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><i class="ti ti-user me-1"></i><?= htmlspecialchars($log['username']); ?></span>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center py-4 text-muted">No recent activity.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">Quick Management Links</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <a href="manage_students.php" class="btn btn-outline-primary w-100 py-4 rounded-4 d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="ti ti-users fs-2 mb-2"></i>
                                <span class="fw-bold">Student Records</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="manage_courses.php" class="btn btn-outline-success w-100 py-4 rounded-4 d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="ti ti-book fs-2 mb-2"></i>
                                <span class="fw-bold">Course Catalog</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="manage_sessions.php" class="btn btn-outline-info w-100 py-4 rounded-4 d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="ti ti-calendar-event fs-2 mb-2"></i>
                                <span class="fw-bold">Academic Sessions</span>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="settings.php" class="btn btn-outline-danger w-100 py-4 rounded-4 d-flex flex-column align-items-center justify-content-center h-100">
                                <i class="ti ti-settings fs-2 mb-2"></i>
                                <span class="fw-bold">System Settings</span>
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
document.addEventListener("DOMContentLoaded", function() {
    
    // Revenue Chart Styling
    <?php if(!empty($months)): ?>
    var revOptions = {
        chart: { height: 350, type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        colors: ["#1e4db7"],
        series: [{ name: "Revenue (NGN)", data: <?php echo json_encode($revenue_series); ?> }],
        xaxis: { 
            categories: <?php echo json_encode($months); ?>,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: { 
            labels: { formatter: function(value) { return "₦" + (value/1000).toFixed(0) + "k"; } }
        },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] } },
    };
    new ApexCharts(document.querySelector("#revenue-growth-chart"), revOptions).render();
    <?php endif; ?>

    // User Demographics Pie Chart
    var pieOptions = {
        chart: { type: 'donut', height: 300, fontFamily: 'inherit' },
        series: [<?php echo $dist['student']; ?>, <?php echo $dist['teacher']; ?>, <?php echo $dist['staff']; ?>],
        labels: ["Students", "Lecturers", "Admin/Staff"],
        colors: ["#1e4db7", "#13DEB9", "#FFAE1F"],
        legend: { position: 'bottom', horizontalAlign: 'center', markers: { radius: 12 } },
        stroke: { show: false },
        plotOptions: { 
            pie: { 
                donut: { 
                    size: '75%', 
                    labels: { 
                        show: true, 
                        name: { show: true, fontSize: '14px', color: '#6c757d' }, 
                        value: { show: true, fontSize: '24px', fontWeight: 700, color: '#212529' }, 
                        total: { show: true, label: 'Total Users', fontSize: '14px', color: '#6c757d' } 
                    } 
                } 
            } 
        }
    };
    new ApexCharts(document.querySelector("#user-dist-pie"), pieOptions).render();
});
</script>

<?php include 'includes/footer.php'; ?>