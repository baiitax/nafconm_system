<?php
/**
 * NAFCONM - Official Bursary Receipt
 * Features: A4 Print CSS, Live QR Code Generation, Anti-Forgery UI
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$current_session = "2025/2026";

// 1. Fetch Student Profile
$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

// 2. Fetch Specific or Latest Payment
if (isset($_GET['id'])) {
    // Fetch the specific historical receipt requested
    $receipt_id = intval($_GET['id']);
    $stmt_pay = $conn->prepare("SELECT * FROM student_payments WHERE id = ? AND student_id = ? AND status IN ('successful', 'approved', 'paid') LIMIT 1");
    $stmt_pay->bind_param("ii", $receipt_id, $student['id']);
} else {
    // Fallback: Fetch the latest successful payment if no ID is provided (e.g. clicking from Dashboard)
    $stmt_pay = $conn->prepare("SELECT * FROM student_payments WHERE student_id = ? AND session = ? AND payment_type = 'School Fees' AND status IN ('successful', 'approved', 'paid') ORDER BY id DESC LIMIT 1");
    $stmt_pay->bind_param("is", $student['id'], $current_session);
}

$stmt_pay->execute();
$payment = $stmt_pay->get_result()->fetch_assoc();
$stmt_pay->close();

if (!$payment) die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h3>No Receipt Available</h3><p>Your payment is either pending verification or has not been made.</p></div>");

// Format Date
$payment_date = date('F jS, Y \a\t h:i A', strtotime($payment['payment_date']));
$qr_data = urlencode("NAFCONM RECEIPT | INV: " . $payment['invoice_no'] . " | MATRIC: " . $student['student_id_number']);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . $qr_data;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Receipt - <?= $payment['invoice_no'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #525659; margin: 0; padding: 20px; color: #111; }
        .receipt-container { background: #fff; width: 180mm; min-height: 250mm; margin: 0 auto; padding: 15mm; box-shadow: 0 5px 20px rgba(0,0,0,0.2); position: relative; border-top: 15px solid #13deb9; box-sizing: border-box; }
        
        .r-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed #ccc; padding-bottom: 20px; margin-bottom: 20px; }
        .r-header-left h1 { margin: 0; color: #004080; font-size: 22px; font-family: 'Segoe UI', sans-serif; font-weight: 900;}
        .r-header-left p { margin: 3px 0 0 0; font-size: 12px; color: #666; }
        .r-header-right h2 { margin: 0; color: #13deb9; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; }
        .r-header-right p { margin: 5px 0 0 0; font-weight: bold; font-size: 14px; }

        .bill-to { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .bill-box { width: 48%; }
        .bill-box h5 { margin: 0 0 10px 0; color: #999; text-transform: uppercase; font-size: 12px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .bill-box p { margin: 4px 0; font-size: 14px; font-weight: bold; }

        .pay-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .pay-table th { background: #004080; color: #fff; text-align: left; padding: 10px; font-size: 12px; text-transform: uppercase; }
        .pay-table td { padding: 12px 10px; border-bottom: 1px solid #ddd; font-size: 14px; font-weight: bold; }
        .pay-table .amount { text-align: right; }

        .total-box { display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 40px; }
        .total-box .paid-stamp { color: #13deb9; font-size: 24px; font-weight: 900; border: 3px solid #13deb9; padding: 5px 15px; border-radius: 5px; transform: rotate(-5deg); display: inline-block; }
        .total-box .total-amt { text-align: right; }
        .total-box .total-amt h3 { margin: 0; font-size: 28px; color: #000; }
        .total-box .total-amt p { margin: 0; color: #666; font-size: 12px; text-transform: uppercase; }

        .footer-sig { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 50px; }
        .qr-code { width: 120px; height: 120px; padding: 5px; border: 1px solid #ddd; }
        .sig-line { width: 200px; border-top: 1px solid #000; text-align: center; padding-top: 5px; font-size: 12px; font-weight: bold; }

        .watermark { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 80px; color: rgba(19, 222, 185, 0.05); font-weight: 900; z-index: 0; pointer-events: none; white-space: nowrap; }

        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .receipt-container { box-shadow: none; width: 100%; border-top: none; }
            .no-print { display: none !important; }
            /* Force exact colors in print */
            .r-header-right h2, .paid-stamp { color: #13deb9 !important; -webkit-print-color-adjust: exact; }
            .pay-table th { background: #004080 !important; color: white !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div style="text-align: center; margin-bottom: 20px;" class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #13deb9; color: #000; border: none; font-size: 16px; cursor: pointer; border-radius: 5px; font-weight: bold;">🖨️ Print Receipt</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #333; color: white; border: none; font-size: 16px; cursor: pointer; border-radius: 5px; font-weight: bold; margin-left: 10px;">Close Window</button>
    </div>

    <div class="receipt-container">
        <div class="watermark">BURSARY CLEARED</div>

        <div style="position: relative; z-index: 1;">
            <div class="r-header">
                <div class="r-header-left">
                    <h1>NAFCONM</h1>
                    <p>Nafisatu College of Nursing & Midwifery</p>
                    <p>Bursary Department, Kwankwaso, Kano</p>
                </div>
                <div class="r-header-right" style="text-align: right;">
                    <h2>RECEIPT</h2>
                    <p>No: <?= htmlspecialchars($payment['invoice_no']) ?></p>
                </div>
            </div>

            <div class="bill-to">
                <div class="bill-box">
                    <h5>Received From (Student)</h5>
                    <p><?= strtoupper(htmlspecialchars($student['full_name'])) ?></p>
                    <p>Matric No: <?= htmlspecialchars($student['student_id_number']) ?></p>
                    <p>Program: <?= htmlspecialchars($student['program_name']) ?> (<?= htmlspecialchars($student['current_level'] ?? '100L') ?>)</p>
                </div>
                <div class="bill-box" style="text-align: right;">
                    <h5>Payment Details</h5>
                    <p>Date: <?= $payment_date ?></p>
                    <p>Session: <?= $payment['session'] ?></p>
                    <p>Method: <?= !empty($payment['reference']) ? 'Online (Paystack)' : 'Bank Deposit / Transfer' ?></p>
                </div>
            </div>

            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Description of Payment</th>
                        <th class="amount">Amount (NGN)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Session <?= htmlspecialchars($payment['payment_type']) ?> (<?= $payment['session'] ?>)</td>
                        <td class="amount"><?= number_format($payment['amount'], 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="total-box">
                <div class="paid-stamp">CLEARED</div>
                <div class="total-amt">
                    <p>Total Paid</p>
                    <h3>₦<?= number_format($payment['amount'], 2) ?></h3>
                </div>
            </div>

            <p style="font-size: 11px; color: #666; text-align: justify; margin-bottom: 40px;">
                <strong>Notice:</strong> This electronic receipt is automatically generated by the NAFCONM Bursary system. It confirms that the above-named student has fulfilled their financial obligations for the stated purpose. The QR code below can be scanned by College Admin to verify the authenticity of this document.
            </p>

            <div class="footer-sig">
                <div>
                    <img src="<?= $qr_url ?>" class="qr-code" alt="Verification QR">
                    <div style="font-size: 10px; color: #999; margin-top: 5px; text-align:center;">Scan to Verify</div>
                </div>
                <div class="sig-block">
                    <div style="font-family: 'Brush Script MT', cursive; font-size: 24px; color: #004080; margin-bottom: -5px;">System Verified</div>
                    <div class="sig-line">For: Bursar, NAFCONM</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>