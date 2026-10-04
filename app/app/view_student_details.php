<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Role-based access control for superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../login.php");
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    header("location: students.php"); // Assuming a student list page exists
    exit;
}

// Fetch student details from the database
$sql = "SELECT s.*, d.name as department_name, u.username 
        FROM students s 
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found.");
}

$page_title = 'Student Details';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Student Profile</h5>
        <a href="students.php" class="btn btn-outline-primary">Back to Student List</a>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <?php if (!empty($student['photo_path'])): ?>
                        <img src="/<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Student Photo" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="mb-3">
                            <i class="ti ti-user-circle" style="font-size: 150px;"></i>
                            <p class="text-muted">No Photo</p>
                        </div>
                    <?php endif; ?>
                    <h5 class="card-title fw-semibold"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($student['student_id_number']); ?></p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-3">Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="admission_letter.php?student_id=<?php echo $student_id; ?>" class="btn btn-primary" target="_blank">
                            <i class="ti ti-file-text me-1"></i> View Admission Letter
                        </a>
                        
                       
                        <a href="student_invoice.php?student_id=<?php echo $student_id; ?>" class="btn btn-info" target="_blank">
                            <i class="ti ti-receipt me-1"></i> View Invoices
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Personal Information</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Full Name</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['full_name']); ?></dd>

                        <dt class="col-sm-4">Email Address</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['email']); ?></dd>

                        <dt class="col-sm-4">Phone Number</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></dd>

                        <dt class="col-sm-4">Date of Birth</dt>
                        <dd class="col-sm-8"><?php echo !empty($student['date_of_birth']) ? date("F j, Y", strtotime($student['date_of_birth'])) : 'N/A'; ?></dd>

                        <dt class="col-sm-4">Address</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></dd>
                    </dl>
                    <hr>
                    <h5 class="card-title fw-semibold my-4">Academic Information</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Student ID</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['student_id_number']); ?></dd>

                        <dt class="col-sm-4">Program of Study</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['program_of_study'] ?? 'Not Assigned'); ?></dd>

                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($student['department_name'] ?? 'Not Assigned'); ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>