<?php
/**
 * NAFCONM - Secure Payment Verification Worker
 * Features: Server-to-Server cURL Verification, Replay Attack Prevention
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- 1. SECURITY & INPUT CHECKS ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

if (!isset($_GET['reference']) || !isset($_GET['session'])) {
    $_SESSION['msg'] = "danger|Invalid payment return parameters.";
    header("Location: pay_fees.php");
    exit;
}

$reference = htmlspecialchars(strip_tags($_GET['reference']));
$academic_session = htmlspecialchars(strip_tags($_GET['session']));
$user_id = $_SESSION["user_id"];

// --- 2. FETCH STUDENT ID ---
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Authentication Error: Student profile not linked.");
}
$student_id = $student['id'];

// --- 3. PREVENT REPLAY ATTACKS (Double-Value checking) ---
// Check if this exact reference has already been verified and credited
$stmt_check = $conn->prepare("SELECT id FROM student_payments WHERE reference = ? LIMIT 1");
$stmt_check->bind_param("s", $reference);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $_SESSION['msg'] = "info|This payment transaction has already been processed.";
    header("Location: pay_fees.php");
    exit;
}
$stmt_check->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifying Payment...</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .loader-card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 400px; width: 90%; }
        .spinner { width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #5D87FF; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .fw-black { font-weight: 900; }
    </style>
</head>
<body>

<div class="loader-card">
    <div class="spinner"></div>
    <h4 class="fw-black text-dark">Verifying Transaction</h4>
    <p class="text-muted small mb-0">Please wait while we confirm your payment with the bank. Do not close this window.</p>
</div>

<?php
// Flush output buffer to show the loading screen instantly to the user
ob_flush();
flush();

// --- 4. SERVER-TO-SERVER PAYSTACK VERIFICATION ---
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
        "Authorization: Bearer $paystack_secret_key",
        "Cache-Control: no-cache",
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

// Generate Invoice Number identically to the pay_fees logic
$invoice_no = "FEE/" . date('Y') . "/" . str_pad($student_id, 4, '0', STR_PAD_LEFT);

if ($err) {
    // cURL network error
    $_SESSION['msg'] = "danger|Network error verifying payment. Please contact ICT with your reference: $reference";
    echo "<script>window.location.href='pay_fees.php';</script>";
    exit;
}

$result = json_decode($response);

if ($result && $result->status && $result->data->status === 'success') {
    // Payment is officially valid and successful!
    $amount_paid = $result->data->amount / 100; // Paystack returns Kobo, divide by 100
    
    // Insert into our database
    $stmt_insert = $conn->prepare("INSERT INTO student_payments (student_id, invoice_no, payment_type, amount, session, status, reference) VALUES (?, ?, 'School Fees', ?, ?, 'successful', ?)");
    $stmt_insert->bind_param("isdss", $student_id, $invoice_no, $amount_paid, $academic_session, $reference);
    
    if ($stmt_insert->execute()) {
        $_SESSION['msg'] = "success|Payment verified successfully! Your course registration is now unlocked.";
    } else {
        // Edge case: Database failed to write, but money was taken.
        $_SESSION['msg'] = "warning|Payment successful, but database update failed. Please show this reference to the Bursary: $reference";
    }
    $stmt_insert->close();

} else {
    // Payment failed or was abandoned
    $_SESSION['msg'] = "danger|Payment verification failed. If you were debited, please contact your bank.";
}

// Final Redirect back to the Pay Fees page
echo "<script>window.location.href='pay_fees.php';</script>";
exit;
?>

</body>
</html>