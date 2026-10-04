<?php 
$page_title = 'Submit Assignment';
require_once 'includes/header.php'; 

$student_id = $_SESSION['student_id'];
$assignments = [];
$message = $error = '';

// Fetch assignments for the student that they haven't submitted yet and are not past due
$sql = "SELECT a.id, a.title, c.course_name 
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
        WHERE e.student_id = ? AND s.id IS NULL AND a.due_date >= NOW()";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
} else {
    $error = "Error fetching assignments: " . $conn->error;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Request validation failed. Please try again.";
    } else {
        $assignment_id = trim($_POST['assignment_id']);

        // 2. Validate inputs
        if (empty($assignment_id) || !isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] != UPLOAD_ERR_OK) {
            $error = "Please select an assignment and upload a file.";
        } else {
            // 3. Handle file upload
            $target_dir = __DIR__ . '/../uploads/submissions/';

            // Check if the directory exists, if not, create it recursively.
            if (!is_dir($target_dir)) {
                // The `true` parameter allows the creation of nested directories.
                // The `0755` permission is a common and secure choice.
                if (!mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
                    // If mkdir fails, it might be a permissions issue.
                    $error = "Failed to create upload directory. Please check server permissions.";
                }
            }
            
            if (empty($error)) {
                $file_info = pathinfo($_FILES["submission_file"]["name"]);
                $file_ext = strtolower($file_info['extension']);
                
                // Create a unique filename to prevent overwrites and add security
                $safe_filename = preg_replace('/[^A-Za-z0-9_\-]/', '', $file_info['filename']);
                $new_filename = "sub_".$student_id."_".$assignment_id."_".time().".".$file_ext;
                $target_file_path = $target_dir . $new_filename;
                $db_file_path = "uploads/submissions/" . $new_filename; // Path to store in DB

                // Basic security checks
                $allowed_ext = ['pdf', 'doc', 'docx', 'txt'];
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($file_ext, $allowed_ext)) {
                    $error = "Sorry, only PDF, DOC, DOCX & TXT files are allowed.";
                } elseif ($_FILES["submission_file"]["size"] > $max_file_size) {
                    $error = "Sorry, your file is too large. Maximum size is 5MB.";
                } else {
                    if (move_uploaded_file($_FILES["submission_file"]["tmp_name"], $target_file_path)) {
                        // 4. Insert into database
                        $sql = "INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)";
                        if ($stmt = $conn->prepare($sql)) {
                            $stmt->bind_param("iis", $assignment_id, $student_id, $db_file_path);
                            if ($stmt->execute()) {
                                $message = "Your assignment has been submitted successfully!";
                                // Refresh assignments list to remove the one just submitted
                                $assignments = array_filter($assignments, function($a) use ($assignment_id) {
                                    return $a['id'] != $assignment_id;
                                });
                            } else {
                                $error = "Database error: Could not save submission. " . $stmt->error;
                                unlink($target_file_path);
                            }
                            $stmt->close();
                        } else {
                             $error = "Database error: " . $conn->error;
                             unlink($target_file_path);
                        }
                    } else {
                        $error = "Sorry, there was an error uploading your file.";
                    }
                }
            }
        }
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Submit Assignment</h5>

            <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if (!empty($assignments)): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label for="assignment_id" class="form-label">Select Assignment</label>
                    <select class="form-select" id="assignment_id" name="assignment_id" required>
                        <option value="">-- Choose an assignment --</option>
                        <?php foreach ($assignments as $assignment): ?>
                            <option value="<?php echo $assignment['id']; ?>"><?php echo htmlspecialchars($assignment['course_name'] . ' - ' . $assignment['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="submission_file" class="form-label">Upload File</label>
                    <input class="form-control" type="file" id="submission_file" name="submission_file" required>
                    <div class="form-text">Allowed formats: PDF, DOC, DOCX, TXT. Max size: 5MB.</div>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <?php else: ?>
                <div class="alert alert-info">You have no pending assignments to submit, or all due dates have passed.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>