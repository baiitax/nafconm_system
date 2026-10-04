<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Role-based access control - All logged-in admin users can see the dashboard
if (!is_logged_in() || !in_array($_SESSION['user_type'], ['superadmin', 'staff', 'teacher'])) {
    header('Location: /login.php');
    exit;
}

// --- Data fetching for dashboard widgets ---

// --- Data for Admissions Trend Chart ---
$chart_data = [
    'labels' => [],
    'data' => []
];

// 1. Get the active session
$active_session_stmt = $pdo->query("SELECT * FROM sessions WHERE is_active = 1 LIMIT 1");
$active_session = $active_session_stmt->fetch(PDO::FETCH_ASSOC);

$chart_title = "Admission Trends";
if ($active_session) {
    $chart_title = "Admission Trends for " . htmlspecialchars($active_session['name']);
    // 2. Get application counts grouped by day for the active session
    $chart_stmt = $pdo->prepare(
        "SELECT DATE(application_date) as app_date, COUNT(id) as count 
         FROM admission_applications 
         WHERE application_date BETWEEN ? AND ?
         GROUP BY DATE(application_date) 
         ORDER BY app_date ASC"
    );
    $chart_stmt->execute([$active_session['start_date'], $active_session['end_date']]);
    $admission_trends = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format data for ApexCharts, creating a full date range to fill gaps
    if (!empty($admission_trends)) {
        $period = new DatePeriod(
            new DateTime($active_session['start_date']),
            new DateInterval('P1D'),
            (new DateTime($active_session['end_date']))->modify('+1 day') // Include the end date
        );

        $applications_by_date = array_column($admission_trends, 'count', 'app_date');

        foreach ($period as $date) {
            $formatted_date = $date->format('Y-m-d');
            $chart_data['labels'][] = $date->format('M j'); // e.g., Aug 21
            $chart_data['data'][] = (int) ($applications_by_date[$formatted_date] ?? 0);
        }
    }
}

// Admissions data
$stmt_pending_admissions = $pdo->query("SELECT COUNT(*) FROM admission_applications WHERE status = 'pending'");
$pending_admissions_count = $stmt_pending_admissions->fetchColumn();

$stmt_review_admissions = $pdo->query("SELECT COUNT(*) FROM admission_applications WHERE status = 'under_review'");
$review_admissions_count = $stmt_review_admissions->fetchColumn();

// Other potential data points
$stmt_total_students = $pdo->query("SELECT COUNT(*) FROM students");
$total_students_count = $stmt_total_students->fetchColumn();

$stmt_total_courses = $pdo->query("SELECT COUNT(*) FROM courses");
$total_courses_count = $stmt_total_courses->fetchColumn();


$pageTitle = "Dashboard";
require_once 'header.php';
?>

<div class="page-wrapper">
    <div class="container-xl">
        <!-- Page title -->
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        Dashboard
                    </h2>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <div class="row row-deck row-cards">
                
                <!-- Admissions Widgets -->
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Pending Admissions</div>
                            </div>
                            <div class="h1 mb-3"><?php echo htmlspecialchars($pending_admissions_count); ?></div>
                            <div class="d-flex mb-2">
                                <div>New applications awaiting review</div>
                            </div>
                            <a href="/admin/admissions.php" class="btn btn-primary w-100">
                                Review Applications
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="subheader">Admissions Under Review</div>
                            </div>
                            <div class="h1 mb-3"><?php echo htmlspecialchars($review_admissions_count); ?></div>
                            <div class="d-flex mb-2">
                                <div>Applications being actively reviewed</div>
                            </div>
                            <a href="/admin/admissions.php" class="btn btn-primary w-100">
                                View All Admissions
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Add other dashboard widgets for students, courses, etc. here -->

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo $chart_title; ?></h3>
                        </div>
                        <div class="card-body">
                            <div id="admission-chart" style="min-height: 350px;"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- 
  Note: Ensure the ApexCharts library is loaded on this page. 
  This is typically done in a shared footer file (e.g., footer.php).
  <script src="/assets/libs/apexcharts/dist/apexcharts.min.js"></script> 
-->
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Check if there is data to display
    const chartData = <?php echo json_encode($chart_data['data']); ?>;
    const chartLabels = <?php echo json_encode($chart_data['labels']); ?>;

    if (chartData && chartData.length > 0) {
        var options = {
            series: [{
                name: 'New Applications',
                data: chartData
            }],
            chart: {
                type: 'area',
                height: 350,
                zoom: {
                    enabled: true
                },
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            xaxis: {
                type: 'category',
                categories: chartLabels
            },
            yaxis: {
                title: { text: 'Number of Applications' },
                min: 0,
                labels: { formatter: (val) => parseInt(val) }
            },
            tooltip: { x: { format: 'dd MMM' } }
        };

        var chart = new ApexCharts(document.querySelector("#admission-chart"), options);
        chart.render();
    } else {
        document.querySelector("#admission-chart").innerHTML = '<div class="text-center text-muted p-4">No admission data available for the current session to display a trend chart.</div>';
    }
});
</script>

<?php require_once 'footer.php'; ?>