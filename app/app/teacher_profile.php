<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'My Profile';

// 1. Check if the user is logged in and is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// 2. Handle the form submission to update the profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and retrieve form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $profile_picture_path_to_update = $teacher['profile_picture_path']; // Keep old path by default

    // Basic validation
    if (empty($full_name) || empty($email)) {
        $message = "<div class='alert alert-danger'>Full Name and Email are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger'>Invalid email format.</div>";
    } else {
        $conn->begin_transaction();
        $new_file_target_path = null;

        try {
            // Handle file upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file = $_FILES['profile_picture'];
                $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($file_ext, $allowed_exts)) {
                    throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed.");
                }
                if ($file['size'] > 2097152) { // 2MB limit
                    throw new Exception("File is too large. Maximum size is 2MB.");
                }

                $new_file_name = 'teacher_' . $user_id . '_' . uniqid() . '.' . $file_ext;
                $new_file_target_path = $upload_dir . $new_file_name;

                if (!move_uploaded_file($file['tmp_name'], $new_file_target_path)) {
                    throw new Exception("Failed to move uploaded file.");
                }
                
                // Set the new path for DB update
                $profile_picture_path_to_update = 'uploads/profile_pictures/' . $new_file_name;
            }

            // Update the teachers table
            $stmt_teacher = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, specialization = ?, profile_picture_path = ? WHERE user_id = ?");
            $stmt_teacher->bind_param('ssssi', $full_name, $email, $specialization, $profile_picture_path_to_update, $user_id);
            $stmt_teacher->execute();

            // If a new password is provided, validate and update it
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception("Passwords do not match.");
                }
                if (strlen($new_password) < 6) { // Example: enforce minimum password length
                    throw new Exception("Password must be at least 6 characters long.");
                }
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_user->bind_param('si', $hashed_password, $user_id);
                $stmt_user->execute();
            }

            $conn->commit();

            // If commit was successful and a new picture was uploaded, delete the old one
            if ($new_file_target_path && !empty($teacher['profile_picture_path']) && $teacher['profile_picture_path'] !== $profile_picture_path_to_update) {
                if (file_exists('../' . $teacher['profile_picture_path'])) {
                    unlink('../' . $teacher['profile_picture_path']);
                }
            }

            log_activity($conn, $user_id, "Updated profile details.");
            $message = "<div class='alert alert-success'>Profile updated successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            // If a new file was uploaded but the transaction failed, delete the new file
            if ($new_file_target_path && file_exists($new_file_target_path)) {
                unlink($new_file_target_path);
            }
            $message = "<div class='alert alert-danger'>An error occurred: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 3. Fetch the teacher's current data to display in the form
$stmt = $conn->prepare("
    SELECT t.*, u.username, d.name as department_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    // This should not happen for a logged-in teacher, but it's a good safeguard
    die("Error: Teacher profile not found.");
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>
    <?php echo $message; ?>
    <div class="card">
      <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-3 text-center mb-3 mb-md-0">
                <img src="<?php echo !empty($teacher['profile_picture_path']) ? '../' . htmlspecialchars($teacher['profile_picture_path']) : '../assets/images/profile/user-1.jpg'; ?>" alt="Profile Picture" class="rounded-circle" width="150" height="150" style="object-fit: cover; border: 4px solid #eee;">
            </div>
            <div class="col-md-9">
                <h5 class="card-title">Update Your Profile</h5>
                <p class="card-subtitle mb-3">Keep your information up to date.</p>
            </div>
        </div>
        <hr>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required></div>
            <div class="mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($teacher['username']); ?>" readonly></div>
            <div class="mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required></div>
            <div class="mb-3"><label for="specialization" class="form-label">Specialization</label><input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>" placeholder="e.g., Pharmacology, Pediatric Nursing"></div>
            <div class="mb-3"><label for="department" class="form-label">Department</label><input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?>" readonly></div>
            <hr>
            <h6 class="mb-3">Update Profile Picture</h6>
            <div class="mb-3"><label for="profile_picture" class="form-label">Upload New Picture</label><input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/png, image/jpeg, image/gif"><div class="form-text">Leave blank to keep your current picture. Max file size: 2MB.</div></div>
            <h6 class="mb-3">Change Password (optional)</h6>
            <div class="mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave blank to keep current password"></div>
            <div class="mb-3"><label for="confirm_password" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password"></div>
            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>