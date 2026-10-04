<?php
/**
 * NAFCONM - Student Financial Ledger
 * Features: Historical Payments, Status Tracking, Dynamic Receipt Links
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- SECURITY & AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Fetch Student ID
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
$student_id = $student['id'];

// Fetch all payment history
$stmt_pay = $conn->prepare("SELECT * FROM student_payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC");
$stmt_pay->bind_param("i", $student_id);
$stmt_pay->execute();
$payments = $stmt_pay->get_result();

$page_title = "Financial Ledger";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Financial Ledger</h3>
            <p class="text-muted small mb-0">Track your payment history and reprint old receipts.</p>
        </div>
        <a href="pay_fees.php" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
            <i class="ti ti-credit-card me-2"></i> Make New Payment
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4">
            <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-receipt-2 text-primary me-2"></i>Transaction History</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Invoice Number</th>
                            <th class="py-3 fw-bold">Payment Type</th>
                            <th class="py-3 fw-bold">Session</th>
                            <th class="py-3 fw-bold">Amount</th>
                            <th class="py-3 fw-bold text-center">Status</th>
                            <th class="py-3 fw-bold">Date</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($payments->num_rows > 0): ?>
                            <?php while($row = $payments->fetch_assoc()): 
                                $status = strtolower($row['status']);
                            ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <span class="fw-bold text-primary font-monospace"><?= htmlspecialchars($row['invoice_no']) ?></span>
                                </td>
                                <td><span class="fw-medium text-dark"><?= htmlspecialchars($row['payment_type']) ?></span></td>
                                <td><?= htmlspecialchars($row['session']) ?></td>
                                <td><span class="fw-black text-dark fs-6">₦<?= number_format($row['amount'], 2) ?></span></td>
                                <td class="text-center">
                                    <?php if($status === 'successful' || $status === 'paid' || $status === 'approved'): ?>
                                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill"><i class="ti ti-check me-1"></i>Cleared</span>
                                    <?php elseif($status === 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill"><i class="ti ti-clock me-1"></i>Review</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill"><i class="ti ti-x me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small fw-medium">
                                    <?= date('d M, Y', strtotime($row['payment_date'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($status === 'successful' || $status === 'paid' || $status === 'approved'): ?>
                                        <!-- Note: We pass the specific payment ID to the print_receipt page -->
                                        <a href="print_receipt.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                            <i class="ti ti-printer me-1"></i> Receipt
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-muted rounded-pill px-3 fw-bold" disabled>No Receipt</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ti ti-receipt fs-1 mb-3 d-block text-light"></i>
                                    No transaction history found.
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
    .table-hover tbody tr { transition: 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8f9ff !important; }
</style>

<?php 
$stmt_pay->close();
include 'includes/footer.php'; 
?>