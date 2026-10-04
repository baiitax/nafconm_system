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

// Handle Student Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_student'])) {
    $student_id = $_POST['student_id'];
    $sql_delete = "DELETE FROM students WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $student_id);
        if ($stmt_delete->execute()) {
            $action_success = "Student deleted successfully.";
        } else {
            $action_err = "Error deleting student.";
        }
        $stmt_delete->close();
    } else {
        $action_err = "Database error.";
    }
}

// Handle Student Creation/Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_student'])) {
    $full_name = trim($_POST['full_name']);
    $student_id_number = trim($_POST['student_id_number']);
    $email = trim($_POST['email']);
    $department_id = $_POST['department_id'];
    $student_id = isset($_POST['student_id']) ? $_POST['student_id'] : null;

    // Basic Validation
    if (empty($full_name) || empty($student_id_number) || empty($email)) {
        $action_err = "Name, Student ID, and Email are required.";
    } else {
        if (!empty($student_id)) {
            // Update Student
            $sql_update = "UPDATE students SET full_name = ?, student_id_number = ?, email = ?, department_id = ? WHERE id = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("ssisi", $full_name, $student_id_number, $email, $department_id, $student_id);
                if ($stmt_update->execute()) {
                    $action_success = "Student updated successfully.";
                } else {
                    $action_err = "Error updating student.";
                }
                $stmt_update->close();
            } else {
                $action_err = "Database error.";
            }
        } else {
            // Create Student (Placeholder - you'll need to create a user account as well)
            $sql_insert = "INSERT INTO students (full_name, student_id_number, email, department_id) VALUES (?, ?, ?, ?)";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("sssi", $full_name, $student_id_number, $email, $department_id);
                if ($stmt_insert->execute()) {
                    $action_success = "Student created successfully.";
                } else {
                    $action_err = "Error creating student.";
                }
                $stmt_insert->close();
            } else {
                $action_err = "Database error.";
            }
        }
    }
}

// Fetch all students
$students = [];
$sql_students = "SELECT s.id, s.full_name, s.student_id_number, s.email, d.name as department_name 
                 FROM students s
                 LEFT JOIN departments d ON s.department_id = d.id
                 ORDER BY s.full_name";
$result_students = $conn->query($sql_students);
if ($result_students) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
}

// Fetch all departments for the dropdown
$departments = [];
$sql_departments = "SELECT id, name FROM departments ORDER BY name";
$result_departments = $conn->query($sql_departments);
if ($result_departments) {
    while ($row = $result_departments->fetch_assoc()) {
        $departments[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Students</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="prepareCreateModal()">Add New Student</button>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>ID</th><th>Name</th><th>Student ID</th><th>Email</th><th>Department</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($students)): foreach ($students as $student): ?>
                <tr>
                  <td><?php echo $student['id']; ?></td>
                  <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                  <td><a href="mailto:<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></a></td>
                  <td><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></td>
                  <td>
                    <a href="view_student_details.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">View</a>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#studentModal" onclick='prepareEditModal(<?php echo json_encode($student, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                    <form action="" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this student?');">
                      <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                      <button type="submit" name="delete_student" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center">No students found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="studentModalLabel">Add New Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="student_id" id="studentId">
          <div class="mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" id="full_name" name="full_name" required></div>
          <div class="mb-3"><label for="student_id_number" class="form-label">Student ID</label><input type="text" class="form-control" id="student_id_number" name="student_id_number" required></div>
          <div class="mb-3"><label for="email" class="form-label">Email address</label><input type="email" class="form-control" id="email" name="email" required></div>
          <div class="mb-3"><label for="department_id" class="form-label">Department</label><select class="form-select" id="department_id" name="department_id"><option value="">Select Department</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="save_student" class="btn btn-primary">Save Student</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function prepareCreateModal() {
    document.getElementById('studentModalLabel').innerText = 'Add New Student';
    const form = document.getElementById('studentModal').querySelector('form');
    form.reset();
    document.getElementById('studentId').value = '';
}

function prepareEditModal(student) {
    document.getElementById('studentModalLabel').innerText = 'Edit Student';
    const form = document.getElementById('studentModal').querySelector('form');
    form.reset();

    document.getElementById('studentId').value = student.id;
    document.getElementById('full_name').value = student.full_name;
    document.getElementById('student_id_number').value = student.student_id_number;
    document.getElementById('email').value = student.email;
    document.getElementById('department_id').value = student.department_id || '';
}
</script>

<?php include 'includes/footer.php'; ?>