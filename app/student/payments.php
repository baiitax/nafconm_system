<?php
session_start();
// Define this BEFORE requiring header to help the Gatekeeper identify the page
$current_page_id = 'payments'; 

require_once '../config/db.php';

// --- 1. Strict Security Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$user_id = intval($_SESSION["user_id"]); 
$student_id = intval($_SESSION["student_id"]); 
$student_email = $_SESSION["email"] ?? 'student@nafconm.edu.ng';

// Safely log activity
if (function_exists('log_activity')) {
    log_activity($user_id, 'Viewed Financial Dashboard'); 
}

// --- 2. Fetch Data ---
// We JOIN with the 'fees' table to find out how much the student SHOULD pay
$sql = "SELECT p.*, f.fee_name, f.amount as total_fee_amount 
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

// --- 3. Logic for "Outstanding Balance" ---
// For NAFCONM, we assume the ND Nursing fee is 15,000 (from your fees table)
$outstanding_balance = (15000.00 - $total_paid);
if ($outstanding_balance < 0) $outstanding_balance = 0;

$page_title = "Financial Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bolder text-dark mb-1">Financial Dashboard</h3>
            <p class="text-muted small">Manage your payments and tuition fees.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-danger-subtle">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-danger fw-bold text-uppercase small">Outstanding Balance</h6>
                        <h2 class="fw-black text-danger mb-0">₦<?= number_format($outstanding_balance, 2) ?></h2>
                    </div>
                    <i class="ti ti-wallet fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-success-subtle">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-success fw-bold text-uppercase small">Total Amount Paid</h6>
                        <h2 class="fw-black text-success mb-0">₦<?= number_format($total_paid, 2) ?></h2>
                    </div>
                    <i class="ti ti-circle-check fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom p-4">
            <h5 class="fw-bold mb-0">Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="pe-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($all_payments)): foreach($all_payments as $p): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-dark"><?= htmlspecialchars($p['transaction_id'] ?: 'N/A') ?></span><br>
                                <small class="text-muted"><?= date('M d, Y', strtotime($p['created_at'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($p['payment_method'] ?: 'Online') ?></td>
                            <td class="text-end fw-bold text-dark">₦<?= number_format($p['amount_paid'], 2) ?></td>
                            <td class="text-center">
                                <?php if($p['status'] === 'approved'): ?>
                                    <span class="badge bg-success rounded-pill">Successful</span>
                                <?php elseif($p['status'] === 'pending'): ?>
                                    <span class="badge bg-warning rounded-pill text-dark">Pending Review</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-center">
                                <?php if($p['status'] === 'approved'): ?>
                                    <a href="receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">View Receipt</a>
                                <?php else: ?>
                                    <span class="text-muted small">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>