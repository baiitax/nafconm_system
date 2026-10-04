<?php
session_start();
$current_page_id = 'payments'; 

require_once '../config/db.php';
require_once '../config/functions.php'; // Ensure log_activity is defined here

// --- 1. Strict Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$user_id = intval($_SESSION["user_id"]); 
$student_id = intval($_SESSION["student_id"]); 

// --- 2. Fetch Active Session & Required Fees ---
// Get the total amount this student is supposed to pay for the active session
$fee_sql = "SELECT SUM(amount) as total_required FROM fees 
            WHERE session_id = (SELECT id FROM sessions WHERE is_active = 1 LIMIT 1)";
$fee_res = $conn->query($fee_sql);
$total_required = ($fee_res->fetch_assoc())['total_required'] ?? 0;

// --- 3. Fetch Payment History ---
$sql = "SELECT p.*, f.fee_name 
        FROM payments p 
        LEFT JOIN fees f ON p.fee_id = f.id 
        WHERE p.student_id = ? 
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$payments_res = $stmt->get_result();

$total_paid = 0.00;
$all_payments = [];

while ($row = $payments_res->fetch_assoc()) {
    $all_payments[] = $row;
    if ($row['status'] === 'approved') {
        $total_paid += floatval($row['amount_paid']);
    }
}

$outstanding_balance = ($total_required - $total_paid);
if ($outstanding_balance < 0) $outstanding_balance = 0;

$page_title = "Financial Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Financial Dashboard</h3>
            <p class="text-muted mb-0">Track your tuition history and pending obligations.</p>
        </div>
        <a href="make_payment.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            <i class="ti ti-plus me-1"></i> New Payment
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-danger">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted fw-bold text-uppercase small">Outstanding Balance</h6>
                        <h2 class="fw-black text-danger mb-0">₦<?= number_format($outstanding_balance, 2) ?></h2>
                        <small class="text-muted">For current academic session</small>
                    </div>
                    <div class="bg-danger-subtle text-danger rounded-circle p-3">
                        <i class="ti ti-wallet fs-7"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-success">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted fw-bold text-uppercase small">Total Amount Paid</h6>
                        <h2 class="fw-black text-success mb-0">₦<?= number_format($total_paid, 2) ?></h2>
                        <small class="text-muted">Confirmed successful payments</small>
                    </div>
                    <div class="bg-success-subtle text-success rounded-circle p-3">
                        <i class="ti ti-circle-check fs-7"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom p-4">
            <h5 class="fw-bold mb-0 text-dark">Recent Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Reference & Description</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="pe-4 text-center">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($all_payments)): foreach($all_payments as $p): 
                            $status = strtolower($p['status']);
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <span class="fw-bold text-dark d-block">REF: <?= htmlspecialchars($p['transaction_id'] ?: 'N/A') ?></span>
                                <small class="text-muted fw-medium"><?= htmlspecialchars($p['fee_name'] ?? 'General Payment') ?> • <?= date('M d, Y', strtotime($p['created_at'])) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-medium"><?= strtoupper($p['payment_method'] ?: 'Online') ?></span>
                            </td>
                            <td class="text-end fw-black text-dark">
                                ₦<?= number_format($p['amount_paid'], 2) ?>
                            </td>
                            <td class="text-center">
                                <?php if($status === 'approved'): ?>
                                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">
                                        <i class="ti ti-check me-1"></i> Successful
                                    </span>
                                <?php elseif($status === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill fw-bold">
                                        <i class="ti ti-clock me-1"></i> Pending
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">
                                        <i class="ti ti-x me-1"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-center">
                                <?php if($status === 'approved'): ?>
                                    <a href="receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="ti ti-printer me-1"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <img src="../assets/images/backgrounds/empty-payment.svg" alt="No data" width="120" class="mb-3 opacity-50">
                                <h6 class="fw-bold text-dark">No transaction records found</h6>
                                <p class="text-muted small">Once you make a payment, it will appear here.</p>
                                <a href="make_payment.php" class="btn btn-primary btn-sm rounded-pill mt-2">Make First Payment</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .bg-danger-subtle { background-color: rgba(250, 137, 107, 0.1) !important; }
    .bg-success-subtle { background-color: rgba(19, 222, 185, 0.1) !important; }
    .bg-warning-subtle { background-color: rgba(255, 174, 31, 0.1) !important; }
    .fs-7 { font-size: 1.5rem; }
</style>

<?php include 'includes/footer.php'; ?>