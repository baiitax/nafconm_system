<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'All Students';

// Role-based access control for superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../login.php");
    exit;
}

// Fetch all students with their department name
$sql = "SELECT s.id, s.full_name, s.student_id_number, s.email, d.name as department_name
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        ORDER BY s.full_name";
$students = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$conn->close();

include 'includes/header.php';
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<?php
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!--  Main wrapper -->
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold"><?php echo $page_title; ?></h5>
        <a href="add_student.php" class="btn btn-primary">Add New Student</a>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="view_student_details.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">View Profile</a>
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
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    $('#studentsTable').DataTable({
        // Add buttons for exporting data
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
});
</script>