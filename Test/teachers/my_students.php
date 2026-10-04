<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../index.php");
    exit;
}

// Assuming teacher_id is stored in session upon login
$teacher_id = $_SESSION['teacher_id'];

// Fetch courses and their enrolled students for the logged-in teacher
$courses_with_students = [];
$sql = "SELECT 
            c.id as course_id, c.course_name, c.course_code,
            s.full_name as student_name, s.student_id_number, s.email as student_email
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        JOIN students s ON e.student_id = s.id
        WHERE c.teacher_id = ?
        ORDER BY c.course_name, s.full_name";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $course_id = $row['course_id'];
            // Initialize course array if it doesn't exist
            if (!isset($courses_with_students[$course_id])) {
                $courses_with_students[$course_id] = [
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'],
                    'students' => []
                ];
            }
            // Add student to the course
            $courses_with_students[$course_id]['students'][] = $row;
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
    <h5 class="card-title fw-semibold mb-4">My Students by Course</h5>

    <div class="accordion" id="studentsAccordion">
      <?php if (!empty($courses_with_students)): ?>
        <?php foreach ($courses_with_students as $course_id => $course): ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?php echo $course_id; ?>">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $course_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $course_id; ?>">
                <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                <span class="badge bg-primary rounded-pill ms-2"><?php echo count($course['students']); ?> Students</span>
              </button>
            </h2>
            <div id="collapse-<?php echo $course_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $course_id; ?>" data-bs-parent="#studentsAccordion">
              <div class="accordion-body">
                <div class="table-responsive">
                  <table class="table table-sm table-striped">
                    <thead><tr><th>Student Name</th><th>Student ID</th><th>Email</th></tr></thead>
                    <tbody>
                      <?php foreach ($course['students'] as $student): ?>
                        <tr><td><?php echo htmlspecialchars($student['student_name']); ?></td><td><?php echo htmlspecialchars($student['student_id_number']); ?></td><td><?php echo htmlspecialchars($student['student_email']); ?></td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="alert alert-info">You are not assigned to any courses with enrolled students.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>