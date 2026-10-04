<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

$page_title = 'Enter Scores';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher' || !isset($_SESSION['teacher_id'])) {
    header("location: ../../index.php");
    exit;
}

$teacher_id = $_SESSION['teacher_id'];
$user_id = $_SESSION['user_id'];
$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
$action_err = $action_success = "";

if (!$assessment_id) {
    header("location: manage_assessments.php");
    exit;
}

// Fetch assessment details to verify ownership and get max_marks
$stmt_assessment = $conn->prepare("SELECT a.*, c.course_name FROM assessments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND a.created_by_teacher_id = ?");
$stmt_assessment->bind_param("ii", $assessment_id, $teacher_id);
$stmt_assessment->execute();
$assessment = $stmt_assessment->get_result()->fetch_assoc();
$stmt_assessment->close();

if (!$assessment) {
    die("Assessment not found or you do not have permission to edit it.");
}

// Handle score submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_scores'])) {
    $scores = $_POST['scores'];
    $max_marks = $assessment['max_marks'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO assessment_scores (assessment_id, student_id, score, recorded_by_teacher_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
        foreach ($scores as $student_id => $score) {
            if ($score !== '' && is_numeric($score)) {
                $score_val = (float)$score;
                if ($score_val > $max_marks) {
                    throw new Exception("Score for a student cannot be greater than the max marks of {$max_marks}.");
                }
                $stmt->bind_param("iidi", $assessment_id, $student_id, $score_val, $teacher_id);
                $stmt->execute();
            }
        }
        $stmt->close();
        $conn->commit();
        $action_success = "Scores saved successfully.";
        log_activity($conn, $user_id, "Updated scores for assessment ID {$assessment_id}.");
    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "Error saving scores: " . $e->getMessage();
    }
}

// Fetch enrolled students and their current scores for this assessment
$sql_students = "
    SELECT s.id, s.full_name, s.student_id_number, sc.score
    FROM students s
    JOIN enrollments e ON s.id = e.student_id
    LEFT JOIN assessment_scores sc ON s.id = sc.student_id AND sc.assessment_id = ?
    WHERE e.course_id = ? AND e.session_id = ?
    ORDER BY s.full_name
";
$stmt_students = $conn->prepare($sql_students);
$stmt_students->bind_param("iii", $assessment_id, $assessment['course_id'], $assessment['session_id']);
$stmt_students->execute();
$students = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold m-0">Enter Scores for: <?php echo htmlspecialchars($assessment['name']); ?></h5>
        <a href="manage_assessments.php" class="btn btn-outline-primary">Back to Assessments</a>
    </div>
    <p>Course: <strong><?php echo htmlspecialchars($assessment['course_name']); ?></strong> | Max Marks: <strong><?php echo htmlspecialchars($assessment['max_marks']); ?></strong></p>

    <?php if ($action_success): ?><div class="alert alert-success"><?php echo $action_success; ?></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger"><?php echo $action_err; ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>Student Name</th><th>Student ID</th><th>Score</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
                                <td>
                                    <input type="number" step="0.01" name="scores[<?php echo $student['id']; ?>]" class="form-control" 
                                           value="<?php echo htmlspecialchars($student['score'] ?? ''); ?>" 
                                           max="<?php echo htmlspecialchars($assessment['max_marks']); ?>" min="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="save_scores" class="btn btn-primary mt-3">Save All Scores</button>
            </form>
        </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>