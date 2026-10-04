<?php
/**
 * NAFCONM - Strategic Financial Dashboard
 * Features: Dynamic Balance Calculation, Transaction Tracking, and Payment Entry
 */
$page_title = 'Payments Overview';
require_once 'includes/header.php';

$student_id = (int)$_SESSION['student_id'];
$active_session_id = null;

// 1. Get Active Session & Required Fees
$session_res = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
$session_data = $session_res->fetch_assoc();
$active_session_id = $session_data['id'] ?? 0;

// Fetch total fees assigned to this student's program/session
$fee_stmt = $conn->prepare("SELECT SUM(amount) FROM fees WHERE session_id = ?");
$fee_stmt->bind_param("i", $active_session_id);
$fee_stmt->execute();
$fee_stmt->bind_result($total_fees_required);
$fee_stmt->fetch();
$fee_stmt->close();

// 2. Fetch Payment Analytics
$stats = ['paid' => 0, 'pending' => 0, 'history' => []];

$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN amount_paid ELSE 0 END) as approved_total,
        SUM(CASE WHEN status = 'pending' THEN amount_paid ELSE 0 END) as pending_total
    FROM payments WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($stats['paid'], $stats['pending']);
$stmt->fetch();
$stmt->close();

$balance = ($total_fees_required ?? 0) - $stats['paid'];
$balance = ($balance < 0) ? 0 : $balance;

// 3. Fetch Transaction History
$history_stmt = $conn->prepare("
    SELECT p.*, f.fee_name 
    FROM payments p 
    LEFT JOIN fees f ON p.fee_id = f.id 
    WHERE p.student_id = ? 
    ORDER BY p.created_at DESC");
$history_stmt->bind_param("i", $student_id);
$history_stmt->execute();
$history_res = $history_stmt->get_result();
while($row = $history_res->fetch_assoc()) { $stats['history'][] = $row; }
$history_stmt->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Financial Overview</h3>
            <p class="text-muted mb-0">Session: <span class="fw-bold text-primary"><?= $session_data['name'] ?></span></p>
        </div>
        <div class="d-flex gap-2">
             <a href="fee_invoice.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
                <i class="ti ti-file-download me-1"></i> Invoice
            </a>
            <a href="make_payment.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow">
                <i class="ti ti-plus me-1"></i> Pay Fees
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white overflow-hidden">
                <div class="card-body p-4 position-relative">
                    <div class="position-relative z-1">
                        <h6 class="text-white-50 fw-bold text-uppercase small">Total Required</h6>
                        <h2 class="fw-black text-white mb-0">₦<?= number_format($total_fees_required, 2) ?></h2>
                    </div>
                    <i class="ti ti-building-bank position-absolute end-0 bottom-0 me-n3 mb-n3 text-white opacity-10" style="font-size: 8rem;"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-success">
                <div class="card-body p-4">
                    <h6 class="text-muted fw-bold text-uppercase small">Total Paid</h6>
                    <h2 class="fw-black text-success mb-0">₦<?= number_format($stats['paid'], 2) ?></h2>
                    <p class="mb-0 mt-2 small text-muted">
                        <i class="ti ti-circle-check text-success"></i> Includes all approved transactions
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white border-start border-4 border-danger">
                <div class="card-body p-4">
                    <h6 class="text-muted fw-bold text-uppercase small">Outstanding Balance</h6>
                    <h2 class="fw-black text-danger mb-0">₦<?= number_format($balance, 2) ?></h2>
                    <p class="mb-0 mt-2 small text-muted">
                        <i class="ti ti-clock text-danger"></i> Please clear pending dues
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white p-4 border-bottom">
            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-history text-primary me-2"></i>Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Reference / Item</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="pe-4 text-end">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($stats['history'])): foreach($stats['history'] as $p): 
                            $status_class = match($p['status']) {
                                'approved' => 'bg-success',
                                'pending'  => 'bg-warning text-dark',
                                'rejected' => 'bg-danger',
                                default    => 'bg-secondary'
                            };
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= $p['transaction_id'] ?: 'N/A' ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['fee_name'] ?? 'Tuition/General') ?></small>
                            </td>
                            <td class="text-muted small"><?= strtoupper($p['payment_method']) ?></td>
                            <td class="text-end fw-black text-dark">₦<?= number_format($p['amount_paid'], 2) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $status_class ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 10px;">
                                    <?= strtoupper($p['status']) ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if($p['status'] == 'approved'): ?>
                                    <a href="receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light-primary text-primary fw-bold">
                                        <i class="ti ti-printer"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No transactions recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .btn-light-primary { background-color: rgba(93, 135, 255, 0.1); }
    .btn-light-primary:hover { background-color: #5D87FF; color: white !important; }
</style>

<?php require_once 'includes/footer.php'; ?>