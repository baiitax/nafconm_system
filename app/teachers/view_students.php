<?php
$page_title = 'View Students';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$course_info = null;
$students = [];
$error = '';

if (!$teacher_id || !$course_id) {
    header("location: my_courses.php");
    exit;
}

// 1. Security Check: Verify the teacher is assigned to this course
$sql_verify = "SELECT course_name, course_code FROM courses WHERE id = ? AND teacher_id = ?";
if ($stmt_verify = $conn->prepare($sql_verify)) {
    $stmt_verify->bind_param("ii", $course_id, $teacher_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();
    if ($result_verify->num_rows === 1) {
        $course_info = $result_verify->fetch_assoc();
    } else {
        $error = "You are not authorized to view students for this course.";
    }
    $stmt_verify->close();
} else {
    $error = "Database error: Could not verify course ownership.";
}

// 2. Fetch enrolled students if authorized
if (empty($error)) {
    $sql_students = "SELECT s.id, s.full_name, s.student_id_number, s.email 
                     FROM students s
                     JOIN enrollments e ON s.id = e.student_id
                     WHERE e.course_id = ?
                     ORDER BY s.full_name";
    if ($stmt_students = $conn->prepare($sql_students)) {
        $stmt_students->bind_param("i", $course_id);
        $stmt_students->execute();
        $result_students = $stmt_students->get_result();
        while ($row = $result_students->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt_students->close();
    } else {
        $error = "Error fetching student list: " . $conn->error;
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="card-title fw-semibold m-0">Enrolled Students</h5>
                        <?php if ($course_info): ?><p class="mb-0"><?php echo htmlspecialchars($course_info['course_name']); ?> (<?php echo htmlspecialchars($course_info['course_code']); ?>)</p><?php endif; ?>
                    </div>
                    <a href="my_courses.php" class="btn btn-outline-primary">Back to My Courses</a>
                </div>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php else: ?>
                    <div class="table-responsive"><table class="table table-striped table-hover"><thead class="table-light"><tr><th>#</th><th>Full Name</th><th>Student ID</th><th>Email</th><th>Actions</th></tr></thead><tbody>
                        <?php if (!empty($students)): foreach ($students as $index => $student): ?>
                            <tr><td><?php echo $index + 1; ?></td><td><?php echo htmlspecialchars($student['full_name']); ?></td><td><?php echo htmlspecialchars($student['student_id_number']); ?></td><td><?php echo htmlspecialchars($student['email']); ?></td><td><a href="view_student_profile.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">View Profile</a></td></tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center">No students are enrolled in this course yet.</td></tr>
                        <?php endif; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>