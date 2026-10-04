<?php
$page_title = 'My Profile';
require_once 'includes/header.php';

// student_id and user_id are set in the login script (index.php)
$student_id = $_SESSION['student_id'];
$user_id = $_SESSION['user_id'];

// Initialize variables
$full_name = $email = $phone = $date_of_birth = $address = $emergency_contact_name = $emergency_contact_phone = '';
$current_password = $new_password = $confirm_password = '';
$profile_message = $profile_error = $password_message = $password_error = '';

// Handle Profile Information Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $profile_error = "Request validation failed. Please try again.";
    } else {
        // Sanitize and validate inputs
        $full_name = trim($_POST['full_name']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $phone = trim($_POST['phone']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $address = trim($_POST['address']);
        $emergency_contact_name = trim($_POST['emergency_contact_name']);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

        if (empty($full_name) || empty($email)) {
            $profile_error = "Full Name and Email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_error = "Invalid email format.";
        } else {
            // Update student's profile
            $sql = "UPDATE students SET full_name = ?, email = ?, phone = ?, date_of_birth = ?, address = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssi", $full_name, $email, $phone, $date_of_birth, $address, $emergency_contact_name, $emergency_contact_phone, $student_id);
                if ($stmt->execute()) {
                    $profile_message = "Profile updated successfully!";
                    // Update session variable to reflect name change immediately
                    $_SESSION['full_name'] = $full_name;
                } else {
                    $profile_error = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    }
}

// Handle Password Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $password_error = "Request validation failed. Please try again.";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $password_error = "Password must have at least 6 characters.";
        } else {
            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Current password is correct, hash and update new password
                            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                            if ($update_stmt = $conn->prepare($update_sql)) {
                                $update_stmt->bind_param("si", $new_hashed_password, $user_id);
                                if ($update_stmt->execute()) {
                                    $password_message = "Password changed successfully.";
                                } else {
                                    $password_error = "Oops! Something went wrong.";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $password_error = "The current password you entered is incorrect.";
                        }
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Fetch current student data for display, unless it was just posted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $sql = "SELECT full_name, email, phone, date_of_birth, address, emergency_contact_name, emergency_contact_phone, student_id_number FROM students WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($student_data = $result->fetch_assoc()) {
            $full_name = $student_data['full_name'];
            $email = $student_data['email'];
            $phone = $student_data['phone'];
            $date_of_birth = $student_data['date_of_birth'];
            $address = $student_data['address'];
            $emergency_contact_name = $student_data['emergency_contact_name'];
            $emergency_contact_phone = $student_data['emergency_contact_phone'];
            $student_id_number = $student_data['student_id_number'];
        } else {
            echo "<div class='alert alert-danger'>Error: Profile not found.</div>";
            require_once 'includes/footer.php';
            exit;
        }
        $stmt->close();
    } else {
        echo "<div class='alert alert-danger'>Error fetching profile data.</div>";
        require_once 'includes/footer.php';
        exit;
    }
}
?>


<div class="container-fluid">
    <div class="row">
        <!-- Profile Information Card -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">My Profile</h5>
                    <p class="mb-4">Your Student ID: <strong><?php echo htmlspecialchars($student_id_number ?? 'N/A'); ?></strong></p>

                    <?php if ($profile_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($profile_message); ?></div><?php endif; ?>
                    <?php if ($profile_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($profile_error); ?></div><?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required></div>
                            <div class="col-md-6 mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"></div>
                            <div class="col-md-6 mb-3"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth); ?>"></div>
                        </div>

                        <div class="mb-3"><label for="address" class="form-label">Address</label><textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea></div>

                        <h5 class="card-title fw-semibold my-4">Emergency Contact</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="emergency_contact_name" class="form-label">Contact Name</label><input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($emergency_contact_name); ?>"></div>
                            <div class="col-md-6 mb-3"><label for="emergency_contact_phone" class="form-label">Contact Phone</label><input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($emergency_contact_phone); ?>"></div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Change Password</h5>

                    <?php if ($password_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($password_message); ?></div><?php endif; ?>
                    <?php if ($password_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($password_error); ?></div><?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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