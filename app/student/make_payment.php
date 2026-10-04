<?php
session_start();
$current_page_id = 'payments'; 
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$student_id = intval($_SESSION['student_id']);
$student_email = $_SESSION['email'] ?? 'student@nafconm.edu.ng';
$paystack_public_key = app_env('PAYSTACK_PUBLIC_KEY', '');

// Fetch unpaid fees for the active session
$sql_fees = "SELECT f.* FROM fees f 
             WHERE f.session_id = (SELECT id FROM sessions WHERE is_active = 1 LIMIT 1)
             AND f.id NOT IN (SELECT fee_id FROM payments WHERE student_id = ? AND status = 'approved')";
$stmt = $conn->prepare($sql_fees);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$available_fees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Make Payment";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-4 border-bottom d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-circle p-3 me-3">
                        <i class="ti ti-credit-card fs-5"></i>
                    </div>
                    <div>
                        <h4 class="fw-black text-dark mb-1">Secure Payment Gateway</h4>
                        <p class="text-muted mb-0">Select your fee item and preferred payment method below.</p>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <form id="mainPaymentForm" action="process_manual_payment.php" method="POST" enctype="multipart/form-data">
                        
                        <input type="hidden" name="amount" id="manualAmount" value="">
                        
                        <div class="row g-5">
                            <div class="col-md-6 border-end-md">
                                <h6 class="fw-bold text-uppercase text-muted small mb-3">1. Select Fee Category</h6>
                                <div class="mb-4">
                                    <?php if(empty($available_fees)): ?>
                                        <div class="alert alert-success rounded-4 border-0 p-4 text-center">
                                            <i class="ti ti-circle-check fs-1 d-block mb-2"></i>
                                            <h6 class="fw-bold mb-0">You're all caught up!</h6>
                                            <small>No outstanding fees found for this session.</small>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($available_fees as $fee): ?>
                                            <div class="form-check custom-option mb-3">
                                                <input class="form-check-input fee-radio" type="radio" name="fee_id" 
                                                       id="fee_<?= $fee['id'] ?>" value="<?= $fee['id'] ?>" 
                                                       data-amount="<?= $fee['amount'] ?>" 
                                                       data-name="<?= htmlspecialchars($fee['fee_name']) ?>" required>
                                                <label class="form-check-label border rounded-4 p-3 w-100 cursor-pointer shadow-sm transition-all" for="fee_<?= $fee['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($fee['fee_name']) ?></span>
                                                        <span class="text-primary fw-black fs-5">₦<?= number_format($fee['amount'], 2) ?></span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="bg-light rounded-4 p-4 mb-4">
                                    <h6 class="fw-bold small text-uppercase text-muted mb-3">Payment Summary</h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted" id="summaryName">No fee selected</span>
                                        <span class="fw-bold text-dark"></span>
                                    </div>
                                    <hr class="border-secondary opacity-25">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold fs-5 text-dark">Total Due:</span>
                                        <span class="fw-black fs-3 text-primary" id="summaryAmount">₦0.00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="fw-bold text-uppercase text-muted small mb-3">2. Choose Method</h6>
                                
                                <div class="nav nav-pills nav-fill mb-4 bg-light p-1 rounded-pill" id="payment-tabs" role="tablist">
                                    <button class="nav-link active rounded-pill fw-bold py-2" id="online-tab" data-bs-toggle="pill" data-bs-target="#online-pay" type="button">
                                        <i class="ti ti-world me-1"></i> Online (Instant)
                                    </button>
                                    <button class="nav-link rounded-pill fw-bold py-2" id="manual-tab" data-bs-toggle="pill" data-bs-target="#manual-pay" type="button">
                                        <i class="ti ti-building-bank me-1"></i> Bank Transfer
                                    </button>
                                </div>

                                <div class="tab-content" id="payment-tabsContent">
                                    <div class="tab-pane fade show active" id="online-pay" role="tabpanel">
                                        <div class="alert bg-primary-subtle text-primary border-0 rounded-4 p-4 mb-4 text-center">
                                            <i class="ti ti-bolt fs-1 d-block mb-2"></i>
                                            <strong>Fast & Secure</strong>
                                            <p class="small mb-0 mt-1">Use your ATM card or Bank Transfer via Paystack. Your portal will be cleared immediately.</p>
                                        </div>
                                        <button type="button" onclick="payWithPaystack()" id="payBtnOnline" class="btn btn-primary w-100 py-3 rounded-pill fw-bold fs-5 shadow-sm" disabled>
                                            Pay Online Now <i class="ti ti-arrow-right ms-2"></i>
                                        </button>
                                    </div>

                                    <div class="tab-pane fade" id="manual-pay" role="tabpanel">
                                        <div class="p-4 bg-light border border-2 border-dashed rounded-4 mb-4">
                                            <h6 class="fw-bold small text-uppercase text-muted mb-3"><i class="ti ti-building-bank text-dark me-1"></i> Official Bank Details</h6>
                                            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                                <span class="text-muted">Bank Name:</span>
                                                <span class="fw-bold text-dark">Zenith Bank PLC</span>
                                            </div>
                                            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                                <span class="text-muted">Account Name:</span>
                                                <span class="fw-bold text-dark">NAFCONM General</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Account Number:</span>
                                                <span class="fw-black text-primary fs-5">1012345678</span>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold text-dark">Upload Evidence of Payment</label>
                                            <input type="file" name="evidence" class="form-control form-control-lg rounded-4 bg-light" accept="image/jpeg,image/png,application/pdf" required>
                                            <div class="form-text mt-2"><i class="ti ti-info-circle"></i> Supported formats: JPG, PNG, PDF (Max 5MB).</div>
                                        </div>

                                        <button type="submit" name="submit_manual" id="payBtnManual" class="btn btn-dark w-100 py-3 rounded-pill fw-bold fs-5 shadow-sm" disabled>
                                            Submit for Verification <i class="ti ti-upload ms-2"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
    const radios = document.querySelectorAll('.fee-radio');
    const payBtnOnline = document.getElementById('payBtnOnline');
    const payBtnManual = document.getElementById('payBtnManual');
    
    const summaryName = document.getElementById('summaryName');
    const summaryAmount = document.getElementById('summaryAmount');
    const manualAmountInput = document.getElementById('manualAmount');

    let selectedAmount = 0;
    let selectedFeeId = 0;

    // Update UI when a fee is selected
    radios.forEach(radio => {
        radio.addEventListener('change', (e) => {
            selectedAmount = e.target.dataset.amount;
            selectedFeeId = e.target.value;
            
            // Update Summary Box
            summaryName.innerText = e.target.dataset.name;
            summaryAmount.innerText = '₦' + parseFloat(selectedAmount).toLocaleString('en-NG', {minimumFractionDigits: 2});
            
            // Set hidden field for manual submit
            manualAmountInput.value = selectedAmount;

            // Enable buttons
            payBtnOnline.disabled = false;
            payBtnManual.disabled = false;
        });
    });

    function payWithPaystack() {
        let handler = PaystackPop.setup({
            key: <?= json_encode($paystack_public_key, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            email: '<?= $student_email ?>',
            amount: selectedAmount * 100, // Paystack requires amount in kobo
            currency: 'NGN',
            ref: 'NF-' + Math.floor((Math.random() * 1000000000) + 1),
            metadata: {
                custom_fields: [
                    { display_name: "Fee ID", variable_name: "fee_id", value: selectedFeeId },
                    { display_name: "Student ID", variable_name: "student_id", value: "<?= $student_id ?>" }
                ]
            },
            callback: function(response) {
                window.location.href = "verify_payment.php?reference=" + response.reference + "&fee_id=" + selectedFeeId;
            },
            onClose: function() {
                alert('Transaction cancelled. No charges were made.');
            }
        });
        handler.openIframe();
    }
</script>

<style>
    .cursor-pointer { cursor: pointer; }
    .transition-all { transition: all 0.3s ease; }
    .custom-option input:checked + label { 
        border-color: #5D87FF !important; 
        background-color: rgba(93, 135, 255, 0.05); 
        box-shadow: 0 4px 15px rgba(93,135,255,0.2) !important;
    }
    .fw-black { font-weight: 900 !important; }
    .nav-pills .nav-link.active { background-color: #5D87FF; color: white; box-shadow: 0 2px 10px rgba(93,135,255,0.3); }
    .nav-link { color: #5a6a85; }
    .border-dashed { border-style: dashed !important; }
    @media (min-width: 768px) { .border-end-md { border-right: 1px solid #ebf1f6; } }
</style>

<?php include 'includes/footer.php'; ?>