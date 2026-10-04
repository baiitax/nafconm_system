<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Make sure the path to your vendor/autoload.php is correct
require_once __DIR__ . '/../vendor/autoload.php';

$page_title = 'Payment Success';

// 1. Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student' || !isset($_SESSION['student_id'])) {
    header("location: ../index.php");
    exit;
}

$student_id = $_SESSION['student_id'];
$message = '';
$message_type = 'danger'; // Default to danger

\Stripe\Stripe::setApiKey(app_env('STRIPE_SECRET_KEY', ''));

// 3. Get the checkout session ID from the URL
$checkout_session_id = $_GET['session_id'] ?? null;

if ($checkout_session_id) {
    try {
        // 4. Retrieve the session from Stripe to verify payment
        $checkout_session = \Stripe\Checkout\Session::retrieve($checkout_session_id);
        $payment_intent_id = $checkout_session->payment_intent;

        // 5. Check if this payment has already been processed to prevent duplicates
        $stmt_check = $conn->prepare("SELECT id FROM payments WHERE transaction_id = ?");
        $stmt_check->bind_param('s', $payment_intent_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 0) {
            // Payment is new, so let's add it to our database.
            $metadata = $checkout_session->metadata;
            $db_student_id = $metadata->student_id;
            $db_session_id = $metadata->session_id;
            $amount_paid = $metadata->amount_in_ngn;

            // Security check: ensure the student ID from Stripe matches the logged-in user
            if ((int)$db_student_id !== (int)$student_id) {
                throw new Exception("Payment session mismatch. Please contact support.");
            }

            $conn->begin_transaction();

            // 6. Insert the payment record as 'approved'
            $sql_insert = "INSERT INTO payments (student_id, session_id, amount_paid, payment_date, payment_method, transaction_id, status, reviewed_by_user_id, review_date) VALUES (?, ?, ?, NOW(), 'Stripe', ?, 'approved', NULL, NOW())";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iids", $db_student_id, $db_session_id, $amount_paid, $payment_intent_id);
            $stmt_insert->execute();

            // 7. Enable course registration for the student
            $stmt_student = $conn->prepare("UPDATE students SET can_register_courses = 1 WHERE id = ?");
            $stmt_student->bind_param('i', $db_student_id);
            $stmt_student->execute();

            $conn->commit();

            log_activity($conn, $_SESSION['user_id'], "Completed online payment with Stripe. Txn ID: $payment_intent_id");
            $message = "Your payment was successful! Your account has been updated, and you can now register for courses.";
            $message_type = 'success';

        } else {
            // Payment was already processed (e.g., by a webhook or page refresh)
            $message = "Your payment was successful and has already been recorded.";
            $message_type = 'success';
        }

    } catch (Exception $e) {
        // Handle API errors or other exceptions
        $message = "An error occurred while verifying your payment: " . $e->getMessage();
        // In a real app, you would log this error for investigation.
        error_log("Stripe Success Page Error: " . $e->getMessage());
    }
} else {
    $message = "No payment session ID provided. If you have completed a payment and see this message, please contact support.";
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <div class="card">
        <div class="card-body text-center">
            <?php if ($message_type === 'success'): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading">Thank You!</h4>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <i class="ti ti-circle-check text-success" style="font-size: 80px;"></i>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Payment Error</h4>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
                <i class="ti ti-alert-circle text-danger" style="font-size: 80px;"></i>
            <?php endif; ?>
            <div class="mt-4">
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="payments.php" class="btn btn-outline-primary">View Payment History</a>
            </div>
        </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>