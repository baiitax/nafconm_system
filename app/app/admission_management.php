<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// 1. Strict Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php"); 
    exit;
}

$action_err = $action_success = "";

// 2. Process Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_application'])) {
    $app_id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $remarks = trim($_POST['review_remarks']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE admission_applications SET status = ?, review_remarks = ?, reviewed_by_user_id = ?, review_date = NOW() WHERE id = ?");
        $stmt->bind_param("ssii", $status, $remarks, $_SESSION['user_id'], $app_id);
        $stmt->execute();

        if ($status === 'accepted') {
            $app = $conn->query("SELECT * FROM admission_applications WHERE id = $app_id AND student_id IS NULL")->fetch_assoc();
            if ($app) {
                // Create User
                $username = strtolower($app['first_name']) . $app['id'];
                $pass = password_hash($app['phone'], PASSWORD_DEFAULT);
                $conn->query("INSERT INTO users (username, password, user_type) VALUES ('$username', '$pass', 'student')");
                $user_id = $conn->insert_id;

                // Create Student
                $stud_no = date("Y") . "/" . str_pad($app['id'], 4, "0", STR_PAD_LEFT);
                $full_name = $app['first_name'] . ' ' . $app['last_name'];
                
                $s_stmt = $conn->prepare("INSERT INTO students (student_id_number, user_id, full_name, email, phone, program_id) VALUES (?, ?, ?, ?, ?, ?)");
                $s_stmt->bind_param("sisssi", $stud_no, $user_id, $full_name, $app['email'], $app['phone'], $app['program_id']);
                $s_stmt->execute();
                $student_db_id = $conn->insert_id;

                $conn->query("UPDATE admission_applications SET student_id = $student_db_id WHERE id = $app_id");
                $action_success = "Student Accepted. User: $username";
            }
        } else {
            $action_success = "Status updated to " . $status;
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "Error: " . $e->getMessage();
    }
}

// 3. Fetch List
$res = $conn->query("SELECT a.*, p.name as program_name FROM admission_applications a LEFT JOIN programs p ON a.program_id = p.id ORDER BY a.application_date DESC");
$applications = $res->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <h4 class="fw-semibold mb-4">Manage Applications</h4>
        
        <?php if($action_success): ?><div class="alert alert-success"><?= $action_success ?></div><?php endif; ?>
        <?php if($action_err): ?><div class="alert alert-danger"><?= $action_err ?></div><?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead><tr><th>ID</th><th>Applicant</th><th>Program</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($applications as $app): ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><b><?= $app['first_name'] ?></b><br><small><?= $app['email'] ?></small></td>
                            <td><?= $app['program_name'] ?></td>
                            <td><span class="badge bg-light-info text-info text-uppercase"><?= $app['status'] ?></span></td>
                            <td><a href="view_admission_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-primary">Review</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>