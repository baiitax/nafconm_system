<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Fetch Departments with Analytics
$sql = "SELECT d.*, 
        (SELECT COUNT(*) FROM students WHERE department_id = d.id) as student_count,
        (SELECT COUNT(*) FROM teachers WHERE department_id = d.id) as teacher_count,
        (SELECT COUNT(*) FROM courses WHERE department_id = d.id) as course_count
        FROM departments d ORDER BY d.name ASC";
$departments = $conn->query($sql);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Academic Departments</h4>
            <p class="text-muted small mb-0">Overview of faculty strength and student distribution.</p>
        </div>
        <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#deptModal">
            <i class="ti ti-plus me-1"></i> New Department
        </button>
    </div>

    <div class="row">
        <?php while($row = $departments->fetch_assoc()): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card shadow-none border h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="p-2 bg-light-primary text-primary rounded">
                            <i class="ti ti-school fs-6"></i>
                        </div>
                        <span class="badge bg-light text-muted border">ID: #<?= $row['id'] ?></span>
                    </div>
                    
                    <h5 class="fw-bolder text-dark mb-1"><?= $row['name'] ?></h5>
                    <p class="text-muted small mb-4"><?= $row['description'] ?: 'No description provided for this faculty.' ?></p>
                    
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <h6 class="fw-bold mb-0"><?= $row['student_count'] ?></h6>
                                <small class="text-muted fs-1">Students</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <h6 class="fw-bold mb-0"><?= $row['teacher_count'] ?></h6>
                                <small class="text-muted fs-1">Faculty</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded">
                                <h6 class="fw-bold mb-0"><?= $row['course_count'] ?></h6>
                                <small class="text-muted fs-1">Courses</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top py-3 d-flex justify-content-between">
                    <button class="btn btn-sm btn-light-primary text-primary fw-bold px-3">View Staff</button>
                    <button class="btn btn-sm btn-light border px-3"><i class="ti ti-edit"></i></button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>