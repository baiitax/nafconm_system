<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch all graded assignments for the student
$graded_assignments = [];
$total_grade = 0;
$graded_count = 0;

$sql = "SELECT
            c.course_code,
            c.course_name,
            a.title AS assignment_title,
            s.grade
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE s.student_id = ? AND s.grade IS NOT NULL
        ORDER BY c.course_name, a.title";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $graded_assignments[] = $row;
        $total_grade += $row['grade'];
        $graded_count++;
    }
    $stmt->close();
}
$conn->close();

$average_grade = ($graded_count > 0) ? $total_grade / $graded_count : 0;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">My Grades</h5>

    <div class="row"><div class="col-12"><div class="card bg-light-info overflow-hidden mb-4"><div class="card-body p-4"><h5 class="card-title mb-9 fw-semibold">Overall Average</h5><div class="row align-items-center"><div class="col-8"><h4 class="fw-semibold mb-3"><?php echo number_format($average_grade, 2); ?>%</h4><h6 class="card-subtitle">Based on <?php echo $graded_count; ?> graded assignment(s)</h6></div><div class="col-4"><div class="d-flex justify-content-end"><div class="text-white bg-info rounded-circle p-6 d-flex align-items-center justify-content-center"><i class="ti ti-chart-pie fs-6"></i></div></div></div></div></div></div></div></div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Grade Details</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Assignment</th>
                            <th class="text-end">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($graded_assignments)): ?>
                            <?php foreach ($graded_assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['assignment_title']); ?></td>
                                    <td class="text-end"><span class="badge bg-primary rounded-3 fw-semibold fs-2"><?php echo htmlspecialchars(number_format($assignment['grade'], 2)); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center"><div class="alert alert-light-info m-0">You have no graded assignments yet.</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>