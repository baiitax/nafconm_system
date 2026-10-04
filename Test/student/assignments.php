<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id']; // For logging
$student_id = $_SESSION['student_id']; // Set at login

if ($student_id === null) {
    // Handle error, maybe student profile doesn't exist
    die("Error: Could not find student profile.");
}

$submission_err = $submission_success = "";

// Handle Assignment Submission/Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_assignment'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $submission_err = "Request validation failed. Please try again.";
    } else {
        $assignment_id = (int)$_POST['assignment_id'];
        $content = trim($_POST['content']);
        $submission_id = isset($_POST['submission_id']) ? (int)$_POST['submission_id'] : 0;

        // Security check: ensure assignment is not past due date before processing
        $sql_due_date = "SELECT due_date FROM assignments WHERE id = ?";
        $stmt_due = $conn->prepare($sql_due_date);
        $stmt_due->bind_param("i", $assignment_id);
        $stmt_due->execute();
        $stmt_due->bind_result($due_date);
        $stmt_due->fetch();
        $stmt_due->close();

        if (new DateTime() > new DateTime($due_date)) {
            $submission_err = "Cannot submit or update, the due date has passed.";
        } else {
            // UPDATE existing submission
            if ($submission_id > 0) {
                $conn->begin_transaction();
                try {
                    $sql_update = "UPDATE submissions SET content = ?, submitted_at = NOW() WHERE id = ? AND student_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("sii", $content, $submission_id, $student_id);
                    $stmt_update->execute();
                    $stmt_update->close();

                    if (isset($_FILES['submission_files']) && !empty(array_filter($_FILES['submission_files']['name']))) {
                        $sql_get_old_files = "SELECT file_path FROM submission_files WHERE submission_id = ?";
                        $stmt_get = $conn->prepare($sql_get_old_files);
                        $stmt_get->bind_param("i", $submission_id);
                        $stmt_get->execute();
                        $result_old_files = $stmt_get->get_result();
                        while ($row = $result_old_files->fetch_assoc()) {
                            if (file_exists('../' . $row['file_path'])) {
                                unlink('../' . $row['file_path']);
                            }
                        }
                        $stmt_get->close();

                        $sql_delete_files = "DELETE FROM submission_files WHERE submission_id = ?";
                        $stmt_delete = $conn->prepare($sql_delete_files);
                        $stmt_delete->bind_param("i", $submission_id);
                        $stmt_delete->execute();
                        $stmt_delete->close();

                        $upload_dir = '../uploads/submissions/';
                        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                        $sql_file = "INSERT INTO submission_files (submission_id, file_name, file_path) VALUES (?, ?, ?)";
                        $stmt_file = $conn->prepare($sql_file);
                        foreach ($_FILES['submission_files']['name'] as $key => $name) {
                            if ($_FILES['submission_files']['error'][$key] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES['submission_files']['tmp_name'][$key];
                                $file_name = uniqid() . '-' . basename($name);
                                $file_path = $upload_dir . $file_name;
                                if (move_uploaded_file($tmp_name, $file_path)) {
                                    $db_path = 'uploads/submissions/' . $file_name;
                                    $stmt_file->bind_param("iss", $submission_id, $name, $db_path);
                                    $stmt_file->execute();
                                }
                            }
                        }
                        $stmt_file->close();
                    }
                    $conn->commit();
                    $submission_success = "Assignment updated successfully.";
                    log_activity($conn, $user_id, "Updated submission for assignment ID: {$assignment_id}");
                } catch (Exception $e) {
                    $conn->rollback();
                    $submission_err = "An error occurred during update.";
                }
            } else { // INSERT new submission
                $conn->begin_transaction();
                try {
                    $sql_submission = "INSERT INTO submissions (assignment_id, student_id, content) VALUES (?, ?, ?)";
                    $stmt_submission = $conn->prepare($sql_submission);
                    $stmt_submission->bind_param("iis", $assignment_id, $student_id, $content);
                    $stmt_submission->execute();
                    $submission_id = $conn->insert_id;
                    $stmt_submission->close();

                    if (isset($_FILES['submission_files']) && !empty(array_filter($_FILES['submission_files']['name']))) {
                        $upload_dir = '../uploads/submissions/';
                        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                        $sql_file = "INSERT INTO submission_files (submission_id, file_name, file_path) VALUES (?, ?, ?)";
                        $stmt_file = $conn->prepare($sql_file);
                        foreach ($_FILES['submission_files']['name'] as $key => $name) {
                            if ($_FILES['submission_files']['error'][$key] === UPLOAD_ERR_OK) {
                                $tmp_name = $_FILES['submission_files']['tmp_name'][$key];
                                $file_name = uniqid() . '-' . basename($name);
                                $file_path = $upload_dir . $file_name;
                                if (move_uploaded_file($tmp_name, $file_path)) {
                                    $db_path = 'uploads/submissions/' . $file_name;
                                    $stmt_file->bind_param("iss", $submission_id, $name, $db_path);
                                    $stmt_file->execute();
                                }
                            }
                        }
                        $stmt_file->close();
                    }
                    $conn->commit();
                    $submission_success = "Assignment submitted successfully.";
                    log_activity($conn, $user_id, "Submitted assignment ID: {$assignment_id}");
                } catch (Exception $e) {
                    $conn->rollback();
                    $submission_err = "An error occurred during submission.";
                }
            }
        }
    }
}

// Fetch assignments for the courses the student is enrolled in
$assignments = [];
$sql = "SELECT 
            a.id as assignment_id, a.title, a.description, a.due_date, 
            c.course_name, c.course_code, 
            af.file_name as assignment_file_name, af.file_path as assignment_file_path,
            s.id as submission_id, s.submitted_at, s.content as submission_content,
            sf.file_name as submission_file_name, sf.file_path as submission_file_path
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN assignment_files af ON a.id = af.assignment_id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
        LEFT JOIN submission_files sf ON s.id = sf.submission_id
        WHERE e.student_id = ?
        ORDER BY a.due_date, a.id";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $student_id, $student_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assignment_id = $row['assignment_id'];
            if (!isset($assignments[$assignment_id])) {
                $assignments[$assignment_id] = [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'due_date' => $row['due_date'],
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'],
                    'assignment_files' => [],
                    'submission' => null
                ];
            }
            if ($row['assignment_file_name'] && !in_array($row['assignment_file_path'], array_column($assignments[$assignment_id]['assignment_files'], 'path'))) {
                $assignments[$assignment_id]['assignment_files'][] = ['name' => $row['assignment_file_name'], 'path' => $row['assignment_file_path']];
            }
            if ($row['submission_id']) {
                if (!$assignments[$assignment_id]['submission']) {
                    $assignments[$assignment_id]['submission'] = [
                        'id' => $row['submission_id'],
                        'submitted_at' => $row['submitted_at'],
                        'content' => $row['submission_content'],
                        'files' => []
                    ];
                }
                if ($row['submission_file_name'] && !in_array($row['submission_file_path'], array_column($assignments[$assignment_id]['submission']['files'], 'path'))) {
                    $assignments[$assignment_id]['submission']['files'][] = ['name' => $row['submission_file_name'], 'path' => $row['submission_file_path']];
                }
            }
        }
    }
    $stmt->close();
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
    <div class="container-fluid">
        <h5 class="card-title fw-semibold mb-4">Assignments</h5>
        <?php if ($submission_success): ?><div class="alert alert-success"><?php echo $submission_success; ?></div><?php endif; ?>
        <?php if ($submission_err): ?><div class="alert alert-danger"><?php echo $submission_err; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-body">
                <?php if (!empty($assignments)) : ?>
                    <?php foreach ($assignments as $assignment_id => $assignment) : ?>
                        <div class="mb-4">
                            <h6 class="fw-semibold"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                            <p class="text-muted mb-0">Course: <?php echo htmlspecialchars($assignment['course_name']) . ' (' . htmlspecialchars($assignment['course_code']) . ')'; ?></p>
                            <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                            <p class="text-muted">Due Date: <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($assignment['due_date']))); ?></p>
                            <?php if (!empty($assignment['assignment_files'])): ?>
                                <p class="mb-1"><strong>Attachments:</strong></p>
                                <?php foreach ($assignment['assignment_files'] as $file): ?>
                                    <a href="../<?php echo htmlspecialchars($file['path']); ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank" download><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="mt-3">
                                <?php
                                $is_past_due = new DateTime() > new DateTime($assignment['due_date']);
                                $is_submitted = !empty($assignment['submission']);

                                if ($is_submitted): ?>
                                    <div class="alert alert-success">
                                        <h6 class="alert-heading">Submitted!</h6>
                                        <p>You submitted this assignment on <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($assignment['submission']['submitted_at']))); ?>.</p>
                                        <?php if($assignment['submission']['content']): ?>
                                            <p><strong>Your submission text:</strong><br><?php echo nl2br(htmlspecialchars($assignment['submission']['content'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($assignment['submission']['files'])): ?>
                                            <p class="mb-1"><strong>Your submitted files:</strong></p>
                                            <?php foreach ($assignment['submission']['files'] as $file): ?>
                                                <a href="../<?php echo htmlspecialchars($file['path']); ?>" target="_blank" class="d-block"><?php echo htmlspecialchars($file['name']); ?></a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$is_past_due): ?>
                                    <div class="card p-3 mt-2 bg-light">
                                        <h6 class="fw-semibold"><?php echo $is_submitted ? 'Update Your Submission' : 'Submit Your Work'; ?></h6>
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                                            <?php if ($is_submitted): ?>
                                                <input type="hidden" name="submission_id" value="<?php echo $assignment['submission']['id']; ?>">
                                            <?php endif; ?>
                                            <div class="mb-3"><label for="content-<?php echo $assignment_id; ?>" class="form-label">Text Submission (Optional)</label><textarea class="form-control" id="content-<?php echo $assignment_id; ?>" name="content" rows="4"><?php echo $is_submitted ? htmlspecialchars($assignment['submission']['content']) : ''; ?></textarea></div>
                                            <div class="mb-3"><label for="files-<?php echo $assignment_id; ?>" class="form-label">File Upload (Optional)</label><input class="form-control" type="file" id="files-<?php echo $assignment_id; ?>" name="submission_files[]" multiple>
                                                <?php if ($is_submitted): ?><div class="form-text text-warning">Uploading new files will replace all previously submitted files for this assignment.</div><?php endif; ?>
                                            </div>
                                            <button type="submit" name="submit_assignment" class="btn btn-primary"><?php echo $is_submitted ? 'Update Submission' : 'Submit Assignment'; ?></button>
                                        </form>
                                    </div>
                                <?php elseif (!$is_submitted): ?>
                                    <div class="alert alert-danger">This assignment is past due and was not submitted.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="alert alert-info" role="alert">
                        No assignments found for your enrolled courses.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>