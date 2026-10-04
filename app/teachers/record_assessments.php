<?php
$page_title = 'Record Assessments';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$message = $error = '';

// Fetch teacher's courses and active session
$teacher_courses = [];
$active_session = null;

$sql_courses = "SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name";
if ($stmt = $conn->prepare($sql_courses)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $teacher_courses[] = $row;
    $stmt->close();
}

$sql_session = "SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1";
$result_session = $conn->query($sql_session);
if ($result_session->num_rows > 0) {
    $active_session = $result_session->fetch_assoc();
}

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$students = $assessments = $scores = [];

// Handle POST requests for managing assessments and scores
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Request validation failed.";
    } else {
        $course_id = (int)$_POST['course_id'];

        // Add new assessment
        if (isset($_POST['add_assessment'])) {
            $name = trim($_POST['assessment_name']);
            $max_marks = trim($_POST['max_marks']);
            if (!empty($name) && is_numeric($max_marks)) {
                $sql = "INSERT INTO assessments (course_id, session_id, name, max_marks, created_by_teacher_id) VALUES (?, ?, ?, ?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("iisdi", $course_id, $active_session['id'], $name, $max_marks, $teacher_id);
                    if ($stmt->execute()) $message = "Assessment added successfully.";
                    else $error = "Error: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = "Invalid assessment name or max marks.";
            }
        }

        // Edit existing assessment
        if (isset($_POST['edit_assessment'])) {
            $assessment_id = (int)$_POST['assessment_id'];
            $name = trim($_POST['assessment_name']);
            $max_marks = trim($_POST['max_marks']);
            if (!empty($name) && is_numeric($max_marks) && $assessment_id > 0) {
                // Security check: ensure teacher owns the course for this assessment
                $sql = "UPDATE assessments a JOIN courses c ON a.course_id = c.id SET a.name = ?, a.max_marks = ? WHERE a.id = ? AND c.teacher_id = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sdii", $name, $max_marks, $assessment_id, $teacher_id);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) $message = "Assessment updated successfully.";
                        else $error = "Update failed. You may not have permission or no changes were made.";
                    } else {
                        $error = "Error updating assessment: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = "Invalid assessment name, max marks, or ID.";
            }
        }

        // Delete assessment
        if (isset($_POST['delete_assessment'])) {
            $assessment_id_to_delete = (int)$_POST['assessment_id_to_delete'];
            if ($assessment_id_to_delete > 0) {
                // Security check & Deletion. The ON DELETE CASCADE in DB will handle scores.
                $sql = "DELETE a FROM assessments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.teacher_id = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("ii", $assessment_id_to_delete, $teacher_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) $message = "Assessment and all its scores have been deleted.";
                    else $error = "Deletion failed. You may not have permission or the assessment does not exist.";
                    $stmt->close();
                }
            }
        }

        // Save scores
        if (isset($_POST['save_scores'])) {
            $scores_data = $_POST['scores'];
            $sql = "INSERT INTO assessment_scores (assessment_id, student_id, score, recorded_by_teacher_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)";
            if ($stmt = $conn->prepare($sql)) {
                $conn->begin_transaction();
                foreach ($scores_data as $student_id => $assessment_scores) {
                    foreach ($assessment_scores as $assessment_id => $score) {
                        $score_val = empty($score) ? null : (float)$score;
                        $stmt->bind_param("iidi", $assessment_id, $student_id, $score_val, $teacher_id);
                        $stmt->execute();
                    }
                }
                $conn->commit();
                $message = "Scores saved successfully!";
                $stmt->close();
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}

// Fetch data if a course is selected
if ($course_id && $active_session) {
    // Fetch students
    $sql_students = "SELECT s.id, s.full_name, s.student_id_number FROM students s JOIN enrollments e ON s.id = e.student_id WHERE e.course_id = ? AND e.session_id = ? ORDER BY s.full_name";
    if ($stmt = $conn->prepare($sql_students)) {
        $stmt->bind_param("ii", $course_id, $active_session['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $students[] = $row;
        $stmt->close();
    }

    // Fetch assessments
    $sql_assessments = "SELECT id, name, max_marks FROM assessments WHERE course_id = ? AND session_id = ? ORDER BY created_at";
    if ($stmt = $conn->prepare($sql_assessments)) {
        $stmt->bind_param("ii", $course_id, $active_session['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $assessments[] = $row;
        $stmt->close();
    }

    // Fetch existing scores
    $sql_scores = "SELECT sc.student_id, sc.assessment_id, sc.score FROM assessment_scores sc JOIN assessments a ON sc.assessment_id = a.id WHERE a.course_id = ? AND a.session_id = ?";
    if ($stmt = $conn->prepare($sql_scores)) {
        $stmt->bind_param("ii", $course_id, $active_session['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $scores[$row['student_id']][$row['assessment_id']] = $row['score'];
        }
        $stmt->close();
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Record Assessment Scores</h5>
                <p><strong>Active Session:</strong> <?php echo htmlspecialchars($active_session['name'] ?? 'N/A'); ?></p>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3 mb-4 align-items-end">
                    <div class="col-md-5">
                        <label for="course_id" class="form-label">Select Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">-- Choose a course --</option>
                            <?php foreach ($teacher_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php if ($course_id == $course['id']) echo 'selected'; ?>><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Load Students</button></div>
                </form>

                <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if ($course_id && $active_session): ?>
                    <hr>
                    <!-- Add Assessment Form -->
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?course_id=<?php echo $course_id; ?>" method="post" class="row g-3 mb-4 p-3 border rounded">
                        <h6 class="mb-2">Add New Assessment Type</h6>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="col-md-5"><label for="assessment_name" class="form-label">Name (e.g., Test 1)</label><input type="text" class="form-control" name="assessment_name" required></div>
                        <div class="col-md-4"><label for="max_marks" class="form-label">Max Marks</label><input type="number" step="0.01" class="form-control" name="max_marks" required></div>
                        <div class="col-md-3 d-flex align-items-end"><button type="submit" name="add_assessment" class="btn btn-secondary w-100">Add Assessment</button></div>
                    </form>

                    <?php if (!empty($students) && !empty($assessments)): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?course_id=<?php echo $course_id; ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Student ID</th>
                                            <?php foreach ($assessments as $assessment): ?>
                                                <th class="text-center">
                                                    <div class="d-flex justify-content-center align-items-center">
                                                        <span>
                                                            <?php echo htmlspecialchars($assessment['name']); ?><br>
                                                            <small class="text-muted">( /<?php echo htmlspecialchars($assessment['max_marks']); ?>)</small>
                                                        </span>
                                                        <div class="dropdown ms-2">
                                                            <button class="btn btn-sm btn-light p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-dots-vertical"></i></button>
                                                            <ul class="dropdown-menu">
                                                                <li><button class="dropdown-item edit-assessment-btn" type="button" data-bs-toggle="modal" data-bs-target="#editAssessmentModal" data-id="<?php echo $assessment['id']; ?>" data-name="<?php echo htmlspecialchars($assessment['name']); ?>" data-marks="<?php echo htmlspecialchars($assessment['max_marks']); ?>">Edit</button></li>
                                                                <li>
                                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?course_id=<?php echo $course_id; ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this assessment and all its scores? This cannot be undone.');" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                                                                        <input type="hidden" name="assessment_id_to_delete" value="<?php echo $assessment['id']; ?>">
                                                                        <button type="submit" name="delete_assessment" class="dropdown-item text-danger">Delete</button>
                                                                    </form>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                                <?php foreach ($assessments as $assessment): ?>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               name="scores[<?php echo $student['id']; ?>][<?php echo $assessment['id']; ?>]" 
                                                               value="<?php echo htmlspecialchars($scores[$student['id']][$assessment['id']] ?? ''); ?>"
                                                               max="<?php echo htmlspecialchars($assessment['max_marks']); ?>" min="0">
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" name="save_scores" class="btn btn-primary mt-3">Save All Scores</button>
                        </form>
                    <?php elseif (empty($students)): ?>
                        <div class="alert alert-info">No students are enrolled in this course for the current session.</div>
                    <?php else: ?>
                        <div class="alert alert-warning">No assessment types have been created for this course yet. Please add one above.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-labelledby="editAssessmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?course_id=<?php echo $course_id; ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="editAssessmentModalLabel">Edit Assessment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
            <input type="hidden" name="assessment_id" id="edit_assessment_id">
            <div class="mb-3"><label for="edit_assessment_name" class="form-label">Assessment Name</label><input type="text" class="form-control" id="edit_assessment_name" name="assessment_name" required></div>
            <div class="mb-3"><label for="edit_max_marks" class="form-label">Max Marks</label><input type="number" step="0.01" class="form-control" id="edit_max_marks" name="max_marks" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="edit_assessment" class="btn btn-primary">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var editModal = document.getElementById('editAssessmentModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var assessmentId = button.getAttribute('data-id');
            var assessmentName = button.getAttribute('data-name');
            var assessmentMarks = button.getAttribute('data-marks');

            var idInput = editModal.querySelector('#edit_assessment_id');
            var nameInput = editModal.querySelector('#edit_assessment_name');
            var marksInput = editModal.querySelector('#edit_max_marks');

            idInput.value = assessmentId;
            nameInput.value = assessmentName;
            marksInput.value = assessmentMarks;
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>