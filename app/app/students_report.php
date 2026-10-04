<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Role-based access control
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

$page_title = 'Comprehensive Student Report';

// Fetch detailed student data with academic metadata
$sql = "SELECT 
            s.full_name, 
            s.student_id_number, 
            s.email, 
            s.phone,
            s.can_register_courses,
            p.name as program_name,
            d.name as department_name,
            u.created_at as enrollment_date
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.full_name ASC";
$students_result = $conn->query($sql);

include 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<?php
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Institutional Reporting</h4>
            <p class="text-muted small mb-0">Generate and export official student records and enrollment status.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <div id="export_buttons_container"></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-none border p-2">
                <div class="card-body py-3">
                    <small class="text-muted text-uppercase fw-bold ls-1 fs-1">Total Records</small>
                    <h4 class="fw-bold mb-0 text-primary"><?php echo $students_result->num_rows; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-none border p-2">
                <div class="card-body py-3">
                    <small class="text-muted text-uppercase fw-bold ls-1 fs-1">Cleared Students</small>
                    <h4 class="fw-bold mb-0 text-success">
                        <?php 
                        // Logic for quick counter
                        $students_result->data_seek(0);
                        $cleared = 0;
                        while($r = $students_result->fetch_assoc()){ if($r['can_register_courses']) $cleared++; }
                        echo $cleared;
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-none border">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center">
            <i class="ti ti-table-export text-primary me-2 fs-5"></i>
            <h5 class="fw-bold mb-0">Student Academic Master List</h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="studentsReportTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead class="bg-light-subtle">
                        <tr class="text-muted fs-2 text-uppercase ls-1">
                            <th>Student Identity</th>
                            <th>Registration ID</th>
                            <th>Academic Program</th>
                            <th>Faculty/Dept</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody class="fs-3">
                        <?php 
                        $students_result->data_seek(0); // Reset pointer
                        while($student = $students_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light-primary text-primary rounded-circle p-2 fw-bold fs-2 me-2 d-flex align-items-center justify-content-center" style="width:35px; height:35px;">
                                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark"><?php echo strtoupper($student['full_name']); ?></h6>
                                        <small class="text-muted"><?php echo $student['email']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="fw-bold text-dark"><?php echo $student['student_id_number']; ?></span></td>
                            <td><span class="badge bg-light-primary text-primary fw-bold"><?php echo $student['program_name'] ?? 'N/A'; ?></span></td>
                            <td><?php echo $student['department_name'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if($student['can_register_courses']): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2">OFFICIALLY CLEARED</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2">RESTRICTED</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted"><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
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
    var table = $('#studentsReportTable').DataTable({
        "dom": '<"d-flex justify-content-between align-items-center mb-3"f<"#buttons_go_here">>rtip',
        "pageLength": 25,
        "language": {
            "search": "_INPUT_",
            "searchPlaceholder": "Filter records..."
        },
        "buttons": [
            {
                extend: 'excelHtml5',
                text: '<i class="ti ti-file-spreadsheet me-1"></i> Excel',
                className: 'btn btn-light-success text-success fw-bold border-0 shadow-none'
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="ti ti-file-description me-1"></i> PDF',
                className: 'btn btn-light-danger text-danger fw-bold border-0 shadow-none',
                orientation: 'landscape',
                pageSize: 'A4'
            },
            {
                extend: 'print',
                text: '<i class="ti ti-printer me-1"></i> Print',
                className: 'btn btn-light-info text-info fw-bold border-0 shadow-none'
            }
        ]
    });

    // Move buttons to the custom container in the header
    table.buttons().container().appendTo('#export_buttons_container');
});
</script>