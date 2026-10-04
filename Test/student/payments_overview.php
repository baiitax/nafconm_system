<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Payments Overview';

// Security: Ensure user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// --- Fetch Student's Payment Analytics ---

// KPI: Total Amount Paid
$stmt_total = $conn->prepare("SELECT SUM(amount_paid) FROM payments WHERE student_id = ? AND status = 'approved'");
$stmt_total->bind_param("i", $student_id);
$stmt_total->execute();
$kpi_total_paid = $stmt_total->get_result()->fetch_row()[0] ?? 0;
$stmt_total->close();

// KPI: Pending Payments
$stmt_pending = $conn->prepare("SELECT COUNT(id) FROM payments WHERE student_id = ? AND status = 'pending'");
$stmt_pending->bind_param("i", $student_id);
$stmt_pending->execute();
$kpi_pending_count = $stmt_pending->get_result()->fetch_row()[0] ?? 0;
$stmt_pending->close();

// KPI: Approved Payments
$stmt_approved = $conn->prepare("SELECT COUNT(id) FROM payments WHERE student_id = ? AND status = 'approved'");
$stmt_approved->bind_param("i", $student_id);
$stmt_approved->execute();
$kpi_approved_count = $stmt_approved->get_result()->fetch_row()[0] ?? 0;
$stmt_approved->close();

// Fetch full payment history
$stmt_history = $conn->prepare("SELECT p.id, p.amount_paid, p.payment_date, p.status, p.review_date, s.name as session_name 
                                FROM payments p 
                                LEFT JOIN sessions s ON p.session_id = s.id
                                WHERE p.student_id = ? 
                                ORDER BY p.created_at DESC");
$stmt_history->bind_param("i", $student_id);
$stmt_history->execute();
$payment_history = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_history->close();

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <!-- KPIs -->
    <div class="row">
        <div class="col-md-4"><div class="card bg-light-success"><div class="card-body"><h5 class="card-title">Total Paid</h5><h4 class="fw-bold">₦ <?php echo number_format($kpi_total_paid, 2); ?></h4></div></div></div>
        <div class="col-md-4"><div class="card bg-light-warning"><div class="card-body"><h5 class="card-title">Pending Payments</h5><h4 class="fw-bold"><?php echo $kpi_pending_count; ?></h4></div></div></div>
        <div class="col-md-4"><div class="card bg-light-primary"><div class="card-body"><h5 class="card-title">Approved Payments</h5><h4 class="fw-bold"><?php echo $kpi_approved_count; ?></h4></div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title fw-semibold mb-0">Payment History</h5>
                <a href="payments.php" class="btn btn-primary">Make New Payment</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr><th>Session</th><th>Amount Paid</th><th>Payment Date</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payment_history)): foreach ($payment_history as $history): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($history['session_name'] ?? 'N/A'); ?></td>
                                <td>₦ <?php echo htmlspecialchars(number_format($history['amount_paid'], 2)); ?></td>
                                <td><?php echo date("M d, Y", strtotime($history['payment_date'])); ?></td>
                                <td>
                                    <?php
                                    $status_class = ['pending' => 'bg-warning', 'approved' => 'bg-success', 'rejected' => 'bg-danger'][$history['status']] ?? 'bg-secondary';
                                    echo "<span class='badge {$status_class}'>" . ucfirst($history['status']) . "</span>";
                                    ?>
                                </td>
                                <td><a href="view_payment.php?id=<?php echo $history['id']; ?>" class="btn btn-sm btn-info">View Details</a></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center">You have no payment history.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>