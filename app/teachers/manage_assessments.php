<?php
include 'includes/header.php'; // Assuming teacher-specific header

$page_title = 'Manage Assessments';

// Authentication check for teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher' || !isset($_SESSION['teacher_id'])) {
    header("location: ../../index.php");
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$user_id = $_SESSION['user_id'];
$action_err = $action_success = "";

// --- CRUD Operations ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Create/Edit Assessment
    if (isset($_POST['save_assessment'])) {
        $course_id = (int)$_POST['course_id'];
        $session_id = (int)$_POST['session_id'];
        $name = trim($_POST['name']);
        $max_marks = (float)$_POST['max_marks'];
        $weight = (float)$_POST['weight'];
        $assessment_id = $_POST['assessment_id'] ?? null;

        if (empty($course_id) || empty($session_id) || empty($name) || $max_marks <= 0 || $weight <= 0 || $weight > 1) {
            $action_err = "Invalid input. Ensure all fields are correct and weight is between 0.01 and 1.0.";
        } else {
            if (empty($assessment_id)) { // Create
                $sql = "INSERT INTO assessments (course_id, session_id, name, max_marks, weight, created_by_teacher_id) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iisddi", $course_id, $session_id, $name, $max_marks, $weight, $teacher_id);
            } else { // Update
                $sql = "UPDATE assessments SET name = ?, max_marks = ?, weight = ? WHERE id = ? AND created_by_teacher_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sddii", $name, $max_marks, $weight, $assessment_id, $teacher_id);
            }

            if ($stmt->execute()) {
                $action_success = "Assessment saved successfully.";
                log_activity($conn, $user_id, "Saved assessment '{$name}'.");
            } else {
                $action_err = "Error saving assessment.";
            }
            $stmt->close();
        }
    }

    // Delete Assessment
    if (isset($_POST['delete_assessment'])) {
        $assessment_id = (int)$_POST['assessment_id'];
        $sql = "DELETE FROM assessments WHERE id = ? AND created_by_teacher_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $assessment_id, $teacher_id);
        if ($stmt->execute()) {
            $action_success = "Assessment deleted successfully.";
            log_activity($conn, $user_id, "Deleted assessment ID {$assessment_id}.");
        } else {
            $action_err = "Error deleting assessment. It might have scores attached.";
        }
        $stmt->close();
    }
}

// Fetch teacher's courses for the active session
$active_session_id = $conn->query("SELECT id FROM sessions WHERE is_active = 1 LIMIT 1")->fetch_assoc()['id'] ?? 0;
$courses = [];
if ($active_session_id > 0) {
    $sql_courses = "SELECT c.id, c.course_name, c.course_code, 
                    (SELECT SUM(weight) FROM assessments a WHERE a.course_id = c.id AND a.session_id = ?) as total_weight
                    FROM courses c WHERE c.teacher_id = ?";
    $stmt_courses = $conn->prepare($sql_courses);
    $stmt_courses->bind_param("ii", $active_session_id, $teacher_id);
    $stmt_courses->execute();
    $courses = $stmt_courses->get_result()->fetch_all(MYSQLI_ASSOC);
}

include 'includes/sidebar.php'; // Assuming teacher-specific sidebar
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <?php if ($action_success): ?><div class="alert alert-success"><?php echo $action_success; ?></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger"><?php echo $action_err; ?></div><?php endif; ?>

    <?php if (empty($courses)): ?>
        <div class="alert alert-info">You are not assigned to any courses for the active session.</div>
    <?php else: ?>
        <div class="accordion" id="coursesAccordion">
            <?php foreach ($courses as $course): 
                $total_weight_percent = ($course['total_weight'] ?? 0) * 100;
                $weight_class = (abs($total_weight_percent - 100) < 1) ? 'bg-success' : 'bg-warning';
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?php echo $course['id']; ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $course['id']; ?>">
                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                        <span class="ms-auto me-3 badge <?php echo $weight_class; ?>">Total Weight: <?php echo number_format($total_weight_percent, 0); ?>%</span>
                    </button>
                </h2>
                <div id="collapse<?php echo $course['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#coursesAccordion">
                    <div class="accordion-body">
                        <h6>Assessments for this Course</h6>
                        <table class="table table-sm table-striped">
                            <thead><tr><th>Name</th><th>Max Marks</th><th>Weight</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php
                                $stmt_assess = $conn->prepare("SELECT * FROM assessments WHERE course_id = ? AND session_id = ?");
                                $stmt_assess->bind_param("ii", $course['id'], $active_session_id);
                                $stmt_assess->execute();
                                $assessments = $stmt_assess->get_result()->fetch_all(MYSQLI_ASSOC);
                                foreach ($assessments as $assessment):
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                                    <td><?php echo htmlspecialchars($assessment['max_marks']); ?></td>
                                    <td><?php echo (float)$assessment['weight'] * 100; ?>%</td>
                                    <td>
                                        <a href="enter_scores.php?assessment_id=<?php echo $assessment['id']; ?>" class="btn btn-sm btn-success">Enter Scores</a>
                                        <!-- Edit/Delete buttons would trigger a modal -->
                                    </td>
                                </tr>
                            <?php endforeach; $stmt_assess->close(); ?>
                            </tbody>
                        </table>
                        <hr>
                        <h6>Add New Assessment</h6>
                        <form method="POST">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <input type="hidden" name="session_id" value="<?php echo $active_session_id; ?>">
                            <div class="row">
                                <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Assessment Name" required></div>
                                <div class="col-md-3"><input type="number" step="0.01" name="max_marks" class="form-control" placeholder="Max Marks" required></div>
                                <div class="col-md-3"><input type="number" step="0.01" min="0.01" max="1.0" name="weight" class="form-control" placeholder="Weight (e.g., 0.4 for 40%)" required></div>
                                <div class="col-md-2"><button type="submit" name="save_assessment" class="btn btn-primary w-100">Add</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>