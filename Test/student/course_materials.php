<?php
$page_title = 'Course Materials';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

$student_id = $_SESSION['student_id'];
$courses_with_materials = [];
$error = '';

// Fetch enrolled courses and their materials for the active session
$sql = "SELECT 
            c.id as course_id, 
            c.course_code, 
            c.course_name,
            cm.id as material_id,
            cm.title,
            cm.description,
            cm.file_path,
            cm.uploaded_at
        FROM 
            enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN course_materials cm ON c.id = cm.course_id
        JOIN sessions s ON e.session_id = s.id
        WHERE 
            e.student_id = ? AND s.is_active = 1
        ORDER BY 
            c.course_name, cm.uploaded_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $course_id = $row['course_id'];
        if (!isset($courses_with_materials[$course_id])) {
            $courses_with_materials[$course_id] = [
                'course_code' => $row['course_code'],
                'course_name' => $row['course_name'],
                'materials' => []
            ];
        }
        if ($row['material_id'] !== null) {
            $courses_with_materials[$course_id]['materials'][] = $row;
        }
    }
    $stmt->close();
} else {
    $error = "Error fetching course materials: " . $conn->error;
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Course Materials</h5>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if (!empty($courses_with_materials)): ?>
                    <div class="accordion" id="courseMaterialsAccordion">
                        <?php foreach ($courses_with_materials as $course_id => $course): ?>
                            <div class="accordion-item card">
                                <h2 class="accordion-header" id="heading-<?php echo $course_id; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $course_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $course_id; ?>">
                                        <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                                        <span class="badge bg-primary rounded-pill ms-2"><?php echo count($course['materials']); ?></span>
                                    </button>
                                </h2>
                                <div id="collapse-<?php echo $course_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $course_id; ?>" data-bs-parent="#courseMaterialsAccordion">
                                    <div class="accordion-body">
                                        <?php if (!empty($course['materials'])): ?>
                                            <div class="list-group">
                                                <?php foreach ($course['materials'] as $material): ?>
                                                    <a href="<?php echo BASE_URL . htmlspecialchars($material['file_path']); ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                                            <p class="mb-1 text-muted small"><?php echo htmlspecialchars($material['description'] ?? 'No description.'); ?></p>
                                                            <small>Uploaded on: <?php echo date("M d, Y", strtotime($material['uploaded_at'])); ?></small>
                                                        </div>
                                                        <i class="ti ti-download fs-6"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-light text-center mb-0">No materials have been uploaded for this course yet.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No course materials are available for your currently enrolled courses.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>