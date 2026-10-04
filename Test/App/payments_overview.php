<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Payments Overview';


require_once 'includes/header.php';

// KPIs
$kpi_total_revenue = $conn->query("SELECT SUM(amount_paid) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0;
$kpi_total_transactions = $conn->query("SELECT COUNT(id) FROM payments")->fetch_row()[0] ?? 0;
$kpi_pending = $conn->query("SELECT COUNT(id) FROM payments WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$kpi_approved = $conn->query("SELECT COUNT(id) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0;
$kpi_rejected = $conn->query("SELECT COUNT(id) FROM payments WHERE status = 'rejected'")->fetch_row()[0] ?? 0;

// Chart Data: Revenue per Month (Last 12 months)
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

// Chart Data: Payment Status Distribution
$status_chart = [
    'pending' => $kpi_pending,
    'approved' => $kpi_approved,
    'rejected' => $kpi_rejected
];

// Recent Pending Payments
$recent_pending = [];
$sql_pending = "SELECT p.id, p.amount_paid, p.payment_date, s.full_name as student_name 
                FROM payments p 
                JOIN students s ON p.student_id = s.id 
                WHERE p.status = 'pending' 
                ORDER BY p.payment_date DESC 
                LIMIT 5";
$result_pending = $conn->query($sql_pending);
while ($row = $result_pending->fetch_assoc()) {
    $recent_pending[] = $row;
}

require_once 'includes/sidebar.php';
?>
<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Payment Overview</h5>
    </div>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-4"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title fw-semibold">Total Revenue</h5><h4 class="fw-bold">₦ <?php echo number_format($kpi_total_revenue, 2); ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title fw-semibold">Pending Payments</h5><h4 class="fw-bold"><?php echo $kpi_pending; ?></h4></div></div></div>
    <div class="col-lg-4"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title fw-semibold">Approved Payments</h5><h4 class="fw-bold"><?php echo $kpi_approved; ?></h4></div></div></div>
</div>

<div class="row">
    <div class="col-lg-8 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Monthly Revenue (Last 12 Months)</h5>
                <?php if (!empty($revenue_chart['labels'])): ?><div id="revenue-chart"></div><?php else: ?><div class="alert alert-info">No revenue data available.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Payment Status</h5>
                <?php if (array_sum($status_chart) > 0): ?><div id="status-chart"></div><?php else: ?><div class="alert alert-info">No payment data available.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-semibold mb-0">Recent Pending Payments</h5>
            <a href="manage_payments.php?status=pending" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead class="table-light"><tr><th>Student</th><th>Amount</th><th>Payment Date</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!empty($recent_pending)): foreach ($recent_pending as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                            <td>₦ <?php echo number_format($payment['amount_paid'], 2); ?></td>
                            <td><?php echo date("M d, Y", strtotime($payment['payment_date'])); ?></td>
                            <td><a href="manage_payments.php?payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-primary">Review</a></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center">No pending payments.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    <?php if (!empty($revenue_chart['labels'])): ?>
    var revenueOptions = { series: [{ name: 'Revenue', data: <?php echo json_encode($revenue_chart['data']); ?> }], chart: { type: 'area', height: 350, toolbar: { show: true }, zoom: { enabled: false } }, dataLabels: { enabled: false }, stroke: { curve: 'smooth' }, xaxis: { type: 'category', categories: <?php echo json_encode($revenue_chart['labels']); ?> }, tooltip: { theme: 'light', x: { format: 'MMM yyyy' }, y: { formatter: function(val) { return "₦ " + val.toFixed(2); } } }, };
    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();
    <?php endif; ?>

    // Status Chart
    <?php if (array_sum($status_chart) > 0): ?>
    var statusOptions = { series: <?php echo json_encode(array_values($status_chart)); ?>, chart: { type: 'donut', height: 350 }, labels: <?php echo json_encode(array_map('ucfirst', array_keys($status_chart))); ?>, colors: ['#ffc107', '#28a745', '#dc3545'], legend: { position: 'bottom' }, responsive: [{ breakpoint: 480, options: { chart: { width: 200 }, legend: { position: 'bottom' } } }] };
    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>