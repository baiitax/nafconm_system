<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Manage Payments';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id']) && isset($_POST['new_status'])) {
    $payment_id = (int)$_POST['payment_id'];
    $new_status = $_POST['new_status'];
    $student_id = (int)$_POST['student_id'];
    $session_id = !empty($_POST['session_id']) ? (int)$_POST['session_id'] : null;

    if (in_array($new_status, ['approved', 'rejected'])) {
        $conn->begin_transaction();
        try {
            // Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = ?, reviewed_by_user_id = ?, review_date = NOW() WHERE id = ?");
            $stmt->bind_param('sii', $new_status, $user_id, $payment_id);
            $stmt->execute();

            $action_log = "Updated payment ID $payment_id to $new_status.";

            // If approved and it's a session fee, enable course registration for the student
            if ($new_status === 'approved' && $session_id !== null) {
                $stmt_student = $conn->prepare("UPDATE students SET can_register_courses = 1 WHERE id = ?");
                $stmt_student->bind_param('i', $student_id);
                $stmt_student->execute();
                $action_log .= " Enabled course registration for student ID $student_id.";
            }

            // Log activity
            log_activity($conn, $user_id, $action_log);

            $conn->commit();
            $message = "<div class='alert alert-success'>Payment status updated successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>Error updating payment status: " . $e->getMessage() . "</div>";
        }
    }
}

// Fetch all payments
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$sql_where = '';
if (!empty($filter_status) && in_array($filter_status, ['pending', 'approved', 'rejected'])) {
    $sql_where = " WHERE p.status = '$filter_status'";
}

$sql = "SELECT p.*, s.full_name as student_name, s.student_id_number, u.username as reviewer_name 
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        LEFT JOIN users u ON p.reviewed_by_user_id = u.id
        $sql_where
        ORDER BY p.created_at DESC";
$payments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<?php
require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Manage Payments</h5>
    <?php echo $message; ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="paymentsTable" class="table table-striped" style="width:100%">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Amount (₦)</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Reviewed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['student_name']); ?><br><small><?php echo htmlspecialchars($payment['student_id_number']); ?></small></td>
                                <td><?php echo number_format($payment['amount_paid'], 2); ?></td>
                                <td><?php echo date("M d, Y", strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    if ($payment['status'] == 'approved') $status_class = 'bg-success';
                                    if ($payment['status'] == 'pending') $status_class = 'bg-warning';
                                    if ($payment['status'] == 'rejected') $status_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($payment['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reviewer_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick='reviewPayment(<?php echo json_encode($payment); ?>)'>Review</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Review Payment Modal -->
<div class="modal fade" id="reviewPaymentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Review Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Student:</strong> <span id="modal_student_name"></span></p>
                <p><strong>Student ID:</strong> <span id="modal_student_id_number"></span></p>
                <p><strong>Amount Paid:</strong> ₦<span id="modal_amount_paid"></span></p>
                <p><strong>Payment Date:</strong> <span id="modal_payment_date"></span></p>
            </div>
            <div class="col-md-6">
                <p><strong>Transaction ID:</strong> <span id="modal_transaction_id"></span></p>
                <p><strong>Payment Method:</strong> <span id="modal_payment_method"></span></p>
                <p><strong>Current Status:</strong> <span id="modal_status" class="badge"></span></p>
            </div>
        </div>
        <hr>
        <h5>Proof of Payment</h5>
        <div id="modal_proof_of_payment"></div>
      </div>
      <div class="modal-footer">
        <form method="POST" action="" class="d-inline">
            <input type="hidden" name="payment_id" id="modal_payment_id">
            <input type="hidden" name="student_id" id="modal_student_id">
            <input type="hidden" name="session_id" id="modal_session_id">
            <input type="hidden" name="new_status" value="rejected">
            <button type="submit" class="btn btn-danger">Reject</button>
        </form>
        <form method="POST" action="" class="d-inline">
            <input type="hidden" name="payment_id" id="modal_payment_id_approve">
            <input type="hidden" name="student_id" id="modal_student_id_approve">
            <input type="hidden" name="session_id" id="modal_session_id_approve">
            <input type="hidden" name="new_status" value="approved">
            <button type="submit" class="btn btn-success">Approve</button>
        </form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
    const reviewModal = new bootstrap.Modal(document.getElementById('reviewPaymentModal'));

    function reviewPayment(payment) {
        document.getElementById('modal_student_name').textContent = payment.student_name;
        document.getElementById('modal_student_id_number').textContent = payment.student_id_number;
        document.getElementById('modal_amount_paid').textContent = parseFloat(payment.amount_paid).toFixed(2);
        document.getElementById('modal_payment_date').textContent = new Date(payment.payment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('modal_transaction_id').textContent = payment.transaction_id || 'N/A';
        document.getElementById('modal_payment_method').textContent = payment.payment_method || 'N/A';
        
        const statusBadge = document.getElementById('modal_status');
        statusBadge.textContent = payment.status.charAt(0).toUpperCase() + payment.status.slice(1);
        statusBadge.className = 'badge';
        if (payment.status === 'approved') statusBadge.classList.add('bg-success');
        if (payment.status === 'pending') statusBadge.classList.add('bg-warning');
        if (payment.status === 'rejected') statusBadge.classList.add('bg-danger');

        const proofContainer = document.getElementById('modal_proof_of_payment');
        if (payment.proof_of_payment) {
            proofContainer.innerHTML = `<a href="../${payment.proof_of_payment}" target="_blank" class="btn btn-info">View Proof</a>`;
        } else {
            proofContainer.innerHTML = '<p>No proof of payment uploaded.</p>';
        }

        // Set IDs for form submission
        document.getElementById('modal_payment_id').value = payment.id;
        document.getElementById('modal_student_id').value = payment.student_id;
        document.getElementById('modal_session_id').value = payment.session_id;
        document.getElementById('modal_payment_id_approve').value = payment.id;
        document.getElementById('modal_student_id_approve').value = payment.student_id;
        document.getElementById('modal_session_id_approve').value = payment.session_id;

        // Disable buttons if not pending
        const isPending = payment.status === 'pending';
        document.querySelector('.modal-footer .btn-danger').disabled = !isPending;
        document.querySelector('.modal-footer .btn-success').disabled = !isPending;

        reviewModal.show();
    }

    $(document).ready(function() {
        $('#paymentsTable').DataTable({
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'pdf']
        });

        // Check for payment_id in URL to auto-open modal
        const urlParams = new URLSearchParams(window.location.search);
        const paymentIdToReview = urlParams.get('payment_id');
        if (paymentIdToReview) {
            // This requires fetching the payment data via AJAX or having it available in JS
            // For simplicity, we'll rely on the user clicking the button.
            // A more advanced implementation would fetch payment data here.
            console.log("Need to review payment ID:", paymentIdToReview);
        }
    });
</script>