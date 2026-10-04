<?php
$page_title = 'Course Materials';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$teacher_id = $_SESSION['teacher_id'] ?? null;
$upload_err = $upload_success = $delete_err = $delete_success = "";

if ($teacher_id === null) {
    die("Error: Could not find teacher profile. Please log out and log back in.");
}

// Fetch courses taught by this teacher for the dropdown
$teacher_courses = [];
$sql_courses = "SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name";
if ($stmt_courses = $conn->prepare($sql_courses)) {
    $stmt_courses->bind_param("i", $teacher_id);
    $stmt_courses->execute();
    $result = $stmt_courses->get_result();
    while ($row = $result->fetch_assoc()) {
        $teacher_courses[] = $row;
    }
    $stmt_courses->close();
}

// Handle Material Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_material'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $upload_err = "Request validation failed. Please try again.";
    } else {
        $course_id = trim($_POST['course_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);

        if (empty($course_id) || empty($title) || !isset($_FILES['material_file']) || $_FILES['material_file']['error'] != UPLOAD_ERR_OK) {
            $upload_err = "Please select a course, provide a title, and upload a file.";
        } else {
            $upload_dir = __DIR__ . '/../uploads/materials/';
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                $upload_err = "Failed to create upload directory.";
            }

            if (empty($upload_err)) {
                $file_info = pathinfo($_FILES["material_file"]["name"]);
                $file_ext = strtolower($file_info['extension']);
                $safe_filename = preg_replace('/[^A-Za-z0-9_\-.]/', '', $file_info['filename']);
                $new_filename = "mat_" . $teacher_id . "_" . time() . "_" . $safe_filename;
                $target_file_path = $upload_dir . $new_filename;
                $db_file_path = 'uploads/materials/' . $new_filename;

                $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'png'];
                $max_file_size = 10 * 1024 * 1024; // 10MB

                if (!in_array($file_ext, $allowed_ext)) {
                    $upload_err = "Sorry, only PDF, DOC(X), PPT(X), XLS(X), TXT, JPG, PNG files are allowed.";
                } elseif ($_FILES["material_file"]["size"] > $max_file_size) {
                    $upload_err = "Sorry, your file is too large. Maximum size is 10MB.";
                } else {
                    if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $target_file_path)) {
                        $sql_insert = "INSERT INTO course_materials (course_id, teacher_id, title, description, file_path) VALUES (?, ?, ?, ?, ?)";
                        if ($stmt_insert = $conn->prepare($sql_insert)) {
                            $stmt_insert->bind_param("iisss", $course_id, $teacher_id, $title, $description, $db_file_path);
                            if ($stmt_insert->execute()) {
                                $upload_success = "Material uploaded successfully.";
                                log_activity($conn, $user_id, "Uploaded material '{$title}' for course ID {$course_id}.");
                            } else {
                                $upload_err = "Database error: Could not save material.";
                                unlink($target_file_path);
                            }
                            $stmt_insert->close();
                        }
                    } else {
                        $upload_err = "Sorry, there was an error uploading your file.";
                    }
                }
            }
        }
    }
}

// Handle Material Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_material'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $delete_err = "Request validation failed.";
    } else {
        $material_id = (int)$_POST['material_id'];

        $sql_get_path = "SELECT file_path FROM course_materials WHERE id = ? AND teacher_id = ?";
        if ($stmt_path = $conn->prepare($sql_get_path)) {
            $stmt_path->bind_param("ii", $material_id, $teacher_id);
            $stmt_path->execute();
            $stmt_path->bind_result($file_to_delete);
            if ($stmt_path->fetch()) {
                $stmt_path->close();
                $sql_delete = "DELETE FROM course_materials WHERE id = ? AND teacher_id = ?";
                if ($stmt_delete = $conn->prepare($sql_delete)) {
                    $stmt_delete->bind_param("ii", $material_id, $teacher_id);
                    if ($stmt_delete->execute()) {
                        $full_file_path = realpath(__DIR__ . '/../' . $file_to_delete);
                        if ($full_file_path && file_exists($full_file_path)) {
                            unlink($full_file_path);
                        }
                        $delete_success = "Material deleted successfully.";
                    } else {
                        $delete_err = "Error deleting material from database.";
                    }
                    $stmt_delete->close();
                }
            } else {
                $delete_err = "Material not found or you are not authorized to delete it.";
                $stmt_path->close();
            }
        }
    }
}

// Fetch existing course materials for this teacher
$materials = [];
$sql_materials = "SELECT cm.id, cm.title, cm.description, cm.file_path, cm.uploaded_at, c.course_name, c.course_code 
 FROM course_materials cm
                  JOIN courses c ON cm.course_id = c.id
                  WHERE cm.teacher_id = ?
                  ORDER BY c.course_name, cm.uploaded_at DESC";

if ($stmt_materials = $conn->prepare($sql_materials)) {
    $stmt_materials->bind_param("i", $teacher_id);
    $stmt_materials->execute();
    $result = $stmt_materials->get_result();
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    $stmt_materials->close();
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-lg-5 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Upload New Material</h5>
                <?php if ($upload_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($upload_success); ?></div><?php endif; ?>
                <?php if ($upload_err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($upload_err); ?></div><?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3"><label for="course_id" class="form-label">Course</label><select class="form-select" id="course_id" name="course_id" required><option value="">Select a course...</option><?php foreach ($teacher_courses as $course): ?><option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" class="form-control" id="title" name="title" required></div>
                    <div class="mb-3"><label for="description" class="form-label">Description (Optional)</label><textarea class="form-control" id="description" name="description" rows="3"></textarea></div>
                    <div class="mb-3"><label for="material_file" class="form-label">File</label><input class="form-control" type="file" id="material_file" name="material_file" required><div class="form-text">Max size: 10MB.</div></div>
                    <button type="submit" name="upload_material" class="btn btn-primary">Upload Material</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Uploaded Materials</h5>
                <?php if ($delete_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($delete_success); ?></div><?php endif; ?>
                <?php if ($delete_err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($delete_err); ?></div><?php endif; ?>
                <div class="table-responsive"><table class="table table-striped">
                    <thead class="table-light"><tr><th>Title</th><th>Course</th><th>Uploaded On</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (!empty($materials)): foreach ($materials as $material): ?>
                            <tr>
                                <td><h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($material['title']); ?></h6><span class="fw-normal text-muted"><?php echo htmlspecialchars($material['description']); ?></span></td>
                                <td><?php echo htmlspecialchars($material['course_code']); ?></td>
                                <td><?php echo date("M d, Y", strtotime($material['uploaded_at'])); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL . htmlspecialchars($material['file_path']); ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this material?');" style="display:inline;"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="material_id" value="<?php echo $material['id']; ?>"><button type="submit" name="delete_material" class="btn btn-sm btn-danger">Delete</button></form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center">You have not uploaded any materials yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>