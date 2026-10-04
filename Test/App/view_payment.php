<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'View Payment';
require_once 'includes/header.php';

$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) {
    header("location: payment_history.php");
    exit;
}

// Handle Payment Status Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_payment_status'])) {
    $student_id = $_POST['student_id'];
    $new_status = $_POST['status'];
    $user_id = $_SESSION['user_id'];

    if (empty($new_status) || empty($student_id)) {
        $_SESSION['action_error'] = "Invalid request.";
    } else {
        $conn->begin_transaction();
        try {
            $sql_payment = "UPDATE payments SET status = ?, reviewed_by_user_id = ?, review_date = NOW() WHERE id = ?";
            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bind_param("sii", $new_status, $user_id, $payment_id);
            $stmt_payment->execute();
            $stmt_payment->close();

            if ($new_status === 'approved') {
                $sql_student = "UPDATE students SET can_register_courses = 1 WHERE id = ?";
                log_activity($conn, $user_id, "Approved payment ID {$payment_id} and enabled course registration for student ID {$student_id}.");
            } else {
                $sql_student = "UPDATE students SET can_register_courses = 0 WHERE id = ?";
                log_activity($conn, $user_id, "Set payment ID {$payment_id} to '{$new_status}' for student ID {$student_id}. Course registration disabled.");
            }
            $stmt_student = $conn->prepare($sql_student);
            $stmt_student->bind_param("i", $student_id);
            $stmt_student->execute();
            $stmt_student->close();

            $conn->commit();
            $_SESSION['action_success'] = "Payment status updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['action_error'] = "Database error: " . $e->getMessage();
        }
    }
    header("location: payment_history.php");
    exit;
}

// Fetch payment details
$payment = null;
$sql = "SELECT p.*, s.full_name as student_name, s.student_id_number, sess.name as session_name, u.username as reviewer_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN sessions sess ON p.session_id = sess.id
        LEFT JOIN users u ON p.reviewed_by_user_id = u.id
        WHERE p.id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $payment = $result->fetch_assoc();
    } else {
        die("Payment not found.");
    }
    $stmt->close();
}

require_once 'includes/sidebar.php';
?>
<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Payment Details</h5>
    </div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">Payment Details</h5>
                    <a href="payment_history.php" class="btn btn-outline-primary">Back to History</a>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="fw-semibold">Student & Session Info</h6>
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($payment['student_name']); ?> (<?php echo htmlspecialchars($payment['student_id_number']); ?>)</p>
                        <p><strong>Session:</strong> <?php echo htmlspecialchars($payment['session_name']); ?></p>
                        <hr>
                        <h6 class="fw-semibold">Payment Info</h6>
                        <p><strong>Amount Paid:</strong> Ksh <?php echo number_format($payment['amount_paid'], 2); ?></p>
                        <p><strong>Payment Date:</strong> <?php echo date("F j, Y", strtotime($payment['payment_date'])); ?></p>
                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></p>
                        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></p>
                        <hr>
                        <h6 class="fw-semibold">Status & Review</h6>
                        <p><strong>Current Status:</strong> <?php $status = htmlspecialchars($payment['status']); $badge_class = 'bg-warning text-dark'; if ($status === 'approved') { $badge_class = 'bg-success'; } elseif ($status === 'rejected') { $badge_class = 'bg-danger'; } echo "<span class='badge $badge_class'>" . ucfirst($status) . "</span>"; ?></p>
                        <p><strong>Reviewed By:</strong> <?php echo htmlspecialchars($payment['reviewer_name'] ?? 'N/A'); ?></p>
                        <p><strong>Review Date:</strong> <?php echo $payment['review_date'] ? date("F j, Y, g:i a", strtotime($payment['review_date'])) : 'N/A'; ?></p>
                        <hr>
                        <div class="d-flex align-items-center"><h6 class="fw-semibold me-3">Actions:</h6><form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $payment_id; ?>" method="post" class="d-inline-flex"><input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>"><input type="hidden" name="student_id" value="<?php echo $payment['student_id']; ?>"><select name="status" class="form-select form-select-sm me-2" style="width: 120px;"><option value="pending" <?php if ($payment['status'] === 'pending') echo 'selected'; ?>>Pending</option><option value="approved" <?php if ($payment['status'] === 'approved') echo 'selected'; ?>>Approve</option><option value="rejected" <?php if ($payment['status'] === 'rejected') echo 'selected'; ?>>Reject</option></select><button type="submit" name="update_payment_status" class="btn btn-sm btn-primary">Update Status</button></form><a href="payment_receipt.php?id=<?php echo $payment_id; ?>" target="_blank" class="btn btn-sm btn-secondary ms-2">View Receipt</a></div>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="fw-semibold">Proof of Payment</h6>
                        <?php if (!empty($payment['proof_of_payment']) && file_exists('../' . $payment['proof_of_payment'])): ?>
                            <?php $file_ext = strtolower(pathinfo($payment['proof_of_payment'], PATHINFO_EXTENSION)); ?>
                            <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?><img src="../<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" class="img-fluid rounded" alt="Proof of Payment"><?php elseif ($file_ext === 'pdf'): ?><iframe src="../<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" width="100%" height="600px"></iframe><?php else: ?><a href="../<?php echo htmlspecialchars($payment['proof_of_payment']); ?>" target="_blank" class="btn btn-primary">Download Proof</a><?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">No proof of payment was uploaded or the file is missing.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>