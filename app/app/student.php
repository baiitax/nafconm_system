<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Students List';

// Role-based access control
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// Fetch all students with their program and department names
$sql = "SELECT 
            s.id,
            s.full_name, 
            s.student_id_number, 
            s.email, 
            s.phone,
            p.name as program_name,
            d.name as department_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        ORDER BY s.full_name";
$students_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$active_session_id = $conn->query("SELECT id FROM sessions WHERE is_active = 1 LIMIT 1")->fetch_assoc()['id'] ?? 0;

$conn->close();

include 'includes/header.php';
?>
<!-- DataTables CSS -->
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
                <table id="studentsTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_list as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="view_student_details.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="View Details">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a href="student_financials.php?student_id=<?php echo $student['id']; ?>&session_id=<?php echo $active_session_id; ?>" class="btn btn-sm btn-info" title="View Financials">
                                        <i class="ti ti-currency-naira"></i>
                                    </a>
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

<script>
$(document).ready(function() {
    $('#studentsTable').DataTable();
});
</script>
