<?php
/**
 * NAFCONM - Student Payment History & Financial Ledger
 * Features: Financial KPIs, Chronological Ledger, Dynamic Action Buttons
 */
session_start();
$current_page_id = 'payment_history';
$page_title = 'Payment History';
require_once '../config/db.php';

// Security check: Ensure only students can access
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$student_id = (int)$_SESSION['student_id'];

// --- 1. Fetch Financial KPIs for the Student ---
$kpi_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN amount_paid ELSE 0 END) as total_approved,
        SUM(CASE WHEN status = 'pending' THEN amount_paid ELSE 0 END) as total_pending,
        COUNT(id) as total_transactions
    FROM payments 
    WHERE student_id = ?
");
$kpi_stmt->bind_param("i", $student_id);
$kpi_stmt->execute();
$kpi_result = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

$total_approved = $kpi_result['total_approved'] ?? 0;
$total_pending = $kpi_result['total_pending'] ?? 0;
$total_transactions = $kpi_result['total_transactions'] ?? 0;

// --- 2. Fetch the Chronological Transaction Ledger ---
$log_stmt = $conn->prepare("
    SELECT p.id, p.amount_paid, p.payment_method, p.status, p.proof_file, p.transaction_id, p.created_at,
           f.fee_name, s.name as session_name
    FROM payments p
    LEFT JOIN fees f ON p.fee_id = f.id
    LEFT JOIN sessions s ON p.session_id = s.id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$transactions = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$log_stmt->close();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Financial Ledger</h3>
            <p class="text-muted mb-0">Track your payment history, verifications, and download receipts.</p>
        </div>
        <a href="make_payment.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm d-none d-md-block">
            <i class="ti ti-plus me-1"></i> Make New Payment
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white overflow-hidden h-100">
                <div class="card-body p-4 position-relative z-1">
                    <h6 class="text-white-50 fw-bold text-uppercase small mb-2">Total Verified Payments</h6>
                    <h2 class="fw-black text-white mb-0">₦<?= number_format($total_approved, 2) ?></h2>
                    <i class="ti ti-receipt position-absolute end-0 bottom-0 me-n3 mb-n3 opacity-10" style="font-size: 8rem; z-index:-1;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-warning h-100">
                <div class="card-body p-4">
                    <h6 class="text-muted fw-bold text-uppercase small mb-2">Pending Verification</h6>
                    <h2 class="fw-black text-dark mb-0">₦<?= number_format($total_pending, 2) ?></h2>
                    <p class="small mb-0 mt-1 text-warning fw-bold"><i class="ti ti-clock me-1"></i> Awaiting Bursary approval</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-info h-100">
                <div class="card-body p-4">
                    <h6 class="text-muted fw-bold text-uppercase small mb-2">Total Transactions</h6>
                    <h2 class="fw-black text-dark mb-0"><?= number_format($total_transactions) ?></h2>
                    <p class="small mb-0 mt-1 text-info fw-bold"><i class="ti ti-chart-dots me-1"></i> Lifetime portal activities</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-list-details text-primary me-2"></i> Payment History</h5>
            <span class="badge bg-light text-dark border px-3 py-2"><?= count($transactions) ?> Records</span>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="ti ti-wallet text-muted fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No Payments Found</h5>
                    <p class="text-muted">You have not made any payments through the portal yet.</p>
                    <a href="make_payment.php" class="btn btn-primary rounded-pill px-4 mt-2 shadow-sm">Make Your First Payment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Date / Ref</th>
                                <th>Description</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): 
                                $status = strtolower($t['status']);
                            ?>
                            <tr class="transition-all">
                                <td class="ps-4 py-3">
                                    <span class="fw-bold text-dark d-block"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
                                    <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($t['transaction_id'] ?? 'REF-'.$t['id']) ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($t['fee_name'] ?? 'General Fee') ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($t['session_name'] ?? 'Current Session') ?></small>
                                </td>
                                <td>
                                    <?php if(strtolower($t['payment_method']) === 'online'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="ti ti-world me-1"></i> Online</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="ti ti-building-bank me-1"></i> Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-black text-dark fs-6">
                                    ₦<?= number_format($t['amount_paid'], 2) ?>
                                </td>
                                <td class="text-center">
                                    <?php if($status === 'approved'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-1"><i class="ti ti-check me-1"></i> Approved</span>
                                    <?php elseif($status === 'pending'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><i class="ti ti-clock me-1"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1"><i class="ti ti-x me-1"></i> Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if($status === 'approved'): ?>
                                        <a href="print_receipt.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold shadow-sm">
                                            <i class="ti ti-download me-1"></i> Receipt
                                        </a>
                                    <?php elseif($status === 'pending' && $t['payment_method'] === 'manual' && !empty($t['proof_file'])): ?>
                                        <a href="../uploads/receipts/<?= htmlspecialchars($t['proof_file']) ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3 shadow-sm">
                                            <i class="ti ti-eye me-1"></i> View Proof
                                        </a>
                                    <?php elseif($status === 'rejected'): ?>
                                        <button class="btn btn-sm btn-light text-danger rounded-pill px-3" disabled>
                                            <i class="ti ti-ban me-1"></i> Failed
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .transition-all { transition: all 0.2s ease; }
    .table-hover tbody tr:hover { background-color: rgba(93, 135, 255, 0.03) !important; }
</style>

<?php require_once 'includes/footer.php'; ?>