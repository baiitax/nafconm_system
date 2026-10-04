<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_department'])) {
    $department_id = $_POST['department_id'];
    $sql = "DELETE FROM departments WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $department_id);
        if ($stmt->execute()) {
            $action_success = "Department deleted successfully.";
        } else {
            $action_err = "Error: This department may have courses assigned to it and cannot be deleted.";
        }
        $stmt->close();
    }
}

// Handle Create/Edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_department'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $department_id = isset($_POST['department_id']) ? $_POST['department_id'] : null;

    if (empty($name)) {
        $action_err = "Department name is required.";
    } else {
        if (!empty($department_id)) {
            $sql = "UPDATE departments SET name = ?, description = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $department_id);
            if ($stmt->execute()) {
                $action_success = "Department updated successfully.";
            } else {
                $action_err = "Error updating department.";
            }
        } else {
            $sql = "INSERT INTO departments (name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $action_success = "Department created successfully.";
            } else {
                $action_err = "Error creating department.";
            }
        }
        $stmt->close();
    }
}

// Fetch all departments
$departments = [];
$sql_departments = "SELECT d.id, d.name, d.description, COUNT(c.id) as course_count FROM departments d LEFT JOIN courses c ON d.id = c.department_id GROUP BY d.id ORDER BY d.name";
$result = $conn->query($sql_departments);
if ($result) {
    while ($row = $result->fetch_assoc()) {
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
        <h5 class="card-title fw-semibold">Manage Departments</h5>
        <div>
            <a href="departments_overview.php" class="btn btn-outline-primary">Overview</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="prepareCreateModal()">Create New</button>
        </div>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>ID</th><th>Name</th><th>Courses</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($departments)): foreach ($departments as $dept): ?>
                <tr>
                  <td><?php echo $dept['id']; ?></td>
                  <td><?php echo htmlspecialchars($dept['name']); ?></td>
                  <td><?php echo $dept['course_count']; ?></td>
                  <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick='prepareEditModal(<?php echo json_encode($dept, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                    <form action="" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this department?');"><input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>"><button type="submit" name="delete_department" class="btn btn-sm btn-danger">Delete</button></form>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center">No departments found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1" aria-labelledby="departmentModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form action="" method="post"><div class="modal-header"><h5 class="modal-title" id="departmentModalLabel">Create Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="department_id" id="departmentId"><div class="mb-3"><label for="name" class="form-label">Department Name</label><input type="text" class="form-control" id="name" name="name" required></div><div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_department" class="btn btn-primary">Save</button></div></form></div></div>
</div>

<script>
function prepareCreateModal() {
    document.getElementById('departmentModalLabel').innerText = 'Create Department';
    document.getElementById('departmentModal').querySelector('form').reset();
    document.getElementById('departmentId').value = '';
}
function prepareEditModal(dept) {
    document.getElementById('departmentModalLabel').innerText = 'Edit Department';
    document.getElementById('departmentId').value = dept.id;
    document.getElementById('name').value = dept.name;
    document.getElementById('description').value = dept.description;
}
</script>

<?php include 'includes/footer.php'; ?>