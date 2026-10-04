<?php
/**
 * NAFCONM - Central Admissions & Bursary Dashboard
 * Features: Dual-Role UI, MySQL Transactions, SweetAlert2 Integrations, Live Search
 */
session_start();
require_once '../config/db.php';


// Universal Security & RBAC Guard
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_superadmin = ($user_type === 'superadmin' || $user_type === 'admin');

// Fetch Privileges to guard POST requests
if (!$is_superadmin && isset($_SESSION['user_id'])) {
    $_SESSION['privileges'] = [];
    $stmt_p = $conn->prepare("SELECT privilege_name FROM teacher_privileges WHERE user_id = ?");
    $stmt_p->bind_param("i", $_SESSION['user_id']);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    while ($row = $res_p->fetch_assoc()) { $_SESSION['privileges'][] = $row['privilege_name']; }
    $stmt_p->close();
}

// Map the current page to required privilege
$page_map = [
    'admission_desk.php' => 'Admission', 'manage_payments.php' => 'Payment', 
    'hostel_warden.php' => 'Hostel', 'manage_hostels.php' => 'Hostel', 
    'manage_academics.php' => 'Academic', 'student_academic_record.php' => 'Academic'
];
$req_privilege = $page_map[basename($_SERVER['PHP_SELF'])] ?? 'NONE';

if (!isset($_SESSION["loggedin"]) || (!$is_superadmin && !in_array($req_privilege, $_SESSION['privileges'] ?? []))) {
    header("location: ../teacher/index.php");
    exit;
}

// --- 1. BURSARY PAYMENT ACTIONS (FINANCE ROLE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payment_action'])) {
    $app_id = intval($_POST['application_id']);
    $action = $_POST['payment_action'];
    $admin_note = htmlspecialchars($_POST['admin_note'] ?? '');
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $_SESSION['msg'] = "success|Payment verified and approved for Application #$app_id.";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'unpaid', proof_of_payment = NULL, admin_note = CONCAT(IFNULL(admin_note,''), ?) WHERE id = ?");
        $note = " [BURSARY REJECTED: $admin_note] ";
        $stmt->bind_param("si", $note, $app_id);
        $stmt->execute();
        $_SESSION['msg'] = "warning|Payment rejected. The applicant must upload a new valid proof.";
    } elseif ($action === 'admin_pay') {
        $note = " [BURSARY OVERRIDE: $admin_note] ";
        $stmt = $conn->prepare("UPDATE admission_applications SET payment_status = 'approved', admin_note = CONCAT(IFNULL(admin_note,''), ?) WHERE id = ?");
        $stmt->bind_param("si", $note, $app_id);
        $stmt->execute();
        $_SESSION['msg'] = "success|Manual payment cleared successfully for Application #$app_id.";
    }
    
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. REGISTRAR ADMISSION ACTION (THE COMPLEX SQL TRANSACTION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registrar_action'])) {
    $app_id = intval($_POST['application_id']);
    
    // Validate the application is ready
    $app = $conn->query("SELECT * FROM admission_applications WHERE id = $app_id AND status = 'pending'")->fetch_assoc();
    
    if ($app && in_array(strtolower($app['payment_status']), ['paid', 'approved'])) {
        
        // START SECURE TRANSACTION
        $conn->begin_transaction();
        try {
            // A. Generate Matric Number dynamically
            $year = date('Y');
            $count_query = $conn->query("SELECT COUNT(id) FROM students WHERE student_id_number LIKE '%/$year/%'");
            $count = $count_query->fetch_row()[0] + 1;
            $matric_no = "NAF/NUR/$year/" . str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // B. Create Secure User Login
            $username = strtolower(str_replace('/', '', $matric_no)); // e.g. nafnur2026001
            $raw_password = ucfirst(strtolower($app['last_name'])) . "@$year"; // e.g., Baita@2026
            $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
            
            $stmt_u = $conn->prepare("INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'student')");
            $stmt_u->bind_param("sss", $username, $app['email'], $hashed_password);
            $stmt_u->execute();
            $new_user_id = $conn->insert_id;
            $stmt_u->close();
            
            // C. Create Student Profile linked to User ID
            $full_name = strtoupper($app['last_name']) . " " . ucwords(strtolower($app['first_name']));
            $stmt_s = $conn->prepare("INSERT INTO students (student_id_number, user_id, full_name, email, phone, program_id, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_s->bind_param("sisssis", $matric_no, $new_user_id, $full_name, $app['email'], $app['phone'], $app['program_id'], $app['photo']);
            $stmt_s->execute();
            $new_student_id = $conn->insert_id;
            $stmt_s->close();
            
            // D. Update Application Status to Close the Loop
            $admin_note = " [REGISTRAR ADMITTED on " . date('Y-m-d') . ". Matric: $matric_no] ";
            $stmt_a = $conn->prepare("UPDATE admission_applications SET status = 'accepted', student_id = ?, admin_note = CONCAT(IFNULL(admin_note,''), ?) WHERE id = ?");
            $stmt_a->bind_param("isi", $new_student_id, $admin_note, $app_id);
            $stmt_a->execute();
            $stmt_a->close();
            
            // COMMIT TRANSACTION (Saves all 3 tables permanently!)
            $conn->commit();
            $_SESSION['msg'] = "success|Candidate officially admitted! Matric No: <strong>$matric_no</strong>. Auto-Password: <strong>$raw_password</strong>";
            
        } catch (Exception $e) {
            // ROLLBACK if anything fails
            $conn->rollback();
            $_SESSION['msg'] = "danger|Admission failed due to system error: " . $e->getMessage();
        }
    } else {
        $_SESSION['msg'] = "warning|Cannot admit candidate. Financial clearance from the Bursary is strictly required first.";
    }
    
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. KPI DATA AGGREGATION ---
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
    SUM(CASE WHEN status = 'pending' AND (payment_status = 'paid' OR payment_status = 'approved') THEN 1 ELSE 0 END) as pending_admissions,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted
    FROM admission_applications")->fetch_assoc();

// Fetch Pending Applications
$pending_apps = $conn->query("
    SELECT a.*, p.name as program_name 
    FROM admission_applications a 
    JOIN programs p ON a.program_id = p.id 
    WHERE a.status = 'pending'
    ORDER BY 
        CASE WHEN a.payment_status = 'pending' THEN 1 WHEN a.payment_status = 'unpaid' THEN 2 ELSE 3 END, 
        a.application_date DESC
");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.info { border-left-color: var(--bs-info); }
    .stat-card.success { border-left-color: var(--bs-success); }
    
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
    .fw-black { font-weight: 900 !important; }
    
    .search-container { position: relative; max-width: 350px; width: 100%; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    .search-container input { padding-left: 45px; border-radius: 50px; background-color: #f8fafc; border: 1px solid #e2e8f0; transition: 0.3s; }
    .search-container input:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); background-color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col-md-8 mb-3 mb-md-0">
            <h3 class="fw-black text-dark mb-1">Admissions & Bursary Center</h3>
            <p class="text-muted small mb-0">Monitor applications, clear financial holds, and process candidate admissions.</p>
        </div>
    </div>

    <!-- KPI WIDGETS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Applied</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-users text-primary fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info text-uppercase fw-bold mb-1" style="font-size: 11px;">Bursary Review</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['pending_payments'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-info-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-receipt text-info fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Ready to Admit</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['pending_admissions'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-gavel text-warning fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Enrolled Students</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['accepted'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-rosette-discount-check text-success fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION ALERT -->
    <?php if(isset($_SESSION['msg'])): list($color, $text) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 alert-dismissible fade show shadow-sm mb-4 d-flex align-items-center">
            <i class="ti <?= $color === 'success' ? 'ti-circle-check' : 'ti-alert-triangle' ?> fs-4 me-3"></i>
            <div><?= $text; ?></div>
            <button type="button" class="btn-close mt-1" data-bs-dismiss="alert"></button>
        </div>
    <?php unset($_SESSION['msg']); endif; ?>

    <!-- PENDING QUEUE TABLE -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-list-details text-primary me-2"></i>Pending Applicant Queue</h5>
            
            <!-- Real-Time Search Bar -->
            <div class="search-container">
                <i class="ti ti-search"></i>
                <input type="text" id="applicantSearch" class="form-control" placeholder="Search by name, reg no, or program...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="applicantTable">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Applicant Details</th>
                            <th class="py-3 fw-bold">Program</th>
                            <th class="py-3 fw-bold text-center">Bursary Status</th>
                            <th class="py-3 fw-bold text-center">Admissions Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($pending_apps && $pending_apps->num_rows > 0): ?>
                            <?php while($app = $pending_apps->fetch_assoc()): 
                                $p_status = strtolower($app['payment_status']);
                                $is_cleared = ($p_status == 'paid' || $p_status == 'approved');
                                $full_name = ucwords(strtolower($app['first_name'].' '.$app['last_name']));
                            ?>
                            <tr class="applicant-row">
                                <!-- User Details -->
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($app['photo'])): ?>
                                            <img src="../uploads/admissions/<?= htmlspecialchars($app['photo']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border" style="width:45px; height:45px;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded-circle fw-bold fs-5 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:45px; height:45px;">
                                                <?= strtoupper(substr($app['first_name'],0,1).substr($app['last_name'],0,1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold mb-1 text-dark applicant-name"><?= htmlspecialchars($full_name); ?></h6>
                                            <div class="text-muted small fw-medium font-monospace applicant-reg"><?= htmlspecialchars($app['reg_no']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Program -->
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1 rounded-2 fw-semibold d-block text-start mb-1 applicant-program"><?= htmlspecialchars($app['program_name']); ?></span>
                                    <?php if(!empty($app['jamb_score'])): ?>
                                        <div class="text-muted small" style="font-size: 11px;"><i class="ti ti-certificate me-1 text-success"></i>JAMB: <strong><?= htmlspecialchars($app['jamb_score']) ?></strong></div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- BURSARY ROLE COLUMN -->
                                <td class="text-center">
                                    <?php if($is_cleared): ?>
                                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill border border-success-subtle"><i class="ti ti-check me-1"></i>Cleared</span>
                                    <?php elseif($p_status == 'pending'): ?>
                                        <button class="btn btn-sm btn-warning rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $app['id'] ?>"><i class="ti ti-receipt me-1"></i> Review Teller</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-info rounded-pill fw-bold bg-light" data-bs-toggle="modal" data-bs-target="#manualPayModal<?= $app['id'] ?>"><i class="ti ti-cash me-1"></i> Manual Clear</button>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- REGISTRAR ROLE COLUMN -->
                                <td class="text-center pe-4">
                                    <div class="d-flex justify-content-center gap-2">
                                        <a href="view_application.php?id=<?= $app['id']; ?>" class="btn btn-light btn-sm rounded-circle fw-bold text-dark border p-2" title="Review Documents">
                                            <i class="ti ti-eye fs-5"></i>
                                        </a>
                                        
                                        <?php if($is_cleared): ?>
                                            <form method="POST" class="admit-form">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                <input type="hidden" name="registrar_action" value="admit">
                                                <button type="button" class="btn btn-success btn-sm rounded-pill fw-bold shadow-sm px-3 admit-btn" data-name="<?= htmlspecialchars($full_name) ?>">
                                                    <i class="ti ti-gavel me-1"></i> Admit
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm rounded-pill fw-bold disabled opacity-50 px-3" title="Awaiting Financial Clearance">
                                                <i class="ti ti-lock me-1"></i> Locked
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- MODAL: Verify Teller -->
                            <?php if($p_status == 'pending'): ?>
                                <div class="modal fade" id="verifyModal<?= $app['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                            <div class="modal-header bg-dark text-white p-3">
                                                <h5 class="modal-title fw-bold">Verify Teller: <?= htmlspecialchars($app['reg_no']) ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 bg-light text-center">
                                                <div class="bg-white p-2 rounded-4 border shadow-sm mb-4 d-inline-block w-100 text-center" style="min-height: 250px;">
                                                    <?php 
                                                    $proof_ext = strtolower(pathinfo($app['proof_of_payment'], PATHINFO_EXTENSION));
                                                    if($proof_ext === 'pdf'): ?>
                                                        <object data="../uploads/admissions/<?= htmlspecialchars($app['proof_of_payment']) ?>" type="application/pdf" width="100%" height="400px"></object>
                                                    <?php else: ?>
                                                        <img src="../uploads/admissions/<?= htmlspecialchars($app['proof_of_payment']) ?>" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <input type="text" name="admin_note" class="form-control mb-3 rounded-pill px-4" placeholder="Rejection reason (Required if rejecting)...">
                                                    <div class="d-flex justify-content-center gap-3">
                                                        <button type="submit" name="payment_action" value="reject" class="btn btn-outline-danger fw-bold px-4 py-2 rounded-pill">Reject Proof</button>
                                                        <button type="submit" name="payment_action" value="approve" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm">Approve & Clear</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- MODAL: Manual Payment Clear -->
                            <?php if($p_status == 'unpaid'): ?>
                                <div class="modal fade" id="manualPayModal<?= $app['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                            <div class="modal-header bg-info text-white p-3">
                                                <h5 class="modal-title fw-bold"><i class="ti ti-cash me-2"></i>Manual Payment Override</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <form method="POST" class="manual-clear-form">
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <div class="mb-4">
                                                        <label class="form-label fw-bold small text-muted">Receipt / Reference Number</label>
                                                        <input type="text" name="admin_note" class="form-control form-control-lg bg-light border-0" placeholder="e.g. Cash Receipt #104" required>
                                                    </div>
                                                    <button type="button" class="btn btn-info w-100 text-white fw-bold py-3 rounded-pill shadow-sm manual-clear-btn" data-name="<?= htmlspecialchars($full_name) ?>">Force Clear Application Fee</button>
                                                    <!-- Hidden submit button to trigger native form submission after SweetAlert -->
                                                    <button type="submit" name="payment_action" value="admin_pay" class="d-none real-submit-btn"></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted"><i class="ti ti-inbox fs-1 d-block mb-3 opacity-25"></i><h6 class="fw-bold text-dark">No Pending Applications</h6><p class="small">All applicant files have been processed.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 for beautiful, secure confirmation dialogs -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Live Search functionality
    const searchInput = document.getElementById('applicantSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.applicant-row');

            rows.forEach(row => {
                const name = row.querySelector('.applicant-name').innerText.toLowerCase();
                const regNo = row.querySelector('.applicant-reg').innerText.toLowerCase();
                const program = row.querySelector('.applicant-program').innerText.toLowerCase();
                
                if (name.includes(filter) || regNo.includes(filter) || program.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 2. SweetAlert for Admission Action
    const admitBtns = document.querySelectorAll('.admit-btn');
    admitBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.admit-form');
            const candidateName = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Admit Candidate?',
                html: `Are you sure you want to officially admit <strong>${candidateName}</strong>?<br><br><span class="text-muted small">This will immediately generate their Matriculation Number and Student Portal Login.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#13DEB9', // Success green
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Admit Now',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // 3. SweetAlert for Manual Payment Clear
    const manualClearBtns = document.querySelectorAll('.manual-clear-btn');
    manualClearBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.manual-clear-form');
            const candidateName = this.getAttribute('data-name');
            
            // Check if input is empty before firing sweetalert
            const inputField = form.querySelector('input[name="admin_note"]');
            if (inputField.value.trim() === '') {
                inputField.reportValidity(); // Triggers HTML5 native required warning
                return;
            }

            Swal.fire({
                title: 'Force Clear Payment?',
                html: `You are manually bypassing the payment gateway for <strong>${candidateName}</strong>.<br><br>Make sure you have physically verified the receipt.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#49BEFF', // Info Blue
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Clear Payment'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.querySelector('.real-submit-btn').click();
                }
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>