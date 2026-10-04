<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

// Get student ID from user ID
$student_id = null;
$sql_student = "SELECT id FROM students WHERE user_id = ?";
if ($stmt_student = $conn->prepare($sql_student)) {
    $stmt_student->bind_param("i", $_SESSION['user_id']);
    if ($stmt_student->execute()) {
        $stmt_student->store_result();
        if ($stmt_student->num_rows == 1) {
            $stmt_student->bind_result($s_id);
            if ($stmt_student->fetch()) {
                $student_id = $s_id;
            }
        }
    }
    $stmt_student->close();
}

if ($student_id === null) {
    die("Error: Could not find student profile.");
}

// Fetch grades for the student
$grades = [];
$sql = "SELECT c.course_name, c.course_code, a.title AS assignment_title, g.grade, g.feedback 
        FROM grades g
        JOIN assignments a ON g.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        WHERE g.student_id = ?
        ORDER BY c.course_name, a.title";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grades[] = $row;
        }
    }
    $stmt->close();
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">My Grades</h5>
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Course</th><th>Assignment</th><th>Grade</th><th>Feedback</th></tr></thead>
            <tbody>
              <?php if (!empty($grades)) : foreach ($grades as $grade) : ?>
                <tr>
                  <td><?php echo htmlspecialchars($grade['course_name'] . ' (' . $grade['course_code'] . ')'); ?></td>
                  <td><?php echo htmlspecialchars($grade['assignment_title']); ?></td>
                  <td><?php echo $grade['grade'] !== null ? htmlspecialchars(number_format($grade['grade'], 2)) . '%' : 'Not Graded'; ?></td>
                  <td><?php echo htmlspecialchars($grade['feedback']); ?></td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="4" class="text-center">No grades are available at this time.</td></tr>
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