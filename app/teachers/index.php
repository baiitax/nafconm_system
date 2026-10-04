<?php
/**
 * NAFCONM - Teacher Portal Dashboard
 * Features: Crash-Proof Query Engine, Fallback Profile Finder, Real-time Class Analytics
 */
// 1. Force display errors to reveal hidden crashes instead of silent redirect loops
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once '../config/db.php';

// 2. Authorization Guard
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_loggedin = $_SESSION['loggedin'] ?? false;

if (!$is_loggedin || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("Location: /login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
date_default_timezone_set('Africa/Lagos');

// 3. Active Session Check
$current_session_id = 1;
$current_session_name = "2025/2026 Session";

$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
if ($sess_query && $sess_query->num_rows > 0) {
    $active_session = $sess_query->fetch_assoc();
    $current_session_id = (int)$active_session['id'];
    $current_session_name = $active_session['name'];
}

// 4. Fetch Teacher Profile Safely (With Username Fallback)
$teacher_id = 0;
$teacher_full_name = $_SESSION['username'] ?? 'Faculty Member';
$specialization = 'General Nursing';

// Primary Lookup: By user_id
$stmt_teacher = $conn->prepare("SELECT id, full_name, specialization FROM teachers WHERE user_id = ? LIMIT 1");
if ($stmt_teacher) {
    $stmt_teacher->bind_param("i", $user_id);
    $stmt_teacher->execute();
    $res_t = $stmt_teacher->get_result();
    if ($res_t && $res_t->num_rows > 0) {
        $t_row = $res_t->fetch_assoc();
        $teacher_id = (int)$t_row['id'];
        $teacher_full_name = $t_row['full_name'];
        $specialization = $t_row['specialization'] ?: 'General Nursing';
    } else {
        // Fallback Lookup: By Email if user_id link wasn't established
        $user_email = $_SESSION['username'] ?? '';
        $stmt_fb = $conn->prepare("SELECT id, full_name, specialization FROM teachers WHERE email = ? LIMIT 1");
        if ($stmt_fb) {
            $stmt_fb->bind_param("s", $user_email);
            $stmt_fb->execute();
            $res_fb = $stmt_fb->get_result();
            if ($res_fb && $res_fb->num_rows > 0) {
                $tf_row = $res_fb->fetch_assoc();
                $teacher_id = (int)$tf_row['id'];
                $teacher_full_name = $tf_row['full_name'];
                $specialization = $tf_row['specialization'] ?: 'General Nursing';
            }
            $stmt_fb->close();
        }
    }
    $stmt_teacher->close();
}

// Greeting Logic
$hour = (int)date('H');
if ($hour < 12) $greeting = "Good morning";
elseif ($hour < 17) $greeting = "Good afternoon";
else $greeting = "Good evening";

// 5. Fetch Assigned Courses (Resilient Query)
$course_count = 0;
$student_count = 0;
$assigned_courses_list = [];

if ($teacher_id > 0) {
    // Ultra-safe SQL query avoiding missing columns or complex JSON functions
    $sql_courses = "
        SELECT c.id, c.course_code, c.course_title, c.credit_units
        FROM course_teachers ct
        JOIN courses c ON ct.course_id = c.id
        WHERE ct.teacher_id = ? AND ct.session_id = ?
        ORDER BY c.course_code ASC
    ";
    
    if ($stmt = $conn->prepare($sql_courses)) {
        $stmt->bind_param("ii", $teacher_id, $current_session_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $code = $row['course_code'];
            
            // Calculate enrolled students using safe string matching
            $enrolled = 0;
            $stmt_count = $conn->prepare("SELECT COUNT(*) FROM course_registrations WHERE session = ? AND status = 'approved' AND course_codes LIKE ?");
            if ($stmt_count) {
                $like_code = '%' . $code . '%';
                $stmt_count->bind_param("ss", $current_session_name, $like_code);
                $stmt_count->execute();
                $stmt_count->bind_result($enrolled);
                $stmt_count->fetch();
                $stmt_count->close();
            }
            
            $row['enrolled_students'] = $enrolled;
            $assigned_courses_list[] = $row;
            $course_count++;
            $student_count += $enrolled;
        }
        $stmt->close();
    }
}

$page_title = "Faculty Dashboard";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .stat-card { transition: transform 0.2s ease; border-radius: 16px; border: none; }
    .stat-card:hover { transform: translateY(-3px); }
    .course-card { border: 1px solid #e2e8f0; transition: 0.3s; background: #fff; border-radius: 16px; }
    .course-card:hover { border-color: #5D87FF; transform: translateY(-3px); box-shadow: 0 10px 25px rgba(93, 135, 255, 0.1); }
</style>

<div class="container-fluid py-4">

    <!-- Delegated Privileges Notification -->
    <?php if (!empty($_SESSION['privileges'])): ?>
        <div class="card bg-primary-subtle border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="mb-3 mb-md-0 text-center text-md-start">
                    <h5 class="fw-black text-primary mb-1"><i class="ti ti-shield-check fs-4 me-2"></i>Administrative Access</h5>
                    <p class="text-muted small mb-0">You have been granted administrative privileges for the following modules:</p>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <?php if (has_privilege('Admission')): ?>
                        <a href="../app/admissions_overview.php" class="btn btn-primary btn-sm px-4 py-2 fw-bold shadow-sm rounded-pill"><i class="ti ti-user-check me-1"></i> Admissions Desk</a>
                    <?php endif; ?>
                    <?php if (has_privilege('Payment')): ?>
                        <a href="../app/manage_payments.php" class="btn btn-success btn-sm px-4 py-2 fw-bold shadow-sm rounded-pill"><i class="ti ti-cash-banknote me-1"></i> Bursary</a>
                    <?php endif; ?>
                    <?php if (has_privilege('Hostel')): ?>
                        <a href="../app/hostel_warden.php" class="btn btn-warning text-dark btn-sm px-4 py-2 fw-bold shadow-sm rounded-pill"><i class="ti ti-bed me-1"></i> Hostel Warden</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="bg-primary text-white rounded-4 p-4 p-md-5 mb-4 shadow-sm position-relative overflow-hidden">
        <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <h3 class="fw-black mb-1"><?= $greeting ?>, <?= htmlspecialchars(explode(' ', trim($teacher_full_name))[0]) ?> 👋</h3>
                <p class="mb-0 opacity-75">Instructional Portal | <?= htmlspecialchars($current_session_name) ?></p>
            </div>
            <div class="mt-3 mt-md-0">
                <span class="badge bg-white text-primary border-0 px-3 py-2 fw-bold shadow-sm rounded-pill"><i class="ti ti-calendar-event me-1"></i><?= date('d M Y') ?></span>
            </div>
        </div>
    </div>

    <!-- Stats KPI Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="card stat-card bg-primary-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div><p class="text-primary fw-bold text-uppercase small mb-1">Assigned Classes</p><h3 class="fw-black text-primary mb-0"><?= $course_count ?></h3></div>
                    <div class="bg-white rounded-circle p-3 shadow-sm"><i class="ti ti-book fs-3 text-primary"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card stat-card bg-success-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div><p class="text-success fw-bold text-uppercase small mb-1">Total Enrolled Students</p><h3 class="fw-black text-success mb-0"><?= number_format($student_count) ?></h3></div>
                    <div class="bg-white rounded-circle p-3 shadow-sm"><i class="ti ti-users fs-3 text-success"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="card stat-card bg-info-subtle shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div><p class="text-info fw-bold text-uppercase small mb-1">Staff ID / Department</p><h3 class="fw-black text-info mb-0" style="font-size: 15px;"><?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?></h3></div>
                    <div class="bg-white rounded-circle p-3 shadow-sm"><i class="ti ti-id fs-3 text-info"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Courses Section -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white p-4 border-bottom">
            <h5 class="fw-bold text-dark mb-0"><i class="ti ti-books text-primary me-2"></i>My Allocated Courses</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <?php if (!empty($assigned_courses_list)): foreach ($assigned_courses_list as $c): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card course-card h-100 p-2">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace fw-bold"><?= htmlspecialchars($c['course_code']) ?></span>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1"><?= $c['enrolled_students'] ?> Enrolled</span>
                                </div>
                                <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars($c['course_title']) ?></h6>
                                <p class="text-muted small mb-0"><i class="ti ti-award me-1"></i> Credit Units: <strong><?= $c['credit_units'] ?> Units</strong></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="ti ti-folder-off fs-1 d-block mb-2 opacity-50"></i>
                        <h6 class="fw-bold text-dark">No Classes Allocated</h6>
                        <p class="small mb-0">You have not been assigned to any courses for the <?= htmlspecialchars($current_session_name) ?>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>