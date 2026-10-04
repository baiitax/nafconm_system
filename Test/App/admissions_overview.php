<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Admissions Overview';
require_once 'includes/header.php';

// KPIs
$kpi_total = $conn->query("SELECT COUNT(id) FROM admission_applications")->fetch_row()[0] ?? 0;
$kpi_pending = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$kpi_accepted = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'accepted'")->fetch_row()[0] ?? 0;
$kpi_under_review = $conn->query("SELECT COUNT(id) FROM admission_applications WHERE status = 'under_review'")->fetch_row()[0] ?? 0;

// Chart Data: Applications by Program
$program_chart = ['labels' => [], 'data' => []];
$sql_program_chart = "SELECT program_of_interest, COUNT(id) as app_count 
                     FROM admission_applications 
                     WHERE program_of_interest IS NOT NULL AND program_of_interest != ''
                     GROUP BY program_of_interest 
                     ORDER BY app_count DESC 
                     LIMIT 10";
$result_program = $conn->query($sql_program_chart);
while ($row = $result_program->fetch_assoc()) {
    $program_chart['labels'][] = $row['program_of_interest'];
    $program_chart['data'][] = (int)$row['app_count'];
}

// Chart Data: Application Status Distribution
$status_chart_data = $conn->query("SELECT status, COUNT(id) as count FROM admission_applications GROUP BY status");
$status_chart = ['labels' => [], 'data' => []];
while($row = $status_chart_data->fetch_assoc()){
    $status_chart['labels'][] = ucfirst(str_replace('_', ' ', $row['status']));
    $status_chart['data'][] = (int)$row['count'];
}

// Recent Applications
$recent_applications = [];
$sql_recent = "SELECT id, first_name, last_name, program_of_interest, application_date, status FROM admission_applications ORDER BY application_date DESC LIMIT 5";
$result_recent = $conn->query($sql_recent);
while ($row = $result_recent->fetch_assoc()) {
    $recent_applications[] = $row;
}

require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">All Admission Applications</h5>
    </div>

<div class="row">
    <!-- KPIs -->
    <div class="col-lg-3"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title fw-semibold">Total Applications</h5><h4 class="fw-bold"><?php echo $kpi_total; ?></h4></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title fw-semibold">Pending Review</h5><h4 class="fw-bold"><?php echo $kpi_pending; ?></h4></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-info"><div class="card-body"><h5 class="card-title fw-semibold">Under Review</h5><h4 class="fw-bold"><?php echo $kpi_under_review; ?></h4></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title fw-semibold">Accepted</h5><h4 class="fw-bold"><?php echo $kpi_accepted; ?></h4></div></div></div>
</div>

<div class="row">
    <div class="col-lg-7 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Applications by Program</h5>
                <?php if (!empty($program_chart['labels'])): ?><div id="program-chart"></div><?php else: ?><div class="alert alert-info">No application data by program.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Application Status</h5>
                <?php if (array_sum($status_chart['data']) > 0): ?><div id="status-chart"></div><?php else: ?><div class="alert alert-info">No application status data.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title fw-semibold mb-0">Recent Applications</h5>
            <a href="admission_applications.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover">
                <thead class="table-light"><tr><th>Applicant</th><th>Program</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (!empty($recent_applications)): foreach ($recent_applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['program_of_interest']); ?></td>
                            <td><?php echo date("M d, Y", strtotime($app['application_date'])); ?></td>
                            <td><span class="badge bg-light-warning rounded-3 fw-semibold"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $app['status']))); ?></span></td>
                            <td><a href="view_admission_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-info">View Details</a></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center">No recent applications.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($program_chart['labels'])): ?>
    var programOptions = { series: [{ name: 'Applications', data: <?php echo json_encode($program_chart['data']); ?> }], chart: { type: 'bar', height: 350 }, plotOptions: { bar: { horizontal: true } }, dataLabels: { enabled: false }, xaxis: { categories: <?php echo json_encode($program_chart['labels']); ?> } };
    var programChart = new ApexCharts(document.querySelector("#program-chart"), programOptions);
    programChart.render();
    <?php endif; ?>

    <?php if (array_sum($status_chart['data']) > 0): ?>
    var statusOptions = { series: <?php echo json_encode($status_chart['data']); ?>, chart: { type: 'donut', height: 350 }, labels: <?php echo json_encode($status_chart['labels']); ?>, colors: ['#5D87FF', '#49BEFF', '#13DEB9', '#FFAE1F', '#FA896B'], legend: { position: 'bottom' } };
    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>