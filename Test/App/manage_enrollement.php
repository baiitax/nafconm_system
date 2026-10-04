<?php
session_start();
require_once '../config/db.php';

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

// Handle Create/Edit Enrollment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_enrollment'])) {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $enrollment_date = $_POST['enrollment_date'];
    $enrollment_id = isset($_POST['enrollment_id']) ? $_POST['enrollment_id'] : null;

    if (empty($student_id) || empty($course_id) || empty($enrollment_date)) {
        $action_err = "Please fill in all required fields.";
    } else {
        // Check for duplicate enrollment
        $duplicate_check_sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
        if (!empty($enrollment_id)) {
            $duplicate_check_sql .= " AND id != ?";
        }
        
        if ($stmt_check = $conn->prepare($duplicate_check_sql)) {
            if (!empty($enrollment_id)) {
                $stmt_check->bind_param("iii", $student_id, $course_id, $enrollment_id);
            } else {
                $stmt_check->bind_param("ii", $student_id, $course_id);
            }
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $action_err = "This enrollment already exists.";
            }
            $stmt_check->close();
        }
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Enrollments</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
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
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
            <tbody>
              <?php if (!empty($enrollments)) : foreach ($enrollments as $enrollment) : ?>
                <tr>
                  <td><?php echo $enrollment['id']; ?></td>
                  <td><?php echo $enrollment['student_name']; ?></td>
                  <td><?php echo $enrollment['course_name']; ?></td>
                  <td><?php echo $enrollment['enrollment_date']; ?></td>
                  <td>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareEditModal(<?php echo $enrollment['id']; ?>)">Edit</button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this enrollment?');">
                      <input type="hidden" name="enrollment_id" value="<?php echo $enrollment['id']; ?>">
                      <button type="submit" name="delete_enrollment" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollmentModal" onclick="prepareCreateModal()">Create New Enrollment</button>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
