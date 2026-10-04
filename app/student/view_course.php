<?php
$page_title = 'View Course';
require_once 'includes/header.php';

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = $_SESSION['student_id'];

if (!$course_id) {
    header("location: my_courses.php");
    exit;
}

// Security Check: Ensure student is enrolled in this course
$sql_check = "SELECT e.id FROM enrollments e JOIN sessions s ON e.session_id = s.id WHERE e.student_id = ? AND e.course_id = ? AND s.is_active = 1";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ii", $student_id, $course_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows === 0) {
    // Not enrolled or course not in active session, redirect
    header("location: my_courses.php");
    exit;
}
$stmt_check->close();

// Fetch Course Details
$sql_course = "SELECT c.*, t.full_name as teacher_name, d.name as department_name 
               FROM courses c 
               LEFT JOIN teachers t ON c.teacher_id = t.id
               LEFT JOIN departments d ON c.department_id = d.id
               WHERE c.id = ?";
$stmt_course = $conn->prepare($sql_course);
$stmt_course->bind_param("i", $course_id);
$stmt_course->execute();
$course = $stmt_course->get_result()->fetch_assoc();
$stmt_course->close();

if (!$course) {
    // Course not found
    header("location: my_courses.php");
    exit;
}

// Fetch Course Materials
$sql_materials = "SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC";
$stmt_materials = $conn->prepare($sql_materials);
$stmt_materials->bind_param("i", $course_id);
$stmt_materials->execute();
$materials = $stmt_materials->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_materials->close();

// Fetch Assignments
$sql_assignments = "SELECT * FROM assignments WHERE course_id = ? ORDER BY due_date DESC";
$stmt_assignments = $conn->prepare($sql_assignments);
$stmt_assignments->bind_param("i", $course_id);
$stmt_assignments->execute();
$assignments = $stmt_assignments->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assignments->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title fw-semibold"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($course['course_code']); ?></h6>
                    </div>
                    <a href="my_courses.php" class="btn btn-outline-primary">Back to My Courses</a>
                </div>
                <hr>
                <p><?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?></p>
                <div class="row">
                    <div class="col-md-4"><strong>Instructor:</strong> <?php echo htmlspecialchars($course['teacher_name'] ?? 'N/A'); ?></div>
                    <div class="col-md-4"><strong>Department:</strong> <?php echo htmlspecialchars($course['department_name'] ?? 'N/A'); ?></div>
                    <div class="col-md-4"><strong>Credit Units:</strong> <?php echo htmlspecialchars($course['credit_units']); ?></div>
                </div>
            </div>
        </div>

        <!-- Course Materials -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-3">Course Materials</h5>
                <?php if (!empty($materials)): ?>
                    <div class="list-group">
                        <?php foreach ($materials as $material): ?>
                            <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                    <p class="mb-1 text-muted small"><?php echo htmlspecialchars($material['description']); ?></p>
                                </div>
                                <small>Uploaded on <?php echo date('M d, Y', strtotime($material['uploaded_at'])); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No materials have been uploaded for this course yet.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Assignments -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-3">Assignments</h5>
                <?php if (!empty($assignments)): ?>
                    <div class="list-group">
                        <?php foreach ($assignments as $assignment): ?>
                            <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                    <p class="mb-1 text-muted small"><?php echo substr(htmlspecialchars($assignment['description']), 0, 100); ?>...</p>
                                </div>
                                <small class="text-danger">Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">There are no assignments for this course at the moment.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php
$conn->close();
require_once 'includes/footer.php';
?>