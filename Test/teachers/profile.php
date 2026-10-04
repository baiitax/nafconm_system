<?php
$page_title = 'My Profile';
require_once 'includes/header.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['user_id'])) {
    // Redirect to login or show an error
    header('Location: ../login.php'); // Adjust path as needed
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$user_id = $_SESSION['user_id'];

$update_success = $password_success = '';
$update_error = $password_error = '';

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $specialization = trim($_POST['specialization']);

    if (empty($full_name) || empty($email)) {
        $update_error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $update_error = "Invalid email format.";
    } else {
        // Check if email is already taken by any other user in the system
        $sql_check_email = "
            SELECT 1 FROM teachers WHERE email = ? AND id != ?
            UNION ALL
            SELECT 1 FROM students WHERE email = ?
            UNION ALL
            SELECT 1 FROM staffs WHERE email = ?
            UNION ALL
            SELECT 1 FROM superadmins WHERE email = ?
        ";
        $stmt = $conn->prepare($sql_check_email);
        $stmt->bind_param("sisss", $email, $teacher_id, $email, $email, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $update_error = "This email address is already in use by another account.";
            $stmt->close();
        } else {
            $stmt->close();
            // Update teachers table
            $stmt_update = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, specialization = ? WHERE id = ?");
            $stmt_update->bind_param("sssi", $full_name, $email, $specialization, $teacher_id);
            if ($stmt_update->execute()) {
                $update_success = "Profile updated successfully!";
                if (isset($_SESSION['full_name'])) {
                    $_SESSION['full_name'] = $full_name;
                }
            } else {
                $update_error = "Error updating profile: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "Password must be at least 8 characters long.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        if ($stmt->fetch() && password_verify($current_password, $password_hash)) {
            $stmt->close();
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_password_hash, $user_id);
            if ($stmt_update->execute()) {
                $password_success = "Password changed successfully!";
            } else {
                $password_error = "Error changing password: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $stmt->close();
            $password_error = "Incorrect current password.";
        }
    }
}

// Fetch current teacher data for display
$stmt = $conn->prepare("SELECT t.full_name, t.email, t.specialization, u.username FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    die("Teacher profile not found.");
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">My Profile</h1>
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 fw-semibold">Profile Information</h6></div>
                <div class="card-body">
                    <?php if ($update_success): ?><div class="alert alert-success"><?php echo $update_success; ?></div><?php endif; ?>
                    <?php if ($update_error): ?><div class="alert alert-danger"><?php echo $update_error; ?></div><?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($teacher['username']); ?>" disabled readonly><div class="form-text">Your username cannot be changed.</div></div>
                        <div class="mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($teacher['full_name']); ?>" required></div>
                        <div class="mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($teacher['email']); ?>" required></div>
                        <div class="mb-3"><label for="specialization" class="form-label">Specialization</label><input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($teacher['specialization'] ?? ''); ?>"></div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 fw-semibold">Change Password</h6></div>
                <div class="card-body">
                    <?php if ($password_success): ?><div class="alert alert-success"><?php echo $password_success; ?></div><?php endif; ?>
                    <?php if ($password_error): ?><div class="alert alert-danger"><?php echo $password_error; ?></div><?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3"><label for="current_password" class="form-label">Current Password</label><input type="password" class="form-control" id="current_password" name="current_password" required></div>
                        <div class="mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password" required></div>
                        <div class="mb-3"><label for="confirm_password" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required></div>
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>