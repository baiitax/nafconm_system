<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) die("Invalid Payment ID.");

$payment = null;
$sql = "SELECT p.*, s.full_name as student_name, s.student_id_number, sess.name as session_name 
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN sessions sess ON p.session_id = sess.id
        WHERE p.id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $payment = $result->fetch_assoc();
    } else {
        die("Payment not found or you do not have permission to view it.");
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - #<?php echo htmlspecialchars($payment['id']); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.min.css" />
    <style>
        body { background-color: #f8f9fa; }
        .receipt-container { max-width: 800px; margin: 50px auto; background: #fff; border: 1px solid #dee2e6; box-shadow: 0 0 15px rgba(0,0,0,.05); }
        .receipt-header { background-color: #ffffffff; padding: 2rem; border-bottom: 1px solid #dee2e6; }
        .receipt-body { padding: 2rem; }
        .receipt-footer {background-color: #ff0707ff; padding: 2rem; text-align: center; font-size: 0.9em; color: #ffffffff; }
        .paid-stamp { color: #28a745; border: 3px solid #ff1616ff; display: inline-block; padding: 5px 10px; text-transform: uppercase; font-weight: bold; font-size: 2em; transform: rotate(-15deg); opacity: 0.5; }
        @media print {
            body { background-color: #fff; }
            .receipt-container { margin: 0; border: none; box-shadow: none; }
            .no-print { display: none; }
             .receipt-footer {background-color: #ff0707ff; padding: 2rem; text-align: center; font-size: 0.9em; color: #ffffffff; }
        .paid-stamp { color: #28a745; border: 3px solid #ff1616ff; display: inline-block; padding: 5px 10px; text-transform: uppercase; font-weight: bold; font-size: 2em; transform: rotate(-15deg); opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <div class="row align-items-center">
                <div class="col-8">
                    <img src="../assets/images/logos/favicon.png" width="100" alt="Logo">
                    <h3 class="mt-2">Payment Receipt</h3>
                </div>
                <div class="col-4 text-end">
                    <p class="mb-0"><strong>Receipt #:</strong> <?php echo htmlspecialchars(str_pad($payment['id'], 6, '0', STR_PAD_LEFT)); ?></p>
                    <p class="mb-0"><strong>Date:</strong> <?php echo date("F j, Y"); ?></p>
                </div>
            </div>
        </div>
        <div class="receipt-body">
            <div class="row mb-4">
                <div class="col-6">
                    <h6>Billed To:</h6>
                    <p class="mb-0"><?php echo htmlspecialchars($payment['student_name']); ?></p>
                    <p class="mb-0">Student ID: <?php echo htmlspecialchars($payment['student_id_number']); ?></p>
                </div>
                <div class="col-6 text-end">
                    <?php if ($payment['status'] === 'approved'): ?>
                        <div class="paid-stamp" style="color: #01ce45ff; border-color: #01ce45ff;">Paid</div>
                    <?php else: ?>
                        <div class="paid-stamp" style="color: #ffc107; border-color: #ffc107;"><?php echo ucfirst(htmlspecialchars($payment['status'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr><th>Description</th><th class="text-end">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Payment for <?php echo htmlspecialchars($payment['session_name']); ?></td>
                        <td class="text-end">₦ <?php echo number_format($payment['amount_paid'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-end">Total Paid:</th>
                        <th class="text-end">₦ <?php echo number_format($payment['amount_paid'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
            <div class="mt-4">
                <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></p>
                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></p>
                <p><strong>Payment Date:</strong> <?php echo date("F j, Y", strtotime($payment['payment_date'])); ?></p>
            </div>
        </div>
        <div class="receipt-footer">
            <p>Thank you for your payment!</p>
            <p>NAFCONM-Portal - Your School Management Solution</p>
        </div>
    </div>
    <div class="text-center my-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Receipt</button>
        <a href="view_payment.php?id=<?php echo $payment_id; ?>" class="btn btn-secondary">Back to Payment Details</a>
    </div>
</body>
</html>