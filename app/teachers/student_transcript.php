<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$student_id) die("No student specified.");

// Fetch student details
$stmt_student = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$student = $stmt_student->get_result()->fetch_assoc();
$stmt_student->close();
if (!$student) die("Student not found.");

// Fetch all results grouped by session
$sql_results = "
    SELECT s.id as session_id, s.name as session_name, c.course_name, c.course_code, c.credit_units, e.final_grade, e.grade_point
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN sessions s ON e.session_id = s.id
    WHERE e.student_id = ? AND e.final_grade IS NOT NULL
    ORDER BY s.start_date ASC, c.course_code ASC
";
$stmt_results = $conn->prepare($sql_results);
$stmt_results->bind_param("i", $student_id);
$stmt_results->execute();
$results_data = $stmt_results->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_results->close();

$sessions = [];
foreach ($results_data as $row) {
    $sessions[$row['session_id']]['name'] = $row['session_name'];
    $sessions[$row['session_id']]['courses'][] = $row;
}

$cgpa_total_points = 0;
$cgpa_total_units = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Transcript - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.min.css" />
    <style>
        body { background-color: #f8f9fa; }
        .transcript-container { max-width: 850px; margin: 30px auto; background: #fff; border: 1px solid #dee2e6; box-shadow: 0 0 15px rgba(0,0,0,.05); }
        .transcript-header { text-align: center; padding: 2rem; border-bottom: 2px solid #333; }
        .transcript-header img { max-height: 90px; }
        .transcript-header h3 { margin: 10px 0 0 0; color: #333; }
        .student-info { padding: 1.5rem; display: flex; justify-content: space-between; }
        .session-block { padding: 1.5rem; }
        .session-title { font-weight: bold; font-size: 1.2em; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
        .results-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        .results-table th, .results-table td { padding: 8px; border: 1px solid #ddd; }
        .results-table th { background-color: #f2f2f2; }
        .gpa-summary { text-align: right; font-weight: bold; margin-top: 10px; }
        .cgpa-summary { padding: 1.5rem; text-align: right; border-top: 2px solid #333; margin-top: 20px; }
        @media print {
            body { background-color: #fff; }
            .no-print { display: none; }
            .transcript-container { margin: 0; border: none; box-shadow: none; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container-fluid no-print text-center my-3">
        <button onclick="window.print()" class="btn btn-primary">Print Transcript</button>
        <a href="view_student_details.php?student_id=<?php echo $student_id; ?>" class="btn btn-secondary">Back to Profile</a>
    </div>

    <div class="transcript-container">
        <div class="transcript-header">
            <img src="../assets/images/logos/favicon.png" alt="Logo">
            <h3>Nafisatu College of Nursing & Midwifery</h3>
            <p><strong>Official Academic Transcript</strong></p>
        </div>
        <div class="student-info">
            <div>
                <p><strong>Student:</strong> <?php echo htmlspecialchars(strtoupper($student['full_name'])); ?></p>
                <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id_number']); ?></p>
            </div>
            <div>
                <p><strong>Date Issued:</strong> <?php echo date("F j, Y"); ?></p>
            </div>
        </div>

        <?php foreach ($sessions as $session_id => $session): 
            $session_total_points = 0;
            $session_total_units = 0;
        ?>
        <div class="session-block">
            <div class="session-title"><?php echo htmlspecialchars($session['name']); ?></div>
            <table class="results-table">
                <thead><tr><th>Course Code</th><th>Course Title</th><th>Units</th><th>Grade</th></tr></thead>
                <tbody>
                    <?php foreach ($session['courses'] as $course): 
                        if (is_numeric($course['grade_point']) && is_numeric($course['credit_units'])) {
                            $session_total_points += $course['grade_point'] * $course['credit_units'];
                            $session_total_units += $course['credit_units'];
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($course['credit_units']); ?></td>
                        <td><?php echo htmlspecialchars($course['final_grade']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="gpa-summary">
                <?php 
                    $session_gpa = ($session_total_units > 0) ? $session_total_points / $session_total_units : 0;
                    $cgpa_total_points += $session_total_points;
                    $cgpa_total_units += $session_total_units;
                    echo "Session GPA: " . number_format($session_gpa, 2);
                ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="cgpa-summary">
            <?php 
                $cgpa = ($cgpa_total_units > 0) ? $cgpa_total_points / $cgpa_total_units : 0;
            ?>
            <h4>Cumulative GPA (CGPA): <?php echo number_format($cgpa, 2); ?></h4>
        </div>
    </div>
</body>
</html>
