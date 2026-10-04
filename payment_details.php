<?php
/**
 * NAFCONM - Application Fee Checkout (Flutterwave)
 * Features: ₦16,430 Static Fee, Reg No Display, Premium UI
 */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['last_app_id'])) {
    header("Location: apply.php");
    exit;
}

$app_id = intval($_SESSION['last_app_id']);

$stmt = $conn->prepare("SELECT * FROM admission_applications WHERE id = ? AND payment_status = 'unpaid' LIMIT 1");
$stmt->bind_param("i", $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    $_SESSION['msg'] = "info|Your application has already been paid or is under review. Please use the tracker.";
    header("Location: check_status.php");
    exit;
}

$page_title = "Pay Application Fee";
include 'includes/header.php';

$flutterwave_public_key = app_env('FLUTTERWAVE_PUBLIC_KEY', '');
?>

<style>
    body { background-color: #f4f7fa; }
    .invoice-ticket { background: #fff; border-radius: 16px; position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
    .invoice-header { background: #1e293b; color: white; padding: 30px; border-radius: 16px 16px 0 0; text-align: center; position: relative; }
    .invoice-header::after { content: ''; position: absolute; bottom: -10px; left: 0; right: 0; height: 20px; background-image: radial-gradient(circle at 10px 0, transparent 10px, #fff 11px); background-size: 25px 20px; background-repeat: repeat-x; }
    .invoice-body { padding: 40px 30px 30px; }
    .dashed-divider { border-top: 2px dashed #e2e8f0; margin: 20px 0; }
    .copy-btn { cursor: pointer; transition: 0.2s; }
    .copy-btn:hover { color: #1e4db7 !important; transform: scale(1.1); }
    .fw-black { font-weight: 900 !important; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            
            <div class="d-flex justify-content-between mb-5 position-relative">
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm border border-2 border-white" style="width: 45px; height: 45px;"><i class="ti ti-check fs-5"></i></div>
                    <div class="small fw-bold text-success">Bio-Data</div>
                </div>
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-lg border border-2 border-white" style="width: 45px; height: 45px;"><i class="ti ti-cash fs-5"></i></div>
                    <div class="small fw-bold text-primary">Payment</div>
                </div>
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2 border border-2 border-white shadow-sm" style="width: 45px; height: 45px;"><i class="ti ti-rosette-discount-check fs-5"></i></div>
                    <div class="small text-muted fw-medium">Complete</div>
                </div>
                <div class="position-absolute top-50 start-0 translate-middle-y w-100" style="height: 3px; background: #e2e8f0; z-index: 0;"></div>
            </div>

            <div class="invoice-ticket border-0">
                <div class="invoice-header">
                    <h6 class="text-uppercase tracking-wider opacity-75 mb-2 fw-bold small">Application Fee Invoice</h6>
                    <h1 class="fw-black text-white mb-0 display-4">₦<?= number_format($app['application_fee'], 2) ?></h1>
                    <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mt-3 fw-bold"><i class="ti ti-clock-hour-4 me-1"></i> Awaiting Payment</span>
                </div>
                
                <div class="invoice-body">
                    <div class="alert bg-primary-subtle text-primary border-0 rounded-3 mb-4 p-3 d-flex align-items-start">
                        <i class="ti ti-info-circle fs-4 me-2"></i>
                        <div class="small fw-medium">
                            Your Registration Number is <strong><?= htmlspecialchars($app['reg_no']) ?></strong>. This will serve as your Username for all future portal access.
                        </div>
                    </div>

                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted fw-bold pb-3">Reg No (Username):</td>
                            <td class="text-end fw-bold font-monospace text-dark pb-3"><?= htmlspecialchars($app['reg_no']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold pb-3">Applicant:</td>
                            <td class="text-end fw-bold text-dark pb-3"><?= ucwords(strtolower($app['first_name'] . ' ' . $app['last_name'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold pb-3">Email:</td>
                            <td class="text-end fw-bold text-dark pb-3"><?= htmlspecialchars($app['email']) ?></td>
                        </tr>
                    </table>

                    <div class="dashed-divider"></div>

                    <div class="bg-light rounded-3 p-3 d-flex align-items-center justify-content-between mb-4 border border-light">
                        <div>
                            <small class="text-dark fw-bold text-uppercase d-block mb-1" style="font-size: 11px;">Your Tracking Token</small>
                            <h4 class="fw-black text-dark font-monospace mb-0" id="tokenText"><?= htmlspecialchars($app['token']) ?></h4>
                        </div>
                        <div class="copy-btn bg-white border rounded-circle p-2 shadow-sm d-flex align-items-center justify-content-center text-dark" onclick="copyToken()" title="Copy Token">
                            <i class="ti ti-copy fs-4"></i>
                        </div>
                    </div>
                    
                    <div class="text-center text-muted small mb-4 fw-medium">
                        <i class="ti ti-lock me-1"></i> Payments are processed securely via Flutterwave. Do not refresh this page during payment.
                    </div>

                    <button type="button" id="payBtn" onClick="makePayment()" class="btn btn-primary btn-lg w-100 py-3 rounded-pill fw-black shadow-sm d-flex justify-content-center align-items-center transition-all">
                        <span id="btnText">Pay Securely Now</span> 
                        <i id="btnIcon" class="ti ti-credit-card ms-2 fs-4"></i>
                        <div id="btnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://checkout.flutterwave.com/v3.js"></script>
<script>
function copyToken() {
    const token = document.getElementById("tokenText").innerText;
    navigator.clipboard.writeText(token).then(() => {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success',
            title: 'Token copied to clipboard!',
            showConfirmButton: false, timer: 3000
        });
    });
}

function makePayment() {
    const btn = document.getElementById("payBtn");
    const btnText = document.getElementById("btnText");
    const btnIcon = document.getElementById("btnIcon");
    const btnSpinner = document.getElementById("btnSpinner");
    
    btn.disabled = true;
    btn.classList.add("opacity-75");
    btnText.innerText = "Connecting to Secure Gateway...";
    btnIcon.classList.add("d-none");
    btnSpinner.classList.remove("d-none");

    FlutterwaveCheckout({
        public_key: <?= json_encode($flutterwave_public_key, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        tx_ref: "<?= $app['invoice_no'] ?>",
        amount: <?= $app['application_fee'] ?>,
        currency: "NGN",
        payment_options: "card, banktransfer, ussd",
        customer: {
            email: "<?= $app['email'] ?>",
            phone_number: "<?= $app['phone'] ?>",
            name: "<?= $app['first_name'] . ' ' . $app['last_name'] ?>",
        },
        customizations: {
            title: "NAFCONM Admissions",
            description: "Application Fee Payment",
            logo: "assets/images/logos/favicon.png", 
        },
        callback: function (data) {
            btnText.innerText = "Verifying Payment...";
            window.location.href = "verify_flutterwave.php?transaction_id=" + data.transaction_id + "&tx_ref=" + data.tx_ref;
        },
        onclose: function() {
            btn.disabled = false;
            btn.classList.remove("opacity-75");
            btnText.innerText = "Pay Securely Now";
            btnIcon.classList.remove("d-none");
            btnSpinner.classList.add("d-none");

            Swal.fire('Payment Cancelled', 'You closed the secure payment window. Try again when ready.', 'warning');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
