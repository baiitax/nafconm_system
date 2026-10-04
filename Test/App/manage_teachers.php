<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Manage Teachers';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// Fetch all teachers with their department name
$sql = "SELECT t.*, d.name as department_name
        FROM teachers t
        LEFT JOIN departments d ON t.department_id = d.id
        ORDER BY t.full_name";
$teachers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$conn->close();

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<?php
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="teachersTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Specialization</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></td>
                                <td>
                                    <a href="view_teacher_profile.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-info">View</a>
                                    <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#teachersTable').DataTable();
});
</script>