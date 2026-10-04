<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$teacher_id = null;
// Get teacher's full name
$teacher_full_name = "";
$sql_teacher = "SELECT id, full_name FROM teachers WHERE user_id = ?";
if ($stmt_teacher = $conn->prepare($sql_teacher)) {
    $stmt_teacher->bind_param("i", $user_id);
    if ($stmt_teacher->execute()) {
        $stmt_teacher->store_result();
        if ($stmt_teacher->num_rows == 1) {
            $stmt_teacher->bind_result($t_id, $t_full_name);
            if ($stmt_teacher->fetch()) {
                $teacher_id = $t_id;
                $teacher_full_name = $t_full_name;
            }
        } else {
            die("Error: Teacher profile not found.");
        }
    }
    $stmt_teacher->close();
}

// --- Fetch Dashboard Data ---
$course_count = 0;
$student_count = 0;
$assignment_count = 0;
$ungraded_count = 0;
$chart_data = ['labels' => [], 'series' => []];
$assessment_performance_data = ['labels' => [], 'series' => []];

if ($teacher_id) {
    // 1. Course Count
    $sql_courses = "SELECT COUNT(id) AS course_count FROM courses WHERE teacher_id = ?";
    if ($stmt = $conn->prepare($sql_courses)) {
        $stmt->bind_param("i", $teacher_id);
        if ($stmt->execute()) {
            $stmt->bind_result($c_count);
            if ($stmt->fetch()) {
                $course_count = $c_count;
            }
        }
        $stmt->close();
    }

    // 2. Total Student Count
    $sql_students = "SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = ?";
    if ($stmt = $conn->prepare($sql_students)) {
        $stmt->bind_param("i", $teacher_id);
        if ($stmt->execute()) {
            $stmt->bind_result($s_count);
            if ($stmt->fetch()) {
                $student_count = $s_count;
            }
        }
        $stmt->close();
    }

    // 3. Assignment Count
    $sql_assignments = "SELECT COUNT(a.id) FROM assignments a JOIN courses c ON a.course_id = c.id WHERE c.teacher_id = ?";
    if ($stmt = $conn->prepare($sql_assignments)) {
        $stmt->bind_param("i", $teacher_id);
        if ($stmt->execute()) {
            $stmt->bind_result($a_count);
            if ($stmt->fetch()) {
                $assignment_count = $a_count;
            }
        }
        $stmt->close();
    }

    // 4. Ungraded Submissions Count
    $sql_ungraded = "SELECT COUNT(s.id) as ungraded_count FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE a.teacher_id = ? AND s.grade IS NULL";
    if ($stmt_ug = $conn->prepare($sql_ungraded)) {
        $stmt_ug->bind_param("i", $teacher_id);
        if ($stmt_ug->execute()) {
            $stmt_ug->bind_result($ug_count);
            if ($stmt_ug->fetch()) {
                $ungraded_count = $ug_count;
            }
        }
        $stmt_ug->close();
    }

    // 5. Data for Assessment Performance Chart
    $sql_assessment_perf = "SELECT
                                a.name,
                                c.course_code,
                                AVG(sc.score) as average_score,
                                a.max_marks
                            FROM assessments a
                            JOIN assessment_scores sc ON a.id = sc.assessment_id
                            JOIN courses c ON a.course_id = c.id
                            WHERE a.created_by_teacher_id = ? AND sc.score IS NOT NULL
                            GROUP BY a.id, a.name, c.course_code, a.max_marks
                            ORDER BY a.created_at DESC
                            LIMIT 7";
    if ($stmt_ap = $conn->prepare($sql_assessment_perf)) {
        $stmt_ap->bind_param("i", $teacher_id);
        if ($stmt_ap->execute()) {
            $result_ap = $stmt_ap->get_result();
            $avg_scores = [];
            while ($row_ap = $result_ap->fetch_assoc()) {
                $assessment_performance_data['labels'][] = $row_ap['name'] . ' (' . $row_ap['course_code'] . ')';
                $percentage = ($row_ap['max_marks'] > 0) ? ($row_ap['average_score'] / $row_ap['max_marks']) * 100 : 0;
                $avg_scores[] = round($percentage, 2);
            }
            $assessment_performance_data['series'][] = [
                'name' => 'Average Performance (%)',
                'data' => array_reverse($avg_scores)
            ];
            $assessment_performance_data['labels'] = array_reverse($assessment_performance_data['labels']);
        }
        $stmt_ap->close();
    }

    // 4. Data for Chart
    $sql_chart = "SELECT c.course_name, COUNT(e.student_id) as student_count FROM courses c LEFT JOIN enrollments e ON c.id = e.course_id WHERE c.teacher_id = ? GROUP BY c.id, c.course_name ORDER BY c.course_name";
    if ($stmt = $conn->prepare($sql_chart)) {
        $stmt->bind_param("i", $teacher_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $chart_data['labels'][] = $row['course_name'];
                $chart_data['series'][] = $row['student_count'];
            }
        }
        $stmt->close();
    }
}

// 5. Recent Announcements
$recent_announcements = [];
$sql_announcements = "SELECT an.title, an.content, an.posted_at, u.username as poster_name
                      FROM announcements an
                      JOIN users u ON an.posted_by = u.id
                      ORDER BY an.posted_at DESC
                      LIMIT 3";
if ($result = $conn->query($sql_announcements)) {
    while ($row = $result->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="body-wrapper">
    <div class="container-fluid">
        <h5 class="card-title fw-semibold mb-4">Welcome, <?php echo htmlspecialchars($teacher_full_name); ?>!</h5>
        
        <!-- KPIs -->
        <div class="row">
            <div class="col-lg-3"><div class="card bg-light-primary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Assigned Courses</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $course_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-primary rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-book fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-lg-3"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Total Students</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $student_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-success rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-users fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-lg-3"><div class="card bg-light-warning overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Assignments</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $assignment_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-warning rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-file-text fs-6"></i></div></div></div></div></div></div></div>
            <div class="col-lg-3"><div class="card bg-light-danger overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Ungraded</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $ungraded_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-danger rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-file-check fs-6"></i></div></div></div></div></div></div></div>
        </div>

        <!-- Chart and Announcements -->
        <div class="row">
            <div class="col-lg-6 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <div class="d-sm-flex d-block align-items-center justify-content-between mb-9">
                            <div class="mb-3 mb-sm-0">
                                <h5 class="card-title fw-semibold">Student Distribution</h5>
                                <p class="card-subtitle">Across your courses</p>
                            </div>
                        </div>
                        <div id="student-distribution-chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold">Recent Assessment Performance</h5>
                        <p class="card-subtitle">Average class scores in %</p>
                        <div id="assessment-performance-chart"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 d-flex align-items-stretch">
                <div class="card w-100">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4">Recent Announcements</h5>
                        <?php if (!empty($recent_announcements)): foreach ($recent_announcements as $announcement): ?>
                            <div class="mb-4">
                                <h6 class="fw-semibold"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 100) . (strlen($announcement['content']) > 100 ? '...' : ''))); ?></p>
                                <small class="text-muted">Posted by <?php echo htmlspecialchars($announcement['poster_name']); ?> on <?php echo htmlspecialchars(date("M d, Y", strtotime($announcement['posted_at']))); ?></small>
                            </div>
                            <hr>
                        <?php endforeach; else: ?>
                            <div class="alert alert-info" role="alert">No recent announcements.</div>
                        <?php endif; ?>
                        <a href="announcements.php" class="btn btn-primary">View All Announcements</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (!empty($chart_data['labels']) && !empty($chart_data['series']) && max($chart_data['series']) > 0): ?>
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

    var chart = new ApexCharts(document.querySelector("#student-distribution-chart"), options);
    chart.render();
    <?php else: ?>
    document.querySelector("#student-distribution-chart").innerHTML = '<div class="alert alert-info">No student data to display for your courses.</div>';
    <?php endif; ?>

    // Assessment Performance Chart
    <?php if (!empty($assessment_performance_data['labels'])): ?>
    var assessmentPerformanceOptions = {
        series: <?php echo json_encode($assessment_performance_data['series']); ?>,
        chart: { type: 'bar', height: 350, toolbar: { show: true }, foreColor: '#adb0bb', fontFamily: 'inherit' },
        colors: ["#5D87FF"],
        plotOptions: { bar: { horizontal: false, columnWidth: '35%', borderRadius: 4 } },
        dataLabels: {
            enabled: true,
            formatter: function (val) { return val.toFixed(0) + "%"; },
            style: {
                colors: ["#fff"]
            }
        },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: {
            categories: <?php echo json_encode($assessment_performance_data['labels']); ?>,
            labels: {
                style: { cssClass: "grey--text lighten-2--text fill-color" },
            },
        },
        yaxis: {
            title: { text: 'Average Score (%)' },
            min: 0,
            max: 100,
            labels: {
                style: { cssClass: "grey--text lighten-2--text fill-color" },
            },
        },
        grid: {
            borderColor: "rgba(0,0,0,0.1)",
            strokeDashArray: 3,
        },
        fill: { opacity: 1 },
        tooltip: { theme: "light", y: { formatter: function (val) { return val + " %" } } }
    };

    var assessmentChart = new ApexCharts(document.querySelector("#assessment-performance-chart"), assessmentPerformanceOptions);
    assessmentChart.render();
    <?php else: ?>
    document.querySelector("#assessment-performance-chart").innerHTML = '<div class="alert alert-info">No assessment performance data to display yet.</div>';
    <?php endif; ?>
});
</script>
<?php
include 'includes/footer.php';
?>