<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Ensure user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

// Include the database configuration
require_once realpath(__DIR__ . '/../config/db.php');

$student_id = $_SESSION['student_id'];
$student_info = [];
$courses = [];
$session_name = 'N/A';
$total_units = 0;

// 1. Fetch student information
$sql_student = "SELECT full_name, student_id_number FROM students WHERE id = ?";
if ($stmt_student = $conn->prepare($sql_student)) {
    $stmt_student->bind_param("i", $student_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();
    $student_info = $result_student->fetch_assoc();
    $stmt_student->close();
}

// 2. Fetch active session
$sql_session = "SELECT name FROM sessions WHERE is_active = 1 LIMIT 1";
$result_session = $conn->query($sql_session);
if ($result_session->num_rows > 0) {
    $session_name = $result_session->fetch_assoc()['name'];
}

// 3. Fetch enrolled courses
$sql_courses = "SELECT
            c.course_code,
            c.course_name,
            c.credit_units,
            t.full_name AS teacher_name
        FROM
            enrollments e
        JOIN courses c ON
            e.course_id = c.id
        LEFT JOIN teachers t ON
            c.teacher_id = t.id
        JOIN sessions s ON
            e.session_id = s.id
        WHERE
            e.student_id = ? AND s.is_active = 1
        ORDER BY
            c.course_code";

if ($stmt_courses = $conn->prepare($sql_courses)) {
    $stmt_courses->bind_param("i", $student_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $courses[] = $row;
        $total_units += (int)$row['credit_units'];
    }
    $stmt_courses->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Registration Slip</title>
    <link rel="stylesheet" href="<?php echo '/n-portal/'; ?>assets/css/styles.min.css" />
    <style>
        body {
            background-color: #fff;
            font-family: sans-serif;
        }
        .container {
            width: 80%;
            margin: 40px auto;
            border: 1px solid #ddd;
            padding: 30px;
        }
        .header, .student-details {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h2 {
            margin: 0;
        }
        .student-details p {
            margin: 5px 0;
        }
        .no-print {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body, .container {
                margin: 0;
                padding: 0;
                border: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="<?php echo '/n-portal/'; ?>assets/images/logos/favicon.png" width="180" alt="Logo">
            <h4>Course Registration Slip</h4>
            <p><strong>Session:</strong> <?php echo htmlspecialchars($session_name); ?></p>
        </div>

        <div class="student-details">
            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($student_info['full_name'] ?? 'N/A'); ?></p>
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_info['student_id_number'] ?? 'N/A'); ?></p>
        </div>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Credit Units</th>
                    <th>Instructor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $index => $course): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['credit_units']); ?></td>
                            <td><?php echo htmlspecialchars($course['teacher_name'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No courses registered for this session.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total Credit Units:</th>
                    <th colspan="2"><?php echo htmlspecialchars($total_units); ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="mt-5 text-center">
            <p>_________________________</p>
            <p>Student's Signature</p>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
        <a href="my_courses.php" class="btn btn-secondary">Back to My Courses</a>
    </div>
</body>
</html>