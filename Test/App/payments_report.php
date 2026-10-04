<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Payment Reports';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// --- Analytics Queries ---

// Overall KPIs
$kpi_total_revenue = $conn->query("SELECT SUM(amount_paid) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0;
$kpi_total_transactions = $conn->query("SELECT COUNT(id) FROM payments")->fetch_row()[0] ?? 0;
$kpi_approved_count = $conn->query("SELECT COUNT(id) FROM payments WHERE status = 'approved'")->fetch_row()[0] ?? 0;
$kpi_approval_rate = ($kpi_total_transactions > 0) ? ($kpi_approved_count / $kpi_total_transactions) * 100 : 0;

// Revenue by Payment Method
$revenue_by_method = [];
$sql_method = "SELECT payment_method, SUM(amount_paid) as total
               FROM payments
               WHERE status = 'approved' AND payment_method IS NOT NULL
               GROUP BY payment_method";
$result_method = $conn->query($sql_method);
while ($row = $result_method->fetch_assoc()) {
    $revenue_by_method['labels'][] = $row['payment_method'];
    $revenue_by_method['data'][] = (float)$row['total'];
}

// Revenue by Department
$revenue_by_dept = [];
$sql_dept = "SELECT d.name, SUM(p.amount_paid) as total
             FROM payments p
             JOIN students s ON p.student_id = s.id
             JOIN departments d ON s.department_id = d.id
             WHERE p.status = 'approved'
             GROUP BY d.name
             ORDER BY total DESC";
$result_dept = $conn->query($sql_dept);
while ($row = $result_dept->fetch_assoc()) {
    $revenue_by_dept['labels'][] = $row['name'];
    $revenue_by_dept['data'][] = (float)$row['total'];
}

// Fees by Session
$sql_fees = "SELECT s.name as session_name, f.fee_name, f.amount
             FROM fees f
             JOIN sessions s ON f.session_id = s.id
             ORDER BY s.name, f.fee_name";
$result_fees = $conn->query($sql_fees);
$sessions_data = [];
while ($row = $result_fees->fetch_assoc()) {
    if (!isset($sessions_data[$row['session_name']])) {
        $sessions_data[$row['session_name']] = [];
    }
    $sessions_data[$row['session_name']][$row['fee_name']] = (float)$row['amount'];
}

// Prepare data for stacked bar chart
$fee_chart_labels = array_keys($sessions_data); // Sessions are categories
$fee_names = [];
foreach ($sessions_data as $session => $fees) {
    foreach (array_keys($fees) as $fee_name) {
        if (!in_array($fee_name, $fee_names)) {
            $fee_names[] = $fee_name;
        }
    }
}
$fee_chart_series = [];
foreach ($fee_names as $fee_name) {
    $data = [];
    foreach ($fee_chart_labels as $session_name) {
        $data[] = $sessions_data[$session_name][$fee_name] ?? 0;
    }
    $fee_chart_series[] = ['name' => $fee_name, 'data' => $data];
}

// Full transaction list for DataTable
$sql_all = "SELECT p.*, s.full_name as student_name, s.student_id_number, u.username as reviewer_name
            FROM payments p
            JOIN students s ON p.student_id = s.id
            LEFT JOIN users u ON p.reviewed_by_user_id = u.id
            ORDER BY p.created_at DESC";
$all_payments = $conn->query($sql_all)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<?php
require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Payment Reports</h5>

    <div class="row">
        <div class="col-md-4"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title">Total Revenue</h5><h4 class="fw-bold">₦ <?php echo number_format($kpi_total_revenue, 2); ?></h4></div></div></div>
        <div class="col-md-4"><div class="card bg-light-info"><div class="card-body"><h5 class="card-title">Total Transactions</h5><h4 class="fw-bold"><?php echo number_format($kpi_total_transactions); ?></h4></div></div></div>
        <div class="col-md-4"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title">Approval Rate</h5><h4 class="fw-bold"><?php echo number_format($kpi_approval_rate, 1); ?>%</h4></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-6 d-flex align-items-stretch">
            <div class="card w-100">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Revenue by Payment Method</h5>
                    <?php if (!empty($revenue_by_method['labels'])): ?><div id="method-chart"></div><?php else: ?><div class="alert alert-info">No data available.</div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6 d-flex align-items-stretch">
            <div class="card w-100">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Revenue by Department</h5>
                    <?php if (!empty($revenue_by_dept['labels'])): ?><div id="dept-chart"></div><?php else: ?><div class="alert alert-info">No data available.</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Fee Composition by Session</h5>
            <?php if (!empty($fee_chart_labels)): ?>
                <div id="fees-by-session-chart"></div>
            <?php else: ?><div class="alert alert-info">No fee data available to display chart.</div><?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">All Transactions</h5>
            <div class="table-responsive">
                <table id="allPaymentsTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Student</th><th>Amount (₦)</th><th>Date</th><th>Status</th><th>Method</th><th>Reviewed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_payments as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                                <td><?php echo number_format($p['amount_paid'], 2); ?></td>
                                <td><?php echo date("Y-m-d", strtotime($p['payment_date'])); ?></td>
                                <td><?php echo ucfirst($p['status']); ?></td>
                                <td><?php echo htmlspecialchars($p['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($p['reviewer_name'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue by Method Chart
    <?php if (!empty($revenue_by_method['labels'])): ?>
    var methodOptions = {
        series: <?php echo json_encode($revenue_by_method['data']); ?>,
        chart: { type: 'pie', height: 350 },
        labels: <?php echo json_encode($revenue_by_method['labels']); ?>,
        legend: { position: 'bottom' }
    };
    var methodChart = new ApexCharts(document.querySelector("#method-chart"), methodOptions);
    methodChart.render();
    <?php endif; ?>

    // Revenue by Department Chart
    <?php if (!empty($revenue_by_dept['labels'])): ?>
    var deptOptions = {
        series: [{ name: 'Revenue', data: <?php echo json_encode($revenue_by_dept['data']); ?> }],
        chart: { type: 'bar', height: 350 },
        plotOptions: { bar: { horizontal: true } },
        dataLabels: { enabled: false },
        xaxis: { categories: <?php echo json_encode($revenue_by_dept['labels']); ?> },
        tooltip: { y: { formatter: function(val) { return "₦ " + val.toFixed(2); } } }
    };
    var deptChart = new ApexCharts(document.querySelector("#dept-chart"), deptOptions);
    deptChart.render();
    <?php endif; ?>

    // Fees by Session Chart
    <?php if (!empty($fee_chart_labels)): ?>
    var feesBySessionOptions = {
        series: <?php echo json_encode($fee_chart_series); ?>,
        chart: {
            type: 'bar',
            height: 350,
            stacked: true,
        },
        plotOptions: {
            bar: {
                horizontal: true,
            },
        },
        stroke: {
            width: 1,
            colors: ['#fff']
        },
        xaxis: {
            categories: <?php echo json_encode($fee_chart_labels); ?>,
            labels: {
                formatter: function (val) {
                    return "₦" + val.toFixed(0);
                }
            }
        },
        yaxis: { title: { text: undefined } },
        tooltip: { y: { formatter: function (val) { return "₦" + val.toFixed(2) } } },
        fill: { opacity: 1 },
        legend: { position: 'top', horizontalAlign: 'left', offsetX: 40 }
    };
    var feesBySessionChart = new ApexCharts(document.querySelector("#fees-by-session-chart"), feesBySessionOptions);
    feesBySessionChart.render();
    <?php endif; ?>

    // DataTable for all payments
    $('#allPaymentsTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf']
    });
});
</script>
