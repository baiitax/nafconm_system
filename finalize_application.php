<?php
// LOGIC FIRST
session_start();
require_once 'config/db.php';

$app_id = intval($_GET['id'] ?? 0);
$method = $_GET['method'] ?? 'online';
$reference = $_GET['reference'] ?? '';

if (!$app_id) {
    header("Location: apply.php");
    exit;
}

// Update status if online payment was successful
if ($method === 'online' && !empty($reference)) {
    $conn->query("UPDATE admission_applications SET payment_status = 'paid' WHERE id = $app_id");
}

// Fetch Application, Program, and Results
$app_query = $conn->query("SELECT aa.*, p.name as program_name 
                           FROM admission_applications aa 
                           JOIN programs p ON aa.program_id = p.id 
                           WHERE aa.id = $app_id");
$app = $app_query->fetch_assoc();

if (!$app) {
    die("Error: Application not found.");
}

$results = $conn->query("SELECT * FROM admission_results WHERE application_id = $app_id");

// NOW START HTML
$page_title = "Acknowledgment Slip";
include 'includes/header.php';
?>

<style>
    /* Premium Print Styles */
    @media print {
        body { background-color: #fff !important; }
        .no-print, .btn, .navmenu, .topbar { display: none !important; }
        .card { border: none !important; box-shadow: none !important; margin-top: 0 !important; }
        .container { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        .print-border { border: 1px solid #ccc !important; padding: 20px !important; }
        .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
        .bg-light { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="d-flex justify-content-end mb-3 no-print">
                <button onclick="window.print()" class="btn btn-outline-dark fw-bold rounded-pill px-4 shadow-sm">
                    <i class="ti ti-printer me-2"></i> Print / Save Slip
                </button>
            </div>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4 print-border">
                <div class="card-body p-5 text-center position-relative">
                    
                    <i class="ti ti-school position-absolute top-50 start-50 translate-middle text-primary opacity-10" style="font-size: 20rem; z-index: 0;"></i>
                    
                    <div class="position-relative z-1 mb-4">
                        <?php if ($app['payment_status'] === 'paid'): ?>
                            <i class="ti ti-circle-check fs-1 text-success mb-2 d-inline-block" style="font-size: 4rem;"></i>
                            <h2 class="fw-bolder mb-1">Application Confirmed</h2>
                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold border border-success">Payment Verified</span>
                        <?php else: ?>
                            <i class="ti ti-clock-hour-4 fs-1 text-warning mb-2 d-inline-block" style="font-size: 4rem;"></i>
                            <h2 class="fw-bolder mb-1">Application Received</h2>
                            <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill fw-bold border border-warning">Awaiting Payment Verification</span>
                        <?php endif; ?>
                    </div>

                    <div class="row justify-content-center position-relative z-1">
                        <div class="col-md-7">
                            <div class="bg-light p-4 rounded-4 border border-dashed border-2 mb-4">
                                <small class="text-uppercase fw-bold text-muted d-block mb-1">Your Tracking Token</small>
                                <h2 class="fw-bolder text-primary mb-0 letter-spacing-2"><?= htmlspecialchars($app['token']) ?></h2>
                                <p class="small text-muted mt-2 mb-0">Use this token to check your admission status later.</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted mb-0 position-relative z-1 fs-5">Dear <strong class="text-dark"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></strong>, your application for the <strong class="text-dark"><?= htmlspecialchars($app['program_name']) ?></strong> program has been successfully logged.</p>
                </div>

                <div class="card-body bg-white border-top p-4 p-md-5 position-relative z-1">
                    
                    <div class="d-flex align-items-center mb-4 border-bottom pb-4">
                        <?php if(!empty($app['photo'])): ?>
                            <img src="/app/uploads/admissions/<?= htmlspecialchars($app['photo']) ?>" class="rounded-3 border shadow-sm me-4" width="100" height="100" style="object-fit: cover;" alt="Applicant">
                        <?php endif; ?>
                        <div>
                            <h5 class="fw-bolder text-uppercase text-dark mb-1">Applicant Bio-Data</h5>
                            <p class="text-muted mb-0 small">Application Reference: #<?= htmlspecialchars($app['invoice_no']) ?></p>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-3"><span class="text-muted small text-uppercase fw-bold d-block">Full Name</span><span class="fw-medium fs-5 text-dark"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></span></li>
                                <li class="mb-3"><span class="text-muted small text-uppercase fw-bold d-block">Email Address</span><span class="fw-medium text-dark"><?= htmlspecialchars($app['email']) ?></span></li>
                                <li class="mb-0"><span class="text-muted small text-uppercase fw-bold d-block">Phone Number</span><span class="fw-medium text-dark"><?= htmlspecialchars($app['phone']) ?></span></li>
                            </ul>
                        </div>
                        <div class="col-md-6 border-start-md ps-md-4">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-3"><span class="text-muted small text-uppercase fw-bold d-block">Program Selection</span><span class="fw-bold text-primary fs-5"><?= htmlspecialchars($app['program_name']) ?></span></li>
                                <?php if(!empty($app['jamb_number'])): ?>
                                    <li class="mb-3"><span class="text-muted small text-uppercase fw-bold d-block">JAMB Reg Number</span><span class="fw-bolder text-dark"><?= htmlspecialchars($app['jamb_number']) ?></span></li>
                                    <li class="mb-0"><span class="text-muted small text-uppercase fw-bold d-block">JAMB Score</span><span class="fw-bolder text-success fs-5"><?= htmlspecialchars($app['jamb_score']) ?></span></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <h6 class="fw-bolder text-uppercase text-dark mb-3 pt-3 border-top"><i class="ti ti-school me-2 text-primary"></i>Submitted O-Level Results</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th class="fw-bold text-muted small text-uppercase">Subject</th>
                                    <th class="fw-bold text-muted small text-uppercase text-center" style="width: 150px;">Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($results && $results->num_rows > 0): ?>
                                    <?php while($res = $results->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-medium"><?= htmlspecialchars($res['subject']) ?></td>
                                            <td class="text-center fw-bolder"><?= htmlspecialchars($res['grade']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted fst-italic py-3">No O-Level results uploaded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light-subtle p-4 border-top">
                    <h6 class="fw-bolder text-uppercase text-dark mb-3 text-center">What's Next?</h6>
                    <div class="row text-center g-4">
                        <div class="col-md-4">
                            <i class="ti ti-mail fs-5 text-primary mb-2"></i>
                            <p class="small text-muted mb-0 fw-medium">Check your email for the acknowledgment copy.</p>
                        </div>
                        <div class="col-md-4">
                            <i class="ti ti-shield-check fs-5 text-primary mb-2"></i>
                            <p class="small text-muted mb-0 fw-medium">Our registrar will verify your uploaded credentials.</p>
                        </div>
                        <div class="col-md-4">
                            <i class="ti ti-device-analytics fs-5 text-primary mb-2"></i>
                            <p class="small text-muted mb-0 fw-medium">Use your token to track your status on the portal.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center no-print mb-5">
                <a href="index.php" class="btn btn-dark px-5 py-3 fw-bold rounded-pill shadow-sm">
                    <i class="ti ti-home me-2"></i> Return to Homepage
                </a>
            </div>

        </div>
    </div>
</div>

<?php 
// Clean up the session so they can't refresh and mess up the flow
unset($_SESSION['last_app_id']);
include 'includes/footer.php'; 
?>