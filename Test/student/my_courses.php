<?php
$page_title = 'My Courses';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

// Fetch enrolled courses for the student
$student_id = $_SESSION['student_id'];
$courses = [];
$error = '';

$sql = "SELECT
            c.course_code,
            c.course_name,
            c.description,
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

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
} else {
    $error = "Error fetching courses: " . $conn->error;
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">My Enrolled Courses</h5>
                    <a href="print_courses.php" target="_blank" class="btn btn-primary">Print Registration Slip</a>
                </div>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="row">
                    <?php if (!empty($courses)): ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="col-md-6 col-lg-4 d-flex align-items-stretch">
                                <div class="card w-100">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?></p>
                                        <p class="card-text mb-1"><small class="text-muted"><strong>Credit Units:</strong> <?php echo htmlspecialchars($course['credit_units']); ?></small></p>
                                        <p class="card-text"><small class="text-muted"><strong>Instructor:</strong> <?php echo htmlspecialchars($course['teacher_name'] ?? 'N/A'); ?></small></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info" role="alert">
                                You are not enrolled in any courses for the current session. Visit the <a href="course_registration.php" class="alert-link">Course Registration</a> page to enroll.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>