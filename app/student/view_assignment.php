<?php
$page_title = 'View Assignment';
require_once 'includes/header.php';

$student_id = $_SESSION['student_id'];
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = $error = '';

if (!$assignment_id) {
    header("location: assignments.php");
    exit;
}

// Security Check: Ensure student is enrolled in the course for this assignment
$sql_auth = "SELECT a.id FROM assignments a JOIN enrollments e ON a.course_id = e.course_id WHERE a.id = ? AND e.student_id = ?";
$stmt_auth = $conn->prepare($sql_auth);
$stmt_auth->bind_param("ii", $assignment_id, $student_id);
$stmt_auth->execute();
$stmt_auth->store_result();
if ($stmt_auth->num_rows === 0) {
    header("location: assignments.php");
    exit;
}
$stmt_auth->close();

// Handle Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Request validation failed.";
    } else {
        $file_path = null;
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/submissions/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $file_info = pathinfo($_FILES["submission_file"]["name"]);
            $file_ext = strtolower($file_info['extension']);
            $safe_filename = "sub_{$student_id}_{$assignment_id}_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $safe_filename;
            $db_path = 'uploads/submissions/' . $safe_filename;

            if (move_uploaded_file($_FILES["submission_file"]["tmp_name"], $target_file)) {
                $file_path = $db_path;
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }

        if (empty($error)) {
            // Check if a submission already exists
            $sql_check = "SELECT id, file_path FROM submissions WHERE assignment_id = ? AND student_id = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("ii", $assignment_id, $student_id);
            $stmt_check->execute();
            $existing_submission = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($existing_submission) {
                // Update existing submission
                if ($file_path) {
                    // Delete old file if it exists
                    if ($existing_submission['file_path'] && file_exists('../' . $existing_submission['file_path'])) {
                        unlink('../' . $existing_submission['file_path']);
                    }
                    $sql_update = "UPDATE submissions SET file_path = ?, submitted_at = NOW() WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $file_path, $existing_submission['id']);
                }
            } else {
                // Insert new submission
                $sql_update = "INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("iis", $assignment_id, $student_id, $file_path);
            }

            if ($stmt_update->execute()) {
                $message = "Assignment submitted successfully!";
            } else {
                $error = "Database error. Could not save submission.";
            }
            $stmt_update->close();
        }
    }
}

// Fetch Assignment Details
$sql_assignment = "SELECT a.*, c.course_name, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ?";
$stmt_assignment = $conn->prepare($sql_assignment);
$stmt_assignment->bind_param("i", $assignment_id);
$stmt_assignment->execute();
$assignment = $stmt_assignment->get_result()->fetch_assoc();
$stmt_assignment->close();

// Fetch Submission Details (if any)
$sql_submission = "SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?";
$stmt_submission = $conn->prepare($sql_submission);
$stmt_submission->bind_param("ii", $assignment_id, $student_id);
$stmt_submission->execute();
$submission = $stmt_submission->get_result()->fetch_assoc();
$stmt_submission->close();

$is_past_due = new DateTime() > new DateTime($assignment['due_date']);

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="card-title fw-semibold"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                        <h6 class="card-subtitle text-muted"><?php echo htmlspecialchars($assignment['course_name'] . ' (' . $assignment['course_code'] . ')'); ?></h6>
                    </div>
                    <a href="assignments.php" class="btn btn-outline-primary">Back to All Assignments</a>
                </div>

                <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <div class="row">
                    <!-- Left Column: Details & Submission -->
                    <div class="col-lg-7">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <h6 class="fw-semibold">Assignment Details</h6>
                                <p><strong>Due Date:</strong> <span class="text-danger"><?php echo date("F j, Y, g:i a", strtotime($assignment['due_date'])); ?></span></p>
                                <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            </div>
                        </div>

                        <?php if (!$is_past_due): ?>
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-semibold"><?php echo $submission ? 'Update Your Submission' : 'Submit Your Work'; ?></h6>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $assignment_id; ?>" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="mb-3">
                                        <label for="submission_file" class="form-label">Upload File</label>
                                        <input class="form-control" type="file" id="submission_file" name="submission_file" required>
                                        <div class="form-text">Allowed formats: PDF, DOC, DOCX, TXT. Max size: 5MB.</div>
                                        <?php if ($submission): ?>
                                            <div class="form-text text-warning">Uploading a new file will replace your previous submission.</div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="submit" name="submit_assignment" class="btn btn-primary"><?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?></button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Status & Feedback -->
                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-semibold">Status & Feedback</h6>
                                <?php if ($submission): ?>
                                    <p><strong>Status:</strong> <span class="badge bg-success">Submitted</span></p>
                                    <p><strong>Submitted On:</strong> <?php echo date("F j, Y, g:i a", strtotime($submission['submitted_at'])); ?></p>
                                    <?php if ($submission['file_path']): ?>
                                        <p><strong>Your Submission:</strong> <a href="../<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank"><i class="ti ti-download me-1"></i>Download File</a></p>
                                    <?php endif; ?>

                                    <hr>

                                    <?php if ($submission['grade'] !== null): ?>
                                        <h6 class="fw-semibold mt-3">Grade</h6>
                                        <div class="alert alert-info">
                                            <h4 class="alert-heading text-center"><?php echo htmlspecialchars(number_format($submission['grade'], 2)); ?>%</h4>
                                        </div>
                                        <h6 class="fw-semibold mt-3">Feedback</h6>
                                        <p><?php echo !empty($submission['feedback']) ? nl2br(htmlspecialchars($submission['feedback'])) : '<em>No feedback provided.</em>'; ?></p>
                                        <p><small class="text-muted">Graded on: <?php echo date("F j, Y", strtotime($submission['graded_at'])); ?></small></p>
                                    <?php else: ?>
                                        <div class="alert alert-warning">Your submission has not been graded yet.</div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <p><strong>Status:</strong> <span class="badge bg-danger">Not Submitted</span></p>
                                    <?php if ($is_past_due): ?>
                                        <div class="alert alert-danger">This assignment is past the due date.</div>
                                    <?php else: ?>
                                        <div class="alert alert-info">Please submit your assignment using the form on the left.</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>