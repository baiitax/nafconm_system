<?php
/**
 * NAFCONM - Student Assignments Hub
 * Features: Dynamic Sessions, Cryptographic MIME Uploads, CSRF Protection, Anti-Double Submit
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

// Fetch Student Profile
$stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student_id = $stmt->get_result()->fetch_assoc()['id'] ?? 0;
$stmt->close();

// --- 2. HANDLE SECURE SUBMISSION UPLOAD ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $assignment_id = intval($_POST['assignment_id']);
        
        // Check if overdue
        $check_due = $conn->query("SELECT due_date FROM assignments WHERE id = $assignment_id")->fetch_assoc();
        if (strtotime($check_due['due_date']) < time()) {
            $error = "Submission failed: The deadline for this assignment has passed.";
        } else {
            $file = $_FILES['submission_file'];

            if ($file['error'] === UPLOAD_ERR_OK) {
                if ($file['size'] > 10485760) { // 10MB Limit
                    $error = "File exceeds the 10MB limit.";
                } else {
                    // Secure MIME-Type Check
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $allowed_mimes = [
                        'application/pdf' => 'pdf',
                        'application/msword' => 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                        'application/zip' => 'zip',
                        'application/x-zip-compressed' => 'zip'
                    ];

                    if (array_key_exists($mime_type, $allowed_mimes)) {
                        $ext = $allowed_mimes[$mime_type];
                        $upload_dir = "../uploads/assignments/";
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                        $new_name = "SUB_" . time() . "_" . $student_id . "_" . $assignment_id . "." . $ext;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                            // Upsert logic: if they resubmit before deadline, overwrite the old file record
                            $stmt_upsert = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submitted_at = NOW()");
                            $stmt_upsert->bind_param("iis", $assignment_id, $student_id, $new_name);
                            if ($stmt_upsert->execute()) {
                                $message = "success|Assignment submitted successfully!";
                            } else {
                                $error = "Database error during submission.";
                            }
                            $stmt_upsert->close();
                        } else {
                            $error = "Failed to upload file to the server.";
                        }
                    } else {
                        $error = "Invalid file type. Strictly PDF, DOC, DOCX, or ZIP allowed.";
                    }
                }
            } else {
                $error = "Please select a valid file to upload.";
            }
        }
    }
}

// --- 3. FETCH REGISTERED COURSES (JSON PARSING) ---
$registered_course_ids = [];
$stmt_reg = $conn->prepare("SELECT course_codes FROM course_registrations WHERE student_id = ? AND session = ? AND status = 'approved' LIMIT 1");
$stmt_reg->bind_param("is", $student_id, $current_session);
$stmt_reg->execute();
$reg_res = $stmt_reg->get_result();

if ($reg_res->num_rows > 0) {
    $codes_array = json_decode($reg_res->fetch_assoc()['course_codes'], true);
    if (!empty($codes_array)) {
        $placeholders = implode(',', array_fill(0, count($codes_array), '?'));
        $types = str_repeat('s', count($codes_array));
        $stmt_c = $conn->prepare("SELECT id FROM courses WHERE course_code IN ($placeholders)");
        $stmt_c->bind_param($types, ...$codes_array);
        $stmt_c->execute();
        $c_res = $stmt_c->get_result();
        while ($row = $c_res->fetch_assoc()) $registered_course_ids[] = $row['id'];
        $stmt_c->close();
    }
}
$stmt_reg->close();

// --- 4. FETCH ASSIGNMENTS & SUBMISSION STATUS ---
$assignments = [];
if (!empty($registered_course_ids)) {
    $ids_str = implode(',', $registered_course_ids);
    $sql_tasks = "
        SELECT a.*, c.course_code, c.course_title, t.full_name as teacher_name, 
               sub.id as sub_id, sub.file_path as sub_file, sub.grade, sub.feedback, sub.submitted_at 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        LEFT JOIN teachers t ON a.teacher_id = t.id 
        LEFT JOIN submissions sub ON a.id = sub.assignment_id AND sub.student_id = $student_id
        WHERE a.course_id IN ($ids_str) 
        ORDER BY a.due_date DESC
    ";
    $task_res = $conn->query($sql_tasks);
    if ($task_res) {
        while ($row = $task_res->fetch_assoc()) $assignments[] = $row;
    }
}

$page_title = "Assignments";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .task-card { border: 1px solid #e2e8f0; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border-radius: 16px; overflow: hidden; }
    .accordion-button { border-radius: 16px !important; }
    .accordion-button:not(.collapsed) { background-color: rgba(93, 135, 255, 0.05); color: #5D87FF; font-weight: bold; box-shadow: none; border-bottom-left-radius: 0 !important; border-bottom-right-radius: 0 !important; }
    .accordion-button:focus { box-shadow: none; }
    
    .custom-file-upload {
        border: 2px dashed #cbd5e1; border-radius: 12px; padding: 25px 20px; background: #f8fafc;
        display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.3s; cursor: pointer; text-align: center;
    }
    .custom-file-upload:hover { border-color: #5D87FF; background: #eff6ff; }
    .custom-file-upload i { color: #5D87FF; font-size: 32px; margin-bottom: 10px; }
    .file-name-display { font-size: 14px; color: #64748b; font-weight: 600; margin-top: 5px; word-break: break-all; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-black text-dark mb-1">Assignments & Tasks</h3>
            <p class="text-muted small mb-0">Submit coursework and view instructor feedback for <?= htmlspecialchars($current_session) ?>.</p>
        </div>
        <div class="bg-white p-2 rounded-pill shadow-sm px-3 mt-3 mt-md-0 border">
            <span class="small fw-bold text-muted">Total Tasks: <?= count($assignments) ?></span>
        </div>
    </div>

    <!-- ALERTS -->
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

    <?php if (empty($registered_course_ids)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5 bg-light">
            <div class="card-body py-5">
                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm" style="width: 80px; height: 80px;">
                    <i class="ti ti-lock fs-1 text-muted"></i>
                </div>
                <h4 class="fw-black text-dark">No Courses Registered</h4>
                <p class="text-muted mb-0">You must register for your courses before you can receive assignments.</p>
                <a href="course_registration.php" class="btn btn-primary rounded-pill px-4 mt-3 fw-bold shadow-sm">Go to Registration</a>
            </div>
        </div>
    <?php else: ?>

        <div class="accordion" id="assignmentsAccordion">
            <?php if (!empty($assignments)): foreach ($assignments as $index => $a): 
                $is_overdue = strtotime($a['due_date']) < time();
                $is_submitted = !empty($a['sub_id']);
                $is_graded = $a['grade'] !== null;
                
                // Status Logic
                if ($is_graded) {
                    $status_badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill"><i class="ti ti-check-circle me-1"></i>Graded</span>';
                } elseif ($is_submitted) {
                    $status_badge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill"><i class="ti ti-upload me-1"></i>Submitted</span>';
                } elseif ($is_overdue) {
                    $status_badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill"><i class="ti ti-alert-triangle me-1"></i>Overdue</span>';
                } else {
                    $status_badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill"><i class="ti ti-clock me-1"></i>Pending</span>';
                }
            ?>
                <div class="accordion-item card task-card mb-4 border-0">
                    <h2 class="accordion-header" id="heading<?= $a['id'] ?>">
                        <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?> p-4 bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $a['id'] ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                <div>
                                    <span class="badge bg-dark text-white font-monospace fw-bold small me-2 px-2 py-1"><?= htmlspecialchars($a['course_code']) ?></span>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($a['title']) ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="text-muted small fw-medium d-none d-md-block"><i class="ti ti-calendar-due me-1"></i>Due: <strong class="<?= $is_overdue && !$is_submitted ? 'text-danger' : 'text-dark' ?>"><?= date('d M Y, h:i A', strtotime($a['due_date'])) ?></strong></span>
                                    <?= $status_badge ?>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?= $a['id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#assignmentsAccordion">
                        <div class="accordion-body p-4 bg-light border-top">
                            <div class="row g-4">
                                <!-- Instructions Area -->
                                <div class="col-md-7">
                                    <h6 class="fw-bold text-dark text-uppercase small mb-2"><i class="ti ti-align-left text-primary me-1"></i> Assignment Instructions</h6>
                                    <div class="bg-white p-4 rounded-4 border shadow-sm text-dark mb-3" style="line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($a['description'])) ?>
                                    </div>
                                    <p class="text-muted small mb-0 fw-medium"><i class="ti ti-user-check me-1"></i> Assigned by: <?= htmlspecialchars($a['teacher_name']) ?></p>
                                </div>
                                
                                <!-- Action Area -->
                                <div class="col-md-5">
                                    <?php if ($is_graded): ?>
                                        <div class="bg-success-subtle border border-success-subtle rounded-4 p-4 text-center h-100 d-flex flex-column justify-content-center shadow-sm">
                                            <h6 class="text-success fw-bold text-uppercase small mb-1">Final Score</h6>
                                            <h1 class="fw-black text-dark mb-3 display-5"><?= htmlspecialchars($a['grade']) ?> <span class="fs-5 text-muted fw-medium">/ 100</span></h1>
                                            <?php if(!empty($a['feedback'])): ?>
                                                <div class="bg-white p-3 rounded-3 text-start border shadow-sm mt-auto">
                                                    <small class="fw-bold text-dark d-block mb-1"><i class="ti ti-message-2 text-primary me-1"></i> Instructor Feedback:</small>
                                                    <span class="text-muted small"><?= nl2br(htmlspecialchars($a['feedback'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>
                                        <div class="bg-white border rounded-4 p-4 h-100 shadow-sm">
                                            <?php if ($is_submitted): ?>
                                                <div class="alert bg-info-subtle text-info border-0 p-3 text-center mb-3 rounded-3">
                                                    <i class="ti ti-file-check fs-1 d-block mb-2"></i>
                                                    <strong class="d-block mb-1">File Submitted!</strong>
                                                    <small><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!$is_overdue): ?>
                                                <form method="POST" enctype="multipart/form-data" class="upload-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                                                    
                                                    <label class="custom-file-upload mb-3" for="fileInput<?= $a['id'] ?>">
                                                        <i class="ti ti-cloud-upload"></i>
                                                        <span class="fw-bold text-dark small"><?= $is_submitted ? 'Upload New File (Overwrites previous)' : 'Click to Browse File' ?></span>
                                                        <span class="file-name-display mt-2" id="fileName<?= $a['id'] ?>">No file selected</span>
                                                    </label>
                                                    <input type="file" name="submission_file" id="fileInput<?= $a['id'] ?>" class="visually-hidden submission-input" accept=".pdf,.doc,.docx,.zip" required>
                                                    
                                                    <button type="submit" name="submit_assignment" class="submit-btn btn <?= $is_submitted ? 'btn-dark' : 'btn-primary' ?> w-100 rounded-pill fw-black py-2 shadow-sm d-flex justify-content-center align-items-center">
                                                        <span class="btn-text"><i class="ti ti-upload me-2"></i><?= $is_submitted ? 'Resubmit Work' : 'Submit Work' ?></span>
                                                        <div class="spinner-border spinner-border-sm text-light d-none ms-2" role="status"></div>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <div class="alert bg-danger-subtle text-danger border-0 p-4 text-center mb-0 rounded-3 h-100 d-flex flex-column justify-content-center">
                                                    <i class="ti ti-lock fs-1 d-block mb-2"></i>
                                                    <strong class="d-block mb-1">Upload Locked</strong>
                                                    <small>The deadline for this assignment has passed and submissions are no longer accepted.</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="text-center py-5">
                    <div class="bg-success-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                        <i class="ti ti-mood-smile fs-1 text-success"></i>
                    </div>
                    <h4 class="fw-black text-dark">You're all caught up!</h4>
                    <p class="text-muted max-w-md mx-auto">No assignments have been posted for your registered courses yet. Check back later or review your learning materials.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Custom File Input Display Logic
    const inputs = document.querySelectorAll('.submission-input');
    inputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const assignmentId = this.id.replace('fileInput', '');
            const nameDisplay = document.getElementById('fileName' + assignmentId);
            
            if (this.files[0]) {
                if (this.files[0].size > 10485760) { // 10MB check
                    Swal.fire('File Too Large', 'Your submission must be less than 10MB', 'error');
                    this.value = '';
                    nameDisplay.innerText = "No file selected";
                    nameDisplay.classList.remove('text-primary');
                } else {
                    nameDisplay.innerText = this.files[0].name;
                    nameDisplay.classList.add('text-primary');
                }
            }
        });
    });

    // Anti-Double Submit Logic
    const forms = document.querySelectorAll('.upload-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const fileInput = form.querySelector('.submission-input');
            if(!fileInput.value) {
                e.preventDefault();
                Swal.fire('Missing File', 'Please select a file to submit.', 'warning');
                return;
            }
            
            const btn = form.querySelector('.submit-btn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner-border');
            
            btn.disabled = true;
            btn.classList.add('opacity-75');
            btnText.innerText = "Uploading...";
            spinner.classList.remove('d-none');
        });
    });

});
</script>

<?php include 'includes/footer.php'; ?>