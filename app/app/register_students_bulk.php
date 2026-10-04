<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$import_results = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['student_csv'])) {
    $file = $_FILES['student_csv']['tmp_name'];
    $handle = fopen($file, "r");
    $header = fgetcsv($handle); // Skip header row

    $conn->begin_transaction();
    try {
        while (($data = fgetcsv($handle)) !== FALSE) {
            // CSV Format: full_name, email, phone, student_id_number, program_id
            $full_name = trim($data[0]);
            $email = trim($data[1]);
            $phone = trim($data[2]);
            $matric = trim($data[3]);
            $prog_id = intval($data[4]);
            
            // 1. Create User Account (Username = Matric Number, Pass = phone)
            $username = $matric;
            $password = password_hash($phone, PASSWORD_DEFAULT);
            $stmt_user = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')");
            $stmt_user->bind_param("ss", $username, $password);
            $stmt_user->execute();
            $new_user_id = $conn->insert_id;

            // 2. Create Student Profile
            $stmt_student = $conn->prepare("INSERT INTO students (student_id_number, user_id, full_name, email, phone, program_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_student->bind_param("sisssi", $matric, $new_user_id, $full_name, $email, $phone, $prog_id);
            $stmt_student->execute();
            
            $import_results[] = "Imported: $full_name ($matric)";
        }
        $conn->commit();
        $_SESSION['msg'] = "Bulk import completed successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Import failed: " . $e->getMessage();
    }
    fclose($handle);
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="card bg-light-primary shadow-none mb-4">
        <div class="card-body p-4">
            <h4 class="fw-bold text-primary mb-1">Bulk Student Onboarding</h4>
            <p class="text-muted mb-0">Upload a CSV file to register multiple students and generate their accounts instantly.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card border shadow-none">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0">Upload CSV Source</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4 text-center border-dashed p-5 rounded-3 bg-light">
                            <i class="ti ti-upload fs-9 text-primary mb-2"></i>
                            <input type="file" name="student_csv" class="form-control mt-3" accept=".csv" required>
                            <small class="text-muted d-block mt-2">Required Columns: Name, Email, Phone, MatricNo, ProgramID</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Start Batch Processing</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border shadow-none h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="fw-bold mb-0">Import Logs</h5>
                </div>
                <div class="card-body scroll-sidebar" style="max-height: 300px;">
                    <?php if(!empty($import_results)): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach($import_results as $log): ?>
                                <li class="list-group-item fs-2 py-1 border-0"><i class="ti ti-check text-success me-2"></i> <?= $log ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No active import session.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>