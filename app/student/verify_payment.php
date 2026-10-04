<?php
session_start();
require_once '../config/db.php';

$reference = $_GET['reference'] ?? '';
$fee_id = intval($_GET['fee_id'] ?? 0);
$student_id = intval($_SESSION['student_id']);
$paystack_secret_key = app_env('PAYSTACK_SECRET_KEY', '');

if (!$reference) {
    die("No transaction reference found.");
}

// 1. Verify transaction with Paystack API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
        "accept: application/json",
        "authorization: Bearer $paystack_secret_key",
        "cache-control: no-cache"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("cURL Error #:" . $err);
}

$tranx = json_decode($response);

if (!$tranx->status) {
    die("API Error: " . $tranx->message);
}

if ('success' === $tranx->data->status) {
    // 2. Transaction is successful. Update database.
    $amount_paid = $tranx->data->amount / 100; // Convert kobo to Naira
    $payment_date = date('Y-m-d H:i:s');
    
    // Use transaction to ensure data integrity
    $conn->begin_transaction();
    try {
        // Insert payment record
        $stmt = $conn->prepare("INSERT INTO payments (student_id, fee_id, transaction_id, amount_paid, status, payment_method, payment_date, session_id) 
                                VALUES (?, ?, ?, ?, 'approved', 'online', ?, (SELECT id FROM sessions WHERE is_active = 1 LIMIT 1))");
        $stmt->bind_param("iisss", $student_id, $fee_id, $reference, $amount_paid, $payment_date);
        $stmt->execute();

        // LOGIC: If this was tuition/acceptance fee, clear the student for course registration automatically
        $update = $conn->prepare("UPDATE students SET can_register_courses = 1 WHERE id = ?");
        $update->bind_param("i", $student_id);
        $update->execute();

        $conn->commit();
        header("Location: payments.php?status=success");
    } catch (Exception $e) {
        $conn->rollback();
        die("Critical Database Error.");
    }
} else {
    header("Location: payments.php?status=failed");
}

?>