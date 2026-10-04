<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Teachers Report';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// Fetch all teachers with department name and course count
$sql = "SELECT 
            t.*, 
            d.name as department_name,
            (SELECT COUNT(c.id) FROM courses c WHERE c.teacher_id = t.id) as course_count
        FROM teachers t
        LEFT JOIN departments d ON t.department_id = d.id
        ORDER BY t.full_name";
$teachers_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$conn->close();

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
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
                <table id="teachersReportTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Specialization</th>
                            <th>Department</th>
                            <th>Assigned Courses</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers_list as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['specialization'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo $teacher['course_count']; ?></td>
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
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script>
$(document).ready(function() {
    $('#teachersReportTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf'
        ]
    });
});
</script>