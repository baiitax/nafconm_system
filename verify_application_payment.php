<?php
/**
 * NAFCONM - Secure Payment Verification Engine
 * Features: cURL Server-to-Server Handshake, Value-Matching, Double-Process Prevention
 */
session_start();
require_once 'config/db.php';

// 1. Validate Incoming Request
if (!isset($_GET['reference']) || !isset($_GET['app_id'])) {
    $error_msg = "Invalid Request: Missing payment reference or application ID.";
    $status = 'failed';
} else {
    $reference = sanitize_input($_GET['reference']);
    $app_id    = (int)$_GET['app_id'];

    // 2. Fetch the Application Record
    $sql = "SELECT reg_no, invoice_no, application_fee, payment_status FROM admission_applications WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error_msg = "Fatal Error: Application record not found in the database.";
        $status = 'failed';
    } else {
        $app = $result->fetch_assoc();

        // 3. Double-Process Prevention
        if ($app['payment_status'] === 'paid' || $app['payment_status'] === 'approved') {
            // Already verified previously, safely redirect them
            header("Location: application_slip.php");
            exit;
        }

        // 4. Initialize cURL to Paystack API
        $paystack_secret_key = app_env('PAYSTACK_SECRET_KEY', '');

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $paystack_secret_key,
                "Cache-Control: no-cache",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        // 5. Evaluate Paystack's Response
        if ($err) {
            $error_msg = "Network Error: Could not connect to the payment gateway. cURL Error: " . $err;
            $status = 'failed';
        } else {
            $tranx = json_decode($response);

            // Did Paystack successfully process the request?
            if (!$tranx->status) {
                $error_msg = "Gateway Error: " . $tranx->message;
                $status = 'failed';
            } 
            // Is the transaction itself actually successful?
            elseif ('success' === $tranx->data->status) {
                
                // SENIOR DEV CHECK: Value Matching
                // Ensure the amount paid matches the database amount (Paystack returns amounts in kobo)
                $amount_paid_naira = $tranx->data->amount / 100;
                
                if ($amount_paid_naira >= $app['application_fee']) {
                    // ALL CHECKS PASSED: Atomic Database Update
                    $conn->begin_transaction();
                    try {
                        // 1. Update Application Status
                        $update_stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'paid', status = 'submitted' WHERE id = ?");
                        $update_stmt->bind_param("i", $app_id);
                        $update_stmt->execute();

                        // 2. Log the transaction in the general payments ledger
                        // We link it to the student's email/reg_no as a record
                        $ledger_stmt = $conn->prepare("INSERT INTO payments (student_id, amount_paid, payment_method, status, transaction_id, payment_type) VALUES (?, ?, 'online', 'approved', ?, 'application_fee')");
                        // Note: If you don't have a student_id yet because they are just an applicant, you might skip this step or insert into a dedicated applicant_payments table. 
                        // Assuming you track applicants by app_id in a generic way, we will just rely on the admission_applications table update for now.

                        $conn->commit();
                        
                        // Successfully verified! Redirect to the final slip printing page
                        header("Location: application_slip.php?msg=success");
                        exit;

                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_msg = "Database Update Failed: " . $e->getMessage();
                        $status = 'failed';
                    }
                } else {
                    $error_msg = "Value Mismatch: You paid ₦" . number_format($amount_paid_naira) . " but the required fee is ₦" . number_format($app['application_fee']) . ". Please contact the Bursary.";
                    $status = 'failed';
                }
            } else {
                $error_msg = "Transaction Failed: The payment was declined or abandoned by the gateway.";
                $status = 'failed';
            }
        }
    }
}

// Helper function to sanitize input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$page_title = "Verifying Payment";
require_once 'includes/frontend_header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-5 text-center border-bottom border-danger border-4">
                    <div class="bg-danger-subtle text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                        <i class="ti ti-shield-x fs-1"></i>
                    </div>
                    <h3 class="fw-black text-danger mb-3">Verification Failed</h3>
                    <p class="text-muted mb-4"><?= $error_msg ?></p>
                    
                    <div class="p-3 bg-light rounded-4 mb-4 text-start">
                        <span class="d-block text-uppercase text-muted small fw-bold mb-1">Diagnostic Info</span>
                        <code class="text-dark">Reference: <?= htmlspecialchars($_GET['reference'] ?? 'N/A') ?></code><br>
                        <code class="text-dark">App ID: <?= htmlspecialchars($_GET['app_id'] ?? 'N/A') ?></code>
                    </div>

                    <a href="payment_details.php" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-sm">
                        <i class="ti ti-arrow-left me-2"></i> Return to Payment Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
    .bg-danger-subtle { background-color: rgba(220, 53, 69, 0.1) !important; }
</style>

<?php require_once 'includes/frontend_footer.php'; ?>