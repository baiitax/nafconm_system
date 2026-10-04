<?php
$page_title = 'Student Dashboard';
require_once 'includes/header.php';
 
$user_id = $_SESSION['user_id'];
$student_id = null;

// Get student ID and full name from user ID
$sql_student = "SELECT id FROM students WHERE user_id = ?";
if ($stmt_student = $conn->prepare($sql_student)) {
    $stmt_student->bind_param("i", $user_id);
    if ($stmt_student->execute()) {
        $stmt_student->store_result();
        if ($stmt_student->num_rows == 1) {
            $stmt_student->bind_result($s_id);
            if ($stmt_student->fetch()) {
                $student_id = $s_id;
                $_SESSION['student_id'] = $s_id;
            }
        } else {
            die("Error: Student profile not found.");
        }
    }
    $stmt_student->close();
}

$student_full_name = $_SESSION['full_name'] ?? 'Student';

// --- Fetch Dashboard Data ---
$course_count = 0;
$submitted_assignments_count = 0;
$pending_assignments_count = 0;
$overall_average_grade = 0;
$performance_data = ['labels' => [], 'series' => []];

if ($student_id) {
    // 1. KPIs
    // Enrolled Courses
    $stmt = $conn->prepare("SELECT COUNT(id) FROM enrollments WHERE student_id = ? AND session_id = (SELECT id FROM sessions WHERE is_active = 1)");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($course_count);
    $stmt->fetch();
    $stmt->close();

    // Submitted Assignments
    $stmt = $conn->prepare("SELECT COUNT(id) FROM submissions WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($submitted_assignments_count);
    $stmt->fetch();
    $stmt->close();

    // Overall Average Grade
    $stmt = $conn->prepare("SELECT AVG(sc.score / a.max_marks * 100) FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE sc.student_id = ? AND sc.score IS NOT NULL AND a.max_marks > 0");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($overall_average_grade);
    $stmt->fetch();
    $stmt->close();
    $overall_average_grade = $overall_average_grade ?? 0;

    // Pending Assignments
    $stmt = $conn->prepare("SELECT COUNT(a.id) FROM assignments a JOIN enrollments e ON a.course_id = e.course_id LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = e.student_id WHERE e.student_id = ? AND a.due_date >= NOW() AND s.id IS NULL");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($pending_assignments_count);
    $stmt->fetch();
    $stmt->close();

    // 2. Performance Chart Data (Last 5 graded assessments)
    $sql_perf = "SELECT a.name, c.course_code, (sc.score / a.max_marks * 100) as percentage FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id JOIN courses c ON a.course_id = c.id WHERE sc.student_id = ? AND sc.score IS NOT NULL AND a.max_marks > 0 ORDER BY a.created_at DESC LIMIT 5";
    if ($stmt_perf = $conn->prepare($sql_perf)) {
        $stmt_perf->bind_param("i", $student_id);
        $stmt_perf->execute();
        $result = $stmt_perf->get_result();
        while($row = $result->fetch_assoc()) {
            $performance_data['labels'][] = $row['name'] . ' (' . $row['course_code'] . ')';
            $performance_data['series'][] = round($row['percentage'], 2);
        }
        $stmt_perf->close();
        $performance_data['labels'] = array_reverse($performance_data['labels']);
        $performance_data['series'] = array_reverse($performance_data['series']);
    }
}

// 2. Upcoming Assignments
$upcoming_assignments = [];
$sql_assignments = "SELECT a.title, a.due_date, c.course_name
                    FROM assignments a
                    JOIN courses c ON a.course_id = c.id
                    JOIN enrollments e ON c.id = e.course_id
                    WHERE e.student_id = ? AND a.due_date > NOW()
                    ORDER BY a.due_date ASC
                    LIMIT 5";
if ($stmt_assignments = $conn->prepare($sql_assignments)) {
    $stmt_assignments->bind_param("i", $student_id);
    if ($stmt_assignments->execute()) {
        $result = $stmt_assignments->get_result();
        while ($row = $result->fetch_assoc()) {
            $upcoming_assignments[] = $row;
        }
    }
    $stmt_assignments->close();
}

// 3. Recent Announcements
$recent_announcements = [];
$sql_announcements = "SELECT an.title, an.content, an.posted_at, u.username as poster_name
                      FROM announcements an
                      JOIN users u ON an.posted_by = u.id
                      ORDER BY an.posted_at DESC, an.id DESC
                      LIMIT 2";
if ($result = $conn->query($sql_announcements)) {
    while ($row = $result->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
}

$conn->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-lg-12">
        <h5 class="card-title fw-semibold mb-4">Welcome, <?php echo htmlspecialchars($student_full_name); ?>!</h5>
    </div>
</div>
<div class="row">
    <!-- KPIs -->
    <div class="col-lg-3"><div class="card bg-light-primary overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Enrolled Courses</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $course_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-primary rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-book fs-6"></i></div></div></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-success overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Submitted</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $submitted_assignments_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-success rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-file-check fs-6"></i></div></div></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-warning overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Pending</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo $pending_assignments_count; ?></h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-warning rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-file-text fs-6"></i></div></div></div></div></div></div></div>
    <div class="col-lg-3"><div class="card bg-light-info overflow-hidden"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Avg. Grade</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo number_format($overall_average_grade, 1); ?>%</h4></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-info rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-award fs-6"></i></div></div></div></div></div></div></div>
</div>
<div class="row">
    <div class="col-lg-7 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Upcoming Assignments</h5>
                <div class="table-responsive">
                    <table class="table text-nowrap mb-0 align-middle">
                        <thead class="text-dark fs-4">
                            <tr><th class="border-bottom-0"><h6 class="fw-semibold mb-0">Assignment</h6></th><th class="border-bottom-0"><h6 class="fw-semibold mb-0">Course</h6></th><th class="border-bottom-0"><h6 class="fw-semibold mb-0">Due Date</h6></th></tr>
                        </thead>
                        <tbody>
              <?php if (!empty($upcoming_assignments)): foreach ($upcoming_assignments as $assignment): ?>
                <tr><td class="border-bottom-0"><h6 class="fw-normal mb-0"><?php echo htmlspecialchars($assignment['title']); ?></h6></td><td class="border-bottom-0"><p class="mb-0 fw-normal"><?php echo htmlspecialchars($assignment['course_name']); ?></p></td><td class="border-bottom-0"><p class="mb-0 fw-normal"><?php echo htmlspecialchars(date("M d, Y", strtotime($assignment['due_date']))); ?></p></td></tr>
              <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-center">No upcoming assignments.</td></tr>
              <?php endif; ?>
            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">My Recent Performance</h5>
                <?php if (!empty($performance_data['labels'])): ?>
                    <div id="performance-chart"></div>
                <?php else: ?>
                    <div class="alert alert-info">No assessment performance data to display yet.</div>
                <?php endif; ?>
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
            <div class="mb-3"><h6 class="fw-semibold"><?php echo htmlspecialchars($announcement['title']); ?></h6><p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 150) . (strlen($announcement['content']) > 150 ? '...' : ''))); ?></p><small class="text-muted">Posted by <?php echo htmlspecialchars($announcement['poster_name']); ?> on <?php echo htmlspecialchars(date("M d, Y", strtotime($announcement['posted_at']))); ?></small></div>
            <?php if(next($recent_announcements)) echo '<hr>'; ?>
          <?php endforeach; else: ?>
          <div class="alert alert-info" role="alert">No recent announcements.</div>
        <?php endif; ?>
                <a href="announcements.php" class="btn btn-primary mt-3">View All Announcements</a>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Performance Chart
    <?php if (!empty($performance_data['labels'])): ?>
    var performanceOptions = {
        series: [{
            name: 'Score (%)',
            data: <?php echo json_encode($performance_data['series']); ?>
        }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, foreColor: '#adb0bb', fontFamily: 'inherit' },
        colors: ["#5D87FF"],
        plotOptions: { bar: { horizontal: false, columnWidth: '35%', borderRadius: 4 } },
        dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(0) + "%"; } },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: {
            categories: <?php echo json_encode($performance_data['labels']); ?>,
            labels: { show: false },
        },
        yaxis: { title: { text: 'Score (%)' }, min: 0, max: 100 },
        grid: { borderColor: "rgba(0,0,0,0.1)", strokeDashArray: 3, },
        fill: { opacity: 1 },
        tooltip: { theme: "light", y: { formatter: function (val) { return val + " %" } } }
    };

    var performanceChart = new ApexCharts(document.querySelector("#performance-chart"), performanceOptions);
    performanceChart.render();
    <?php endif; ?>
});
</script>

<?php
require_once 'includes/footer.php';
?>
