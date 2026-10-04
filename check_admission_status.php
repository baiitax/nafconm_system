<?php
/**
 * NAFCONM - Admission Status Tracker
 * Features: Premium UI, Schema-Synced Statuses, Matric Number Reveal
 */
session_start();
require_once 'config/db.php';

$status_data = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['track_status'])) {
    $token = trim($_POST['token']);
    $email = trim($_POST['email']);

    // Enhanced Query: Joins the students table to pull the auto-generated Matric Number
    $sql = "SELECT aa.*, p.name as program_name, s.student_id_number 
            FROM admission_applications aa 
            JOIN programs p ON aa.program_id = p.id 
            LEFT JOIN students s ON aa.student_id = s.id
            WHERE aa.token = ? AND aa.email = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $status_data = $result->fetch_assoc();
    } else {
        $error = "No application found with those credentials. Please verify your token and email.";
    }
    $stmt->close();
}

$page_title = "Track Admission Status";
include 'includes/header.php';
?>

<style>
    body { background-color: #f4f7fa; }
    .form-floating > label { color: #64748b; font-weight: 500; }
    .form-control { border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none !important; }
    .form-control:focus { border-color: #5D87FF; }
    .fw-black { font-weight: 900 !important; }
    .tracker-step { position: relative; z-index: 1; padding: 0 10px; background: #fff; }
    .tracker-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 8px auto; transition: 0.3s; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            
            <div class="text-center mb-5">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                    <i class="ti ti-radar fs-1"></i>
                </div>
                <h2 class="fw-black text-dark mb-2">Admission Tracker</h2>
                <p class="text-muted">Enter your unique token to monitor your application progress.</p>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 p-md-5">
                    <form method="POST">
                        <div class="form-floating mb-3">
                            <input type="email" name="email" id="emailInput" class="form-control" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <label for="emailInput">Registered Email Address</label>
                        </div>
                        <div class="form-floating mb-4">
                            <input type="text" name="token" id="tokenInput" class="form-control font-monospace text-uppercase" placeholder="A2B3C4D5" required value="<?= htmlspecialchars($_POST['token'] ?? '') ?>">
                            <label for="tokenInput">Tracking Token</label>
                        </div>
                        <button type="submit" name="track_status" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm d-flex justify-content-center align-items-center">
                            Check Status <i class="ti ti-arrow-right ms-2"></i>
                        </button>
                    </form>
                    
                    <?php if($error): ?>
                        <div class="alert bg-danger-subtle text-danger border-0 small mt-4 rounded-3 d-flex align-items-center">
                            <i class="ti ti-alert-circle fs-5 me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($status_data): 
                $decision = strtolower($status_data['status']); 
                $pay_status = strtolower($status_data['payment_status']);
                $pay_done = ($pay_status === 'paid' || $pay_status === 'approved');
                
                // Color Mapping for Decision
                $dec_color = 'bg-light text-muted border';
                $dec_icon = 'ti-hourglass';
                if ($decision === 'accepted') { $dec_color = 'bg-success text-white shadow-sm'; $dec_icon = 'ti-certificate'; }
                if ($decision === 'rejected') { $dec_color = 'bg-danger text-white shadow-sm'; $dec_icon = 'ti-x'; }
            ?>
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden animate__animated animate__fadeInUp">
                <div class="card-header bg-dark text-white p-4 text-center border-0">
                    <h5 class="fw-black mb-1"><?= strtoupper(htmlspecialchars($status_data['first_name'] . ' ' . $status_data['last_name'])) ?></h5>
                    <span class="badge bg-white text-dark opacity-75 fw-bold"><?= htmlspecialchars($status_data['program_name']) ?></span>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    
                    <!-- Visual Roadmap -->
                    <div class="mb-5 position-relative">
                        <div class="position-absolute top-50 start-0 translate-middle-y w-100" style="height: 3px; background: #e2e8f0; z-index: 0;"></div>
                        
                        <div class="d-flex justify-content-between text-center">
                            <div class="tracker-step">
                                <div class="tracker-icon bg-success text-white shadow-sm"><i class="ti ti-check fs-5"></i></div>
                                <small class="fw-bold text-dark">Applied</small>
                            </div>
                            <div class="tracker-step">
                                <div class="tracker-icon <?= $pay_done ? 'bg-success text-white shadow-sm' : 'bg-light text-muted border' ?>">
                                    <i class="ti <?= $pay_done ? 'ti-check' : 'ti-cash' ?> fs-5"></i>
                                </div>
                                <small class="<?= $pay_done ? 'fw-bold text-dark' : 'text-muted' ?>">Payment</small>
                            </div>
                            <div class="tracker-step">
                                <div class="tracker-icon <?= $dec_color ?>">
                                    <i class="ti <?= $dec_icon ?> fs-5"></i>
                                </div>
                                <small class="<?= ($decision !== 'pending') ? 'fw-bold text-dark' : 'text-muted' ?>">Decision</small>
                            </div>
                        </div>
                    </div>

                    <!-- Status Display -->
                    <div class="text-center p-4 rounded-4 <?= ($decision === 'accepted') ? 'bg-success-subtle text-success' : (($decision === 'rejected') ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary') ?>">
                        
                        <?php if ($decision === 'accepted'): ?>
                            <div class="mb-3"><i class="ti ti-rosette-discount-check fs-1"></i></div>
                            <h4 class="fw-black mb-2">Congratulations!</h4>
                            <p class="small mb-3 text-dark fw-medium">You have been offered provisional admission.</p>
                            
                            <?php if(!empty($status_data['student_id_number'])): ?>
                                <div class="bg-white p-3 rounded-3 border border-success-subtle mb-3">
                                    <small class="text-muted text-uppercase fw-bold d-block mb-1">Official Matric Number</small>
                                    <h4 class="fw-black font-monospace text-success mb-0"><?= $status_data['student_id_number'] ?></h4>
                                </div>
                                <div class="alert alert-success border-0 small text-start">
                                    <i class="ti ti-lock me-1"></i> <strong>Portal Login:</strong> Your default password is your Last Name (Capitalized) followed by @<?= date('Y') ?> (e.g. Doe@<?= date('Y') ?>).
                                </div>
                            <?php endif; ?>

                            <a href="generate_admission_letter.php?id=<?= $status_data['id'] ?>&token=<?= $token ?>" target="_blank" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm">
                                <i class="ti ti-download me-1"></i> Download Admission Letter
                            </a>
                            
                        <?php elseif ($decision === 'rejected'): ?>
                            <div class="mb-3"><i class="ti ti-circle-x fs-1"></i></div>
                            <h4 class="fw-black mb-1">Application Declined</h4>
                            <p class="small mb-0 text-dark fw-medium">We regret to inform you that your application was not successful for this academic session.</p>
                            
                        <?php else: ?>
                            <div class="mb-3"><i class="ti ti-clock-hour-4 fs-1"></i></div>
                            <h4 class="fw-black mb-1">Under Review</h4>
                            <p class="small mb-0 text-dark fw-medium">Your application is currently being evaluated by the Admissions Board. Please check back later.</p>
                        <?php endif; ?>
                        
                    </div>

                    <?php if(!empty($status_data['admin_note']) && $decision !== 'pending'): ?>
                        <div class="mt-4 p-3 border border-warning-subtle rounded-3 bg-light text-start">
                            <small class="fw-bold text-dark text-uppercase d-block mb-1"><i class="ti ti-note me-1"></i> Registrar's Note:</small>
                            <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($status_data['admin_note'])) ?></p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>