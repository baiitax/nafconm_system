<?php
/**
 * NAFCONM - Student Academic Analytics Report
 * Features: Visual KPIs, Horizontal Performance Benchmarking, and Detailed Logs
 */
$page_title = 'Academic Reports';
require_once 'includes/header.php';

$student_id = (int)$_SESSION['student_id'];
$active_session_id = null;
$active_session_name = "No Active Session";

// Get Active Session Details
$res_session = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
if ($res_session->num_rows > 0) {
    $session_data = $res_session->fetch_assoc();
    $active_session_id = $session_data['id'];
    $active_session_name = $session_data['name'];
}

// 1. Data Aggregation (KPIs)
$kpi = ['courses' => 0, 'credits' => 0, 'avg' => 0];

if ($active_session_id) {
    $stmt = $conn->prepare("SELECT COUNT(c.id), SUM(c.credit_units) FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? AND e.session_id = ?");
    $stmt->bind_param("ii", $student_id, $active_session_id);
    $stmt->execute();
    $stmt->bind_result($kpi['courses'], $kpi['credits']);
    $stmt->fetch();
    $stmt->close();
}

// All-time Average
$stmt = $conn->prepare("SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE sc.student_id = ? AND a.max_marks > 0");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($kpi['avg']);
$stmt->fetch();
$stmt->close();

// 2. Chart Data: Analytical breakdown per course
$course_performance = ['labels' => [], 'data' => [], 'colors' => []];
$sql_course_perf = "SELECT c.course_code, AVG(sc.score / a.max_marks * 100) as avg_perc 
                    FROM courses c 
                    JOIN assessments a ON c.id = a.course_id 
                    JOIN assessment_scores sc ON a.id = sc.assessment_id 
                    WHERE sc.student_id = ? AND a.max_marks > 0 
                    GROUP BY c.id";
$stmt = $conn->prepare($sql_course_perf);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $val = round($row['avg_perc'], 1);
    $course_performance['labels'][] = $row['course_code'];
    $course_performance['data'][] = $val;
    // Dynamic Bar Colors based on performance
    if ($val >= 70) $course_performance['colors'][] = '#13DEB9'; // Success
    elseif ($val >= 45) $course_performance['colors'][] = '#5D87FF'; // Primary
    else $course_performance['colors'][] = '#FA896B'; // Danger
}
$stmt->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Performance Analytics</h3>
            <p class="text-muted mb-0">Visual summary of your academic standing for <?= $active_session_name ?>.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 rounded-circle p-3 me-3">
                        <i class="ti ti-books fs-1 text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-black text-white mb-0"><?= $kpi['courses'] ?></h4>
                        <span class="small fw-bold text-white-50 text-uppercase">Active Courses</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-success text-white">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 rounded-circle p-3 me-3">
                        <i class="ti ti-chart-arrows fs-1 text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-black text-white mb-0"><?= $kpi['credits'] ?? 0 ?></h4>
                        <span class="small fw-bold text-white-50 text-uppercase">Registered Units</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-info text-white">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 rounded-circle p-3 me-3">
                        <i class="ti ti-award fs-1 text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-black text-white mb-0"><?= number_format($kpi['avg'], 1) ?>%</h4>
                        <span class="small fw-bold text-white-50 text-uppercase">Overall Performance</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 w-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Course Performance Index</h5>
                    <?php if (!empty($course_performance['labels'])): ?>
                        <div id="course-performance-chart"></div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="ti ti-chart-bar-off fs-1 text-muted opacity-25"></i>
                            <p class="text-muted mt-2">Insufficient data to generate trends.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 w-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold mb-0">Recent Assessment Log</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="ps-4 py-3">Course</th>
                                    <th>Task</th>
                                    <th class="text-end pe-4">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql_details = "SELECT c.course_code, a.name as a_name, a.max_marks, sc.score 
                                                FROM assessment_scores sc 
                                                JOIN assessments a ON sc.assessment_id = a.id 
                                                JOIN courses c ON a.course_id = c.id 
                                                WHERE sc.student_id = ? AND a.session_id = ?
                                                ORDER BY sc.recorded_at DESC LIMIT 15";
                                $stmt = $conn->prepare($sql_details);
                                $stmt->bind_param("ii", $student_id, $active_session_id);
                                $stmt->execute();
                                $res_details = $stmt->get_result();
                                if ($res_details->num_rows > 0):
                                    while ($r = $res_details->fetch_assoc()):
                                        $p = ($r['score'] / $r['max_marks']) * 100;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-bold text-dark small"><?= $r['course_code'] ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?= htmlspecialchars($r['a_name']) ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="fw-bold <?= ($p >= 50) ? 'text-success' : 'text-danger' ?>">
                                            <?= $r['score'] ?>/<?= $r['max_marks'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="3" class="text-center py-5 text-muted">No assessment records found.</td></tr>
                                <?php endif; $stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($course_performance['labels'])): ?>
    var options = {
        series: [{ name: 'Avg Grade', data: <?= json_encode($course_performance['data']); ?> }],
        chart: { type: 'bar', height: 350, toolbar: { show: false } },
        colors: <?= json_encode($course_performance['colors']); ?>,
        plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true, barHeight: '45%' } },
        dataLabels: { enabled: true, formatter: val => val + "%", style: { fontSize: '12px', fontWeight: 900 } },
        xaxis: { categories: <?= json_encode($course_performance['labels']); ?>, min: 0, max: 100 },
        grid: { borderColor: '#f1f1f1' },
        legend: { show: false },
        tooltip: { theme: 'dark', y: { formatter: val => val + "%" } }
    };
    new ApexCharts(document.querySelector("#course-performance-chart"), options).render();
    <?php endif; ?>
});
</script>

<style>
    .fw-black { font-weight: 900 !important; }
    .bg-opacity-20 { background-color: rgba(255, 255, 255, 0.2) !important; }
</style>

<?php require_once 'includes/footer.php'; ?>