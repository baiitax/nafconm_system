<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$id = (int)$_GET['id'];
$app = $conn->query("SELECT a.*, p.name as program_name FROM admission_applications a LEFT JOIN programs p ON a.program_id = p.id WHERE a.id = $id")->fetch_assoc();
$results = $conn->query("SELECT * FROM admission_olevel_results WHERE application_id = $id");
$docs = $conn->query("SELECT * FROM application_documents WHERE application_id = $id");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white"><b>Bio-Data</b></div>
                    <div class="card-body">
                        <p><b>Name:</b> <?= $app['first_name'] ?> <?= $app['last_name'] ?></p>
                        <p><b>Program:</b> <?= $app['program_name'] ?></p>
                        <p><b>Phone:</b> <?= $app['phone'] ?></p>
                        <hr>
                        <h6>O'Level Results</h6>
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Subject</th><th>Grade</th></tr></thead>
                            <tbody>
                                <?php while($r = $results->fetch_assoc()): ?>
                                <tr><td><?= $r['subject'] ?></td><td><?= $r['grade'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4 border-primary">
                    <div class="card-header bg-primary text-white">Update Status</div>
                    <div class="card-body">
                        <form action="admission_management.php" method="POST">
                            <input type="hidden" name="application_id" value="<?= $id ?>">
                            <select name="status" class="form-select mb-3">
                                <option value="pending" <?= $app['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="accepted" <?= $app['status'] == 'accepted' ? 'selected' : '' ?>>Accept</option>
                                <option value="rejected" <?= $app['status'] == 'rejected' ? 'selected' : '' ?>>Reject</option>
                            </select>
                            <textarea name="review_remarks" class="form-control mb-3" placeholder="Remarks"><?= $app['review_remarks'] ?></textarea>
                            <button type="submit" name="review_application" class="btn btn-primary w-100">Update Now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>