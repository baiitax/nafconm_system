<?php
/**
 * NAFCONM - Student Portal Dashboard
 * Features: Smart Alerts, Academic Snapshot, Quick Action Hub
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- 1. SECURITY & AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// --- 2. FETCH STUDENT PROFILE ---
$stmt = $conn->prepare("
    SELECT s.*, p.name as program_name 
    FROM students s 
    LEFT JOIN programs p ON s.program_id = p.id 
    WHERE s.user_id = ? 
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Error: Student profile not found. Please contact ICT.");
}

$student_id = $student['id'];
$current_session = "2025/2026"; // In a real app, fetch this from a `settings` table
$current_semester = "First Semester";

// --- 3. SMART METRICS & CHECKS ---
// Check if school fees are paid for the current session (Mock logic based on standard schema)
$fees_paid = false;
$stmt_fees = $conn->prepare("SELECT id FROM student_payments WHERE student_id = ? AND session = ? AND payment_type = 'School Fees' AND status = 'successful' LIMIT 1");
if ($stmt_fees) {
    $stmt_fees->bind_param("is", $student_id, $current_session);
    $stmt_fees->execute();
    if ($stmt_fees->get_result()->num_rows > 0) {
        $fees_paid = true;
    }
    $stmt_fees->close();
}

// Check if courses are registered for the current semester
$courses_registered = false;
$stmt_courses = $conn->prepare("SELECT id FROM course_registrations WHERE student_id = ? AND session = ? AND semester = ? LIMIT 1");
if ($stmt_courses) {
    $stmt_courses->bind_param("iss", $student_id, $current_session, $current_semester);
    $stmt_courses->execute();
    if ($stmt_courses->get_result()->num_rows > 0) {
        $courses_registered = true;
    }
    $stmt_courses->close();
}

// Time-based greeting
$hour = date('H');
$greeting = ($hour < 12) ? "Good Morning" : (($hour < 17) ? "Good Afternoon" : "Good Evening");

$page_title = "Student Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .welcome-card { background: linear-gradient(135deg, #5D87FF 0%, #4062cb 100%); color: white; border: none; }
    .action-card { transition: all 0.3s ease; border: 1px solid #e9ecef; border-radius: 16px; background: #fff; cursor: pointer; text-decoration: none; display: block; color: inherit; }
    .action-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(93, 135, 255, 0.15); border-color: #5D87FF; }
    .icon-box { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 15px; }
    .fw-black { font-weight: 900 !important; }
    .stat-box { background: rgba(255,255,255,0.1); border-radius: 12px; padding: 15px; text-align: center; }
</style>

<div class="container-fluid py-4">
    
    <div class="card welcome-card shadow-sm rounded-4 mb-4 overflow-hidden position-relative">
        <div class="position-absolute top-0 end-0 opacity-25" style="transform: translate(20%, -20%);">
            <i class="ti ti-school" style="font-size: 250px;"></i>
        </div>
        
        <div class="card-body p-4 p-md-5 position-relative z-1">
            <div class="row align-items-center">
                <div class="col-md-8 mb-4 mb-md-0">
                    <h2 class="fw-black mb-1"><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $student['full_name'])[0]) ?>! 👋</h2>
                    <p class="opacity-75 mb-4 text-white">Welcome back to your portal. Here is your academic overview for the <?= $current_session ?> session.</p>
                    
                    <div class="d-flex flex-wrap gap-3">
                        <div class="stat-box">
                            <small class="d-block opacity-75 text-uppercase fw-bold" style="font-size: 10px;">Matric No.</small>
                            <span class="fw-bold fs-5"><?= htmlspecialchars($student['student_id_number']) ?></span>
                        </div>
                        <div class="stat-box">
                            <small class="d-block opacity-75 text-uppercase fw-bold" style="font-size: 10px;">Program</small>
                            <span class="fw-bold fs-6"><?= htmlspecialchars($student['program_name']) ?></span>
                        </div>
                        <div class="stat-box">
                            <small class="d-block opacity-75 text-uppercase fw-bold" style="font-size: 10px;">Level</small>
                            <span class="fw-bold fs-5"><?= htmlspecialchars($student['current_level'] ?? '100L') ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end text-center">
                    <?php if (!empty($student['photo_path'])): ?>
                        <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" class="rounded-circle border border-4 border-white shadow" style="width: 130px; height: 130px; object-fit: cover;" alt="Profile Photo" onerror="this.src='../assets/images/profile/user-1.jpg'">
                    <?php else: ?>
                        <div class="bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center shadow fw-black" style="width: 130px; height: 130px; font-size: 40px;">
                            <?= substr($student['full_name'], 0, 1) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <?php if (!$fees_paid): ?>
            <div class="col-12 animate__animated animate__fadeIn">
                <div class="alert bg-danger-subtle text-danger border-start border-danger border-4 p-4 rounded-4 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h6 class="fw-bold mb-1"><i class="ti ti-alert-triangle me-2 fs-5"></i>School Fees Payment Pending</h6>
                        <p class="mb-0 small text-dark">You have not paid your school fees for the <strong><?= $current_session ?></strong> session. Course registration is locked.</p>
                    </div>
                    <a href="pay_fees.php" class="btn btn-danger fw-bold rounded-pill px-4 shadow-sm">Pay Fees Now</a>
                </div>
            </div>
        <?php elseif (!$courses_registered): ?>
            <div class="col-12 animate__animated animate__fadeIn">
                <div class="alert bg-warning-subtle text-dark border-start border-warning border-4 p-4 rounded-4 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h6 class="fw-bold mb-1"><i class="ti ti-books me-2 fs-5 text-warning"></i>Course Registration Pending</h6>
                        <p class="mb-0 small text-muted">Your fees are cleared, but you haven't registered your courses for the <strong><?= $current_semester ?></strong>.</p>
                    </div>
                    <a href="course_registration.php" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-sm">Register Courses</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <h5 class="fw-bold text-dark mb-3">Quick Actions</h5>
    <div class="row g-4 mb-5">
        
        <div class="col-md-6 col-lg-3">
            <a href="financials.php" class="action-card p-4 text-center">
                <div class="icon-box bg-primary-subtle text-primary mx-auto">
                    <i class="ti ti-wallet"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Financials</h6>
                <p class="small text-muted mb-0">Pay fees & print receipts</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="course_registration.php" class="action-card p-4 text-center">
                <div class="icon-box bg-success-subtle text-success mx-auto">
                    <i class="ti ti-books"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Course Reg.</h6>
                <p class="small text-muted mb-0">Register & print course form</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="results.php" class="action-card p-4 text-center">
                <div class="icon-box bg-warning-subtle text-warning mx-auto">
                    <i class="ti ti-school"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">My Results</h6>
                <p class="small text-muted mb-0">Check semester grades</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="profile.php" class="action-card p-4 text-center">
                <div class="icon-box bg-info-subtle text-info mx-auto">
                    <i class="ti ti-id-badge-2"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Profile & ID</h6>
                <p class="small text-muted mb-0">Update bio-data & print ID</p>
            </a>
        </div>

    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-chart-pie me-2 text-primary"></i>Academic Summary</h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                        <div>
                            <span class="d-block text-muted small fw-bold text-uppercase">Current CGPA</span>
                            <span class="fs-2 fw-black text-dark">--</span>
                        </div>
                        <div class="bg-light rounded-circle p-3"><i class="ti ti-award fs-3 text-warning"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="d-block text-muted small fw-bold text-uppercase">Total Credits Earned</span>
                            <span class="fs-4 fw-bold text-dark">0</span>
                        </div>
                        <div class="bg-light rounded-circle p-3"><i class="ti ti-book fs-4 text-success"></i></div>
                    </div>
                    <div class="mt-4 text-center">
                        <small class="text-muted fst-italic">CGPA is calculated at the end of the academic session.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-activity me-2 text-primary"></i>Recent Activity</h6>
                    <a href="activity_log.php" class="btn btn-sm btn-light fw-bold">View All</a>
                </div>
                <div class="card-body p-4">
                    <div class="position-relative ms-3 border-start border-2 border-primary-subtle ps-4 pb-2">
                        <div class="position-relative mb-4">
                            <span class="position-absolute top-0 start-0 translate-middle p-2 bg-primary border border-white border-3 rounded-circle" style="left: -24px !important;"></span>
                            <h6 class="fw-bold text-dark mb-1">Student Account Created</h6>
                            <p class="text-muted small mb-1">Your portal account was successfully generated following admission.</p>
                            <small class="text-primary fw-bold" style="font-size: 10px;">SYSTEM</small>
                        </div>
                        
                        <div class="position-relative">
                            <span class="position-absolute top-0 start-0 translate-middle p-2 bg-success border border-white border-3 rounded-circle" style="left: -24px !important;"></span>
                            <h6 class="fw-bold text-dark mb-1">Admission Offered</h6>
                            <p class="text-muted small mb-1">Provisional admission was granted for <?= htmlspecialchars($student['program_name']) ?>.</p>
                            <small class="text-success fw-bold" style="font-size: 10px;">REGISTRAR</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>