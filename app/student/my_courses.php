<?php
/**
 * NAFCONM - My Registered Courses
 * Features: JSON Array Decoding, Dynamic Data Fetching, Empty State UI
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
$current_session = "2025/2026";
$current_semester = "First Semester";

// --- 2. FETCH STUDENT PROFILE ---
$stmt = $conn->prepare("SELECT id, full_name, student_id_number, current_level FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student profile error.");
$student_id = $student['id'];
$level = $student['current_level'] ?? '100L';

// --- 3. FETCH REGISTRATION DATA ---
$is_registered = false;
$reg_data = null;
$course_details = [];

$stmt_reg = $conn->prepare("SELECT * FROM course_registrations WHERE student_id = ? AND session = ? AND semester = ? LIMIT 1");
$stmt_reg->bind_param("iss", $student_id, $current_session, $current_semester);
$stmt_reg->execute();
$reg_result = $stmt_reg->get_result();

if ($reg_result->num_rows > 0) {
    $is_registered = true;
    $reg_data = $reg_result->fetch_assoc();
    
    // Decode the JSON array of course codes (e.g., ["NUR 111", "ANA 111"])
    $registered_codes = json_decode($reg_data['course_codes'], true);
    
    if (!empty($registered_codes)) {
        // Dynamically build the SQL IN clause based on how many courses they registered for
        $placeholders = implode(',', array_fill(0, count($registered_codes), '?'));
        $types = str_repeat('s', count($registered_codes)); // 's' because course codes are strings
        
        $stmt_courses = $conn->prepare("SELECT * FROM courses WHERE course_code IN ($placeholders) ORDER BY is_core DESC, course_code ASC");
        $stmt_courses->bind_param($types, ...$registered_codes);
        $stmt_courses->execute();
        $course_result = $stmt_courses->get_result();
        
        while ($row = $course_result->fetch_assoc()) {
            $course_details[] = $row;
        }
        $stmt_courses->close();
    }
}
$stmt_reg->close();

$page_title = "My Registered Courses";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .table-hover tbody tr { transition: 0.2s; }
    .table-hover tbody tr:hover { background-color: #f8f9ff !important; }
    .empty-state-icon { font-size: 80px; color: #e9ecef; margin-bottom: 20px; }
    .summary-card { background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e9ecef; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">My Courses</h3>
            <p class="text-muted small mb-0">View your officially registered courses for the current semester.</p>
        </div>
        <?php if ($is_registered): ?>
            <a href="print_course_form.php" target="_blank" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold d-flex align-items-center">
                <i class="ti ti-printer fs-5 me-2"></i> Print Form
            </a>
        <?php endif; ?>
    </div>

    <?php if (!$is_registered): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body py-5">
                <i class="ti ti-books empty-state-icon"></i>
                <h4 class="fw-black text-dark mb-2">No Courses Registered</h4>
                <p class="text-muted mb-4 max-w-50 mx-auto">You have not registered any courses for the <strong><?= $current_session ?> (<?= $current_semester ?>)</strong>. Please ensure your fees are paid, then proceed to the registration portal.</p>
                <a href="course_registration.php" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm">
                    Go to Course Registration <i class="ti ti-arrow-right ms-2"></i>
                </a>
            </div>
        </div>

    <?php else: ?>
        <div class="row g-4">
            
            <div class="col-12">
                <div class="card summary-card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="row align-items-center text-center text-md-start">
                            <div class="col-md-3 mb-3 mb-md-0 border-end-md">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Academic Session</small>
                                <h5 class="fw-bold text-dark mb-0"><?= $current_session ?></h5>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0 border-end-md">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Semester & Level</small>
                                <h5 class="fw-bold text-primary mb-0"><?= $current_semester ?> (<?= htmlspecialchars($level) ?>)</h5>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0 border-end-md">
                                <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Total Registered Units</small>
                                <h5 class="fw-black text-dark mb-0 fs-3"><?= $reg_data['total_credits'] ?> <span class="fs-6 fw-medium text-muted">Units</span></h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 11px;">Approval Status</small>
                                <?php if(strtolower($reg_data['status']) === 'approved'): ?>
                                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold"><i class="ti ti-circle-check me-1"></i> Officially Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill fw-bold"><i class="ti ti-clock me-1"></i> Pending Approval</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h6 class="fw-bold mb-0 text-dark"><i class="ti ti-list-details text-primary me-2"></i>Registered Course List</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3 fw-bold">S/N</th>
                                        <th class="py-3 fw-bold">Course Code</th>
                                        <th class="py-3 fw-bold">Course Title</th>
                                        <th class="py-3 fw-bold text-center">Credit Units</th>
                                        <th class="py-3 fw-bold text-center pe-4">Course Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($course_details)): 
                                        $count = 1;
                                        foreach ($course_details as $c): 
                                            $is_core = $c['is_core'] == 1;
                                    ?>
                                        <tr>
                                            <td class="ps-4 py-3 text-muted fw-bold"><?= $count++ ?></td>
                                            <td><span class="fw-bold text-primary"><?= htmlspecialchars($c['course_code']) ?></span></td>
                                            <td><span class="text-dark fw-medium"><?= htmlspecialchars($c['course_title']) ?></span></td>
                                            <td class="text-center">
                                                <div class="bg-light d-inline-block px-3 py-1 rounded-3 fw-bold text-dark border">
                                                    <?= $c['credit_units'] ?>
                                                </div>
                                            </td>
                                            <td class="text-center pe-4">
                                                <?php if($is_core): ?>
                                                    <span class="badge bg-danger-subtle text-danger px-3 py-1 border border-danger-subtle">Core</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info-subtle text-info px-3 py-1 border border-info-subtle">Elective</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">Error loading course details. Please contact ICT.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

</div>

<style>
    @media (min-width: 768px) {
        .border-end-md { border-right: 1px solid #e9ecef; }
    }
</style>

<?php include 'includes/footer.php'; ?>