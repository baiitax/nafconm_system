<?php
/**
 * NAFCONM - Professional Student Fee Invoice & Statement
 * Features: A4 Print Engine, Ledger Deduction Math, Accurate Address
 */
session_start();
$page_title = 'Session Fee Invoice';
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$student = null;
$fees = [];
$applied_payments = [];

// 1. Optimized Fetch: Student, Program, Department, and Active Session
$sql = "
    SELECT s.*, p.name as program_name, d.name as dept_name, ses.name as session_name, ses.id as session_id
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    JOIN sessions ses ON ses.is_active = 1
    WHERE s.user_id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
    $session_id = (int)$student['session_id'];
    $student_id = (int)$student['id'];

    // 2. Fetch fees assessed for the active session
    $fee_stmt = $conn->prepare("SELECT fee_name, amount FROM fees WHERE session_id = ?");
    $fee_stmt->bind_param("i", $session_id);
    $fee_stmt->execute();
    $fees = $fee_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fee_stmt->close();

    // 3. Fetch APPROVED payments to display in the ledger
    $pay_list_stmt = $conn->prepare("
        SELECT amount_paid, payment_method, transaction_id, review_date, created_at 
        FROM payments 
        WHERE student_id = ? AND session_id = ? AND status = 'approved'
        ORDER BY created_at ASC
    ");
    $pay_list_stmt->bind_param("ii", $student_id, $session_id);
    $pay_list_stmt->execute();
    $applied_payments = $pay_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pay_list_stmt->close();

    // 4. Fetch PENDING payments for the warning note
    $pending_stmt = $conn->prepare("SELECT SUM(amount_paid) as total_pending FROM payments WHERE student_id = ? AND session_id = ? AND status = 'pending'");
    $pending_stmt->bind_param("ii", $student_id, $session_id);
    $pending_stmt->execute();
    $pending_res = $pending_stmt->get_result()->fetch_assoc();
    $total_pending = (float)($pending_res['total_pending'] ?? 0);
    $pending_stmt->close();
}
$stmt->close();

if (!$student) {
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    require_once 'includes/topbar.php';
    echo '
    <div class="container-fluid py-5">
        <div class="card border-0 shadow-sm rounded-4 text-center p-5 max-w-50 mx-auto">
            <i class="ti ti-alert-triangle fs-1 text-warning mb-3 d-block"></i> 
            <h4 class="fw-bold">No Active Session</h4>
            <p class="text-muted">We could not locate an active academic session for your profile. Please contact the ICT unit.</p>
        </div>
    </div>';
    require_once 'includes/footer.php';
    exit;
}

// Image Path Resolution
$photo_path = !empty($student['photo_path']) ? '../uploads/admissions/' . htmlspecialchars($student['photo_path']) : '../assets/images/profile/user-1.jpg';

// Financial Calculations
$total_fees = array_sum(array_column($fees, 'amount'));
$total_approved = array_sum(array_column($applied_payments, 'amount_paid'));
$balance = max(0, $total_fees - $total_approved);

// Dynamic Status Badge Logic
if ($balance == 0 && $total_fees > 0) {
    $status_label = "FULLY CLEARED";
    $status_color = "#198754"; 
    $status_bg = "rgba(25, 135, 84, 0.1)";
} elseif ($total_pending > 0 && ($total_approved + $total_pending) >= $total_fees) {
    $status_label = "PENDING VERIFICATION";
    $status_color = "#fd7e14"; 
    $status_bg = "rgba(253, 126, 20, 0.1)";
} elseif ($total_approved > 0) {
    $status_label = "PARTIALLY PAID";
    $status_color = "#0dcaf0"; 
    $status_bg = "rgba(13, 202, 240, 0.1)";
} else {
    $status_label = "UNPAID";
    $status_color = "#dc3545"; 
    $status_bg = "rgba(220, 53, 69, 0.1)";
}

// QR Code Data
$invoice_ref = "INV-" . $student['student_id_number'] . "-" . $session_id;
$qr_data = $invoice_ref . "|BAL:NGN" . $balance;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    /* --- Professional A4 Print Engine --- */
    .invoice-wrapper { 
        background: #fff; 
        max-width: 210mm; /* A4 Width */
        min-height: 297mm; /* A4 Height */
        margin: 20px auto; 
        padding: 25mm 20mm; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        position: relative;
        color: #111;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        line-height: 1.5;
        overflow: hidden;
    }
    
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-size: 8rem;
        font-weight: 900;
        color: rgba(0, 64, 128, 0.03);
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
        text-align: center;
        line-height: 1;
    }

    .status-stamp {
        position: absolute;
        top: 40mm;
        right: 20mm;
        border: 4px solid <?= $status_color ?>;
        color: <?= $status_color ?>;
        background: <?= $status_bg ?>;
        padding: 10px 20px;
        font-size: 22px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 2px;
        transform: rotate(15deg);
        border-radius: 8px;
        z-index: 1;
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact;
    }

    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #004080; padding-bottom: 20px; margin-bottom: 30px; z-index: 2; position: relative; }
    .invoice-header h2 { color: #004080; font-family: 'Arial Black', Impact, sans-serif; margin: 0; text-transform: uppercase; font-size: 22px; letter-spacing: 1px; }
    .invoice-header p { margin: 2px 0; font-size: 13px; color: #444; }
    
    .meta-box { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 13px; z-index: 1; position: relative; }
    .meta-col { width: 78%; }
    .meta-table { width: 100%; }
    .meta-table td { padding: 6px 0; }
    .meta-table .label { font-weight: bold; width: 120px; color: #555; padding: 4px; }
    .meta-table .val { font-weight: bold; color: #000; border-bottom: 1px solid #eee; }

    .student-photo { width: 100px; height: 120px; border: 2px solid #004080; padding: 3px; object-fit: cover; background: #fff; float: right; }

    .financial-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; z-index: 1; position: relative; }
    .financial-table th { background-color: #004080 !important; color: #fff !important; padding: 10px 12px; text-transform: uppercase; font-size: 12px; text-align: left; border: 1px solid #004080; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .financial-table td { border: 1px solid #ddd; padding: 10px 12px; font-size: 14px; }
    
    .summary-section { width: 350px; float: right; margin-bottom: 40px; z-index: 1; position: relative; }
    .summary-row { display: flex; justify-content: space-between; padding: 8px 12px; font-size: 14px; border-bottom: 1px solid #eee; }
    .summary-row.total { background-color: #f8f9fa !important; font-weight: 900; font-size: 18px; border-top: 2px solid #004080; border-bottom: 2px solid #004080; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .clear { clear: both; }

    .invoice-footer { margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end; z-index: 1; position: relative; border-top: 1px solid #ccc; padding-top: 20px;}
    
    .no-print-btn { text-align: center; margin-bottom: 20px; }

    @media print {
        body { background: white; margin: 0; }
        .no-print-btn, .left-sidebar, .app-header, footer { display: none !important; }
        .body-wrapper { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .invoice-wrapper { border: none; box-shadow: none; margin: 0; padding: 0; width: 100%; min-height: auto; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="container-fluid py-4">
    <div class="no-print-btn">
        <button onclick="window.print()" class="btn btn-dark shadow rounded-pill px-5 py-2 fw-bold fs-5 me-2">
            <i class="ti ti-printer me-2"></i> Print Invoice
        </button>
        <?php if ($balance > 0): ?>
            <a href="make_payment.php" class="btn btn-primary shadow rounded-pill px-5 py-2 fw-bold fs-5">
                <i class="ti ti-credit-card me-2"></i> Pay Balance
            </a>
        <?php endif; ?>
    </div>

    <div class="invoice-wrapper">
        <div class="watermark">NAFCONM<br>STATEMENT</div>
        <div class="status-stamp"><?= $status_label ?></div>

        <div class="invoice-header">
            <div>
                <img src="../assets/images/logos/favicon.png" height="75" class="mb-2" alt="College Logo">
                <h2>Nafisatu College of Nursing Sciences</h2>
                <p>Kwankwaso Town, Madobi LG, Kano State.</p>
                <p>Email: bursary@nafconm.edu.ng</p>
            </div>
            <div style="text-align: right;">
                <h4 style="color: #004080; font-weight: 900; margin-bottom: 5px;">STATEMENT OF ACCOUNT</h4>
                <p style="font-weight: bold; font-size: 15px;">INV No: <span style="color:#dc3545;"><?= $invoice_ref ?></span></p>
                <p>Date Issued: <?= date('F j, Y') ?></p>
            </div>
        </div>

        <div class="meta-box">
            <div class="meta-col">
                <h6 style="font-weight: bold; color: #004080; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">BILLED TO:</h6>
                <table class="meta-table">
                    <tr><td class="label">Full Name:</td><td class="val text-uppercase"><?= htmlspecialchars($student['full_name']); ?></td></tr>
                    <tr><td class="label">Matric No:</td><td class="val"><?= htmlspecialchars($student['student_id_number']); ?></td></tr>
                    <tr><td class="label">Department:</td><td class="val"><?= htmlspecialchars(strtoupper($student['dept_name'] ?? 'N/A')); ?></td></tr>
                    <tr><td class="label">Program:</td><td class="val"><?= htmlspecialchars(strtoupper($student['program_name'] ?? 'N/A')); ?></td></tr>
                    <tr><td class="label">Academic Session:</td><td class="val"><?= htmlspecialchars($student['session_name']); ?></td></tr>
                    <tr><td class="label">Level:</td><td class="val"><?= $student['current_level']; ?> Level</td></tr>
                </table>
            </div>
            
        </div>

        <h6 style="font-weight: bold; color: #004080; margin-bottom: 10px;">1. ASSESSED FEES</h6>
        <table class="financial-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">S/N</th>
                    <th>Fee Description</th>
                    <th style="width: 200px; text-align: right;">Amount (NGN)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($fees)): foreach ($fees as $index => $fee): ?>
                <tr>
                    <td style="text-align: center; font-weight: bold;"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($fee['fee_name']) ?></td>
                    <td style="text-align: right; font-family: monospace; font-size: 15px;">
                        <?= number_format($fee['amount'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align: center; padding: 20px;">No fees assessed for this academic session.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h6 style="font-weight: bold; color: #198754; margin-bottom: 10px; margin-top: 30px;">2. PAYMENTS APPLIED</h6>
        <table class="financial-table">
            <thead style="background-color: #198754 !important;">
                <tr>
                    <th style="background-color: #198754 !important; border-color: #198754; width: 120px;">Date</th>
                    <th style="background-color: #198754 !important; border-color: #198754;">Reference / Method</th>
                    <th style="background-color: #198754 !important; border-color: #198754; width: 200px; text-align: right;">Amount Paid (NGN)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($applied_payments)): foreach ($applied_payments as $pay): 
                    $pay_date = !empty($pay['review_date']) ? $pay['review_date'] : $pay['created_at'];
                    $ref = !empty($pay['transaction_id']) ? $pay['transaction_id'] : 'MANUAL TRANSFER';
                ?>
                <tr>
                    <td style="font-size: 13px;"><?= date('d/m/Y', strtotime($pay_date)) ?></td>
                    <td>
                        <span style="font-family: monospace; font-weight: bold;"><?= strtoupper($ref) ?></span><br>
                        <small style="color: #666; text-transform: uppercase;"><?= htmlspecialchars($pay['payment_method']) ?> PAYMENT</small>
                    </td>
                    <td style="text-align: right; font-family: monospace; font-size: 15px; color: #198754; font-weight: bold;">
                        - <?= number_format($pay['amount_paid'], 2) ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align: center; padding: 20px; color: #dc3545; font-weight: bold;">No approved payments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-section">
            <div class="summary-row">
                <span class="text-muted">Total Assessed Fees:</span>
                <span style="font-family: monospace; font-weight: bold;">₦<?= number_format($total_fees, 2) ?></span>
            </div>
            
            <div class="summary-row" style="color: #198754;">
                <span>Total Approved Payments:</span>
                <span style="font-family: monospace; font-weight: bold;">- ₦<?= number_format($total_approved, 2) ?></span>
            </div>
            
            <?php if ($total_pending > 0): ?>
            <div class="summary-row" style="color: #fd7e14; font-size: 12px; font-style: italic;">
                <span>*Pending Verification:</span>
                <span style="font-family: monospace;">(₦<?= number_format($total_pending, 2) ?>)</span>
            </div>
            <?php endif; ?>

            <div class="summary-row total" style="color: <?= ($balance > 0) ? '#dc3545' : '#004080' ?>;">
                <span>BALANCE DUE:</span>
                <span style="font-family: monospace;">₦<?= number_format($balance, 2) ?></span>
            </div>
        </div>
        <div class="clear"></div>

        <div class="invoice-footer">
            <div style="width: 60%; font-size: 11px; color: #555;">
                <p style="font-weight: bold; color: #000; margin-bottom: 5px;">Important Notices:</p>
                1. Payments can be made securely online via the student portal.<br>
                2. If paying via Bank Transfer, upload your proof of payment on the portal.<br>
                3. Course Registration will remain locked until the <strong>Balance Due</strong> is ₦0.00.<br>
                <?php if ($total_pending > 0): ?>
                <span style="color: #fd7e14; font-weight: bold;">* You have pending payments. Please do not re-pay until Bursary reviews your upload.</span><br>
                <?php endif; ?>
                <br><em>System Generated Document. Valid without signature.</em>
            </div>
            <div style="text-align: center;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode($qr_data) ?>" alt="QR Code" style="border: 2px solid #000; padding: 2px; margin-bottom: 5px;">
                <div style="font-size: 9px; font-weight: bold;">SCAN TO VERIFY</div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>