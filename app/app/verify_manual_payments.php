<?php
/**
 * NAFCONM - Admin Payment Verification Portal
 * Features: View Evidence, Approve/Reject Logic, Automatic Student Clearance
 */
require_once '../config/db.php';
session_start();

// Security: Ensure only Admin can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'admin') {
    header("location: ../login.php");
    exit;
}

$message = $error = '';

// --- Handle Approval / Rejection ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    $action = $_GET['action'];
    $admin_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        $conn->begin_transaction();
        try {
            // 1. Mark Payment as Approved
            $stmt = $conn->prepare("UPDATE payments SET status = 'approved', review_date = NOW() WHERE id = ?");
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();

            // 2. Fetch Student ID associated with this payment
            $res = $conn->query("SELECT student_id FROM payments WHERE id = $payment_id");
            $student_id = $res->fetch_assoc()['student_id'];

            // 3. Automatically enable course registration for the student
            $update = $conn->prepare("UPDATE students SET can_register_courses = 1 WHERE id = ?");
            $update->bind_param("i", $student_id);
            $update->execute();

            $conn->commit();
            $message = "Payment ID #$payment_id approved. Student has been cleared.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Approval failed. Please try again.";
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE payments SET status = 'rejected', review_date = NOW() WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $message = "Payment ID #$payment_id has been rejected.";
    }
}

// Fetch Pending Payments
$sql = "SELECT p.*, s.full_name, s.student_id_number, f.fee_name 
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        JOIN fees f ON p.fee_id = f.id 
        WHERE p.status = 'pending' AND p.payment_method = 'manual'
        ORDER BY p.created_at ASC";
$pending_payments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$page_title = "Verify Payments";
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Manual Payment Verification</h3>
            <p class="text-muted mb-0">Review bank tellers and receipts uploaded by students.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= $message ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Student Info</th>
                        <th>Fee Category</th>
                        <th>Amount</th>
                        <th class="text-center">Proof</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pending_payments)): foreach ($pending_payments as $p): ?>
                    <tr>
                        <td class="ps-4">
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($p['full_name']) ?></h6>
                            <small class="text-muted"><?= $p['student_id_number'] ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light-primary text-primary"><?= htmlspecialchars($p['fee_name']) ?></span>
                        </td>
                        <td class="fw-bold text-dark">₦<?= number_format($p['amount_paid'], 2) ?></td>
                        <td class="text-center">
                            <a href="../uploads/proofs/<?= $p['proof_file'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill">
                                <i class="ti ti-eye me-1"></i> View Teller
                            </a>
                        </td>
                        <td class="pe-4 text-end">
                            <a href="?action=approve&id=<?= $p['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold me-2" onclick="return confirm('Approve this payment and clear student?')">
                                <i class="ti ti-check me-1"></i> Approve
                            </a>
                            <a href="?action=reject&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" onclick="return confirm('Reject this payment?')">
                                <i class="ti ti-x me-1"></i> Reject
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="ti ti-circle-check fs-1 text-success opacity-20 d-block mb-3"></i>
                            <h5 class="fw-bold text-muted">All clear! No pending payments.</h5>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .bg-light-primary { background-color: rgba(93, 135, 255, 0.1); }
</style>

<?php include 'includes/footer.php'; ?>