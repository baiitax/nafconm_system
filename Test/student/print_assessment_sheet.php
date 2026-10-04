<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

require_once realpath(__DIR__ . '/../config/db.php');

$student_id = $_SESSION['student_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) die("Invalid Course ID.");

$student_info = $course_info = $session_info = null;
$assessments = $scores = [];

// Fetch student, course, and session info
$sql_info = "SELECT s.full_name, s.student_id_number, c.course_name, c.course_code, sess.name as session_name FROM students s, courses c, sessions sess, enrollments e WHERE s.id = ? AND c.id = ? AND sess.is_active = 1 AND e.student_id = s.id AND e.course_id = c.id AND e.session_id = sess.id LIMIT 1";
if ($stmt = $conn->prepare($sql_info)) {
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $student_info = ['full_name' => $row['full_name'], 'student_id_number' => $row['student_id_number']];
        $course_info = ['course_name' => $row['course_name'], 'course_code' => $row['course_code']];
        $session_info = ['session_name' => $row['session_name']];
    } else {
        die("Authorization Error or Data Not Found.");
    }
    $stmt->close();
}

// Fetch assessments and scores
$sql_data = "SELECT a.name, a.max_marks, sc.score FROM assessments a LEFT JOIN assessment_scores sc ON a.id = sc.assessment_id AND sc.student_id = ? WHERE a.course_id = ? AND a.session_id = (SELECT id FROM sessions WHERE is_active = 1) ORDER BY a.created_at";
if ($stmt = $conn->prepare($sql_data)) {
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $assessments[] = $row;
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Assessment Sheet</title>
    <link rel="stylesheet" href="<?php echo '/n-portal/'; ?>assets/css/styles.min.css" />
    <style>
        body { background-color: #fff; font-family: sans-serif; }
        .container { width: 80%; margin: 40px auto; border: 1px solid #ddd; padding: 30px; }
        .header, .student-details { text-align: center; margin-bottom: 30px; }
        .header h4 { margin: 0; }
        .student-details p { margin: 5px 0; }
        .no-print { text-align: center; margin-top: 20px; }
        @media print {
            .no-print { display: none; }
            body, .container { margin: 0; padding: 0; border: none; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="<?php echo '/n-portal/'; ?>assets/images/logos/dark-logo.svg" width="180" alt="Logo">
            <h4>Student Assessment Sheet</h4>
            <p><strong>Session:</strong> <?php echo htmlspecialchars($session_info['session_name'] ?? 'N/A'); ?></p>
        </div>

        <div class="student-details">
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student_info['full_name'] ?? 'N/A'); ?></p>
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id_number'] ?? 'N/A'); ?></p>
            <p><strong>Course:</strong> <?php echo htmlspecialchars($course_info['course_name'] . ' (' . $course_info['course_code'] . ')'); ?></p>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Assessment</th>
                    <th>Max Marks</th>
                    <th>Your Score</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assessments)): ?>
                    <?php $total_max = $total_score = 0; foreach ($assessments as $index => $assessment): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                            <td><?php echo htmlspecialchars($assessment['max_marks']); $total_max += $assessment['max_marks']; ?></td>
                            <td><?php echo htmlspecialchars($assessment['score'] ?? 'N/A'); $total_score += ($assessment['score'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center">No assessments recorded for this course.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($assessments)): ?>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-end">Total:</th>
                    <th><?php echo htmlspecialchars($total_max); ?></th>
                    <th><?php echo htmlspecialchars($total_score); ?></th>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <div class="mt-5 text-center">
            <p>_________________________</p>
            <p>Head of Department</p>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <a href="assessment_sheet.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">Back</a>
    </div>
</body>
</html>