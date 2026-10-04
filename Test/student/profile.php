<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$profile_update_err = $profile_update_success = "";
$password_err = $password_success = "";

// Handle Profile Information Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Sanitize and validate input
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $address = trim($_POST['address']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

    // Basic validation
    if (empty($full_name) || empty($email)) {
        $profile_update_err = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profile_update_err = "Invalid email format.";
    } else {
        // Update student's profile
        $sql = "UPDATE students SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssi", $full_name, $email, $phone, $date_of_birth, $address, $emergency_contact_name, $emergency_contact_phone, $user_id);
            if ($stmt->execute()) {
                $profile_update_success = "Profile updated successfully.";
            } else {
                $profile_update_err = "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}

// Handle Password Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_err = "Please fill all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $password_err = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        // Check current password
        $sql = "SELECT password FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Password is correct, update to new password
                            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                            if ($update_stmt = $conn->prepare($update_sql)) {
                                $update_stmt->bind_param("si", $new_hashed_password, $user_id);
                                if ($update_stmt->execute()) {
                                    $password_success = "Password updated successfully.";
                                } else {
                                    $password_err = "Oops! Something went wrong.";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $password_err = "The current password you entered was not valid.";
                        }
                    }
                }
            }
            $stmt->close();
        }
    }
}

// Fetch current student data to display in the form
$student_data = [];
$sql = "SELECT full_name, student_id_number, email, phone, date_of_birth, address, emergency_contact_name, emergency_contact_phone FROM students WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $student_data = $result->fetch_assoc();
        } else {
            die("Error: Student profile not found.");
        }
    }
    $stmt->close();
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">My Profile</h5>
    <div class="row">
      <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Personal Information</h5>
            <?php if ($profile_update_success): ?><div class="alert alert-success"><?php echo $profile_update_success; ?></div><?php endif; ?>
            <?php if ($profile_update_err): ?><div class="alert alert-danger"><?php echo $profile_update_err; ?></div><?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
              <div class="mb-3"><label for="studentId" class="form-label">Student ID</label><input type="text" class="form-control" id="studentId" value="<?php echo htmlspecialchars($student_data['student_id_number']); ?>" readonly></div>
              <div class="mb-3"><label for="fullName" class="form-label">Full Name</label><input type="text" class="form-control" id="fullName" name="full_name" value="<?php echo htmlspecialchars($student_data['full_name']); ?>"></div>
              <div class="mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($student_data['email']); ?>"></div>
              <div class="mb-3"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($student_data['phone']); ?>"></div>
              <div class="mb-3"><label for="dob" class="form-label">Date of Birth</label><input type="date" class="form-control" id="dob" name="date_of_birth" value="<?php echo htmlspecialchars($student_data['date_of_birth']); ?>"></div>
              <div class="mb-3"><label for="address" class="form-label">Address</label><textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student_data['address']); ?></textarea></div>
              <h5 class="card-title fw-semibold my-4">Emergency Contact</h5>
              <div class="mb-3"><label for="emergencyName" class="form-label">Emergency Contact Name</label><input type="text" class="form-control" id="emergencyName" name="emergency_contact_name" value="<?php echo htmlspecialchars($student_data['emergency_contact_name']); ?>"></div>
              <div class="mb-3"><label for="emergencyPhone" class="form-label">Emergency Contact Phone</label><input type="tel" class="form-control" id="emergencyPhone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($student_data['emergency_contact_phone']); ?>"></div>
              <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
          <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Change Password</h5>
            <?php if ($password_success): ?><div class="alert alert-success"><?php echo $password_success; ?></div><?php endif; ?>
            <?php if ($password_err): ?><div class="alert alert-danger"><?php echo $password_err; ?></div><?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
              <div class="mb-3"><label for="currentPassword" class="form-label">Current Password</label><input type="password" class="form-control" id="currentPassword" name="current_password"></div>
              <div class="mb-3"><label for="newPassword" class="form-label">New Password</label><input type="password" class="form-control" id="newPassword" name="new_password"></div>
              <div class="mb-3"><label for="confirmPassword" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirmPassword" name="confirm_password"></div>
              <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>