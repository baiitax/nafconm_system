<?php
/**
 * NAFCONM - Teacher Analytics & Reports
 * Features: Fixed Schema Crash, JSON Enrollment Counters, ApexCharts, Print UI
 */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once '../config/db.php';

// Security Check
$user_type = strtolower($_SESSION['user_type'] ?? '');
if (!isset($_SESSION["loggedin"]) || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// 1. Fetch Active Session
$current_session_id = 1;
$current_session_name = "2025/2026 Session";
$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
if ($sess_query && $sess_query->num_rows > 0) {
    $active_session = $sess_query->fetch_assoc();
    $current_session_id = (int)$active_session['id'];
    $current_session_name = $active_session['name'];
}

// 2. Fetch Teacher ID Safely
$teacher_id = 0;
$stmt_t = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
if ($stmt_t) {
    $stmt_t->bind_param("i", $user_id);
    $stmt_t->execute();
    $res_t = $stmt_t->get_result();
    if ($res_t->num_rows > 0) {
        $teacher_id = (int)$res_t->fetch_assoc()['id'];
    }
    $stmt_t->close();
}

if (!$teacher_id) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Error</h2><p>Faculty profile could not be located.</p></div>");
}

// --- 3. CALCULATE KPIs SAFELY ---
$kpi_courses = $kpi_students = $kpi_assignments = $kpi_avg_grade = 0;
$course_details = [];
$course_performance = ['labels' => [], 'data' => []];

// Fetch Assigned Courses for this Session
$sql_courses = "SELECT c.id, c.course_code, c.course_title FROM course_teachers ct JOIN courses c ON ct.course_id = c.id WHERE ct.teacher_id = ? AND ct.session_id = ?";
if ($stmt = $conn->prepare($sql_courses)) {
    $stmt->bind_param("ii", $teacher_id, $current_session_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $kpi_courses++;
        $cid = $row['id'];
        $ccode = $row['course_code'];
        
        // Count Students via JSON Array matching
        $enrolled = 0;
        $stmt_s = $conn->prepare("SELECT COUNT(*) FROM course_registrations WHERE session = ? AND status = 'approved' AND course_codes LIKE ?");
        $like_code = '%' . $ccode . '%';
        $stmt_s->bind_param("ss", $current_session_name, $like_code);
        $stmt_s->execute();
        $stmt_s->bind_result($enrolled);
        $stmt_s->fetch();
        $stmt_s->close();
        
        $kpi_students += $enrolled; // Aggregating total student-course mappings
        
        // Count Assignments & Average Grade for this specific course
        $assign_count = 0;
        $course_avg = 0;
        $stmt_a = $conn->prepare("SELECT COUNT(DISTINCT a.id), AVG(CAST(s.grade AS DECIMAL)) FROM assignments a LEFT JOIN submissions s ON a.id = s.assignment_id WHERE a.course_id = ? AND a.teacher_id = ? AND s.grade IS NOT NULL AND s.grade != ''");
        $stmt_a->bind_param("ii", $cid, $teacher_id);
        $stmt_a->execute();
        $stmt_a->bind_result($assign_count, $course_avg);
        $stmt_a->fetch();
        $stmt_a->close();
        
        $kpi_assignments += $assign_count;
        $course_avg = round((float)$course_avg, 2);

        // Populate Table Data
        $course_details[] = [
            'course_code' => $ccode,
            'course_title' => $row['course_title'],
            'student_count' => $enrolled,
            'assignment_count' => $assign_count,
            'avg_grade' => $course_avg
        ];

        // Populate Chart Data
        if ($course_avg > 0) {
            $course_performance['labels'][] = $ccode;
            $course_performance['data'][] = $course_avg;
        }
    }
    $stmt->close();
}

// Calculate Overall Average Grade
$stmt_ov = $conn->prepare("SELECT AVG(CAST(s.grade AS DECIMAL)) FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.teacher_id = ? AND s.grade IS NOT NULL AND s.grade != ''");
$stmt_ov->bind_param("i", $teacher_id);
$stmt_ov->execute();
$stmt_ov->bind_result($kpi_avg_grade);
$stmt_ov->fetch();
$stmt_ov->close();
$kpi_avg_grade = round((float)$kpi_avg_grade, 2);

// --- 4. TOP 20 STUDENTS ALGORITHM ---
$top_students = [];
$sql_top_students = "
    SELECT st.full_name, st.student_id_number, AVG(CAST(su.grade AS DECIMAL)) as avg_student_perc
    FROM submissions su
    JOIN assignments a ON su.assignment_id = a.id
    JOIN students st ON su.student_id = st.id
    WHERE a.teacher_id = ? AND su.grade IS NOT NULL AND su.grade != ''
    GROUP BY st.id
    ORDER BY avg_student_perc DESC
    LIMIT 20
";
if ($stmt = $conn->prepare($sql_top_students)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $top_students[] = $row;
    }
    $stmt->close();
}

$page_title = "Performance Analytics";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 16px; border: none; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    
    .print-btn { background: #1e293b; color: #fff; border-radius: 50px; font-weight: bold; padding: 10px 25px; transition: 0.3s; }
    .print-btn:hover { background: #0f172a; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); color: #fff; }

    /* Print Architecture */
    @media print {
        body { background: #fff !important; color: #000; }
        .no-print, .sidebar, header, .topbar { display: none !important; }
        .container-fluid, .page-wrapper, .body-wrapper { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        .print-header { display: block !important; text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .print-header h2 { font-weight: 900; text-transform: uppercase; margin: 0; }
        
        .card { border: 1px solid #000 !important; box-shadow: none !important; margin-bottom: 20px !important; page-break-inside: avoid; }
        .card-header { background: #f1f1f1 !important; color: #000 !important; border-bottom: 1px solid #000 !important; }
        
        .table th, .table td { border: 1px solid #000 !important; color: #000 !important; }
        .table th { background: #f1f1f1 !important; }
        
        /* Flatten KPI widgets for print */
        .stat-card { border: 1px solid #000 !important; background: transparent !important; }
        .stat-card * { color: #000 !important; }
    }
</style>

<div class="container-fluid py-4">

    <!-- Print Header (Hidden on Screen) -->
    <div class="print-header d-none">
        <img src="../assets/images/logos/favicon.png" alt="Logo" width="60" style="margin-bottom: 10px;">
        <h2>Nafisatu College of Nursing</h2>
        <p style="font-weight: bold; font-size: 14px; margin: 5px 0;">Official Faculty Analytics Report</p>
        <p style="font-size: 12px;">Instructor ID: <?= htmlspecialchars($_SESSION['username']) ?> | Session: <?= htmlspecialchars($current_session_name) ?></p>
    </div>

    <!-- Screen Header -->
    <div class="d-md-flex align-items-center justify-content-between mb-4 no-print">
        <div>
            <h3 class="fw-black text-dark mb-1">Performance Analytics</h3>
            <p class="text-muted small mb-0">Overview of student grading, class averages, and course metrics.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button onclick="window.print();" class="btn print-btn border-0 shadow-sm"><i class="ti ti-printer me-2"></i>Print Official Report</button>
        </div>
    </div>

    <!-- 1. KPI WIDGETS -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card bg-primary-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-primary fw-bold text-uppercase small mb-1">Allocated Courses</p>
                        <h3 class="fw-black text-primary mb-0"><?= number_format($kpi_courses) ?></h3>
                    </div>
                    <div class="bg-white rounded-circle p-3 shadow-sm no-print"><i class="ti ti-books fs-3 text-primary"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card bg-success-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success fw-bold text-uppercase small mb-1">Total Enrollments</p>
                        <h3 class="fw-black text-success mb-0"><?= number_format($kpi_students) ?></h3>
                    </div>
                    <div class="bg-white rounded-circle p-3 shadow-sm no-print"><i class="ti ti-users fs-3 text-success"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card bg-warning-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning fw-bold text-uppercase small mb-1">Total Assignments</p>
                        <h3 class="fw-black text-warning mb-0"><?= number_format($kpi_assignments) ?></h3>
                    </div>
                    <div class="bg-white rounded-circle p-3 shadow-sm no-print"><i class="ti ti-checklist fs-3 text-warning"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card bg-info-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info fw-bold text-uppercase small mb-1">Overall Class Avg.</p>
                        <h3 class="fw-black text-info mb-0"><?= $kpi_avg_grade ?>%</h3>
                    </div>
                    <div class="bg-white rounded-circle p-3 shadow-sm no-print"><i class="ti ti-chart-bar fs-3 text-info"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. COURSE SUMMARY TABLE -->
    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-header bg-white p-4 border-bottom">
            <h5 class="fw-bold text-dark mb-0"><i class="ti ti-list-details text-primary me-2 no-print"></i>Course Analytics Summary</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($course_details)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3 fw-bold">Course Code</th>
                                <th class="py-3 fw-bold">Course Title</th>
                                <th class="py-3 fw-bold text-center">Enrollments</th>
                                <th class="py-3 fw-bold text-center">Tasks</th>
                                <th class="text-end pe-4 py-3 fw-bold">Class Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_details as $c): ?>
                                <tr>
                                    <td class="ps-4 py-3 font-monospace fw-bold text-dark"><?= htmlspecialchars($c['course_code']) ?></td>
                                    <td class="fw-bold text-muted"><?= htmlspecialchars($c['course_title']) ?></td>
                                    <td class="text-center fw-bold"><?= $c['student_count'] ?></td>
                                    <td class="text-center"><?= $c['assignment_count'] ?></td>
                                    <td class="text-end pe-4">
                                        <span class="badge <?= $c['avg_grade'] >= 50 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> border px-3 py-1 fs-6">
                                            <?= number_format($c['avg_grade'], 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="ti ti-folder-off fs-1 d-block mb-2 opacity-50 no-print"></i>
                    <p class="mb-0 fw-bold">No courses assigned to calculate metrics.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- 3. TOP 20 STUDENTS (LEADERBOARD) -->
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold text-dark mb-0"><i class="ti ti-award text-warning me-2 no-print"></i>Top 20 Performing Students</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($top_students)): ?>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small text-uppercase position-sticky top-0" style="z-index: 10;">
                                    <tr>
                                        <th class="ps-4 py-3 fw-bold">Rank</th>
                                        <th class="py-3 fw-bold">Student Name</th>
                                        <th class="py-3 fw-bold">Matric No.</th>
                                        <th class="text-end pe-4 py-3 fw-bold">Score Avg.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $index => $student): ?>
                                        <tr>
                                            <td class="ps-4 py-3 fw-black text-muted">#<?= $index + 1 ?></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars(strtoupper($student['full_name'])) ?></td>
                                            <td class="font-monospace text-primary fw-bold"><?= htmlspecialchars($student['student_id_number']) ?></td>
                                            <td class="text-end pe-4 fw-black <?= $student['avg_student_perc'] >= 70 ? 'text-success' : 'text-dark' ?>">
                                                <?= number_format($student['avg_student_perc'], 1) ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50 no-print"></i>
                            <p class="mb-0 fw-bold">No grading data available to generate leaderboard.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. COURSE PERFORMANCE CHART -->
        <div class="col-xl-5">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold text-dark mb-0"><i class="ti ti-chart-bar text-info me-2 no-print"></i>Course Performance Chart</h5>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                    <?php if (!empty($course_performance['labels'])): ?>
                        <div id="course-performance-chart" class="w-100 no-print"></div>
                        <div class="d-none d-print-block w-100 text-center border p-4 text-muted">
                            <em>[Chart suppressed for physical printing. Refer to the analytics summary table above.]</em>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted w-100">
                            <i class="ti ti-chart-pie-off fs-1 d-block mb-2 opacity-50 no-print"></i>
                            <p class="mb-0 fw-bold">Insufficient grading data to map chart.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- APEXCHARTS INTEGRATION -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($course_performance['labels'])): ?>
    var options = {
        series: [{ 
            name: 'Average Grade', 
            data: <?= json_encode($course_performance['data']); ?> 
        }],
        chart: { 
            type: 'bar', 
            height: 350, 
            fontFamily: 'inherit',
            toolbar: { show: false } 
        },
        colors: ['#5D87FF'],
        plotOptions: { 
            bar: { horizontal: false, columnWidth: '40%', borderRadius: 6 } 
        },
        dataLabels: { 
            enabled: true, 
            formatter: function (val) { return val + "%"; },
            style: { fontWeight: 900 }
        },
        xaxis: { 
            categories: <?= json_encode($course_performance['labels']); ?>,
            labels: { style: { fontWeight: 600, cssClass: 'font-monospace' } }
        },
        yaxis: { 
            title: { text: 'Class Average (%)' }, 
            min: 0, 
            max: 100 
        },
        tooltip: { 
            theme: 'light',
            y: { formatter: function (val) { return val + "%" } } 
        }
    };
    var chart = new ApexCharts(document.querySelector("#course-performance-chart"), options);
    chart.render();
    <?php endif; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>