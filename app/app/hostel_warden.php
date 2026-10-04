<?php
/**
 * NAFCONM - Hostel Warden Dashboard (Enterprise Grade)
 * Features: Dynamic Session Sync, KPI Widgets, Live Search, CSRF Protection, SweetAlert2
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

// --- 1. DYNAMIC SESSION SYNC ---
$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
if ($sess_query && $sess_query->num_rows > 0) {
    $active_session = $sess_query->fetch_assoc();
    $active_session_id = $active_session['id'];
    $active_session_name = $active_session['name'];
} else {
    $active_session_id = 1; // Fallback
    $active_session_name = "Default Session";
}

// --- 2. HANDLE HOSTEL APPROVAL & BED ASSIGNMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_bed'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $application_id = intval($_POST['application_id']);
        $student_id = intval($_POST['student_id']);
        $action = $_POST['status_action']; // 'Approved' or 'Rejected'

        $conn->begin_transaction();
        try {
            if ($action === 'Approved') {
                $bed_id = intval($_POST['bed_id']);
                if (empty($bed_id)) throw new Exception("Please select a valid bed space to approve.");

                // A. Update Application
                $stmt_app = $conn->prepare("UPDATE hostel_applications SET status = 'Approved', approval_date = NOW(), approved_by_user_id = ? WHERE id = ?");
                $stmt_app->bind_param("ii", $user_id, $application_id);
                $stmt_app->execute();

                // B. Create Booking
                $stmt_book = $conn->prepare("INSERT INTO hostel_bookings (student_id, bed_id, start_date) VALUES (?, ?, CURDATE())");
                $stmt_book->bind_param("ii", $student_id, $bed_id);
                $stmt_book->execute();

                // C. Mark Bed as Unavailable
                $conn->query("UPDATE beds SET is_available = 0 WHERE id = $bed_id");
                
                $_SESSION['msg'] = "success|Bed space officially assigned to the student.";
            } else {
                // Reject Application
                $stmt_rej = $conn->prepare("UPDATE hostel_applications SET status = 'Rejected', remarks = 'No available space or criteria not met.' WHERE id = ?");
                $stmt_rej->bind_param("i", $application_id);
                $stmt_rej->execute();
                $_SESSION['msg'] = "warning|Hostel application has been rejected.";
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "error|" . $e->getMessage();
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. FETCH AVAILABLE BEDS (For Dropdown) ---
$available_beds = $conn->query("
    SELECT b.id, b.bed_number, r.room_number, h.name as hostel_name, h.gender 
    FROM beds b 
    JOIN rooms r ON b.room_id = r.id 
    JOIN hostels h ON r.hostel_id = h.id 
    WHERE b.is_available = 1 
    ORDER BY h.name, r.room_number, b.bed_number
");

// --- 4. FETCH KPI STATS ---
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM hostel_applications 
    WHERE session_id = $active_session_id
")->fetch_assoc();

// --- 5. FETCH PENDING & PROCESSED APPLICATIONS ---
$applications = $conn->query("
    SELECT ha.*, s.full_name, s.student_id_number, s.photo_path, h.name as requested_hostel, h.gender
    FROM hostel_applications ha
    JOIN students s ON ha.student_id = s.id
    JOIN hostels h ON ha.hostel_id = h.id
    WHERE ha.session_id = $active_session_id
    ORDER BY CASE WHEN ha.status = 'Pending' THEN 1 ELSE 2 END, ha.application_date DESC
");

$page_title = "Hostel Warden";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; border-left: 4px solid transparent; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .stat-card.primary { border-left-color: var(--bs-primary); }
    .stat-card.warning { border-left-color: var(--bs-warning); }
    .stat-card.success { border-left-color: var(--bs-success); }
    .stat-card.danger { border-left-color: var(--bs-danger); }
    
    .table-hover tbody tr { transition: background-color 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    .fw-black { font-weight: 900 !important; }
    
    .search-container { position: relative; max-width: 350px; width: 100%; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    .search-container input { padding-left: 45px; border-radius: 50px; background-color: #f8fafc; border: 1px solid #e2e8f0; transition: 0.3s; }
    .search-container input:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); background-color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Hostel Warden Command</h3>
            <p class="text-muted small mb-0">Review accommodation requests and assign bed spaces for <?= htmlspecialchars($active_session_name) ?>.</p>
        </div>
    </div>

    <!-- KPI STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card primary shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-muted text-uppercase fw-bold mb-1" style="font-size: 11px;">Total Requests</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['total'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-primary-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-folders text-primary fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card warning shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-warning text-uppercase fw-bold mb-1" style="font-size: 11px;">Pending Allocation</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['pending'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-warning-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-hourglass-empty text-warning fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card success shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-success text-uppercase fw-bold mb-1" style="font-size: 11px;">Spaces Allocated</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['approved'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-bed text-success fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card danger shadow-sm border-0 h-100 rounded-4">
                <div class="card-body p-3 p-xl-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="text-danger text-uppercase fw-bold mb-1" style="font-size: 11px;">Rejected / Full</p>
                        <h3 class="fw-black text-dark mb-0"><?= number_format($stats['rejected'] ?? 0); ?></h3>
                    </div>
                    <div class="bg-danger-subtle rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="ti ti-ban text-danger fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN ROSTER CARD -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-home-check text-primary me-2"></i>Student Applications</h6>
            
            <!-- Real-Time Search Bar -->
            <div class="search-container">
                <i class="ti ti-search"></i>
                <input type="text" id="appSearch" class="form-control" placeholder="Search Matric, Name, or Hostel...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Student Identity</th>
                            <th class="py-3 fw-bold">Requested Hostel</th>
                            <th class="py-3 fw-bold text-center">Status</th>
                            <th class="py-3 fw-bold">Applied On</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications->num_rows > 0): while ($app = $applications->fetch_assoc()): ?>
                            <tr class="app-row">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <?php if(!empty($app['photo_path'])): ?>
                                            <img src="../uploads/admissions/<?= htmlspecialchars($app['photo_path']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border" style="width:45px; height:45px;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                                        <?php else: ?>
                                            <div class="bg-primary text-white rounded-circle fw-bold fs-5 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:45px; height:45px;">
                                                <?= strtoupper(substr($app['full_name'],0,1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0 app-name"><?= htmlspecialchars($app['full_name']) ?></h6>
                                            <small class="font-monospace text-primary fw-bold app-matric"><?= htmlspecialchars($app['student_id_number']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark app-hostel"><?= htmlspecialchars($app['requested_hostel']) ?></span>
                                    <small class="d-block text-muted">(<?= $app['gender'] ?> Wing)</small>
                                </td>
                                <td class="text-center">
                                    <?php if ($app['status'] == 'Approved'): ?>
                                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill border border-success-subtle"><i class="ti ti-check me-1"></i>Allocated</span>
                                    <?php elseif ($app['status'] == 'Rejected'): ?>
                                        <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill border border-danger-subtle"><i class="ti ti-x me-1"></i>Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark px-3 py-1 rounded-pill border border-warning"><i class="ti ti-clock me-1"></i>Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small fw-medium">
                                    <i class="ti ti-calendar-event me-1"></i><?= date('d M, Y', strtotime($app['application_date'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($app['status'] == 'Pending'): ?>
                                        <button class="btn btn-sm btn-primary rounded-pill fw-bold px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#assignModal<?= $app['id'] ?>">Process</button>
                                        
                                        <!-- Assign Modal -->
                                        <div class="modal fade text-start" id="assignModal<?= $app['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header bg-dark text-white p-3">
                                                        <h5 class="modal-title fw-bold">Allocate Bed Space</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <div class="modal-body p-4">
                                                            <input type="hidden" name="assign_bed" value="1">
                                                            <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                            <input type="hidden" name="student_id" value="<?= $app['student_id'] ?>">
                                                            
                                                            <div class="alert bg-primary-subtle text-primary border-0 small mb-4 rounded-3">
                                                                <i class="ti ti-info-circle me-1"></i> You are assigning a bed for <strong><?= htmlspecialchars($app['full_name']) ?></strong> in the <strong><?= $app['gender'] ?></strong> wing.
                                                            </div>

                                                            <div class="mb-4">
                                                                <label class="form-label fw-bold small text-muted text-uppercase">Select Available Bed</label>
                                                                <select name="bed_id" class="form-select fw-bold bg-light border-0">
                                                                    <option value="">-- Choose Space (Required for Approval) --</option>
                                                                    <?php 
                                                                    $available_beds->data_seek(0);
                                                                    $has_beds = false;
                                                                    while($b = $available_beds->fetch_assoc()): 
                                                                        if ($b['gender'] == $app['gender']):
                                                                            $has_beds = true;
                                                                    ?>
                                                                        <option value="<?= $b['id'] ?>"><?= $b['hostel_name'] ?> - Room <?= $b['room_number'] ?> (Bed <?= $b['bed_number'] ?>)</option>
                                                                    <?php endif; endwhile; ?>
                                                                </select>
                                                                <?php if(!$has_beds): ?>
                                                                    <small class="text-danger mt-2 d-block"><i class="ti ti-alert-circle me-1"></i>No available beds in the <?= $app['gender'] ?> wing.</small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex justify-content-center gap-2">
                                                                <button type="submit" name="status_action" value="Rejected" class="btn btn-outline-danger fw-bold rounded-pill w-100" formnovalidate>Reject Request</button>
                                                                <button type="submit" name="status_action" value="Approved" class="btn btn-success fw-bold rounded-pill shadow-sm w-100" <?= !$has_beds ? 'disabled' : '' ?>>Approve & Assign</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-muted rounded-pill fw-bold px-4 border disabled">Resolved</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr id="emptyState"><td colspan="5" class="text-center py-5 text-muted"><i class="ti ti-home-minus fs-1 d-block mb-2 opacity-50"></i>No hostel applications found for this session.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 for Toast Notifications & Live Search -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Live Search Engine
    const searchInput = document.getElementById('appSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.app-row');

            rows.forEach(row => {
                const name = row.querySelector('.app-name').innerText.toLowerCase();
                const matric = row.querySelector('.app-matric').innerText.toLowerCase();
                const hostel = row.querySelector('.app-hostel').innerText.toLowerCase();
                
                if (name.includes(filter) || matric.includes(filter) || hostel.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 2. SweetAlert Notifications based on PHP Sessions
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