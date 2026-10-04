<?php
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);

// Handle Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_admission'])) {
    $new_status = $_POST['status'];
    $admin_note = mysqli_real_escape_string($conn, $_POST['admin_note']);
    $payment_update = $_POST['payment_status'];

    $update_sql = "UPDATE admission_applications SET status = ?, admin_note = ?, payment_status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sssi", $new_status, $admin_note, $payment_update, $id);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Application updated successfully.";
    }
}

// Fetch Applicant Bio-data
$app_query = $conn->query("SELECT aa.*, p.name as program_name FROM admission_applications aa JOIN programs p ON aa.program_id = p.id WHERE aa.id = $id");
$app = $app_query->fetch_assoc();

if (!$app) { die("Applicant not found."); }

// Fetch O-Level Results
$results_query = $conn->query("SELECT * FROM admission_results WHERE application_id = $id");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="fw-bold">Applicant Review: <span class="text-primary"><?= $app['token'] ?></span></h4>
        <a href="review_admissions.php" class="btn btn-outline-dark btn-sm">Back to List</a>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0">O-Level Qualifications</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Subject</th>
                                    <th class="text-center">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($res = $results_query->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= $res['subject'] ?></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary fs-3"><?= $res['grade'] ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-none border">
                <div class="card-header bg-transparent py-3">
                    <h5 class="fw-bold mb-0">Payment Verification</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Method: <strong><?= strtoupper($app['payment_method']) ?></strong></p>
                    <?php if($app['payment_proof']): ?>
                        <label class="d-block mb-2 small fw-bold">Submitted Receipt:</label>
                        <img src="../uploads/proofs/<?= $app['payment_proof'] ?>" class="img-fluid rounded border shadow-sm" style="max-height: 400px;">
                        <div class="mt-3">
                            <a href="../uploads/proofs/<?= $app['payment_proof'] ?>" target="_blank" class="btn btn-sm btn-dark">Open Full Image</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary py-2 small">No manual proof uploaded (Check online gateway logs).</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-none border sticky-top" style="top: 100px;">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0">Final Decision</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Payment Status</label>
                            <select name="payment_status" class="form-select border-2">
                                <option value="unpaid" <?= $app['payment_status'] == 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                <option value="paid" <?= $app['payment_status'] == 'paid' ? 'selected' : '' ?>>Verified Paid</option>
                                <option value="pending_verification" <?= $app['payment_status'] == 'pending_verification' ? 'selected' : '' ?>>Pending Review</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Admission Status</label>
                            <select name="status" class="form-select border-2">
                                <option value="pending" <?= $app['status'] == 'pending' ? 'selected' : '' ?>>Still Pending</option>
                                <option value="admitted" <?= $app['status'] == 'admitted' ? 'selected' : '' ?>>Admit Student</option>
                                <option value="rejected" <?= $app['status'] == 'rejected' ? 'selected' : '' ?>>Reject Application</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Internal Note (Reason)</label>
                            <textarea name="admin_note" class="form-control border-2" rows="4" placeholder="Mention why you admitted or rejected..."><?= $app['admin_note'] ?></textarea>
                        </div>

                        <button type="submit" name="update_admission" class="btn btn-primary w-100 py-2 fw-bold shadow">Save Decision</button>
                    </form>
                    
                    <?php if($app['status'] == 'admitted'): ?>
                        <hr>
                        <a href="generate_admission_letter.php?id=<?= $app['id'] ?>" target="_blank" class="btn btn-success w-100 py-2 fw-bold">
                            <i class="ti ti-printer me-2"></i>Print Admission Letter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>