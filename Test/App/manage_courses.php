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

// Handle Bulk Save/Update/Delete Courses
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_all_courses'])) {
    $courses_data = $_POST['courses'] ?? [];
    $conn->begin_transaction();
    try {
        $updated_count = 0;
        $created_count = 0;
        $deleted_count = 0;

        // Prepare statements
        $stmt_update = $conn->prepare("UPDATE courses SET course_name = ?, course_code = ?, department_id = ?, teacher_id = ?, description = ?, is_open_for_registration = ? WHERE id = ?");
        $stmt_insert = $conn->prepare("INSERT INTO courses (course_name, course_code, department_id, teacher_id, description, is_open_for_registration) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_delete = $conn->prepare("DELETE FROM courses WHERE id = ?");

        foreach ($courses_data as $key => $course) {
            $course_id = $course['id'] ?? null;
            $course_name = trim($course['course_name']);
            $course_code = trim($course['course_code']);
            $department_id = !empty($course['department_id']) ? (int)$course['department_id'] : null;
            $teacher_id = !empty($course['teacher_id']) ? (int)$course['teacher_id'] : null;
            $description = trim($course['description']);
            $is_open = isset($course['is_open_for_registration']) ? 1 : 0;

            if (isset($course['delete']) && !empty($course_id)) {
                $stmt_delete->bind_param("i", $course_id);
                $stmt_delete->execute();
                $deleted_count += $stmt_delete->affected_rows;
            } elseif (!empty($course_name) && !empty($course_code)) {
                if (!empty($course_id)) { // Update
                    $stmt_update->bind_param("ssisisi", $course_name, $course_code, $department_id, $teacher_id, $description, $is_open, $course_id);
                    $stmt_update->execute();
                    $updated_count += $stmt_update->affected_rows;
                } else { // Insert
                    $stmt_insert->bind_param("ssisis", $course_name, $course_code, $department_id, $teacher_id, $description, $is_open);
                    $stmt_insert->execute();
                    $created_count += $stmt_insert->affected_rows;
                }
            }
        }

        $stmt_update->close();
        $stmt_insert->close();
        $stmt_delete->close();
        $conn->commit();
        $action_success = "Courses saved! ($created_count created, $updated_count updated, $deleted_count deleted).";
        log_activity($conn, $_SESSION['user_id'], "Bulk-edited courses.");
    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "An error occurred. The course code might already exist or a course you tried to delete has dependencies. Error: " . $e->getMessage();
    }
}

// Fetch all courses for display
$courses = [];
$sql_courses = "SELECT c.id, c.course_name, c.course_code, c.department_id, c.teacher_id, c.description, c.is_open_for_registration
                FROM courses c 
                ORDER BY c.course_name";
$result_courses = $conn->query($sql_courses) or die($conn->error);
while ($row = $result_courses->fetch_assoc()) $courses[] = $row;

// Fetch all teachers for the dropdown
$teachers = [];
$sql_teachers = "SELECT id, full_name FROM teachers ORDER BY full_name";
$result_teachers = $conn->query($sql_teachers) or die($conn->error);
while ($row = $result_teachers->fetch_assoc()) $teachers[] = $row;

// Fetch all departments for the dropdown
$departments = [];
$sql_departments = "SELECT id, name FROM departments ORDER BY name";
$result_departments = $conn->query($sql_departments) or die($conn->error);
while ($row = $result_departments->fetch_assoc()) $departments[] = $row;

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Courses</h5>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="table-responsive">
                <table class="table table-bordered" id="coursesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Course Name</th>
                            <th>Code</th>
                            <th>Department</th>
                            <th>Teacher</th>
                            <th class="text-center">Open Reg.</th>
                            <th class="text-center">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <input type="hidden" name="courses[<?php echo $course['id']; ?>][id]" value="<?php echo $course['id']; ?>">
                                <td><input type="text" class="form-control" name="courses[<?php echo $course['id']; ?>][course_name]" value="<?php echo htmlspecialchars($course['course_name']); ?>" required></td>
                                <td><input type="text" class="form-control" name="courses[<?php echo $course['id']; ?>][course_code]" value="<?php echo htmlspecialchars($course['course_code']); ?>" required></td>
                                <td><select class="form-select" name="courses[<?php echo $course['id']; ?>][department_id]"><option value="">(No Department)</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>" <?php if($dept['id'] == $course['department_id']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?></select></td>
                                <td><select class="form-select" name="courses[<?php echo $course['id']; ?>][teacher_id]"><option value="">(No Teacher)</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>" <?php if($teacher['id'] == $course['teacher_id']) echo 'selected'; ?>><?php echo htmlspecialchars($teacher['full_name']); ?></option><?php endforeach; ?></select></td>
                                <td class="text-center align-middle"><input class="form-check-input" type="checkbox" name="courses[<?php echo $course['id']; ?>][is_open_for_registration]" value="1" <?php if($course['is_open_for_registration']) echo 'checked'; ?>></td>
                                <td class="text-center align-middle"><input class="form-check-input" type="checkbox" name="courses[<?php echo $course['id']; ?>][delete]" value="1" title="Mark to delete"></td>
                                <input type="hidden" class="form-control" name="courses[<?php echo $course['id']; ?>][description]" value="<?php echo htmlspecialchars($course['description']); ?>">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" onclick="addRow()">Add New Course</button>
                <button type="submit" name="save_all_courses" class="btn btn-primary float-end">Save All Changes</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
let newRowIdx = 0;
function addRow() {
    const table = document.getElementById('coursesTable').getElementsByTagName('tbody')[0];
    const newRow = table.insertRow();
    newRow.innerHTML = `
        <td><input type="text" class="form-control" name="courses[new_${newRowIdx}][course_name]" required></td>
        <td><input type="text" class="form-control" name="courses[new_${newRowIdx}][course_code]" required></td>
        <td><select class="form-select" name="courses[new_${newRowIdx}][department_id]"><option value="">(No Department)</option><?php foreach ($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?></select></td>
        <td><select class="form-select" name="courses[new_${newRowIdx}][teacher_id]"><option value="">(No Teacher)</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option><?php endforeach; ?></select></td>
        <td class="text-center align-middle"><input class="form-check-input" type="checkbox" name="courses[new_${newRowIdx}][is_open_for_registration]" value="1" checked></td>
        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">X</button></td>
        <input type="hidden" name="courses[new_${newRowIdx}][description]" value="">
    `;
    newRowIdx++;
}
</script>

<?php
include 'includes/footer.php';
?>