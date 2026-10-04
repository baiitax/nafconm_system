<?php
/**
 * NAFCONM - Master Payment Ledger
 * Features: Financial KPIs, Teller Verification Modals, Real-time Roster
 */
session_start();
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../login.php");
    exit;
}

// --- 1. HANDLE MANUAL PAYMENT VERIFICATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_action'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['verify_action']; // 'approve' or 'reject'
    $admin_note = htmlspecialchars(trim($_POST['admin_note'] ?? ''));

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE student_payments SET status = 'successful' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if($stmt->execute()) {
            $_SESSION['msg'] = "success|Payment officially verified and cleared.";
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE student_payments SET status = 'failed' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if($stmt->execute()) {
            $_SESSION['msg'] = "warning|Payment rejected. The student will be notified to try again.";
        }
        $stmt->close();
    }
    header("Location: student_payments.php");
    exit;
}

// --- 2. AGGREGATE FINANCIAL KPIs ---
$kpi_revenue = $conn->query("SELECT SUM(amount) FROM student_payments WHERE status IN ('successful', 'approved', 'paid')")->fetch_row()[0] ?? 0;
$kpi_pending = $conn->query("SELECT COUNT(id) FROM student_payments WHERE status = 'pending'")->fetch_row()[0] ?? 0;
$kpi_successful = $conn->query("SELECT COUNT(id) FROM student_payments WHERE status IN ('successful', 'approved', 'paid')")->fetch_row()[0] ?? 0;
$kpi_total_tx = $conn->query("SELECT COUNT(id) FROM student_payments")->fetch_row()[0] ?? 0;

// --- 3. FETCH MASTER LEDGER DATA ---
$sql = "
    SELECT p.*, s.full_name, s.student_id_number, pr.name as program_name 
    FROM student_payments p 
    JOIN students s ON p.student_id = s.id 
    LEFT JOIN programs pr ON s.program_id = pr.id
    ORDER BY 
        CASE WHEN p.status = 'pending' THEN 1 ELSE 2 END, 
        p.payment_date DESC
";
$payments = $conn->query($sql);

$page_title = "Payment Ledger";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; background: #fff; border-radius: 16px; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.success { border-left-color: var(--bs-success); }
    .stat-card.info { border-left-color: var(--bs-info); }
    
    .table-hover tbody tr { transition: background-color 0.2s; cursor: pointer; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    
    .status-badge { padding: 6px 12px; font-weight: 700; letter-spacing: 0.5px; border-radius: 8px; font-size: 11px; text-transform: uppercase; }
    .proof-img-container { width: 100%; height: 350px; border: 2px dashed #cbd5e1; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; }
    .proof-img-container img { width: 100%; height: 100%; object-fit: contain; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Financial Master Ledger</h3>
            <p class="text-muted small mb-0">Audit, verify, and track all institutional revenue.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <button class="btn btn-outline-dark fw-bold rounded-pill shadow-sm px-4" onclick="window.print()"><i class="ti ti-printer me-2"></i> Print Report</button>
            <a href="manage_fees.php" class="btn btn-primary fw-bold rounded-pill shadow-sm px-4"><i class="ti ti-settings me-2"></i> Fee Matrix</a>
        </div>
    </div>

    <!-- NOTIFICATION AREA -->
    <?php if(isset($_SESSION['msg'])): list($color, $text) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 p-4 shadow-sm mb-4 alert-dismissible fade show animate__animated animate__fadeInDown">
            <div class="d-flex align-items-center">
                <i class="ti <?= $color === 'success' ? 'ti-circle-check' : 'ti-alert-triangle' ?> fs-3 me-3"></i>
                <div><?= htmlspecialchars($text); ?></div>
            </div>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="alert"></button>
        </div>
    <?php unset($_SESSION['msg']); endif; ?>

    <!-- KPI WIDGETS -->
    <div class="row g-4 mb-5">
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card primary shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Revenue</p>
                        <h3 class="fw-black text-dark mb-0">₦<?= number_format($kpi_revenue / 1000000, 2) ?>M</h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-3 d-flex align-items-center justify-content-center text-primary"><i class="ti ti-currency-naira fs-3"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card warning shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Pending Verification</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($kpi_pending) ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-3 d-flex align-items-center justify-content-center text-warning"><i class="ti ti-hourglass fs-3"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card success shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Cleared Payments</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($kpi_successful) ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-3 d-flex align-items-center justify-content-center text-success"><i class="ti ti-circle-check fs-3"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card info shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Transactions</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($kpi_total_tx) ?></h3>
                    </div>
                    <div class="bg-info-subtle rounded-circle p-3 d-flex align-items-center justify-content-center text-info"><i class="ti ti-receipt-2 fs-3"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MASTER LEDGER TABLE -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-list-details text-primary me-2"></i>Transaction Roster</h5>
            <div class="input-group" style="max-width: 350px;">
                <span class="input-group-text bg-light border-0 px-3"><i class="ti ti-search text-muted"></i></span>
                <input type="text" id="ledgerSearch" class="form-control bg-light border-0 py-2" placeholder="Search by Invoice, Matric, or Name...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="ledgerTable">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Invoice Ref</th>
                            <th class="py-3 fw-bold">Student Details</th>
                            <th class="py-3 fw-bold text-end">Amount (NGN)</th>
                            <th class="py-3 fw-bold text-center">Date</th>
                            <th class="py-3 fw-bold text-center">Status</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments && $payments->num_rows > 0): while ($p = $payments->fetch_assoc()): 
                            $status = strtolower($p['status']);
                            $method = strtolower($p['payment_method'] ?? 'online');
                            $is_pending_manual = ($status === 'pending' && ($method === 'manual' || $method === 'bank_transfer'));
                        ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <span class="font-monospace text-primary fw-bold"><?= htmlspecialchars($p['invoice_no']) ?></span>
                                    <small class="d-block text-muted text-uppercase" style="font-size: 10px;">
                                        <i class="ti <?= $method === 'online' ? 'ti-credit-card' : 'ti-building-bank' ?> me-1"></i><?= $method ?>
                                    </small>
                                </td>
                                <td>
                                    <h6 class="fw-bold text-dark mb-0"><?= ucwords(strtolower($p['full_name'])) ?></h6>
                                    <small class="font-monospace text-muted fw-bold"><?= htmlspecialchars($p['student_id_number']) ?></small>
                                </td>
                                <td class="text-end">
                                    <h6 class="fw-black text-dark mb-0">₦<?= number_format($p['amount'], 2) ?></h6>
                                </td>
                                <td class="text-center text-muted small fw-medium">
                                    <?= date('d M, Y', strtotime($p['payment_date'])) ?><br>
                                    <span style="font-size: 10px;"><?= date('h:i A', strtotime($p['payment_date'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($status === 'successful' || $status === 'paid' || $status === 'approved'): ?>
                                        <span class="status-badge bg-success-subtle text-success border border-success-subtle"><i class="ti ti-check me-1"></i>Cleared</span>
                                    <?php elseif ($status === 'pending'): ?>
                                        <span class="status-badge bg-warning-subtle text-warning border border-warning-subtle"><i class="ti ti-hourglass me-1"></i>Pending</span>
                                    <?php else: ?>
                                        <span class="status-badge bg-danger-subtle text-danger border border-danger-subtle"><i class="ti ti-x me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($is_pending_manual): ?>
                                        <button class="btn btn-warning btn-sm rounded-pill fw-bold shadow-sm px-3 animate__animated animate__pulse animate__infinite" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $p['id'] ?>">
                                            <i class="ti ti-eye me-1"></i> Verify Teller
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm rounded-pill fw-bold px-3 text-muted" onclick="alert('Transaction receipt is locked for auditing.')">
                                            <i class="ti ti-lock me-1"></i> Locked
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- DOM-Safe Modal for Teller Verification -->
                            <?php if ($is_pending_manual): ?>
                                <div class="modal fade" id="verifyModal<?= $p['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                            <div class="modal-header bg-dark text-white p-3">
                                                <h5 class="modal-title fw-bold">Verify Bank Teller</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 bg-light">
                                                <div class="row g-4">
                                                    <div class="col-md-5">
                                                        <h6 class="fw-bold text-uppercase small text-muted mb-3">Transaction Details</h6>
                                                        <ul class="list-group list-group-flush rounded-3 border mb-3">
                                                            <li class="list-group-item bg-white d-flex justify-content-between small"><span class="text-muted">Student:</span> <strong class="text-end"><?= ucwords(strtolower($p['full_name'])) ?></strong></li>
                                                            <li class="list-group-item bg-white d-flex justify-content-between small"><span class="text-muted">Matric No:</span> <strong><?= htmlspecialchars($p['student_id_number']) ?></strong></li>
                                                            <li class="list-group-item bg-white d-flex justify-content-between small"><span class="text-muted">Invoice Ref:</span> <strong><?= htmlspecialchars($p['invoice_no']) ?></strong></li>
                                                            <li class="list-group-item bg-primary-subtle text-primary d-flex justify-content-between"><span class="fw-bold">Amount Due:</span> <strong class="fs-5">₦<?= number_format($p['amount'], 2) ?></strong></li>
                                                        </ul>
                                                        
                                                        <form method="POST">
                                                            <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" name="admin_note" class="form-control" id="note<?= $p['id'] ?>" placeholder="Optional Note">
                                                                <label for="note<?= $p['id'] ?>">Auditor's Note (Optional)</label>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="submit" name="verify_action" value="reject" class="btn btn-outline-danger w-50 fw-bold rounded-pill" onclick="return confirm('Reject this teller? The student will remain unpaid.')">Reject</button>
                                                                <button type="submit" name="verify_action" value="approve" class="btn btn-success w-50 fw-bold shadow-sm rounded-pill" onclick="return confirm('Approve this payment? The student will be financially cleared.')">Approve</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <h6 class="fw-bold text-uppercase small text-muted mb-3">Uploaded Proof of Payment</h6>
                                                        <div class="proof-img-container shadow-sm bg-white border p-2">
                                                            <?php if(!empty($p['proof_of_payment'])): ?>
                                                                <?php 
                                                                $ext = strtolower(pathinfo($p['proof_of_payment'], PATHINFO_EXTENSION));
                                                                if($ext === 'pdf'): ?>
                                                                    <object data="../uploads/payments/<?= $p['proof_of_payment'] ?>" type="application/pdf" width="100%" height="100%">
                                                                        <p class="text-muted">PDF Viewer not supported. <a href="../uploads/payments/<?= $p['proof_of_payment'] ?>" target="_blank" class="text-primary fw-bold">Click to Download</a></p>
                                                                    </object>
                                                                <?php else: ?>
                                                                    <img src="../uploads/payments/<?= $p['proof_of_payment'] ?>" alt="Bank Teller">
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <div class="text-center text-muted">
                                                                    <i class="ti ti-photo-off fs-1 d-block mb-2"></i> No file uploaded.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="ti ti-inbox fs-1 d-block mb-3"></i>No transactions found in the ledger.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Simple Front-End Search Filter for Ledger
document.getElementById('ledgerSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#ledgerTable tbody tr');
    
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>