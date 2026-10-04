<?php
/**
 * NAFCONM - Student Fee Payment Gateway
 * Features: Smart Fee Resolution, Secure MIME Uploads, CSRF Protection, Flutterwave V3
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- 1. SECURITY & CSRF ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION["user_id"];
$current_session = "2025/2026";
$message = $error = "";

// --- 2. FETCH STUDENT DATA ---
$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Error: Student profile missing.");
$student_id = $student['id'];
$program_id = $student['program_id'];

// Normalize the Level string (e.g., converts "100", "100 Level" into "100L")
$raw_level = $student['current_level'] ?? '100L';
$num_only = preg_replace('/[^0-9]/', '', $raw_level);
$normalized_level = $num_only ? $num_only . 'L' : $raw_level;

// --- 3. SMART FEE RESOLUTION ENGINE ---
$fee_amount = 0;
$fee_configured = false;

$stmt_fee = $conn->prepare("
    SELECT amount 
    FROM fee_structures 
    WHERE session = ? 
    AND (program_id = ? OR program_id IS NULL OR program_id = 0)
    AND (level = ? OR level = ? OR level = 'All' OR level IS NULL OR level = '')
    ORDER BY program_id DESC, level DESC 
    LIMIT 1
");
$stmt_fee->bind_param("siss", $current_session, $program_id, $raw_level, $normalized_level);
$stmt_fee->execute();
$fee_result = $stmt_fee->get_result();

if ($fee_result->num_rows > 0) {
    $fee_amount = (float) $fee_result->fetch_assoc()['amount'];
    $fee_configured = true;
}
$stmt_fee->close();

// Generate a unique invoice number
$invoice_no = "FEE/" . date('Y') . "/" . str_pad($student_id, 4, '0', STR_PAD_LEFT);

// --- 4. SECURE MANUAL TELLER UPLOAD ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_manual_proof']) && $fee_configured) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $upload_dir = "../uploads/receipts/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file = $_FILES['proof_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] > 2097152) {
                $error = "File exceeds the 2MB limit.";
            } else {
                // Secure MIME-Type check
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed_mimes = [
                    'image/jpeg'      => 'jpg',
                    'image/png'       => 'png',
                    'application/pdf' => 'pdf'
                ];

                if (array_key_exists($mime_type, $allowed_mimes)) {
                    $ext = $allowed_mimes[$mime_type];
                    $new_name = "FEE_PROOF_" . time() . "_" . $student_id . "_" . bin2hex(random_bytes(2)) . "." . $ext;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                        $stmt_insert = $conn->prepare("INSERT INTO student_payments (student_id, invoice_no, payment_type, amount, session, status, proof_of_payment) VALUES (?, ?, 'School Fees', ?, ?, 'pending', ?)");
                        $stmt_insert->bind_param("isdss", $student_id, $invoice_no, $fee_amount, $current_session, $new_name);
                        
                        if ($stmt_insert->execute()) {
                            $message = "success|Proof uploaded successfully! Awaiting Bursary verification.";
                        } else {
                            $error = "Database error: Could not save payment record.";
                        }
                        $stmt_insert->close();
                    } else {
                        $error = "File upload failed. Please check folder permissions.";
                    }
                } else {
                    $error = "Invalid file type. Must be JPG, PNG, or PDF.";
                }
            }
        } else {
            $error = "Please select a valid file to upload.";
        }
    }
}

// --- 5. CHECK CURRENT PAYMENT STATUS ---
$payment_record = null;
$stmt_check = $conn->prepare("SELECT * FROM student_payments WHERE student_id = ? AND session = ? AND payment_type = 'School Fees' ORDER BY id DESC LIMIT 1");
$stmt_check->bind_param("is", $student_id, $current_session);
$stmt_check->execute();
$res = $stmt_check->get_result();
if ($res->num_rows > 0) {
    $payment_record = $res->fetch_assoc();
}
$stmt_check->close();

$flutterwave_public_key = app_env('FLUTTERWAVE_PUBLIC_KEY', '');

$page_title = "Pay School Fees";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .nav-pills .nav-link { color: #64748b; border-radius: 12px; font-weight: 700; transition: 0.3s; padding: 12px 20px; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    
    .invoice-card { 
        background: #fff; border-radius: 16px; position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    }
    .invoice-header {
        background: #1e293b; color: white; padding: 30px; border-radius: 16px 16px 0 0; text-align: center; position: relative;
    }
    .invoice-header::after {
        content: ''; position: absolute; bottom: -10px; left: 0; right: 0; height: 20px;
        background-image: radial-gradient(circle at 10px 0, transparent 10px, #fff 11px);
        background-size: 25px 20px; background-repeat: repeat-x;
    }
    .invoice-body { padding: 40px 30px 30px; }
    
    .custom-file-upload {
        border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px 20px; background: #fff;
        display: flex; align-items: center; transition: 0.3s; cursor: pointer; position: relative;
    }
    .custom-file-upload:hover { border-color: #5D87FF; box-shadow: 0 4px 10px rgba(93, 135, 255, 0.1); background: #f8fafc; }
    .custom-file-upload i { color: #5D87FF; font-size: 28px; margin-right: 15px; }
    .file-name-display { font-size: 13px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Session School Fees</h3>
            <p class="text-muted small mb-0">Clear your financial obligations for the <?= $current_session ?> academic year.</p>
        </div>
        <a href="dashboard.php" class="btn btn-light border rounded-circle shadow-sm p-2 text-dark hover-primary"><i class="ti ti-arrow-left fs-5"></i></a>
    </div>

    <!-- ALERTS -->
    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 p-4 shadow-sm mb-4 alert-dismissible fade show">
            <div class="d-flex align-items-center"><i class="ti ti-check-circle fs-3 me-3"></i><div><?= $txt ?></div></div>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert bg-danger-subtle text-danger border-0 rounded-4 p-4 shadow-sm mb-4 alert-dismissible fade show animate__animated animate__shakeX">
            <div class="d-flex align-items-center"><i class="ti ti-alert-triangle fs-3 me-3"></i><div><strong>Error:</strong><br><?= $error ?></div></div>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$fee_configured): ?>
        <div class="alert bg-warning-subtle text-dark border-start border-4 border-warning rounded-4 p-4 shadow-sm mb-4">
            <h5 class="fw-bold text-dark mb-1"><i class="ti ti-alert-circle me-2 text-warning"></i>Fee Structure Not Configured</h5>
            <p class="mb-0 text-muted">The Bursary has not yet configured the school fees for <strong><?= htmlspecialchars($student['program_name'] ?? 'your program') ?> (<?= htmlspecialchars($raw_level) ?>)</strong> for the <?= $current_session ?> session. Please check back later or contact the ICT department.</p>
        </div>
    <?php else: ?>

        <div class="row g-4">
            <!-- INVOICE CARD -->
            <div class="col-lg-5">
                <div class="invoice-ticket border-0 h-100">
                    <div class="invoice-header">
                        <h6 class="text-uppercase tracking-wider opacity-75 mb-2 fw-bold small">Official Invoice</h6>
                        <h1 class="fw-black text-white mb-0 display-5">₦<?= number_format($fee_amount, 2) ?></h1>
                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill mt-3 fw-bold"><i class="ti ti-clock-hour-4 me-1"></i> <?= $current_session ?> Session</span>
                    </div>
                    
                    <div class="invoice-body">
                        <table class="table table-borderless table-sm mb-0">
                            <tr>
                                <td class="text-muted fw-bold pb-3">Invoice Ref:</td>
                                <td class="text-end fw-bold font-monospace text-primary pb-3"><?= $invoice_no ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-bold pb-3">Student Name:</td>
                                <td class="text-end fw-bold text-dark pb-3"><?= htmlspecialchars($student['full_name']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-bold pb-3">Matric Number:</td>
                                <td class="text-end fw-bold text-dark pb-3"><?= htmlspecialchars($student['student_id_number']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted fw-bold pb-0">Program/Level:</td>
                                <td class="text-end fw-bold text-dark pb-0"><?= htmlspecialchars($student['program_name']) ?> (<?= htmlspecialchars($raw_level) ?>)</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ACTION PANEL -->
            <div class="col-lg-7">
                <?php 
                $status = $payment_record ? strtolower($payment_record['status']) : 'unpaid';
                
                if ($status === 'successful' || $status === 'paid' || $status === 'approved'): 
                ?>
                    <!-- CLEARED STATE -->
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-success-subtle border-start border-4 border-success">
                        <div class="card-body p-5 text-center d-flex flex-column justify-content-center align-items-center">
                            <i class="ti ti-circle-check fs-1 text-success mb-3" style="font-size: 80px !important;"></i>
                            <h3 class="fw-black text-success mb-2">Payment Cleared!</h3>
                            <p class="text-muted mb-4">Your school fees for the <?= $current_session ?> session have been fully verified. The course registration portal is now unlocked.</p>
                            
                            <div class="d-flex gap-3">
                                <button class="btn btn-outline-success fw-bold rounded-pill px-4 bg-white" onclick="window.print()"><i class="ti ti-printer me-2"></i>Print Receipt</button>
                                <a href="course_registration.php" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">Proceed to Course Reg <i class="ti ti-arrow-right ms-2"></i></a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($status === 'pending'): ?>
                    <!-- PENDING VERIFICATION STATE -->
                    <div class="card border-0 shadow-sm rounded-4 h-100 bg-warning-subtle border-start border-4 border-warning">
                        <div class="card-body p-5 text-center d-flex flex-column justify-content-center align-items-center">
                            <i class="ti ti-clock fs-1 text-warning mb-3" style="font-size: 80px !important;"></i>
                            <h3 class="fw-black text-dark mb-2">Awaiting Verification</h3>
                            <p class="text-muted mb-4">We have received your uploaded bank teller. The Bursary unit is currently verifying the transaction. Please check back within 24 hours.</p>
                            
                            <a href="../uploads/receipts/<?= htmlspecialchars($payment_record['proof_of_payment']) ?>" target="_blank" class="btn btn-warning text-dark fw-bold rounded-pill px-5 shadow-sm">
                                <i class="ti ti-eye me-2"></i>View Uploaded Proof
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- UNPAID / PAYMENT FORM STATE -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                        <div class="card-header bg-light border-0 p-3 pb-0">
                            <ul class="nav nav-pills nav-fill gap-2" id="paymentTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#online">
                                        <i class="ti ti-credit-card me-2"></i>Pay Online (Instant)
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#manual">
                                        <i class="ti ti-building-bank me-2"></i>Bank Transfer
                                    </button>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="card-body p-4 p-md-5">
                            <div class="tab-content" id="paymentTabContent">
                                
                                <!-- ONLINE PAYMENT TAB -->
                                <div class="tab-pane fade show active text-center py-2" id="online">
                                    <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                                        <i class="ti ti-bolt fs-1"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark">Instant Course Unlock</h5>
                                    <p class="text-muted small mb-4">Pay securely using your Debit Card, USSD, or Bank Transfer via Flutterwave. Your course registration will be unlocked immediately.</p>
                                    
                                    <button type="button" id="fwBtn" onclick="payWithFlutterwave()" class="btn btn-primary btn-lg w-100 rounded-pill fw-black shadow-lg d-flex justify-content-center align-items-center transition-all">
                                        <span id="fwBtnText">Pay ₦<?= number_format($fee_amount, 2) ?> Now</span>
                                        <i id="fwBtnIcon" class="ti ti-arrow-right ms-2 fs-5"></i>
                                        <div id="fwBtnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                                    </button>
                                    
                                    <div class="mt-4 opacity-75 small fw-bold text-muted">
                                        <i class="ti ti-shield-lock me-1"></i> Secured by Flutterwave
                                    </div>
                                </div>

                                <!-- MANUAL TRANSFER TAB -->
                                <div class="tab-pane fade" id="manual">
                                    <div class="alert bg-info-subtle border-start border-4 border-info text-dark p-3 rounded-3 mb-4">
                                        <h6 class="fw-bold mb-2">College Bank Details</h6>
                                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Bank Name:</span> <strong>First Bank Nigeria</strong></div>
                                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted">Account Name:</span> <strong>Nafisatu College of Nursing</strong></div>
                                        <div class="d-flex justify-content-between small"><span class="text-muted">Account No:</span> <strong class="fs-6 text-primary">203XXXXXXX</strong></div>
                                    </div>
                                    
                                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" id="manualForm">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        
                                        <div class="mb-4">
                                            <label class="custom-file-upload w-100" for="proofInput">
                                                <i class="ti ti-cloud-upload"></i>
                                                <div class="d-flex flex-column text-start">
                                                    <span class="fw-bold text-dark small">Upload Bank Teller <span class="text-danger">*</span></span>
                                                    <span class="file-name-display" id="proofName">Click to browse file...</span>
                                                </div>
                                            </label>
                                            <input type="file" name="proof_file" id="proofInput" class="visually-hidden" accept=".jpg,.jpeg,.png,.pdf" required>
                                            <small class="text-muted d-block mt-2" style="font-size: 11px;">Max size: 2MB. JPG, PNG, or PDF allowed.</small>
                                        </div>
                                        
                                        <button type="submit" id="submitProofBtn" name="submit_manual_proof" class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow-sm d-flex justify-content-center align-items-center">
                                            <span id="proofBtnText">Submit Proof for Verification</span>
                                            <i id="proofBtnIcon" class="ti ti-upload ms-2"></i>
                                            <div id="proofBtnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                                        </button>
                                    </form>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?> 
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- FLUTTERWAVE V3 INTEGRATION -->
<script src="https://checkout.flutterwave.com/v3.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Custom File Input Display
    const proofInput = document.getElementById('proofInput');
    if(proofInput) {
        proofInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : "Click to browse file...";
            if(e.target.files[0] && e.target.files[0].size > 2097152) {
                Swal.fire('File Too Large', 'Your receipt must be less than 2MB', 'error');
                this.value = '';
                document.getElementById('proofName').innerText = "Click to browse file...";
                document.getElementById('proofName').classList.remove('text-primary', 'fw-bold');
            } else {
                document.getElementById('proofName').innerText = fileName;
                document.getElementById('proofName').classList.add('text-primary', 'fw-bold');
            }
        });
    }

    // Manual Form Anti-Double Submit
    const manualForm = document.getElementById('manualForm');
    if(manualForm) {
        manualForm.addEventListener('submit', function(e) {
            if(!proofInput.value) {
                e.preventDefault();
                Swal.fire('Missing File', 'Please select a file to upload first.', 'warning');
                return;
            }
            const btn = document.getElementById('submitProofBtn');
            btn.disabled = true;
            document.getElementById('proofBtnText').innerText = "Uploading...";
            document.getElementById('proofBtnIcon').classList.add('d-none');
            document.getElementById('proofBtnSpinner').classList.remove('d-none');
        });
    }
});

// --- FLUTTERWAVE PAYMENT ENGINE ---
function payWithFlutterwave() {
    const btn = document.getElementById('fwBtn');
    btn.disabled = true;
    document.getElementById('fwBtnText').innerText = "Connecting to Secure Gateway...";
    document.getElementById('fwBtnIcon').classList.add('d-none');
    document.getElementById('fwBtnSpinner').classList.remove('d-none');

    FlutterwaveCheckout({
        public_key: <?= json_encode($flutterwave_public_key, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        tx_ref: "<?= $invoice_no ?>_" + Math.floor((Math.random() * 1000000000) + 1),
        amount: <?= $fee_amount ?>,
        currency: "NGN",
        payment_options: "card, banktransfer, ussd",
        customer: {
            email: "<?= htmlspecialchars($student['email']) ?>",
            phone_number: "<?= htmlspecialchars($student['phone']) ?>",
            name: "<?= htmlspecialchars($student['full_name']) ?>"
        },
        customizations: {
            title: "NAFCONM School Fees",
            description: "<?= $current_session ?> Academic Session",
            logo: "https://nafconm.edu.ng/assets/images/logos/favicon.png"
        },
        callback: function (data) {
            // UI State change to show verification is happening
            document.getElementById('fwBtnText').innerText = "Verifying Payment...";
            
            // Redirect to the server-side verification script
            window.location.href = "verify_school_fees.php?transaction_id=" + data.transaction_id + "&tx_ref=" + data.tx_ref + "&session=<?= urlencode($current_session) ?>";
        },
        onclose: function() {
            // Restore Button UI if user cancels/closes modal
            btn.disabled = false;
            document.getElementById('fwBtnText').innerText = "Pay ₦<?= number_format($fee_amount, 2) ?> Now";
            document.getElementById('fwBtnIcon').classList.remove('d-none');
            document.getElementById('fwBtnSpinner').classList.add('d-none');
            
            Swal.fire({
                title: 'Payment Cancelled',
                text: 'You closed the secure payment window. You can click the button to try again when you are ready.',
                icon: 'warning',
                confirmButtonColor: '#1e293b'
            });
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>