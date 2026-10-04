<?php
/**
 * NAFCONM - Student Hostel Accommodation Portal
 * Features: Eligibility Guard, Session Sync, Preferred Hostel Selection, Digital Room Key
 */
session_start();
require_once '../config/db.php';

// --- 1. SECURITY & SETUP ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_user_id = (int)$_SESSION['user_id'];
$message = $error = "";

// Fetch active session globally
$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
$active_session = $sess_query->fetch_assoc();
if (!$active_session) die("Error: No active academic session found.");
$active_session_id = $active_session['id'];

// Fetch Student Profile & Eligibility
$stmt_student = $conn->prepare("SELECT id, can_register_courses FROM students WHERE user_id = ? LIMIT 1");
$stmt_student->bind_param("i", $student_user_id);
$stmt_student->execute();
$student = $stmt_student->get_result()->fetch_assoc();
$stmt_student->close();

if (!$student) die("Error: Student profile not found.");
$student_id = $student['id'];
$is_cleared = ($student['can_register_courses'] == 1); // 1 = Fees Paid

// --- 2. HANDLE NEW APPLICATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_for_hostel'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (!$is_cleared) {
        $error = "Financial Hold: You must complete your tuition payment before applying.";
    } else {
        $hostel_id = intval($_POST['hostel_id']);
        
        // Check for existing application IN THIS SESSION
        $check = $conn->prepare("SELECT id FROM hostel_applications WHERE student_id = ? AND session_id = ?");
        $check->bind_param("ii", $student_id, $active_session_id);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            $apply = $conn->prepare("INSERT INTO hostel_applications (student_id, session_id, hostel_id, status) VALUES (?, ?, ?, 'Pending')");
            $apply->bind_param("iii", $student_id, $active_session_id, $hostel_id);
            if ($apply->execute()) {
                $message = "success|Application submitted successfully! The Warden will review your request.";
            } else {
                $error = "System error. Please try again later.";
            }
            $apply->close();
        } else {
            $error = "You already have an active application for this session.";
        }
        $check->close();
    }
}

// --- 3. FETCH AVAILABLE HOSTELS FOR FORM ---
$hostels = $conn->query("SELECT id, name, gender FROM hostels ORDER BY name ASC");

// --- 4. FETCH CURRENT APPLICATION & BOOKING ---
$application = null;
$stmt_app = $conn->prepare("SELECT a.*, h.name as requested_hostel FROM hostel_applications a LEFT JOIN hostels h ON a.hostel_id = h.id WHERE a.student_id = ? AND a.session_id = ? LIMIT 1");
$stmt_app->bind_param("ii", $student_id, $active_session_id);
$stmt_app->execute();
$application = $stmt_app->get_result()->fetch_assoc();
$stmt_app->close();

$booking = null;
if ($application && $application['status'] === 'Approved') {
    $sql_book = "SELECT hb.*, h.name as h_name, r.room_number, b.bed_number 
                 FROM hostel_bookings hb 
                 JOIN beds b ON hb.bed_id = b.id 
                 JOIN rooms r ON b.room_id = r.id 
                 JOIN hostels h ON r.hostel_id = h.id 
                 WHERE hb.student_id = ? AND hb.status = 'booked'";
    $stmt_book = $conn->prepare($sql_book);
    $stmt_book->bind_param("i", $student_id);
    $stmt_book->execute();
    $booking = $stmt_book->get_result()->fetch_assoc();
    $stmt_book->close();
}

$page_title = 'Hostel Application';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .form-floating > label { color: #64748b; font-weight: 500; }
    .form-select { border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: none !important; }
    .form-select:focus { border-color: #5D87FF; }
    .transition-all { transition: all 0.5s ease; }
    
    @media print {
        .left-sidebar, .app-header, .btn, .nav-item { display: none !important; }
        .body-wrapper { margin: 0 !important; padding: 0 !important; }
        .card { border: 2px solid #000 !important; box-shadow: none !important; }
        body { background: #fff; }
    }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Hostel Accommodation</h3>
            <p class="text-muted mb-0">Manage your campus residency for the <?= htmlspecialchars($active_session['name']) ?>.</p>
        </div>
        <?php if ($booking): ?>
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">
                <i class="ti ti-printer me-2"></i> Print Slip
            </button>
        <?php endif; ?>
    </div>

    <!-- NOTIFICATION AREA -->
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

    <!-- VISUAL PROGRESS STEPPER -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-between position-relative" style="z-index: 1;">
                <?php 
                    $steps = ['Eligibility', 'Applied', 'Reviewed', 'Allocated'];
                    $current_step = $is_cleared ? 1 : 0;
                    if($application) $current_step = 2;
                    if($application && $application['status'] != 'Pending') $current_step = 3;
                    if($booking) $current_step = 4;

                    foreach($steps as $index => $step): 
                        $active = ($index < $current_step);
                        $is_current = ($index == $current_step - 1);
                ?>
                <div class="text-center bg-white px-2" style="width: 25%;">
                    <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center <?= $active ? 'bg-primary text-white shadow' : 'bg-light text-muted border' ?>" style="width: 45px; height: 45px; border: 4px solid #fff; z-index: 2; position: relative;">
                        <i class="ti <?= $active ? 'ti-check' : 'ti-circle' ?> fs-4"></i>
                    </div>
                    <span class="small fw-bold <?= $is_current ? 'text-primary' : 'text-muted' ?> d-none d-md-block"><?= $step ?></span>
                </div>
                <?php endforeach; ?>
                <!-- Progress Line Background -->
                <div class="position-absolute top-50 start-0 translate-middle-y w-100" style="height: 3px; background: #e2e8f0; z-index: -1;"></div>
                <!-- Progress Line Fill -->
                <div class="position-absolute top-50 start-0 translate-middle-y bg-primary transition-all" style="height: 3px; width: <?= (($current_step-1)/3)*100 ?>%; z-index: -1;"></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- LEFT COLUMN: APPLICATION FORM OR STATUS -->
        <div class="col-lg-7">
            <?php if (!$application): ?>
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center mb-4">
                            <div class="bg-primary-subtle text-primary rounded-circle p-3 me-3"><i class="ti ti-home-plus fs-3"></i></div>
                            <h5 class="fw-bold text-dark mb-0">Request Accommodation</h5>
                        </div>
                        
                        <?php if ($is_cleared): ?>
                            <p class="text-muted mb-4">You are financially cleared. Please select your preferred hostel block. Note that placements are subject to availability and Warden approval.</p>
                            
                            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="hostelForm">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                
                                <div class="form-floating mb-4">
                                    <select name="hostel_id" class="form-select border-2" id="hostelSelect" required>
                                        <option value="">-- Choose Preferred Hostel --</option>
                                        <?php if($hostels && $hostels->num_rows > 0): while($h = $hostels->fetch_assoc()): ?>
                                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?> (<?= $h['gender'] ?> Block)</option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                    <label for="hostelSelect">Preferred Hostel Block <span class="text-danger">*</span></label>
                                </div>

                                <button type="submit" id="submitBtn" name="apply_for_hostel" class="btn btn-primary btn-lg rounded-pill w-100 fw-bold shadow-sm d-flex justify-content-center align-items-center transition-all">
                                    <span id="btnText">Submit Application</span> <i id="btnIcon" class="ti ti-arrow-right ms-2"></i>
                                    <div id="btnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert bg-danger-subtle text-danger border-start border-4 border-danger rounded-3 p-4">
                                <h6 class="fw-bold mb-2"><i class="ti ti-lock me-2"></i>Financial Hold Active</h6>
                                <p class="small mb-0">You must complete your tuition fee payments for the current session before applying for campus residency. Visit the <a href="pay_fees.php" class="fw-bold text-danger text-decoration-underline">Bursary Portal</a> to clear your balance.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-file-description text-primary me-2"></i>Application Details</h5>
                            <?php 
                                $status_color = 'warning';
                                if ($application['status'] == 'Approved') $status_color = 'success';
                                if ($application['status'] == 'Rejected') $status_color = 'danger';
                            ?>
                            <span class="badge bg-<?= $status_color ?>-subtle text-<?= $status_color ?> rounded-pill px-3 py-2 fw-bold border border-<?= $status_color ?>-subtle">
                                <?= strtoupper($application['status']) ?>
                            </span>
                        </div>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-6">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 11px;">Reference ID</small>
                                <span class="fw-black text-primary font-monospace fs-5">#HST-<?= str_pad($application['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 11px;">Submission Date</small>
                                <span class="fw-bold text-dark fs-5"><?= date("M d, Y", strtotime($application['application_date'])) ?></span>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 11px;">Requested Block</small>
                                <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($application['requested_hostel']) ?></span>
                            </div>
                        </div>

                        <?php if ($application['remarks']): ?>
                            <div class="p-3 bg-light rounded-3 border-start border-4 border-<?= $status_color ?>">
                                <small class="fw-bold text-dark d-block mb-1"><i class="ti ti-note me-1"></i> Warden Remarks</small>
                                <p class="mb-0 small text-muted fst-italic">"<?= nl2br(htmlspecialchars($application['remarks'])) ?>"</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: DIGITAL ROOM KEY (ALLOCATION) -->
        <div class="col-lg-5">
            <?php if ($booking): ?>
                <div class="card border-0 shadow-lg rounded-4 bg-primary text-white overflow-hidden h-100 position-relative">
                    <!-- Background Graphic -->
                    <div class="position-absolute top-0 end-0 opacity-10" style="transform: translate(20%, -20%); pointer-events: none;">
                        <i class="ti ti-key" style="font-size: 250px;"></i>
                    </div>
                    
                    <div class="card-body p-4 p-md-5 position-relative z-1 d-flex flex-column justify-content-center">
                        <div class="text-center mb-4">
                            <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold text-uppercase tracking-wider mb-3">Official Placement</span>
                            <h1 class="fw-black text-white display-2 mb-0"><?= htmlspecialchars($booking['room_number']) ?></h1>
                            <p class="fs-5 fw-medium opacity-75">Room Number</p>
                        </div>
                        
                        <div class="bg-white bg-opacity-10 rounded-4 p-4 mb-4 backdrop-blur">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-white border-opacity-25 pb-2">
                                <span class="opacity-75 small text-uppercase fw-bold">Hostel Block</span>
                                <span class="fw-bold fs-5"><?= htmlspecialchars($booking['h_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="opacity-75 small text-uppercase fw-bold">Bed Space</span>
                                <span class="fw-black fs-4 text-warning"><?= htmlspecialchars($booking['bed_number']) ?></span>
                            </div>
                        </div>

                        <div class="text-center">
                            <small class="opacity-50"><i class="ti ti-calendar-check me-1"></i> Allocated on <?= date("d M, Y", strtotime($booking['booked_at'])) ?></small>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-light border border-2 border-white d-flex align-items-center justify-content-center text-center p-5">
                    <div class="bg-white rounded-circle p-4 shadow-sm mb-4">
                        <i class="ti ti-home-off fs-1 text-muted"></i>
                    </div>
                    <h5 class="fw-bold text-dark">No Placement Yet</h5>
                    <p class="text-muted mb-0">Your digital room key and allocation details will appear here once the Warden approves your request.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Anti-Double Submit for Application Form
    const form = document.getElementById('hostelForm');
    if(form) {
        form.addEventListener('submit', function(e) {
            if(form.checkValidity()) {
                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.classList.add('opacity-75');
                document.getElementById('btnText').innerText = "Processing...";
                document.getElementById('btnIcon').classList.add('d-none');
                document.getElementById('btnSpinner').classList.remove('d-none');
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>