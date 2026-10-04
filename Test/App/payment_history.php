<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}
if (isset($_SESSION['action_success'])) { $action_success = $_SESSION['action_success']; unset($_SESSION['action_success']); }
if (isset($_SESSION['action_error'])) { $action_err = $_SESSION['action_error']; unset($_SESSION['action_error']); }

// Fetch all payments for display
$payments = [];
$sql = "SELECT p.id, p.student_id, p.amount_paid, p.payment_date, p.proof_of_payment, p.status,
               s.full_name as student_name, s.student_id_number,
               sess.name as session_name,
               u.username as reviewer_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN sessions sess ON p.session_id = sess.id
        LEFT JOIN users u ON p.reviewed_by_user_id = u.id
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Payment History</h5>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Student ID</th>
                <th>Session</th>
                <th>Amount Paid</th>
                <th>Payment Date</th>
                <th>Proof</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($payments)) : foreach ($payments as $payment) : ?>
                <tr>
                  <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($payment['student_id_number']); ?></td>
                  <td><?php echo htmlspecialchars($payment['session_name']); ?></td>
                  <td><?php echo htmlspecialchars(number_format($payment['amount_paid'], 2)); ?></td>
                  <td><?php echo htmlspecialchars(date("M d, Y", strtotime($payment['payment_date']))); ?></td>
                  <td>
                    <a href="../<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                  </td>
                  <td>
                    <?php
                        $status = htmlspecialchars($payment['status']);
                        $badge_class = 'bg-warning'; // Default for Pending
                        if ($status === 'approved') { $badge_class = 'bg-success'; } 
                        elseif ($status === 'rejected') { $badge_class = 'bg-danger'; }
                        echo "<span class='badge $badge_class'>" . ucfirst($status) . "</span>";
                    ?>
                  </td>
                  <td>
                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                  </td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="8" class="text-center">No payments found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>