<?php
/**
 * NAFCONM - Bursary Student Payments Dashboard
 * Features: Revenue KPIs, Teller Verification, DOM-Safe Modals, Dynamic Routing
 */
session_start();
require_once '../config/db.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$message = $error = "";
$current_session = "2025/2026"; // You can make this dynamic from a settings table later

// --- 2. HANDLE BURSARY VERIFICATION ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payment_action'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['payment_action'];
    $admin_note = htmlspecialchars($_POST['admin_note'] ?? '');

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE student_payments SET status = 'successful' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "success|Payment #$payment_id officially verified. The student's course portal is now unlocked.";
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        // Mark as failed so the student has to generate a new invoice/upload a new proof
        $stmt = $conn->prepare("UPDATE student_payments SET status = 'failed' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "warning|Payment rejected. The student will be required to upload a new proof of payment.";
        }
        $stmt->close();
    }
    
    // FIX: Dynamic redirect to whatever this file is currently named
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. FETCH REVENUE KPIs ---
$stats = $conn->query("
    SELECT 
        COUNT(id) as total_transactions,
        SUM(CASE WHEN status = 'successful' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_verifications
    FROM student_payments 
    WHERE session = '$current_session'
")->fetch_assoc();

// --- 4. FETCH ALL STUDENT PAYMENTS ---
$sql = "
    SELECT p.*, s.full_name, s.student_id_number, prog.name as program_name 
    FROM student_payments p 
    JOIN students s ON p.student_id = s.id 
    LEFT JOIN programs prog ON s.program_id = prog.id 
    ORDER BY 
        CASE WHEN p.status = 'pending' THEN 1 ELSE 2 END, 
        p.payment_date DESC
";
$payments = $conn->query($sql);

$page_title = "Student Payments Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .stat-card { transition: transform 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.success { border-left-color: var(--bs-success); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    
    .fw-black { font-weight: 900 !important; }
    .table-hover tbody tr { transition: 0.2s; cursor: pointer; }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Bursary Command Center</h3>
            <p class="text-muted small mb-0">Monitor online transactions and verify manual bank tellers for the <?= $current_session ?> session.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold"><i class="ti ti-download me-2"></i>Export Ledger</button>
        </div>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Cleared Revenue (<?= $current_session ?>)</p>
                        <h3 class="fw-black text-success mb-0">₦<?= number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-3 d-flex align-items-center justify-content-center">
                        <i class="ti ti-cash text-success fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Pending Verifications</p>
                        <h3 class="fw-black text-warning mb-0"><?= number_format($stats['pending_verifications'] ?? 0); ?> Tellers</h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-3 d-flex align-items-center justify-content-center">
                        <i class="ti ti-clock text-warning fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Transactions</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total_transactions'] ?? 0); ?> Logs</h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-3 d-flex align-items-center justify-content-center">
                        <i class="ti ti-receipt text-primary fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['msg'])): list($color, $text) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4 alert-dismissible fade show">
            <?= $text; unset($_SESSION['msg']); ?>
            <button type="button" class="btn-close mt-1" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-list-details text-primary me-2"></i>Payment Ledger</h6>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-light border-0"><i class="ti ti-search text-muted"></i></span>
                <input type="text" class="form-control bg-light border-0" placeholder="Search Invoice or Matric...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Student Info</th>
                            <th class="py-3 fw-bold">Invoice & Type</th>
                            <th class="py-3 fw-bold">Amount</th>
                            <th class="py-3 fw-bold text-center">Status</th>
                            <th class="py-3 fw-bold">Date</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $modals = ""; // Initialize DOM-Safe Modals string
                        
                        if($payments && $payments->num_rows > 0): 
                            while($row = $payments->fetch_assoc()): 
                                $status = strtolower($row['status']);
                                $is_online = !empty($row['reference']); // If it has a reference, it was Paystack
                        ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($row['full_name']) ?></h6>
                                    <small class="text-muted fw-bold"><?= htmlspecialchars($row['student_id_number']) ?> • <?= htmlspecialchars($row['program_name']) ?></small>
                                </td>
                                
                                <td>
                                    <span class="d-block fw-bold text-primary font-monospace"><?= htmlspecialchars($row['invoice_no']) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($row['payment_type']) ?></small>
                                </td>
                                
                                <td>
                                    <h6 class="fw-black text-dark mb-0">₦<?= number_format($row['amount'], 2) ?></h6>
                                    <?php if($is_online): ?>
                                        <small class="text-success fw-bold" style="font-size: 10px;"><i class="ti ti-bolt me-1"></i>ONLINE</small>
                                    <?php else: ?>
                                        <small class="text-info fw-bold" style="font-size: 10px;"><i class="ti ti-building-bank me-1"></i>TELLER</small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center">
                                    <?php if($status === 'successful'): ?>
                                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill"><i class="ti ti-check me-1"></i>Cleared</span>
                                    <?php elseif($status === 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill"><i class="ti ti-clock me-1"></i>Review</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill"><i class="ti ti-x me-1"></i>Failed</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-muted small fw-medium">
                                    <?= date('d M, Y h:i A', strtotime($row['payment_date'])) ?>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <?php if($status === 'pending' && !$is_online): ?>
                                        <button class="btn btn-warning btn-sm rounded-pill fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $row['id'] ?>">
                                            Verify Proof
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm rounded-pill fw-bold px-3 text-muted" disabled>
                                            <i class="ti ti-lock"></i> Locked
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php 
                            // --- CAPTURE MODALS SAFELY ---
                            ob_start(); 
                            if($status === 'pending' && !$is_online): 
                            ?>
                            <div class="modal fade" id="verifyModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                        <div class="modal-header bg-dark text-white p-3">
                                            <h5 class="modal-title fw-bold">Verify Payment: <?= htmlspecialchars($row['invoice_no']) ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4 bg-light text-center">
                                            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                                                <div class="text-start">
                                                    <small class="text-muted text-uppercase fw-bold d-block">Student</small>
                                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['full_name']) ?> (<?= htmlspecialchars($row['student_id_number']) ?>)</span>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted text-uppercase fw-bold d-block">Expected Amount</small>
                                                    <span class="fw-black text-danger fs-4">₦<?= number_format($row['amount'], 2) ?></span>
                                                </div>
                                            </div>

                                            <div class="bg-white p-2 rounded border shadow-sm mb-4 d-inline-block w-100 text-center" style="min-height: 250px;">
                                                <?php 
                                                $proof_file = $row['proof_of_payment'];
                                                $proof_ext = strtolower(pathinfo($proof_file, PATHINFO_EXTENSION));
                                                if($proof_ext === 'pdf'): ?>
                                                    <object data="../uploads/receipts/<?= $proof_file ?>" type="application/pdf" width="100%" height="400px">
                                                        <a href="../uploads/receipts/<?= $proof_file ?>" target="_blank" class="btn btn-primary mt-5">Download / View PDF</a>
                                                    </object>
                                                <?php else: ?>
                                                    <img src="../uploads/receipts/<?= $proof_file ?>" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                                                <?php endif; ?>
                                            </div>

                                            <form method="POST" action="">
                                                <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
                                                <div class="d-flex justify-content-center gap-3">
                                                    <button type="submit" name="payment_action" value="reject" class="btn btn-outline-danger fw-bold px-4 py-2 rounded-pill" onclick="return confirm('Reject this payment proof? The student will need to re-upload.')">
                                                        <i class="ti ti-x me-1"></i> Reject Proof
                                                    </button>
                                                    <button type="submit" name="payment_action" value="approve" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="return confirm('Approve this payment? This will permanently unlock the student\'s course registration.')">
                                                        <i class="ti ti-check me-1"></i> Approve & Clear Funds
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            endif;
                            $modals .= ob_get_clean(); 
                            ?>

                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="ti ti-receipt fs-1 mb-3 d-block"></i>No payment transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $modals ?>

<?php include 'includes/footer.php'; ?>