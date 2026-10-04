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

// Fetch attendance records for the student
$attendance_records = [];
$sql = "SELECT c.course_name, c.course_code, a.date, a.status 
        FROM attendance a
        JOIN courses c ON a.course_id = c.id
        WHERE a.student_id = ?
        ORDER BY a.date DESC, c.course_name ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $student_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
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
    <h5 class="card-title fw-semibold mb-4">My Attendance</h5>
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Date</th><th>Course</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (!empty($attendance_records)) : foreach ($attendance_records as $record) : ?>
                <tr>
                  <td><?php echo htmlspecialchars(date("F j, Y", strtotime($record['date']))); ?></td>
                  <td><?php echo htmlspecialchars($record['course_name'] . ' (' . $record['course_code'] . ')'); ?></td>
                  <td>
                    <?php 
                        $status = htmlspecialchars($record['status']);
                        $badge_class = 'bg-secondary'; // Default
                        if ($status === 'Present') { $badge_class = 'bg-success'; } 
                        elseif ($status === 'Absent') { $badge_class = 'bg-danger'; }
                        elseif ($status === 'Excused') { $badge_class = 'bg-info'; }
                        echo "<span class='badge $badge_class'>$status</span>";
                    ?>
                  </td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="3" class="text-center">No attendance records found.</td></tr>
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