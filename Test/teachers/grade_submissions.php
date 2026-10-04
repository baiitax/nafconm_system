<?php
$page_title = 'Grade Submissions';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$assignments = [];
$submissions = [];
$selected_assignment = null;
$message = $error = '';

if ($teacher_id === null) {
    die("Error: Could not find teacher profile. Please log out and log back in.");
}

// Handle bulk grade submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_bulk_grades'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Request validation failed. Please try again.";
    } else {
        $assignment_id = (int)$_POST['assignment_id'];
        $grades = $_POST['grades'] ?? [];
        $feedbacks = $_POST['feedbacks'] ?? [];

        // Security check: ensure teacher owns this assignment
        $sql_verify = "SELECT id FROM assignments WHERE id = ? AND a.teacher_id = ?";
        $stmt_verify = $conn->prepare($sql_verify);
        $stmt_verify->bind_param("ii", $assignment_id, $teacher_id);
        $stmt_verify->execute();
        $stmt_verify->store_result();

        if ($stmt_verify->num_rows > 0) {
            $conn->begin_transaction();
            try {
                $sql_update = "UPDATE submissions SET grade = ?, feedback = ?, graded_by_teacher_id = ?, graded_at = NOW() WHERE id = ? AND assignment_id = ?";
                $stmt_update = $conn->prepare($sql_update);

                foreach ($grades as $submission_id => $grade) {
                    $submission_id = (int)$submission_id;
                    $feedback = trim($feedbacks[$submission_id] ?? '');
                    // Allow empty grade to 'ungrade'
                    $grade_val = ($grade === '' || !is_numeric($grade) || $grade > 100 || $grade < 0) ? null : (float)$grade;
                    
                    $stmt_update->bind_param("dsiii", $grade_val, $feedback, $teacher_id, $submission_id, $assignment_id);
                    $stmt_update->execute();
                }
                $stmt_update->close();
                $conn->commit();
                $message = "Grades saved successfully.";
                log_activity($conn, $_SESSION['user_id'], "Bulk graded assignment ID {$assignment_id}.");
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error saving grades: " . $e->getMessage();
            }
        } else {
            $error = "Authorization failed. You do not own this assignment.";
        }
        $stmt_verify->close();
    }
}

// Fetch all assignments for the teacher
$sql_assignments = "SELECT a.id, a.title, c.course_code, 
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count
                    FROM assignments a
                    JOIN courses c ON a.course_id = c.id
                    WHERE a.teacher_id = ?
                    ORDER BY a.due_date DESC";
if ($stmt = $conn->prepare($sql_assignments)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

// If an assignment is selected, fetch its submissions
$selected_assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if ($selected_assignment_id > 0) {
    // Find the selected assignment details from the already fetched list
    foreach ($assignments as $assignment) {
        if ($assignment['id'] == $selected_assignment_id) {
            $selected_assignment = $assignment;
            break;
        }
    }

    if ($selected_assignment) {
        $sql_submissions = "SELECT 
                                s.id, s.submitted_at, s.grade, s.feedback, s.content,
                                st.full_name, st.student_id_number
                            FROM submissions s
                            JOIN students st ON s.student_id = st.id
                            WHERE s.assignment_id = ?
                            ORDER BY st.full_name";
        if ($stmt_subs = $conn->prepare($sql_submissions)) {
            $stmt_subs->bind_param("i", $selected_assignment_id);
            $stmt_subs->execute();
            $result = $stmt_subs->get_result();
            while ($row = $result->fetch_assoc()) {
                // Fetch files for this submission
                $submission_id = $row['id'];
                $row['files'] = [];
                $sql_files = "SELECT file_name, file_path FROM submission_files WHERE submission_id = ?";
                if ($stmt_files = $conn->prepare($sql_files)) {
                    $stmt_files->bind_param("i", $submission_id);
                    $stmt_files->execute();
                    $result_files = $stmt_files->get_result();
                    while ($file_row = $result_files->fetch_assoc()) $row['files'][] = $file_row;
                    $stmt_files->close();
                }
                $submissions[] = $row;
            }
            $stmt_subs->close();
        }
    } else {
        $error = "Selected assignment not found or you are not authorized to view it.";
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <!-- Assignments List -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Assignments</h5>
                <div class="list-group">
                    <?php if (!empty($assignments)): foreach ($assignments as $assignment): ?>
                        <a href="?assignment_id=<?php echo $assignment['id']; ?>" class="list-group-item list-group-item-action <?php echo ($selected_assignment_id == $assignment['id']) ? 'active' : ''; ?>"><div class="d-flex w-100 justify-content-between"><h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6><span class="badge bg-primary rounded-pill"><?php echo $assignment['submission_count']; ?></span></div><small class="text-muted"><?php echo htmlspecialchars($assignment['course_code']); ?></small></a>
                    <?php endforeach; else: ?>
                        <div class="alert alert-info">You have not created any assignments yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Submissions View -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

                <?php if ($selected_assignment): ?>
                    <h5 class="card-title fw-semibold mb-4">Submissions for "<?php echo htmlspecialchars($selected_assignment['title']); ?>"</h5>
                    <?php if (!empty($submissions)): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?assignment_id=<?php echo $selected_assignment_id; ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="assignment_id" value="<?php echo $selected_assignment_id; ?>">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Submitted At</th>
                                            <th>Submission</th>
                                            <th style="width: 100px;">Grade (/100)</th>
                                            <th style="width: 250px;">Feedback</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $sub): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sub['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($sub['student_id_number']); ?></small></td>
                                            <td><?php echo htmlspecialchars(date("M d, Y, g:i a", strtotime($sub['submitted_at']))); ?></td>
                                            <td>
                                                <?php if (!empty($sub['content'])): ?>
                                                    <p><strong>Text:</strong> <?php echo nl2br(htmlspecialchars($sub['content'])); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($sub['files'])): ?>
                                                    <strong>Files:</strong><br>
                                                    <?php foreach ($sub['files'] as $file): ?>
                                                        <a href="<?php echo BASE_URL . htmlspecialchars($file['file_path']); ?>" target="_blank"><i class="ti ti-download me-1"></i><?php echo htmlspecialchars($file['file_name']); ?></a><br>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <?php if (empty($sub['content']) && empty($sub['files'])): ?>
                                                    <span class="text-muted">No content or files submitted.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><input type="number" step="0.01" min="0" max="100" class="form-control" name="grades[<?php echo $sub['id']; ?>]" value="<?php echo htmlspecialchars($sub['grade'] ?? ''); ?>"></td>
                                            <td><textarea class="form-control" name="feedbacks[<?php echo $sub['id']; ?>]" rows="2"><?php echo htmlspecialchars($sub['feedback'] ?? ''); ?></textarea></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 text-end">
                                <button type="submit" name="save_bulk_grades" class="btn btn-primary">Save All Grades</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">No submissions have been made for this assignment yet.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center p-5"><i class="ti ti-file-text fs-8 text-muted"></i><h5 class="mt-3">Select an assignment</h5><p class="text-muted">Choose an assignment from the list on the left to view and grade submissions.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>