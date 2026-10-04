<?php
/**
 * NAFCONM - Application Tracking Portal
 * Features: 3-State Financial Tracking, Session Restoration, Auto-Credentials Reveal
 */
session_start();
$page_title = "Track Application Status";

// --- ABSOLUTE PATHING FIX ---
$base_dir = __DIR__;
require_once $base_dir . '/config/db.php';

$applicant = null;
$error = '';
$student_username = null;
$default_password = null;

// --- Handle Tracking Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['track_app'])) {
    
    // Sanitize inputs
    $identifier = htmlspecialchars(strip_tags(trim($_POST['identifier'])));
    // Strip spaces/dashes from phone just in case user types "080 1234 5678"
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone']);

    if (empty($identifier) || empty($phone)) {
        $error = "Please provide both your Application ID/Email and Phone Number.";
    } else {
        // Secure Query: Match either Reg No OR Email, BUT force a match on the Phone Number
        $sql = "
            SELECT a.*, p.name as program_name 
            FROM admission_applications a
            LEFT JOIN programs p ON a.program_id = p.id
            WHERE (a.reg_no = ? OR a.email = ?) AND a.phone = ? 
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $identifier, $identifier, $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $applicant = $result->fetch_assoc();
            
            // Restore session variables
            $_SESSION['last_app_id'] = $applicant['id'];
            $_SESSION['applicant_reg_no'] = $applicant['reg_no'];
            
            // If admitted, fetch and reconstruct login credentials
            if (strtolower($applicant['status']) === 'accepted') {
                // Fetch generated username from the users table based on email
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE email = ? LIMIT 1");
                $user_stmt->bind_param("s", $applicant['email']);
                $user_stmt->execute();
                $user_res = $user_stmt->get_result();
                if ($user_res->num_rows > 0) {
                    $student_username = $user_res->fetch_assoc()['username'];
                }
                $user_stmt->close();

                // Reconstruct the default password assigned by the Admin approval logic
                $clean_lastname = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($applicant['last_name']));
                $app_year = date('Y', strtotime($applicant['application_date'] ?? 'now'));
                $default_password = ucfirst($clean_lastname) . '@' . $app_year;
            }
            
        } else {
            $error = "Authentication Failed: We could not find an application matching this combination.";
        }
        $stmt->close();
    }
}

// Safely require the header
if (file_exists($base_dir . '/includes/header.php')) {
    require_once $base_dir . '/includes/header.php'; 
} else {
    die("System Error: Missing header file.");
}
?>

<style>
    .tracking-wrapper { max-width: 850px; margin: 0 auto; }
    
    /* --- Modern Timeline CSS --- */
    .timeline-steps { display: flex; justify-content: space-between; align-items: center; position: relative; margin-bottom: 40px; padding: 0 20px; }
    .timeline-steps::before { content: ''; position: absolute; top: 25px; left: 40px; right: 40px; height: 4px; background-color: #e9ecef; z-index: 1; }
    .step { position: relative; z-index: 2; text-align: center; width: 25%; }
    .step-icon { width: 54px; height: 54px; border-radius: 50%; background-color: #e9ecef; color: #adb5bd; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 10px auto; border: 4px solid #fff; transition: all 0.3s ease; }
    
    .step.active .step-icon { background-color: #5D87FF; color: #fff; box-shadow: 0 0 0 5px rgba(93, 135, 255, 0.2); }
    .step.success .step-icon { background-color: #13deb9; color: #fff; }
    .step.warning .step-icon { background-color: #ffae1f; color: #fff; box-shadow: 0 0 0 5px rgba(255, 174, 31, 0.2); }
    .step.danger .step-icon { background-color: #fa896b; color: #fff; }
    
    .step-label { font-size: 13px; font-weight: 800; color: #495057; text-transform: uppercase; }
    .step-desc { font-size: 11px; color: #888; }
    .timeline-progress { position: absolute; top: 25px; left: 40px; height: 4px; background-color: #13deb9; z-index: 1; transition: width 0.5s ease; }
    
    .fw-black { font-weight: 900 !important; }
    .bg-warning-subtle { background-color: rgba(255, 174, 31, 0.1) !important; }
    .credential-box { background: #f8f9fa; border: 1px dashed #ced4da; border-radius: 8px; display: flex; align-items: center; padding: 12px 15px; transition: 0.2s; }
    .credential-box:hover { border-color: #5D87FF; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
</style>

<div class="container py-5">
    
    <div class="text-center mb-5">
        <h2 class="fw-black text-dark">Track Your Application</h2>
        <p class="text-muted">Enter your details to check your admission status, reprint your screening pass, or complete a pending payment.</p>
    </div>

    <div class="tracking-wrapper">
        
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 p-4 shadow-sm d-flex align-items-center animate__animated animate__shakeX mb-4">
                <i class="ti ti-shield-x fs-1 me-3"></i>
                <div class="fw-bold"><?= $error ?></div>
            </div>
        <?php endif; ?>

        <?php if (!$applicant): ?>
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-body p-5">
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Registration No. or Email</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-0"><i class="ti ti-id"></i></span>
                                    <input type="text" name="identifier" class="form-control bg-light border-0" placeholder="e.g. NAF/REG/..." required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Registered Phone Number</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-0"><i class="ti ti-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control bg-light border-0" placeholder="080XXXXXXXX" required>
                                </div>
                            </div>
                            <div class="col-12 mt-4 text-center">
                                <button type="submit" name="track_app" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm fs-5">
                                    <i class="ti ti-search me-2"></i> Track Status
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light border-0 p-4 text-center">
                    <small class="text-muted"><i class="ti ti-lock me-1"></i> Your connection is secure. Data is protected by 256-bit encryption.</small>
                </div>
            </div>

        <?php else: ?>
            <?php 
                $pay_status = strtolower($applicant['payment_status']);
                // 'paid' is Paystack, 'approved' is Bursary Manual Override. Both mean successful payment.
                $is_paid = in_array($pay_status, ['paid', 'approved']);
                $is_pending = ($pay_status === 'pending');
                $status = strtolower($applicant['status']); // pending, accepted, rejected
                
                // Timeline Progress Logic
                $step1 = 'success'; 
                $step2 = $is_paid ? 'success' : ($is_pending ? 'warning' : 'active');
                $step3 = ($status === 'accepted' || $status === 'rejected') ? 'success' : ($is_paid ? 'active' : '');
                $step4 = '';
                
                $progress_width = '0%';
                if ($step2 === 'active' || $step2 === 'warning') $progress_width = '15%';
                if ($step2 === 'success') $progress_width = '50%';
                if ($step3 === 'active') $progress_width = '50%';
                
                if ($status === 'accepted') {
                    $step4 = 'success';
                    $progress_width = '100%';
                } elseif ($status === 'rejected') {
                    $step4 = 'danger';
                    $progress_width = '100%';
                }
            ?>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden animate__animated animate__fadeIn">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-black mb-1 text-dark">Applicant Profile</h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']) ?> | <?= htmlspecialchars($applicant['program_name']) ?></p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border px-3 py-2 fw-bold fs-6 rounded-pill">
                        <?= htmlspecialchars($applicant['reg_no'] ?? 'N/A') ?>
                    </span>
                </div>

                <div class="card-body p-4 p-md-5">
                    
                    <div class="timeline-steps">
                        <div class="timeline-progress" style="width: <?= $progress_width ?>;"></div>
                        
                        <div class="step <?= $step1 ?>">
                            <div class="step-icon"><i class="ti ti-file-description"></i></div>
                            <div class="step-label">Applied</div>
                            <div class="step-desc">Data Captured</div>
                        </div>
                        
                        <div class="step <?= $step2 ?>">
                            <div class="step-icon">
                                <?= $is_pending ? '<i class="ti ti-clock"></i>' : '<i class="ti ti-credit-card"></i>' ?>
                            </div>
                            <div class="step-label">Payment</div>
                            <div class="step-desc"><?= $is_paid ? 'Verified' : ($is_pending ? 'Under Review' : 'Awaiting') ?></div>
                        </div>
                        
                        <div class="step <?= $step3 ?>">
                            <div class="step-icon"><i class="ti ti-users"></i></div>
                            <div class="step-label">Screening</div>
                            <div class="step-desc">Clearance</div>
                        </div>
                        
                        <div class="step <?= $step4 ?>">
                            <div class="step-icon">
                                <?php if($status === 'accepted'): ?> <i class="ti ti-award"></i>
                                <?php elseif($status === 'rejected'): ?> <i class="ti ti-x"></i>
                                <?php else: ?> <i class="ti ti-school"></i> <?php endif; ?>
                            </div>
                            <div class="step-label">Decision</div>
                            <div class="step-desc text-capitalize"><?= $status === 'pending' ? 'Awaiting' : $status ?></div>
                        </div>
                    </div>

                    <div class="rounded-4 p-4 mt-5 border <?= $is_pending ? 'bg-warning-subtle border-warning' : 'bg-light border-secondary-subtle' ?>">
                        <div class="row align-items-center">
                            
                            <?php if (!$is_paid && !$is_pending): ?>
                                <div class="col-md-8 mb-3 mb-md-0">
                                    <h5 class="fw-bold text-dark"><i class="ti ti-alert-triangle text-danger me-2"></i> Payment Required</h5>
                                    <p class="text-muted mb-0">Your application has been saved, but it will not be processed until your application fee is verified.</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="payment_details.php" class="btn btn-primary rounded-pill w-100 fw-bold shadow-sm py-2">
                                        Pay / Upload Teller <i class="ti ti-arrow-right ms-1"></i>
                                    </a>
                                </div>

                            <?php elseif ($is_pending): ?>
                                <div class="col-md-8 mb-3 mb-md-0">
                                    <h5 class="fw-bold text-warning"><i class="ti ti-clock text-warning me-2"></i> Payment Under Review</h5>
                                    <p class="text-muted mb-0">We have received your uploaded bank teller. The Bursary unit is currently verifying the transaction. This usually takes 24-48 hours.</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="app/uploads/receipts/<?= htmlspecialchars($applicant['proof_of_payment']) ?>" target="_blank" class="btn btn-outline-warning rounded-pill w-100 fw-bold py-2 border-2">
                                        <i class="ti ti-eye me-1"></i> View My Upload
                                    </a>
                                </div>

                            <?php elseif ($is_paid && $status === 'pending'): ?>
                                <div class="col-md-8 mb-3 mb-md-0">
                                    <h5 class="fw-bold text-success"><i class="ti ti-circle-check text-success me-2"></i> Application Cleared</h5>
                                    <p class="text-muted mb-0">
                                        Your payment has been <strong>officially verified</strong>. You are now cleared for the screening exercise. Please print your official pass.
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <a href="application_slip.php" class="btn btn-success rounded-pill w-100 fw-bold shadow-sm py-2">
                                        <i class="ti ti-printer me-1"></i> Print Pass
                                    </a>
                                </div>

                            <?php elseif ($status === 'accepted'): ?>
                                <div class="col-12 text-center py-3">
                                    <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="ti ti-confetti fs-1"></i>
                                    </div>
                                    <h4 class="fw-black text-success">Congratulations!</h4>
                                    <p class="text-muted mb-4">You have been offered provisional admission into the <strong><?= htmlspecialchars($applicant['program_name']) ?></strong> program.</p>
                                    
                                    <div class="bg-white p-4 rounded-4 shadow-sm border-start border-4 border-success mx-auto mb-4 text-start" style="max-width: 650px;">
                                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="ti ti-key me-2 text-primary"></i>Your Official Student Portal Login</h6>
                                        <p class="small text-muted mb-3">An account has been automatically generated for you. Use these credentials to access the Student Portal.</p>
                                        
                                        <div class="row g-3">
                                            <div class="col-sm-6">
                                                <small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size: 10px;">Portal Username / Matric No.</small>
                                                <div class="credential-box">
                                                    <span class="fw-black font-monospace text-primary fs-5 me-auto" id="credUser"><?= htmlspecialchars($student_username ?? 'Processing...') ?></span>
                                                    <button class="btn btn-sm btn-light text-primary border-0 p-1" onclick="copyCred('credUser')" title="Copy Username"><i class="ti ti-copy fs-5"></i></button>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size: 10px;">Default Password</small>
                                                <div class="credential-box">
                                                    <span class="fw-black font-monospace text-dark fs-5 me-auto" id="credPass"><?= htmlspecialchars($default_password ?? 'Processing...') ?></span>
                                                    <button class="btn btn-sm btn-light text-dark border-0 p-1" onclick="copyCred('credPass')" title="Copy Password"><i class="ti ti-copy fs-5"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 bg-danger-subtle text-danger p-2 rounded-3 small fw-bold d-inline-block">
                                            <i class="ti ti-alert-triangle me-1"></i> For your security, please change your password immediately after your first login.
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                                        <a href="admission_letter.php" class="btn btn-outline-success rounded-pill px-4 fw-bold shadow-sm py-2">
                                            <i class="ti ti-download me-1"></i> Admission Letter
                                        </a>
                                        <a href="../login.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm py-2">
                                            Go to Student Portal <i class="ti ti-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                                
                            <?php elseif ($status === 'rejected'): ?>
                                <div class="col-12 text-center py-3">
                                    <div class="bg-danger-subtle text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="ti ti-mood-sad fs-1"></i>
                                    </div>
                                    <h4 class="fw-black text-danger">Admission Unsuccessful</h4>
                                    <p class="text-muted">We regret to inform you that you have not been offered admission for this academic session.</p>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>

                </div>
                <div class="card-footer bg-white border-top p-3 text-center">
                    <a href="track_application.php" class="text-decoration-none fw-bold text-muted"><i class="ti ti-refresh me-1"></i> Check Another Application</a>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function copyCred(elementId) {
    const text = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Copied to clipboard!',
            showConfirmButton: false,
            timer: 2000
        });
    });
}
</script>

<?php 
if (file_exists($base_dir . '/includes/footer.php')) {
    require_once $base_dir . '/includes/footer.php'; 
}
?>