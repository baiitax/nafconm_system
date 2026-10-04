<?php
/**
 * NAFCONM - Course Materials Manager
 * Features: Secure File Uploads, Assigned Course Filtering
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_session_id = 3; 

// Fetch Teacher ID
$stmt_t = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$stmt_t->bind_param("i", $user_id);
$stmt_t->execute();
$teacher = $stmt_t->get_result()->fetch_assoc();
$stmt_t->close();
$teacher_id = $teacher['id'];

$message = $error = "";

// --- 1. HANDLE UPLOAD ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_material'])) {
    $course_id = intval($_POST['course_id']);
    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));

    // Security Check: Does this teacher teach this course?
    $check = $conn->query("SELECT id FROM course_teachers WHERE teacher_id = $teacher_id AND course_id = $course_id AND session_id = $current_session_id");
    if ($check->num_rows > 0) {
        $upload_dir = "../uploads/materials/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file = $_FILES['material_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];

        if (in_array($ext, $allowed) && $file['size'] < 10000000) { // 10MB limit
            $new_name = "MAT_" . time() . "_" . $course_id . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
                $stmt_ins = $conn->prepare("INSERT INTO course_materials (course_id, teacher_id, title, description, file_path) VALUES (?, ?, ?, ?, ?)");
                $stmt_ins->bind_param("iisss", $course_id, $teacher_id, $title, $description, $new_name);
                if ($stmt_ins->execute()) {
                    $message = "success|Material uploaded successfully.";
                }
                $stmt_ins->close();
            } else {
                $error = "Failed to move uploaded file.";
            }
        } else {
            $error = "Invalid file. Must be PDF, DOC/DOCX, PPT/PPTX, or ZIP under 10MB.";
        }
    } else {
        $error = "Unauthorized. You are not assigned to this course.";
    }
}

// --- 2. HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt_del = $conn->prepare("SELECT file_path FROM course_materials WHERE id = ? AND teacher_id = ?");
    $stmt_del->bind_param("ii", $del_id, $teacher_id);
    $stmt_del->execute();
    $res = $stmt_del->get_result();
    if ($res->num_rows > 0) {
        $file_path = $res->fetch_assoc()['file_path'];
        if (file_exists("../uploads/materials/" . $file_path)) unlink("../uploads/materials/" . $file_path);
        $conn->query("DELETE FROM course_materials WHERE id = $del_id");
        $message = "success|Material deleted successfully.";
    }
}

// --- 3. FETCH ASSIGNED COURSES ---
$courses = $conn->query("SELECT c.id, c.course_code, c.course_title FROM course_teachers ct JOIN courses c ON ct.course_id = c.id WHERE ct.teacher_id = $teacher_id AND ct.session_id = $current_session_id ORDER BY c.course_code ASC");

// --- 4. FETCH UPLOADED MATERIALS ---
$materials = $conn->query("SELECT m.*, c.course_code, c.course_title FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE m.teacher_id = $teacher_id ORDER BY m.uploaded_at DESC");

$page_title = "Course Materials";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Course Materials</h3>
            <p class="text-muted small mb-0">Upload syllabus, lecture slides, and reading materials.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="ti ti-upload me-2"></i> Upload Material
        </button>
    </div>

    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check me-2"></i><?= $txt ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-alert-triangle me-2"></i><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <?php if ($materials->num_rows > 0): while ($m = $materials->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 position-relative">
                    <div class="card-body p-4">
                        <span class="badge bg-primary-subtle text-primary border px-2 py-1 mb-2 font-monospace"><?= htmlspecialchars($m['course_code']) ?></span>
                        <h6 class="fw-bold text-dark mb-2"><?= htmlspecialchars($m['title']) ?></h6>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($m['description'] ?? 'No description provided.') ?></p>
                        <small class="text-muted fw-bold" style="font-size: 10px;"><i class="ti ti-clock me-1"></i><?= date('d M, Y', strtotime($m['uploaded_at'])) ?></small>
                    </div>
                    <div class="card-footer bg-light border-0 p-3 d-flex justify-content-between">
                        <a href="../uploads/materials/<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold"><i class="ti ti-download me-1"></i> Download</a>
                        <a href="?delete_id=<?= $m['id'] ?>" onclick="return confirm('Delete this material?')" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold"><i class="ti ti-trash"></i></a>
                    </div>
                </div>
            </div>
        <?php endwhile; else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="ti ti-files fs-1 d-block mb-3"></i>
                <h6 class="fw-bold text-dark">No Materials Uploaded</h6>
                <p>You haven't shared any documents with your classes yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-3">
                <h5 class="modal-title fw-bold">Upload Resource</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Target Course</label>
                        <select name="course_id" class="form-select bg-light border-0" required>
                            <option value="">-- Select Course --</option>
                            <?php $courses->data_seek(0); while($c = $courses->fetch_assoc()): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code'] . ' - ' . $c['course_title']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Document Title</label>
                        <input type="text" name="title" class="form-control bg-light border-0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Description</label>
                        <textarea name="description" class="form-control bg-light border-0" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">File (PDF, DOCX, PPTX, ZIP)</label>
                        <input type="file" name="material_file" class="form-control bg-light border-0" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="submit" name="upload_material" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">Upload File <i class="ti ti-cloud-upload ms-1"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>