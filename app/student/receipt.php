<?php
/**
 * NAFCONM - Official Payment Receipt Generator
 * Features: Financial Security Checks, A4 Print Engine, Live QR Verification
 */
session_start();
$page_title = 'Official Payment Receipt';
require_once '../config/db.php';

// Security Check: Only logged-in students
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h3>Invalid Request</h3><p>No receipt ID provided.</p><a href='payment_history.php'>Go Back</a></div>");
}

$payment_id = (int)$_GET['id'];
$student_id = (int)$_SESSION['student_id'];

// 1. Fetch Highly Secure Payment & Student Data
// We strictly enforce p.student_id = ? AND p.status = 'approved' to prevent unauthorized access
$sql = "
    SELECT p.id, p.amount_paid, p.payment_method, p.transaction_id, p.created_at, p.review_date,
           f.fee_name, s.name as session_name,
           st.full_name, st.student_id_number, 
           d.name as dept_name, pr.name as program_name
    FROM payments p
    LEFT JOIN fees f ON p.fee_id = f.id
    LEFT JOIN sessions s ON p.session_id = s.id
    JOIN students st ON p.student_id = st.id
    LEFT JOIN departments d ON st.department_id = d.id
    LEFT JOIN programs pr ON st.program_id = pr.id
    WHERE p.id = ? AND p.student_id = ? AND p.status = 'approved'
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $payment_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    require_once 'includes/header.php';
    echo '
    <div class="container-fluid py-5">
        <div class="card border-0 shadow-sm rounded-4 text-center p-5 max-w-50 mx-auto">
            <i class="ti ti-shield-x fs-1 text-danger mb-3 d-block"></i> 
            <h4 class="fw-bold">Access Denied</h4>
            <p class="text-muted">This receipt does not exist, belongs to another student, or the payment has not been approved yet.</p>
            <a href="payment_history.php" class="btn btn-primary rounded-pill px-4 mt-2">Return to Ledger</a>
        </div>
    </div>';
    require_once 'includes/footer.php';
    exit;
}

$receipt = $result->fetch_assoc();
$stmt->close();

// Format Transaction Reference
$transaction_ref = !empty($receipt['transaction_id']) ? strtoupper($receipt['transaction_id']) : 'NAF-MANUAL-' . str_pad($receipt['id'], 6, '0', STR_PAD_LEFT);
$payment_date = !empty($receipt['review_date']) ? date('F j, Y', strtotime($receipt['review_date'])) : date('F j, Y', strtotime($receipt['created_at']));

// Generate QR Code Data String
$qr_data = "RECEIPT|" . $transaction_ref . "|" . $receipt['student_id_number'] . "|NGN" . $receipt['amount_paid'];

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    /* --- Professional Receipt Print Engine --- */
    .receipt-wrapper { 
        background: #fff; 
        max-width: 210mm; /* A4 Width */
        min-height: 297mm; /* A4 Height */
        margin: 20px auto; 
        padding: 30mm 20mm; 
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
        font-size: 7rem;
        font-weight: 900;
        color: rgba(0, 64, 128, 0.03);
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
        text-align: center;
        line-height: 1;
    }

    .receipt-header { 
        text-align: center; 
        border-bottom: 2px solid #004080; 
        padding-bottom: 20px; 
        margin-bottom: 30px; 
        z-index: 1; 
        position: relative; 
    }
    .receipt-header h2 { color: #004080; font-family: 'Arial Black', Impact, sans-serif; margin: 0; text-transform: uppercase; font-size: 24px; letter-spacing: 1px; }
    .receipt-header p { margin: 2px 0; font-size: 13px; color: #444; }
    
    .receipt-title {
        text-align: center;
        font-size: 20px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 30px;
        color: #222;
        background: rgba(0, 64, 128, 0.05);
        padding: 10px;
        border-radius: 5px;
        border: 1px dashed #004080;
    }

    .meta-box {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        font-size: 13px;
        z-index: 1; 
        position: relative;
    }
    .meta-col { width: 48%; }
    .meta-table { width: 100%; }
    .meta-table td { padding: 4px 0; }
    .meta-table .label { font-weight: bold; width: 120px; color: #555; }
    .meta-table .val { font-weight: bold; color: #000; border-bottom: 1px solid #eee; }

    .financial-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; z-index: 1; position: relative; }
    .financial-table th { background-color: #004080 !important; color: #fff !important; padding: 12px; text-transform: uppercase; font-size: 12px; text-align: left; border: 1px solid #004080; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .financial-table td { border: 1px solid #ddd; padding: 15px 12px; font-size: 14px; }
    .financial-table .total-row td { background-color: #f8f9fa !important; font-weight: 900; font-size: 16px; border-top: 2px solid #004080; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    .receipt-footer { margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end; z-index: 1; position: relative; }
    .sig-box { text-align: center; width: 200px; }
    .sig-line { border-top: 1px solid #000; padding-top: 5px; font-size: 13px; font-weight: bold; text-transform: uppercase; }
    
    .stamp-area {
        width: 100px;
        height: 100px;
        border: 2px dashed #dc3545;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc3545;
        font-weight: bold;
        font-size: 10px;
        text-align: center;
        transform: rotate(-15deg);
        opacity: 0.6;
        margin: 0 auto 10px auto;
    }

    .no-print-btn { text-align: center; margin-bottom: 20px; }

    @media print {
        body { background: white; margin: 0; }
        .no-print-btn, .left-sidebar, .app-header, footer { display: none !important; }
        .body-wrapper { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .receipt-wrapper { border: none; box-shadow: none; margin: 0; padding: 0; width: 100%; min-height: auto; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="container-fluid py-4">
    <div class="no-print-btn">
        <button onclick="window.print()" class="btn btn-primary shadow rounded-pill px-5 py-2 fw-bold fs-5">
            <i class="ti ti-printer me-2"></i> Print Official Receipt
        </button>
        <a href="payment_history.php" class="btn btn-outline-dark rounded-pill px-4 py-2 ms-2 fw-bold">Back to Ledger</a>
        <p class="text-muted small mt-2"><i class="ti ti-info-circle"></i> Set paper size to A4 and enable "Background Graphics" in your print settings.</p>
    </div>

    <div class="receipt-wrapper">
        <div class="watermark">PAID IN FULL<br>NAFCONM</div>

        <div class="receipt-header">
            <img src="../assets/images/logos/favicon.png" height="75" class="mb-2" alt="College Logo">
            <h2>Nafisatu College of Nursing Sciences</h2>
            <p>BURSARY & FINANCIAL SERVICES DIVISION</p>
            <p>Musa Usman Secretariat, Maiduguri, Borno State.</p>
        </div>

        <div class="receipt-title">Official Payment Receipt</div>

        <div class="meta-box">
            <div class="meta-col">
                <table class="meta-table">
                    <tr><td class="label">Billed To:</td><td class="val text-uppercase"><?= htmlspecialchars($receipt['full_name']); ?></td></tr>
                    <tr><td class="label">Matric No:</td><td class="val"><?= htmlspecialchars($receipt['student_id_number']); ?></td></tr>
                    <tr><td class="label">Department:</td><td class="val"><?= htmlspecialchars(strtoupper($receipt['dept_name'] ?? 'N/A')); ?></td></tr>
                    <tr><td class="label">Academic Session:</td><td class="val"><?= htmlspecialchars($receipt['session_name']); ?></td></tr>
                </table>
            </div>
            <div class="meta-col">
                <table class="meta-table">
                    <tr><td class="label">Receipt No:</td><td class="val text-danger"><?= str_pad($receipt['id'], 8, '0', STR_PAD_LEFT); ?></td></tr>
                    <tr><td class="label">Date of Payment:</td><td class="val"><?= $payment_date; ?></td></tr>
                    <tr><td class="label">Payment Method:</td><td class="val text-uppercase"><?= htmlspecialchars($receipt['payment_method']); ?></td></tr>
                    <tr><td class="label">Transaction Ref:</td><td class="val"><?= $transaction_ref; ?></td></tr>
                </table>
            </div>
        </div>

        <table class="financial-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">S/N</th>
                    <th>Payment Description</th>
                    <th style="width: 200px; text-align: right;">Amount (NGN)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: center; font-weight: bold;">1</td>
                    <td>
                        <strong><?= htmlspecialchars($receipt['fee_name'] ?? 'General School Fee'); ?></strong><br>
                        <small style="color: #666;">Cleared for <?= htmlspecialchars($receipt['session_name']); ?> Academic Session</small>
                    </td>
                    <td style="text-align: right; font-family: monospace; font-size: 16px;">
                        <?= number_format($receipt['amount_paid'], 2); ?>
                    </td>
                </tr>
                <tr><td>&nbsp;</td><td></td><td></td></tr>
                <tr><td>&nbsp;</td><td></td><td></td></tr>
                
                <tr class="total-row">
                    <td colspan="2" style="text-align: right; padding-right: 20px; text-transform: uppercase;">Total Amount Paid:</td>
                    <td style="text-align: right; font-family: monospace; color: #004080;">
                        ₦<?= number_format($receipt['amount_paid'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #004080; font-size: 13px; margin-bottom: 40px; z-index: 1; position: relative;">
            <strong>Amount in Words:</strong> 
            <em style="text-transform: capitalize;">
                <?php 
                    if (class_exists('NumberFormatter')) {
                        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
                        echo $f->format($receipt['amount_paid']) . " Naira Only.";
                    } else {
                        // Fallback if the PHP INTL extension is missing on the server
                        echo "Naira " . number_format($receipt['amount_paid'], 2) . " Only.";
                    }
                ?>
            </em>
        </div>

        <div class="receipt-footer">
            <div class="sig-box">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($qr_data) ?>" alt="Verification QR" style="border: 2px solid #004080; padding: 3px; border-radius: 5px;">
                <div style="font-size: 9px; font-weight: bold; margin-top: 5px; color: #555;">SCAN RECEIPT TO VERIFY</div>
            </div>

            <div class="sig-box">
                <div class="stamp-area">
                    OFFICIAL<br>BURSARY<br>STAMP
                </div>
            </div>

            <div class="sig-box">
                <div class="sig-line">Bursar / Financial Officer</div>
                <div style="font-size: 11px; margin-top: 5px;">Sign & Date</div>
            </div>
        </div>

        <div style="margin-top: 50px; border-top: 1px solid #ccc; padding-top: 10px; text-align: center; font-size: 10px; color: #777;">
            <strong>DISCLAIMER:</strong> This is a system-generated receipt. It remains valid only if the corresponding funds have fully cleared in the College's designated bank accounts. 
            <br>Generated on <?= date('l, F j, Y \a\t h:i A') ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>