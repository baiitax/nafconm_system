<?php
/**
 * NAFCONM - Central Payment & Bursary Dashboard
 * Features: Fixed Schema Crash, Resilient Revenue Tracking, Teller Verification, Scholarship Manager
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

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION["user_id"];

// Fetch Active Session
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// --- 1. HANDLE TELLER VERIFICATION (APPROVE / REJECT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_teller'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $payment_id = intval($_POST['payment_id']);
        $action = $_POST['action_type']; // 'approve' or 'reject'
        $note = htmlspecialchars(trim($_POST['admin_note']));

        if ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE student_payments SET status = 'approved', admin_note = ? WHERE id = ?");
            $stmt->bind_param("si", $note, $payment_id);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "success|Manual payment teller verified and approved successfully.";
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE student_payments SET status = 'failed', admin_note = ? WHERE id = ?");
            $stmt->bind_param("si", $note, $payment_id);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "warning|Payment teller rejected. Student must upload a valid receipt.";
            }
            $stmt->close();
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. GRANT SCHOLARSHIP / WAIVER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['grant_scholarship'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $matric_no = htmlspecialchars(trim($_POST['matric_no']));
        $waiver_type = htmlspecialchars($_POST['payment_type']);
        $amount_waived = floatval($_POST['amount_waived']);
        $note = htmlspecialchars(trim($_POST['scholarship_note']));
        $invoice_no = "SCH-" . strtoupper(substr(uniqid(), -6)); // Prepended with SCH- to identify scholarships

        // Get student ID
        $stmt_s = $conn->prepare("SELECT id FROM students WHERE student_id_number = ? LIMIT 1");
        $stmt_s->bind_param("s", $matric_no);
        $stmt_s->execute();
        $student = $stmt_s->get_result()->fetch_assoc();
        $stmt_s->close();

        if ($student) {
            // BUG FIX: Removed 'payment_method' column reference
            $stmt = $conn->prepare("INSERT INTO student_payments (student_id, session, invoice_no, payment_type, amount, status, admin_note, payment_date) VALUES (?, ?, ?, ?, ?, 'approved', ?, NOW())");
            $stmt->bind_param("isssds", $student['id'], $current_session, $invoice_no, $waiver_type, $amount_waived, $note);
            if ($stmt->execute()) {
                $_SESSION['msg'] = "success|Scholarship/Waiver granted successfully for $matric_no.";
            }
            $stmt->close();
        } else {
            $_SESSION['msg'] = "error|Student with Matriculation Number '$matric_no' not found.";
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']) . "?tab=scholarships");
    exit;
}

// --- 3. FETCH FINANCIAL KPIs (BULLETPROOF ERROR HANDLING) ---
// BUG FIX: Rewrote query to avoid missing 'payment_method' column. 
// Uses 'invoice_no' pattern and 'proof_of_payment' NULL checks instead.
$kpi_query = $conn->query("
    SELECT 
        SUM(CASE WHEN status IN ('paid', 'approved', 'successful') AND invoice_no NOT LIKE 'SCH-%' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tellers,
        SUM(CASE WHEN proof_of_payment IS NULL AND invoice_no NOT LIKE 'SCH-%' AND status IN ('paid', 'successful', 'approved') THEN 1 ELSE 0 END) as online_success,
        SUM(CASE WHEN invoice_no LIKE 'SCH-%' THEN amount ELSE 0 END) as total_waived
    FROM student_payments 
    WHERE session = '$current_session'
");

// Safely map the data so the page never crashes again!
$stats = $kpi_query ? $kpi_query->fetch_assoc() : [
    'total_revenue' => 0, 
    'pending_tellers' => 0, 
    'online_success' => 0, 
    'total_waived' => 0
];

// --- 4. FETCH PAYMENT DATA (By Category) ---

// Pending Tellers (Manual Uploads)
$pending_tellers = $conn->query("
    SELECT p.*, s.full_name, s.student_id_number, s.photo_path 
    FROM student_payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.status = 'pending' AND p.session = '$current_session'
    ORDER BY p.payment_date ASC
");

// Online Transactions (Identified by having no manual proof uploaded and not being a scholarship)
$online_tx = $conn->query("
    SELECT p.*, s.full_name, s.student_id_number 
    FROM student_payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.proof_of_payment IS NULL AND p.invoice_no NOT LIKE 'SCH-%' AND p.session = '$current_session'
    ORDER BY p.payment_date DESC LIMIT 100
");

// Scholarships & Waivers (Identified by SCH- prefix)
$scholarships = $conn->query("
    SELECT p.*, s.full_name, s.student_id_number 
    FROM student_payments p
    JOIN students s ON p.student_id = s.id
    WHERE p.invoice_no LIKE 'SCH-%' AND p.session = '$current_session'
    ORDER BY p.payment_date DESC
");

$active_tab = $_GET['tab'] ?? 'tellers';
$page_title = "Bursary Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.success { border-left-color: var(--bs-success); }
    .stat-card.info { border-left-color: var(--bs-info); }
    
    .nav-pills .nav-link { border-radius: 50px; font-weight: 700; color: #64748b; padding: 10px 24px; margin-right: 10px; border: 1px solid transparent; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .nav-pills .nav-link:hover:not(.active) { background-color: #f8fafc; border-color: #e2e8f0; }
    
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }

    .search-container { position: relative; max-width: 350px; width: 100%; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    .search-container input { padding-left: 45px; border-radius: 50px; background-color: #f8fafc; border: 1px solid #e2e8f0; transition: 0.3s; }
    .search-container input:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); background-color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Central Bursary Dashboard</h3>
            <p class="text-muted small mb-0">Track revenue, verify manual tellers, and manage scholarships for <?= htmlspecialchars($current_session) ?>.</p>
        </div>
    </div>

    <!-- FINANCIAL KPI WIDGETS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Cleared Revenue</p>
                        <h3 class="fw-black text-dark mb-0">₦<?= number_format($stats['total_revenue'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-cash-banknote text-success fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Pending Tellers</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['pending_tellers'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-receipt text-warning fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-primary text-uppercase fw-bold mb-1" style="font-size: 11px;">Online Success</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['online_success'] ?? 0); ?> Txns</h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-credit-card text-primary fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card info shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-info text-uppercase fw-bold mb-1" style="font-size: 11px;">Waivers & Scholarships</p>
                        <h3 class="fw-black text-dark mb-0">₦<?= number_format($stats['total_waived'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-info-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-school text-info fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABS NAV -->
    <ul class="nav nav-pills mb-4" id="bursaryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'tellers' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tellers" type="button" role="tab"><i class="ti ti-receipt-2 me-2"></i>Pending Tellers</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'online' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#online" type="button" role="tab"><i class="ti ti-credit-card me-2"></i>Online Transactions</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $active_tab === 'scholarships' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#scholarships" type="button" role="tab"><i class="ti ti-school me-2"></i>Scholarships / Waivers</button>
        </li>
    </ul>

    <!-- TABS CONTENT -->
    <div class="tab-content" id="bursaryTabsContent">
        
        <!-- TAB 1: PENDING TELLERS -->
        <div class="tab-pane fade <?= $active_tab === 'tellers' ? 'show active' : '' ?>" id="tellers" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <h5 class="fw-bold mb-0 text-dark">Hanging Bank Tellers Queue</h5>
                    <div class="search-container">
                        <i class="ti ti-search"></i>
                        <input type="text" id="tellerSearch" class="form-control" placeholder="Search by name, matric, or invoice...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 fw-bold">Student Identity</th>
                                    <th class="py-3 fw-bold">Payment Details</th>
                                    <th class="py-3 fw-bold">Date Uploaded</th>
                                    <th class="text-end pe-4 py-3 fw-bold">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_tellers && $pending_tellers->num_rows > 0): while ($p = $pending_tellers->fetch_assoc()): ?>
                                    <tr class="teller-row">
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <?php if(!empty($p['photo_path'])): ?>
                                                    <img src="../uploads/admissions/<?= htmlspecialchars($p['photo_path']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border" style="width:45px; height:45px;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                                                <?php else: ?>
                                                    <div class="bg-primary text-white rounded-circle fw-bold fs-5 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:45px; height:45px;">
                                                        <?= strtoupper(substr($p['full_name'],0,1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0 t-name"><?= htmlspecialchars($p['full_name']) ?></h6>
                                                    <small class="font-monospace text-primary fw-bold t-matric"><?= htmlspecialchars($p['student_id_number']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 mb-1 t-invoice"><?= htmlspecialchars($p['invoice_no']) ?></span><br>
                                            <span class="fw-bold text-dark">₦<?= number_format($p['amount'], 2) ?></span> <small class="text-muted">(<?= htmlspecialchars($p['payment_type']) ?>)</small>
                                        </td>
                                        <td class="text-muted small fw-medium">
                                            <i class="ti ti-calendar me-1"></i><?= date('d M, Y', strtotime($p['payment_date'])) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-warning btn-sm rounded-pill fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#verifyTellerModal<?= $p['id'] ?>">Review Teller</button>
                                        </td>
                                    </tr>

                                    <!-- Verify Teller Modal -->
                                    <div class="modal fade text-start" id="verifyTellerModal<?= $p['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                <div class="modal-header bg-dark text-white p-3">
                                                    <h5 class="modal-title fw-bold">Verify Payment: <?= htmlspecialchars($p['invoice_no']) ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4 bg-light text-center">
                                                    <!-- Proof Viewer -->
                                                    <div class="bg-white p-2 rounded-4 border shadow-sm mb-4 d-inline-block w-100 text-center" style="min-height: 250px;">
                                                        <?php 
                                                        $proof_ext = strtolower(pathinfo($p['proof_of_payment'], PATHINFO_EXTENSION));
                                                        if($proof_ext === 'pdf'): ?>
                                                            <object data="../uploads/payments/<?= htmlspecialchars($p['proof_of_payment']) ?>" type="application/pdf" width="100%" height="400px">
                                                                <p>PDF cannot be displayed. <a href="../uploads/payments/<?= htmlspecialchars($p['proof_of_payment']) ?>" target="_blank">Download here</a>.</p>
                                                            </object>
                                                        <?php elseif (!empty($p['proof_of_payment'])): ?>
                                                            <img src="../uploads/payments/<?= htmlspecialchars($p['proof_of_payment']) ?>" class="img-fluid rounded" style="max-height: 400px; object-fit: contain;">
                                                        <?php else: ?>
                                                            <div class="py-5 text-muted"><i class="ti ti-photo-off fs-1 d-block mb-2"></i> No proof uploaded.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                                        
                                                        <div class="form-floating mb-4">
                                                            <input type="text" name="admin_note" class="form-control rounded-pill px-4 fw-bold" placeholder="Required if rejecting">
                                                            <label class="ps-4">Admin Remarks (Required if rejecting)</label>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-center gap-3">
                                                            <button type="submit" name="action_type" value="reject" class="btn btn-outline-danger fw-bold px-4 py-2 rounded-pill">Reject Payment</button>
                                                            <button type="submit" name="verify_teller" value="approve" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm">Approve & Clear</button>
                                                            <input type="hidden" name="action_type" class="hidden-action-type"> <!-- Handles JS submit -->
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php endwhile; else: ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="ti ti-receipt-off fs-1 d-block mb-2 opacity-50"></i>No pending tellers. The queue is clear!</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: ONLINE TRANSACTIONS -->
        <div class="tab-pane fade <?= $active_tab === 'online' ? 'show active' : '' ?>" id="online" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">Automated Gateway Logs</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 fw-bold">Student</th>
                                    <th class="py-3 fw-bold">Invoice / Type</th>
                                    <th class="py-3 fw-bold">Amount</th>
                                    <th class="py-3 fw-bold text-center">Status</th>
                                    <th class="text-end pe-4 py-3 fw-bold">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($online_tx && $online_tx->num_rows > 0): while ($o = $online_tx->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($o['full_name']) ?></h6>
                                            <small class="font-monospace text-primary"><?= htmlspecialchars($o['student_id_number']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border px-2 py-1 mb-1 font-monospace"><?= htmlspecialchars($o['invoice_no']) ?></span><br>
                                            <small class="text-muted fw-bold"><?= htmlspecialchars($o['payment_type']) ?></small>
                                        </td>
                                        <td class="fw-black text-dark">₦<?= number_format($o['amount'], 2) ?></td>
                                        <td class="text-center">
                                            <?php if (in_array(strtolower($o['status']), ['paid', 'successful', 'approved'])): ?>
                                                <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill"><i class="ti ti-check me-1"></i>Successful</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill"><i class="ti ti-x me-1"></i>Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 text-muted small fw-medium">
                                            <?= date('d M, Y H:i', strtotime($o['payment_date'])) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No online transactions logged yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SCHOLARSHIPS -->
        <div class="tab-pane fade <?= $active_tab === 'scholarships' ? 'show active' : '' ?>" id="scholarships" role="tabpanel">
            <div class="row g-4">
                <!-- Grant Scholarship Form -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-school text-info me-2"></i>Grant Scholarship</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="form-floating mb-3">
                                    <input type="text" name="matric_no" class="form-control fw-bold text-uppercase" placeholder="Matric No" required>
                                    <label>Student Matric Number</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <select name="payment_type" class="form-select fw-bold" required>
                                        <option value="Tuition Fee">Tuition Fee</option>
                                        <option value="Hostel Fee">Hostel Fee</option>
                                        <option value="Clinical Fee">Clinical Fee</option>
                                    </select>
                                    <label>Waiver Category</label>
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="number" name="amount_waived" class="form-control fw-bold font-monospace" placeholder="Amount" required>
                                    <label>Amount Waived (₦)</label>
                                </div>

                                <div class="form-floating mb-4">
                                    <input type="text" name="scholarship_note" class="form-control fw-bold" placeholder="Authority" required>
                                    <label>Authority / Reason</label>
                                </div>
                                
                                <button type="submit" name="grant_scholarship" class="btn btn-info text-white w-100 rounded-pill fw-bold py-3 shadow-sm">Grant Waiver</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Scholarships Table -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white p-4 border-bottom">
                            <h5 class="fw-bold mb-0 text-dark">Active Scholarships & Waivers</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-muted small text-uppercase">
                                        <tr>
                                            <th class="ps-4 py-3 fw-bold">Student</th>
                                            <th class="py-3 fw-bold">Waiver Details</th>
                                            <th class="py-3 fw-bold">Authority Note</th>
                                            <th class="text-end pe-4 py-3 fw-bold">Date Granted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($scholarships && $scholarships->num_rows > 0): while ($s = $scholarships->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($s['full_name']) ?></h6>
                                                    <small class="font-monospace text-primary"><?= htmlspecialchars($s['student_id_number']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info border border-info px-2 py-1 mb-1 font-monospace"><?= htmlspecialchars($s['invoice_no']) ?></span><br>
                                                    <span class="fw-black text-dark">₦<?= number_format($s['amount'], 2) ?></span> <small class="text-muted">(<?= htmlspecialchars($s['payment_type']) ?>)</small>
                                                </td>
                                                <td class="text-muted small fw-medium text-wrap" style="max-width: 200px;">
                                                    <i class="ti ti-notes me-1"></i><?= htmlspecialchars($s['admin_note']) ?>
                                                </td>
                                                <td class="text-end pe-4 text-muted small fw-medium">
                                                    <?= date('d M, Y', strtotime($s['payment_date'])) ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted">No scholarships or waivers granted for this session.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- SweetAlert2 for Toast Notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Live Search for Pending Tellers
    const searchInput = document.getElementById('tellerSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.teller-row');

            rows.forEach(row => {
                const name = row.querySelector('.t-name').innerText.toLowerCase();
                const matric = row.querySelector('.t-matric').innerText.toLowerCase();
                const invoice = row.querySelector('.t-invoice').innerText.toLowerCase();
                
                if (name.includes(filter) || matric.includes(filter) || invoice.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Attach SweetAlert confirmation for Verify Teller buttons
    const verifyForms = document.querySelectorAll('form:has(button[name="verify_teller"])');
    verifyForms.forEach(form => {
        const rejectBtn = form.querySelector('button[value="reject"]');
        const approveBtn = form.querySelector('button[value="approve"]');
        const actionInput = form.querySelector('.hidden-action-type');

        rejectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const noteInput = form.querySelector('input[name="admin_note"]');
            if(noteInput.value.trim() === '') {
                noteInput.reportValidity();
                return;
            }
            actionInput.value = 'reject';
            form.appendChild(actionInput);
            
            Swal.fire({
                title: 'Reject Payment?',
                text: "The student will be notified to upload a new teller.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, Reject it'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        approveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            actionInput.value = 'approve';
            
            Swal.fire({
                title: 'Approve & Clear?',
                text: "This will financially clear the student for this invoice.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#13DEB9',
                confirmButtonText: 'Yes, Approve'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create hidden input to satisfy PHP POST check
                    const submitFlag = document.createElement('input');
                    submitFlag.type = 'hidden';
                    submitFlag.name = 'verify_teller';
                    submitFlag.value = 'approve';
                    form.appendChild(submitFlag);
                    form.submit();
                }
            });
        });
    });

    // SweetAlert Notifications based on PHP Sessions
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); 
        ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $type === "success" ? "success" : ($type === "error" ? "error" : "warning") ?>',
            title: '<?= addslashes($msg) ?>'
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>