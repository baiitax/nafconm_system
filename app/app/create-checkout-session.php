<?php
session_start();
require_once __DIR__ . '/../../config/env.php';

// Adjust the path to your Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php'; 

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student' || !isset($_SESSION['student_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

\Stripe\Stripe::setApiKey(app_env('STRIPE_SECRET_KEY', ''));

header('Content-Type: application/json');

try {
    $json_str = file_get_contents('php://input');
    $json_obj = json_decode($json_str);

    if (!$json_obj || !isset($json_obj->amount) || !isset($json_obj->session_name)) {
        throw new Exception('Invalid input data.');
    }

    $amount = $json_obj->amount;
    $session_name = $json_obj->session_name;
    $student_id = $_SESSION['student_id'];
    $active_session_id = $_SESSION['active_session_id'];

    // Define your success and cancel URLs
    // IMPORTANT: Replace this with your actual live domain name
    $YOUR_DOMAIN = 'https://www.your-real-domain.com';

    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'ngn', // Change to your currency
                'product_data' => [
                    'name' => 'Payment for ' . $session_name,
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $YOUR_DOMAIN . '/student/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $YOUR_DOMAIN . '/student/payments.php',
        'client_reference_id' => $student_id,
        'metadata' => [
            'student_id' => $student_id,
            'session_id' => $active_session_id,
            'amount_in_ngn' => $amount / 100
        ]
    ]);

    echo json_encode(['id' => $checkout_session->id]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}