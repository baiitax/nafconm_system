<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Delete Enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_enrollment'])) {
    $enrollment_id_to_delete = $_POST['enrollment_id'];
    $sql = "DELETE FROM enrollments WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $enrollment_id_to_delete);
        if ($stmt->execute()) {
            $action_success = "Enrollment deleted successfully.";
        } else {
            $action_err = "Error deleting enrollment. Please try again.";
        }
        $stmt->close();
    }
}

// Handle Create/Edit Enrollment for the active session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_enrollment'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $enrollment_id = isset($_POST['enrollment_id']) ? $_POST['enrollment_id'] : null;

    $active_session_id = null;
    $sql_session = "SELECT id FROM sessions WHERE is_active = 1 LIMIT 1";
    $result_session = $conn->query($sql_session);
    if ($result_session->num_rows > 0) {
        $active_session_id = $result_session->fetch_assoc()['id'];
    } else {
        $action_err = "No active session found to enroll the student in.";
    }

    if (empty($student_id) || empty($course_id)) {
        $action_err = "Please select both a student and a course.";
    } elseif (empty($active_session_id)) {
        // Error already set
    } else {
        // Check for duplicate enrollment
        $sql_check = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? AND session_id = ?";
        if (!empty($enrollment_id)) { $sql_check .= " AND id != ?"; }

        if ($stmt_check = $conn->prepare($sql_check)) {
            if (!empty($enrollment_id)) { $stmt_check->bind_param("iiii", $student_id, $course_id, $active_session_id, $enrollment_id); } 
            else { $stmt_check->bind_param("iii", $student_id, $course_id, $active_session_id); }
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) { $action_err = "This student is already enrolled in this course for the active session."; }
            $stmt_check->close();
        }

        if (empty($action_err)) {
            if (!empty($enrollment_id)) { // Update
                $sql = "UPDATE enrollments SET student_id = ?, course_id = ?, session_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiii", $student_id, $course_id, $active_session_id, $enrollment_id);
                if ($stmt->execute()) { $action_success = "Enrollment updated successfully."; } 
                else { $action_err = "Error updating enrollment."; }
            } else { // Create
                $sql = "INSERT INTO enrollments (student_id, course_id, session_id) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $student_id, $course_id, $active_session_id);
                if ($stmt->execute()) { $action_success = "Enrollment created successfully."; } 
                else { $action_err = "Error creating enrollment."; }
            }
            $stmt->close();
        }
    }
}

// Fetch all enrollments for display
$enrollments = [];
$sql_enrollments = "SELECT e.id, e.student_id, e.course_id, s.full_name as student_name, s.student_id_number, c.course_name, c.course_code, e.enrollment_date 
                    FROM enrollments e 
                    JOIN students s ON e.student_id = s.id 
                    JOIN courses c ON e.course_id = c.id 
                    ORDER BY e.enrollment_date DESC";
$result_enrollments = $conn->query($sql_enrollments);
if ($result_enrollments) {
    while ($row = $result_enrollments->fetch_assoc()) {
        $enrollments[] = $row;
    }
}

// Fetch all students for the dropdown
$students = [];
$sql_students = "SELECT id, full_name, student_id_number FROM students ORDER BY full_name";
$result_students = $conn->query($sql_students);
if ($result_students) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
}

// Fetch all courses for the dropdown
$courses = [];
$sql_courses = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
$result_courses = $conn->query($sql_courses);
if ($result_courses) {
    while ($row = $result_courses->fetch_assoc()) {
        $courses[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!--  Main wrapper -->
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Enrollments</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Enroll Student</button>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>ID</th><th>Student</th><th>Course</th><th>Enrollment Date</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo htmlspecialchars($enrollment['student_name'] . ' (' . $enrollment['student_id_number'] . ')'); ?></td>
                  <td><?php echo htmlspecialchars($enrollment['course_name'] . ' (' . $enrollment['course_code'] . ')'); ?></td>
                  <td><?php echo htmlspecialchars(date("M d, Y", strtotime($enrollment['enrollment_date']))); ?></td>
                  <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick='prepareEditModal(<?php echo json_encode($enrollment, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-enrollmentid="<?php echo $enrollment['id']; ?>">Delete</button>
                  </td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="5" class="text-center">No enrollments found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Enrollment Create/Edit Modal -->
<div class="modal fade" id="enrollmentModal" tabindex="-1" aria-labelledby="enrollmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="enrollmentModalLabel">Create Enrollment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="enrollment_id" id="enrollmentId">
          <div class="mb-3">
            <label for="studentId" class="form-label">Student</label>
            <select class="form-select" id="studentId" name="student_id" required>
              <option value="">Select a student...</option>
              <?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id_number'] . ')'); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="courseId" class="form-label">Course</label>
            <select class="form-select" id="courseId" name="course_id" required>
              <option value="">Select a course...</option>
              <?php foreach ($courses as $course): ?><option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="save_enrollment" class="btn btn-primary">Save Enrollment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="enrollment_id" id="deleteEnrollmentId">
          <p>Are you sure you want to delete this enrollment? This will unenroll the student from the course.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete Enrollment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function prepareCreateModal() {
    document.getElementById('enrollmentModalLabel').innerText = 'Create Enrollment';
    const form = document.getElementById('enrollmentModal').querySelector('form');
    form.reset();
    document.getElementById('enrollmentId').value = '';
}

function prepareEditModal(enrollment) {
    document.getElementById('enrollmentModalLabel').innerText = 'Edit Enrollment';
    const form = document.getElementById('enrollmentModal').querySelector('form');
    form.reset();

    document.getElementById('enrollmentId').value = enrollment.id;
    document.getElementById('studentId').value = enrollment.student_id;
    document.getElementById('courseId').value = enrollment.course_id;
}

const deleteModal = document.getElementById('deleteModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const enrollmentId = button.getAttribute('data-enrollmentid');
    const modalEnrollmentIdInput = deleteModal.querySelector('#deleteEnrollmentId');
    modalEnrollmentIdInput.value = enrollmentId;
});
</script>

<?php
include 'includes/footer.php';
?>