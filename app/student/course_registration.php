<?php
/**
 * NAFCONM - Student Course Registration
 * Features: Server-Side Core Enforcement, Dynamic Sessions, Real-time Credit Bar, Financial Gatekeeper
 */
session_start();
$base_dir = __DIR__;
require_once '../config/db.php';

// --- 1. SECURITY & AUTHENTICATION ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION["user_id"];
$message = $error = "";

// Fetch Active Session Dynamically
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';
$current_semester = "First Semester"; // You can easily link this to a settings table later
$max_credits = 24;

// --- 2. FETCH STUDENT PROFILE ---
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Error: Student profile missing.");
$student_id = $student['id'];
$program_id = $student['program_id'];
$level = $student['current_level'] ?? '100L';

// --- 3. GATEKEEPER CHECKS ---
$fees_paid = false;
$courses_registered = false;
$registered_data = null;

try {
    // A. Check Financial Clearance (School Fees paid for active session)
    $stmt_fees = $conn->prepare("SELECT id FROM student_payments WHERE student_id = ? AND session = ? AND payment_type = 'School Fees' AND status IN ('successful', 'approved', 'paid') LIMIT 1");
    $stmt_fees->bind_param("is", $student_id, $current_session);
    $stmt_fees->execute();
    if ($stmt_fees->get_result()->num_rows > 0) $fees_paid = true;
    $stmt_fees->close();

    // B. Check Registration Status
    $stmt_reg = $conn->prepare("SELECT * FROM course_registrations WHERE student_id = ? AND session = ? AND semester = ? LIMIT 1");
    $stmt_reg->bind_param("iss", $student_id, $current_session, $current_semester);
    $stmt_reg->execute();
    $reg_result = $stmt_reg->get_result();
    if ($reg_result->num_rows > 0) {
        $courses_registered = true;
        $registered_data = $reg_result->fetch_assoc();
    }
    $stmt_reg->close();
} catch (Exception $e) {
    $error = "System error during validation checks.";
}

// --- 4. HANDLE FORM SUBMISSION (SECURE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_courses']) && $fees_paid && !$courses_registered) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please refresh and try again.";
    } else {
        $posted_courses = isset($_POST['courses']) ? $_POST['courses'] : [];
        $final_course_ids = [];

        // SECURITY: Automatically fetch and force-inject ALL Core Courses regardless of what the user POSTed
        $stmt_core = $conn->prepare("SELECT id FROM courses WHERE program_id = ? AND level = ? AND semester = ? AND is_core = 1");
        $stmt_core->bind_param("iss", $program_id, $level, $current_semester);
        $stmt_core->execute();
        $res_core = $stmt_core->get_result();
        while($row = $res_core->fetch_assoc()) {
            $final_course_ids[] = $row['id'];
        }
        $stmt_core->close();

        // Merge User Electives with Core Courses, avoiding duplicates
        $final_course_ids = array_unique(array_merge($posted_courses, $final_course_ids));

        if (empty($final_course_ids)) {
            $error = "No courses available to register.";
        } else {
            $total_credits = 0;
            $valid_course_codes = [];

            // Calculate total units securely based on the merged array
            $placeholders = implode(',', array_fill(0, count($final_course_ids), '?'));
            $types = str_repeat('i', count($final_course_ids));
            $stmt_calc = $conn->prepare("SELECT course_code, credit_units FROM courses WHERE id IN ($placeholders)");
            $stmt_calc->bind_param($types, ...$final_course_ids);
            $stmt_calc->execute();
            $calc_res = $stmt_calc->get_result();

            while ($row = $calc_res->fetch_assoc()) {
                $total_credits += (int)$row['credit_units'];
                $valid_course_codes[] = $row['course_code']; // Save the exact string codes for the JSON array
            }
            $stmt_calc->close();

            if ($total_credits > $max_credits) {
                $error = "Registration failed: You selected $total_credits units, exceeding the maximum limit of $max_credits units.";
            } else {
                // Save JSON array to database
                $encoded_courses = json_encode($valid_course_codes);
                $stmt_insert = $conn->prepare("INSERT INTO course_registrations (student_id, session, semester, course_codes, total_credits, status) VALUES (?, ?, ?, ?, ?, 'approved')");
                $stmt_insert->bind_param("isssi", $student_id, $current_session, $current_semester, $encoded_courses, $total_credits);

                if ($stmt_insert->execute()) {
                    $_SESSION['msg'] = "success|Courses successfully registered for $current_semester!";
                    header("Location: " . basename($_SERVER['PHP_SELF']));
                    exit;
                } else {
                    $error = "Database error: Could not save registration.";
                }
            }
        }
    }
}

$page_title = "Course Registration";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .locked-overlay { background: rgba(255,255,255,0.7); backdrop-filter: blur(8px); position: absolute; inset: 0; z-index: 10; display: flex; align-items: center; justify-content: center; flex-direction: column; text-align: center; border-radius: inherit; }
    .course-row { transition: 0.2s; cursor: pointer; }
    .course-row:hover { background-color: #f8fafc !important; }
    .course-row.core-course { background-color: #f8f9fa; cursor: not-allowed; }
    .custom-checkbox { width: 22px; height: 22px; cursor: pointer; border: 2px solid #cbd5e1; }
    .custom-checkbox:checked { background-color: #5D87FF; border-color: #5D87FF; }
    .core-course .custom-checkbox { cursor: not-allowed; opacity: 0.6; }
    .sticky-summary { position: sticky; top: 100px; }
    .fw-black { font-weight: 900 !important; }
    .progress-bar { transition: width 0.4s ease, background-color 0.4s ease; }
    .hover-lift { transition: 0.3s; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(93, 135, 255, 0.3) !important; }
</style>

<div class="container-fluid py-4">

    <!-- PAGE HEADER -->
    <div class="d-md-flex align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-black text-dark mb-1">Course Registration</h3>
            <p class="text-muted small mb-0"><?= htmlspecialchars($current_session) ?> • <?= htmlspecialchars($current_semester) ?></p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-4 py-2 fw-bold fs-6 rounded-pill shadow-sm">
                <i class="ti ti-chart-bar me-1"></i> Level: <?= htmlspecialchars($level) ?>
            </span>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if(isset($_SESSION['msg'])): list($color, $txt) = explode('|', $_SESSION['msg']); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 p-4 alert-dismissible fade show shadow-sm mb-4">
            <div class="d-flex align-items-center"><i class="ti ti-check-circle fs-3 me-3"></i><div><?= $txt; unset($_SESSION['msg']); ?></div></div>
            <button type="button" class="btn-close mt-2" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert bg-danger-subtle text-danger border-0 rounded-4 p-4 shadow-sm mb-4 animate__animated animate__shakeX">
            <div class="d-flex align-items-center"><i class="ti ti-alert-triangle fs-3 me-3"></i><div><strong>Registration Error:</strong><br><?= $error ?></div></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- LEFT COLUMN: COURSE ROSTER -->
        <div class="col-lg-8 position-relative">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 position-relative">

                <!-- FINANCIAL GATEKEEPER OVERLAY -->
                <?php if (!$fees_paid): ?>
                    <div class="locked-overlay p-4">
                        <div class="bg-danger-subtle text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 90px; height: 90px;">
                            <i class="ti ti-lock fs-1"></i>
                        </div>
                        <h3 class="fw-black text-dark mb-2">Registration Locked</h3>
                        <p class="text-muted mb-4 max-w-75 mx-auto fs-6">You must clear your tuition fee balance for the <?= htmlspecialchars($current_session) ?> before accessing the registration portal.</p>
                        <a href="pay_fees.php" class="btn btn-danger btn-lg rounded-pill px-5 fw-bold shadow-sm hover-lift">
                            Proceed to Bursary <i class="ti ti-arrow-right ms-2"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="ti ti-books text-primary me-2"></i>Approved Curriculum</h5>
                </div>

                <div class="card-body p-0">
                    <?php if ($courses_registered): ?>
                        <!-- SUCCESS STATE: REGISTRATION COMPLETE -->
                        <div class="p-4 p-md-5 text-center">
                            <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                                <i class="ti ti-check fs-1"></i>
                            </div>
                            <h4 class="fw-black text-dark">Registration Complete</h4>
                            <p class="text-muted mb-4">Your courses for this semester have been successfully enrolled and approved.</p>

                            <div class="table-responsive px-md-3 mb-5 text-start border rounded-4 overflow-hidden">
                                <table class="table table-hover table-sm align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Course Code</th>
                                            <th class="py-3 text-uppercase small fw-bold text-muted text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $reg_codes = json_decode($registered_data['course_codes'], true);
                                        if(!empty($reg_codes)) {
                                            foreach($reg_codes as $code) {
                                                echo "<tr>
                                                        <td class='ps-4 py-3 fw-bold text-primary font-monospace'>".htmlspecialchars($code)."</td>
                                                        <td class='py-3 text-center'><span class='badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill'><i class='ti ti-check me-1'></i>Approved</span></td>
                                                      </tr>"; 
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <a href="print_crf.php" target="_blank" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-sm hover-lift">
                                <i class="ti ti-printer me-2"></i> Print Official Course Form
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- REGISTRATION FORM -->
                        <form method="POST" id="courseForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="bg-light small text-uppercase text-muted">
                                        <tr>
                                            <th class="ps-4 py-3" style="width: 60px;">Select</th>
                                            <th class="py-3 fw-bold">Course Code</th>
                                            <th class="py-3 fw-bold">Course Title</th>
                                            <th class="py-3 fw-bold text-center">Units</th>
                                            <th class="py-3 fw-bold text-center">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $stmt_c = $conn->prepare("SELECT * FROM courses WHERE program_id = ? AND level = ? AND semester = ? ORDER BY is_core DESC, course_code ASC");
                                        $stmt_c->bind_param("iss", $program_id, $level, $current_semester);
                                        $stmt_c->execute();
                                        $courses = $stmt_c->get_result();

                                        if ($courses->num_rows > 0):
                                            while($c = $courses->fetch_assoc()): 
                                                $is_core = $c['is_core'] == 1;
                                        ?>
                                            <tr class="course-row <?= $is_core ? 'core-course' : '' ?>" <?= !$is_core ? "onclick=\"toggleCheckbox('course_{$c['id']}')\"" : "" ?>>
                                                <td class="ps-4 py-3">
                                                    <input class="form-check-input custom-checkbox course-check" type="checkbox" 
                                                           name="courses[]" value="<?= $c['id'] ?>" id="course_<?= $c['id'] ?>" 
                                                           data-units="<?= $c['credit_units'] ?>" 
                                                           <?= $is_core ? 'checked onclick="return false;" tabindex="-1"' : '' ?>>
                                                </td>
                                                <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($c['course_code']) ?></td>
                                                <td class="text-dark fw-medium"><?= htmlspecialchars($c['course_title']) ?></td>
                                                <td class="text-center fw-black fs-6 text-dark"><?= $c['credit_units'] ?></td>
                                                <td class="text-center">
                                                    <?php if($is_core): ?>
                                                        <span class="badge bg-danger-subtle text-danger px-2 py-1 border border-danger-subtle"><i class="ti ti-lock me-1"></i>Core</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info-subtle text-info px-2 py-1 border border-info-subtle">Elective</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="ti ti-folder-off fs-1 d-block mb-2 opacity-50"></i>No courses mapped for your current level and semester.</td></tr>
                                        <?php endif; $stmt_c->close(); ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: LIVE SUMMARY -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-summary">
                <div class="card-header bg-dark text-white p-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="ti ti-calculator me-2"></i>Unit Summary</h6>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                        <span class="text-muted fw-bold">Max Limit:</span>
                        <span class="fw-black text-dark"><?= $max_credits ?> Units</span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
                        <span class="text-dark fw-bold text-uppercase small">Total Selected</span>
                        <h1 class="fw-black text-primary mb-0" id="totalUnitsDisplay">0</h1>
                    </div>
                    
                    <!-- Live Progress Bar -->
                    <div class="progress mb-4 bg-light shadow-sm" style="height: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div id="unitProgressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%; border-radius: 12px;"></div>
                    </div>

                    <?php if (!$courses_registered && $fees_paid): ?>
                        <div class="alert bg-primary-subtle border-start border-4 border-primary small text-dark mb-4">
                            <strong>Note:</strong> Mandatory core courses are locked and pre-calculated. Select your electives carefully.
                        </div>
                        
                        <button type="submit" form="courseForm" name="register_courses" id="submitRegBtn" class="btn btn-primary w-100 py-3 rounded-pill fw-black shadow-sm hover-lift d-flex justify-content-center align-items-center transition-all">
                            <span id="btnText">Submit Registration</span> <i id="btnIcon" class="ti ti-check ms-2"></i>
                            <div id="btnSpinner" class="spinner-border spinner-border-sm text-light ms-2 d-none" role="status"></div>
                        </button>
                    <?php elseif (!$fees_paid): ?>
                        <button class="btn btn-light border w-100 py-3 rounded-pill fw-bold text-muted" disabled>
                            <i class="ti ti-lock me-2"></i> Registration Locked
                        </button>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const maxCredits = <?= $max_credits ?>;
    const checkboxes = document.querySelectorAll('.course-check');
    const totalDisplay = document.getElementById('totalUnitsDisplay');
    const progressBar = document.getElementById('unitProgressBar');

    // Make entire row clickable (only for electives)
    function toggleCheckbox(id) {
        const cb = document.getElementById(id);
        if (!cb.hasAttribute('onclick')) { // Safely avoids double-clicking locked core courses
            cb.checked = !cb.checked;
            calculateTotal();
        }
    }

    // Stop propagation if user clicks checkbox directly
    checkboxes.forEach(cb => {
        cb.addEventListener('click', function(e) {
            e.stopPropagation(); 
            calculateTotal();
        });
    });

    function calculateTotal() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) total += parseInt(cb.getAttribute('data-units'));
        });

        // Update Text
        totalDisplay.innerText = total;

        // Update Progress Bar
        let percent = (total / maxCredits) * 100;
        if (percent > 100) percent = 100;
        
        if (progressBar) {
            progressBar.style.width = percent + '%';
        }

        // Handle Limits & Colors
        if (total > maxCredits) {
            totalDisplay.classList.remove('text-primary');
            totalDisplay.classList.add('text-danger');
            if (progressBar) {
                progressBar.classList.remove('bg-primary');
                progressBar.classList.add('bg-danger');
            }
            
            Swal.fire({
                toast: true, position: 'top-end', icon: 'error',
                title: 'Credit Limit Exceeded!',
                showConfirmButton: false, timer: 3000
            });
        } else {
            totalDisplay.classList.remove('text-danger');
            totalDisplay.classList.add('text-primary');
            if (progressBar) {
                progressBar.classList.remove('bg-danger');
                progressBar.classList.add('bg-primary');
            }
        }
    }

    // Prevent form submission if units exceeded or 0, and add loading state
    const form = document.getElementById('courseForm');
    if(form) {
        form.addEventListener('submit', function(e) {
            let total = 0;
            checkboxes.forEach(cb => { if (cb.checked) total += parseInt(cb.getAttribute('data-units')); });

            if (total > maxCredits) {
                e.preventDefault();
                Swal.fire('Limit Exceeded', `You selected ${total} units. The maximum allowed is ${maxCredits}.`, 'error');
            } else if (total === 0) {
                e.preventDefault();
                Swal.fire('Empty Form', `Please select at least one course to register.`, 'warning');
            } else {
                // Form is valid, show loading state
                const btn = document.getElementById('submitRegBtn');
                btn.disabled = true;
                btn.classList.add('opacity-75');
                document.getElementById('btnText').innerText = "Processing...";
                document.getElementById('btnIcon').classList.add('d-none');
                document.getElementById('btnSpinner').classList.remove('d-none');
            }
        });
    }

    // Initialize calculation on page load to capture pre-checked Core Courses
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

<?php include 'includes/footer.php'; ?>