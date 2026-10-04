<?php
/**
 * NAFCONM - Secure Flutterwave Verification Worker
 * Features: Server-to-Server cURL Verification, Amount & Currency Validation
 */
session_start();
require_once 'config/db.php';

if (!isset($_GET['transaction_id']) || !isset($_GET['tx_ref'])) {
    die("Invalid payment return parameters.");
}

$transaction_id = htmlspecialchars(strip_tags($_GET['transaction_id']));
$tx_ref = htmlspecialchars(strip_tags($_GET['tx_ref']));

$flutterwave_secret_key = app_env('FLUTTERWAVE_SECRET_KEY', '');

// --- 1. SERVER-TO-SERVER VERIFICATION ---
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/" . rawurlencode($transaction_id) . "/verify",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer $flutterwave_secret_key",
        "Content-Type: application/json"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("Network error verifying payment. Contact ICT with your Reference: $tx_ref");
}

$result = json_decode($response);

// --- 2. VALIDATE THE RESPONSE ---
if ($result && $result->status === 'success') {
    
    $payment_data = $result->data;
    
    // Check if the reference matches what we have in DB
    $stmt = $conn->prepare("SELECT id, application_fee, token FROM admission_applications WHERE invoice_no = ? LIMIT 1");
    $stmt->bind_param("s", $tx_ref);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($app) {
        // Crucial Security Checks: Ensure amount paid is correct and currency is NGN
        if ($payment_data->status === "successful" && $payment_data->amount >= $app['application_fee'] && $payment_data->currency === "NGN") {
            
            // Payment is valid! Update the database
            $stmt_upd = $conn->prepare("UPDATE admission_applications SET payment_status = 'paid' WHERE id = ?");
            $stmt_upd->bind_param("i", $app['id']);
            $stmt_upd->execute();
            $stmt_upd->close();

            // Store token for success screen
            $tracking_token = $app['token'];
            
        } else {
            die("Payment validation failed: Incorrect amount or currency.");
        }
    } else {
        die("Invalid Invoice Reference.");
    }
} else {
    die("Payment failed or was abandoned. If debited, contact your bank.");
}

$page_title = "Application Successful";
include 'includes/header.php';
?>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px;">
                <i class="ti ti-check fs-1" style="font-size: 50px !important;"></i>
            </div>
            <h2 class="fw-black text-dark mb-3">Application Submitted Successfully!</h2>
            <p class="text-muted fs-5 mb-4">Your application fee has been processed and your documents are now under review by the admissions team.</p>
            
            <div class="card border border-primary-subtle shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <small class="text-uppercase fw-bold text-muted d-block mb-2">Your Tracking Token</small>
                    <h1 class="fw-black text-primary font-monospace mb-0" style="letter-spacing: 3px;"><?= htmlspecialchars($tracking_token) ?></h1>
                </div>
            </div>
            
            <div class="alert bg-warning-subtle text-dark border-0 small fw-bold mb-4">
                <i class="ti ti-alert-triangle me-1"></i> Please write this token down. You will need it to check your admission status and download your admission letter.
            </div>

            <a href="index.php" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-sm">Return to Homepage</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>