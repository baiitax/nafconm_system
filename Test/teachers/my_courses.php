<?php
$page_title = 'My Courses';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$courses = [];
$error = '';

if ($teacher_id === null) {
    // This case should ideally not happen if login logic is correct
    die("Error: Could not find teacher profile. Please log out and log back in.");
}

// Fetch courses assigned to this teacher, along with the count of enrolled students
$sql = "SELECT 
            c.id, 
            c.course_code, 
            c.course_name, 
            c.description, 
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count
        FROM courses c
        WHERE c.teacher_id = ?
        ORDER BY c.course_name";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
} else {
    $error = "Error fetching courses: " . $conn->error;
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">My Assigned Courses</h5>
                </div>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if (!empty($courses)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Description</th>
                                    <th>Enrolled Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?></td>
                                        <td><?php echo htmlspecialchars($course['student_count']); ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>teachers/view_students.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">View Students</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">You have not been assigned to any courses yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>