<?php
/**
 * NAFCONM - Admin Application & Payment Command Center
 * Features: Bursary Payment Override, Teller Verification, Locked Decision Logic
 */
session_start();
require_once '../config/db.php';

// 1. Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("location: admissions_overview.php");
    exit;
}

$app_id = intval($_GET['id']);
$message = "";

// --- 2. LOGIC: BURSARY FINANCIAL ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payment_action'])) {
    $action = $_POST['payment_action'];
    $pay_note = htmlspecialchars($_POST['payment_note'] ?? '');

    if ($action === 'approve') {
        // Approve a student-uploaded proof
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $app_id);
        if ($stmt->execute()) {
            $message = "success|Payment officially approved. Admission decision panel is now UNLOCKED.";
        }
    } elseif ($action === 'reject') {
        // Reject a student-uploaded proof
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'unpaid', proof_of_payment = NULL, admin_note = ? WHERE id = ?");
        $stmt->bind_param("si", $pay_note, $app_id);
        if ($stmt->execute()) {
            $message = "warning|Payment proof rejected. The student has been notified to re-upload their receipt.";
        }
    } elseif ($action === 'admin_pay') {
        // Manual Admin Override (Walk-in Payment)
        // We append the Bursary note to the admin_note column for record-keeping
        $formatted_note = "BURSARY CLEARANCE: " . $pay_note;
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'approved', admin_note = ? WHERE id = ?");
        $stmt->bind_param("si", $formatted_note, $app_id);
        if ($stmt->execute()) {
            $message = "success|Manual payment cleared successfully. Admission decision panel is now UNLOCKED.";
        }
    }
    $stmt->close();
}

// --- 3. LOGIC: REGISTRAR ADMISSION DECISION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admission_action'])) {
    $action = $_POST['admission_action'];
    $admin_note = htmlspecialchars($_POST['admin_note'] ?? '');
    
    if ($action === 'accepted') {
        $conn->begin_transaction();
        try {
            $stmt_fetch = $conn->prepare("SELECT * FROM admission_applications WHERE id = ?");
            $stmt_fetch->bind_param("i", $app_id);
            $stmt_fetch->execute();
            $fetch_app = $stmt_fetch->get_result()->fetch_assoc();

            // Credentials Generation
            $username = preg_replace('/[^a-z]/', '', strtolower($fetch_app['first_name'])) . $app_id;
            $temp_pass = ucfirst(preg_replace('/[^a-z]/', '', strtolower($fetch_app['last_name']))) . '@' . date('Y');
            $hashed_pass = password_hash($temp_pass, PASSWORD_DEFAULT);

            // A. Create User
            $stmt_user = $conn->prepare("INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'student')");
            $stmt_user->bind_param("sss", $username, $fetch_app['email'], $hashed_pass);
            $stmt_user->execute();
            $new_user_id = $stmt_user->insert_id;

            // B. Create Student Profile
            $full_name = $fetch_app['first_name'] . ' ' . $fetch_app['last_name'];
            $stmt_student = $conn->prepare("INSERT INTO students (student_id_number, user_id, full_name, email, phone, program_id, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_student->bind_param("sississ", $username, $new_user_id, $full_name, $fetch_app['email'], $fetch_app['phone'], $fetch_app['program_id'], $fetch_app['photo']);
            $stmt_student->execute();
            $new_student_id = $stmt_student->insert_id;

            // C. Finalize Application
            // If there's an existing Bursary note, we append the admission note to it so we don't overwrite financial history
            $final_note = empty($fetch_app['admin_note']) ? $admin_note : $fetch_app['admin_note'] . " || REGISTRAR: " . $admin_note;
            
            $stmt_app = $conn->prepare("UPDATE admission_applications SET status = 'accepted', admin_note = ?, student_id = ? WHERE id = ?");
            $stmt_app->bind_param("sii", $final_note, $new_student_id, $app_id);
            $stmt_app->execute();

            $conn->commit();
            $_SESSION['msg'] = "Candidate Admitted! Credentials generated for $username";
            header("Location: admissions_overview.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "danger|Error: " . $e->getMessage();
        }
    } elseif ($action === 'rejected') {
        $stmt_reject = $conn->prepare("UPDATE admission_applications SET status = 'rejected', admin_note = ? WHERE id = ?");
        $stmt_reject->bind_param("si", $admin_note, $app_id);
        $stmt_reject->execute();
        $_SESSION['msg'] = "Application rejected.";
        header("Location: admissions_overview.php");
        exit;
    }
}

// --- 4. FETCH DISPLAY DATA ---
$query = $conn->prepare("SELECT a.*, p.name as program_name FROM admission_applications a JOIN programs p ON a.program_id = p.id WHERE a.id = ?");
$query->bind_param("i", $app_id);
$query->execute();
$app = $query->get_result()->fetch_assoc();

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center">
            <a href="admissions_overview.php" class="btn btn-outline-dark rounded-circle p-2 me-3"><i class="ti ti-arrow-left"></i></a>
            <div>
                <h4 class="fw-black mb-0">Reviewing: <?= htmlspecialchars($app['first_name'].' '.$app['last_name']) ?></h4>
                <p class="text-muted small mb-0">Ref: <span class="fw-bold text-primary"><?= $app['reg_no'] ?></span></p>
            </div>
        </div>
        <span class="badge bg-primary px-4 py-2 fs-6 rounded-pill shadow-sm">
            STATUS: <?= strtoupper($app['status']) ?>
        </span>
    </div>

    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 shadow-sm rounded-4 mb-4 animate__animated animate__fadeIn">
            <i class="ti ti-info-circle me-2 fs-5"></i><?= $txt ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body text-center p-4">
                    <img src="../uploads/admissions/<?= $app['photo'] ?>" class="rounded-4 mb-3 border border-4 border-light shadow-sm" style="width: 140px; height: 160px; object-fit: cover;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                    <h5 class="fw-black mb-1"><?= $app['first_name'].' '.$app['last_name'] ?></h5>
                    <p class="text-primary fw-bold small"><?= $app['program_name'] ?></p>
                    <hr>
                    <div class="text-start small">
                        <?php 
                            $p_status = strtolower($app['payment_status']);
                            $p_color = ($p_status == 'approved' || $p_status == 'paid') ? 'success' : (($p_status == 'pending') ? 'warning' : 'danger');
                        ?>
                        <div class="mb-2 text-muted border p-2 rounded bg-light">PAY STATUS: <strong class="text-<?= $p_color ?> float-end"><?= strtoupper($p_status) ?></strong></div>
                        <div class="text-muted border p-2 rounded bg-light">JAMB SCORE: <strong class="text-dark float-end"><?= $app['jamb_score'] ?: 'N/A' ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Academic Matrix (O-Levels)</h6>
                    <?php
                    $res_stmt = $conn->prepare("SELECT * FROM admission_results WHERE application_id = ?");
                    $res_stmt->bind_param("i", $app_id);
                    $res_stmt->execute();
                    $results = $res_stmt->get_result();
                    if($results->num_rows > 0):
                        while($row = $results->fetch_assoc()): ?>
                            <div class="d-flex justify-content-between py-2 border-bottom border-light">
                                <span class="text-muted small fw-bold text-uppercase"><?= $row['subject'] ?></span>
                                <span class="fw-black text-dark"><?= $row['grade'] ?></span>
                            </div>
                        <?php endwhile; 
                    else: ?>
                        <p class="text-muted small fst-italic">No results declared.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            
            <?php if ($p_status === 'pending'): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-warning bg-warning-subtle">
                    <div class="card-body p-4">
                        <h5 class="fw-black text-dark mb-3"><i class="ti ti-receipt-2 me-2"></i>Step 1: Verify Uploaded Teller</h5>
                        <p class="small text-muted mb-4">The applicant uploaded a payment proof. If it is valid, approve it to unlock the admission decision.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Rejection Reason (Only if rejecting)</label>
                                <textarea name="payment_note" class="form-control form-control-sm bg-white border-0" rows="2" placeholder="e.g. Reference number not found on bank statement."></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="payment_action" value="reject" class="btn btn-outline-danger fw-bold rounded-pill px-4" onclick="return confirm('Reject this payment proof?')">Reject Proof</button>
                                <button type="submit" name="payment_action" value="approve" class="btn btn-success fw-bold rounded-pill px-4 shadow" onclick="return confirm('Confirm payment is verified?')">Approve & Clear Finance</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($p_status === 'unpaid'): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-info bg-info-subtle">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div>
                                <h5 class="fw-black text-dark mb-1"><i class="ti ti-cash-banknote me-2 text-info"></i>Bursary Manual Override</h5>
                                <p class="small text-muted mb-0">The applicant is currently <strong>UNPAID</strong>. If they paid cash or brought a physical teller to the Bursary, you can manually clear their payment here.</p>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Payment Reference / Receipt Number</label>
                                <input type="text" name="payment_note" class="form-control bg-white border-0" placeholder="e.g. Paid Cash - Receipt #5021" required>
                            </div>
                            <button type="submit" name="payment_action" value="admin_pay" class="btn btn-info text-white fw-bold rounded-pill px-5 shadow-sm" onclick="return confirm('Are you sure you want to forcefully clear this payment on behalf of the student?')">
                                Force Clear Payment
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4 bg-success-subtle border-start border-4 border-success">
                    <div class="card-body p-3 d-flex align-items-center">
                        <i class="ti ti-circle-check fs-1 text-success me-3"></i>
                        <div>
                            <h6 class="fw-bold text-success mb-0">Financial Clearance Granted</h6>
                            <small class="text-dark">This application fee has been settled. Admission panel is unlocked.</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-4"><i class="ti ti-folders text-primary me-2"></i>Verification Documents</h6>
                    <div class="row g-3">
                        <?php 
                        $docs = [
                            ['title' => 'Bank Proof', 'file' => $app['proof_of_payment'] ?? '', 'icon' => 'ti-receipt', 'color' => 'success'],
                            ['title' => 'SSCE Cert', 'file' => $app['ssce_attachment'], 'icon' => 'ti-certificate', 'color' => 'primary'],
                            ['title' => 'Birth Cert', 'file' => $app['birth_attachment'], 'icon' => 'ti-baby-carriage', 'color' => 'warning'],
                            ['title' => 'Indigene', 'file' => $app['indigene_attachment'], 'icon' => 'ti-map-pin', 'color' => 'info']
                        ];
                        foreach($docs as $doc): if(!empty($doc['file'])):
                        ?>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-3 d-flex align-items-center doc-card" onclick="viewDocument('../uploads/admissions/<?= $doc['file'] ?>', '<?= $doc['title'] ?>')">
                                    <div class="bg-<?= $doc['color'] ?>-subtle text-<?= $doc['color'] ?> p-3 rounded-circle me-3"><i class="ti <?= $doc['icon'] ?> fs-5"></i></div>
                                    <div><span class="fw-bold d-block small"><?= $doc['title'] ?></span><small class="text-muted">Click to preview</small></div>
                                </div>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            </div>

            <?php 
                $is_cleared = ($p_status === 'paid' || $p_status === 'approved');
                $is_decided = in_array(strtolower($app['status']), ['accepted', 'rejected']);
            ?>

            <?php if ($is_cleared && !$is_decided): ?>
                <div class="card border-0 shadow-lg rounded-4 border-top border-4 border-primary">
                    <div class="card-body p-5">
                        <h4 class="fw-black mb-3 text-center">Step 2: Admission Decision</h4>
                        <p class="text-muted small text-center mb-4">The candidate's finances are clear. You may now admit or reject them.</p>
                        <form method="POST">
                            <textarea name="admin_note" class="form-control mb-4 bg-light border-0" rows="3" placeholder="Admission note for candidate (Optional)..."></textarea>
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="submit" name="admission_action" value="rejected" class="btn btn-outline-danger rounded-pill px-5 py-3 fw-bold" onclick="return confirm('Reject this application?')">Reject Candidate</button>
                                <button type="submit" name="admission_action" value="accepted" class="btn btn-primary rounded-pill px-5 py-3 fw-black shadow-lg hover-lift" onclick="return confirm('Admit candidate and generate student portal access?')">Approve Admission</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif (!$is_cleared): ?>
                <div class="alert bg-light border-dashed p-5 text-center rounded-4 shadow-none mb-0">
                    <i class="ti ti-lock fs-1 text-muted mb-3 d-block"></i>
                    <h6 class="fw-bold text-muted mb-0">Admission Panel Locked</h6>
                    <p class="small text-muted mb-0">The applicant must have a verified payment status before admission can be processed.</p>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 bg-light text-center p-5">
                    <i class="ti ti-gavel fs-1 text-muted mb-2"></i>
                    <h5 class="fw-bold text-dark">Decision Concluded</h5>
                    <p class="text-muted small mb-0">This application was marked as <strong><?= strtoupper($app['status']) ?></strong>.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="modal fade" id="docModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold" id="docTitle"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-0 bg-light" id="docBody" style="min-height: 70vh;"></div></div></div></div>

<script>
function viewDocument(url, title) {
    document.getElementById('docTitle').innerText = title;
    const body = document.getElementById('docBody');
    body.innerHTML = url.toLowerCase().endsWith('.pdf') ? `<iframe src="${url}" width="100%" height="600px" style="border:none;"></iframe>` : `<div class="p-3 text-center"><img src="${url}" class="img-fluid rounded shadow" style="max-height: 75vh;"></div>`;
    new bootstrap.Modal(document.getElementById('docModal')).show();
}
</script>

<style>
    .fw-black { font-weight: 900 !important; }
    .bg-warning-subtle { background-color: rgba(255, 193, 7, 0.1) !important; }
    .bg-info-subtle { background-color: rgba(13, 202, 240, 0.1) !important; }
    .doc-card { cursor: pointer; transition: 0.3s; background: #fff; }
    .doc-card:hover { border-color: #5D87FF !important; background: #f0f5ff; transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.05)!important; }
    .border-dashed { border: 2px dashed #dee2e6 !important; }
    .hover-lift { transition: 0.3s; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(93, 135, 255, 0.4) !important; }
</style>

<?php include 'includes/footer.php'; ?>