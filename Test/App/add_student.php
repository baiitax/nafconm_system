<?php
$page_title = 'Add New Student';
// This assumes you have a header file in a superadmin/includes/ directory
// similar to the one in the teachers/ directory. It should handle session
// start, DB connection, and security checks for the 'superadmin' user type.
require_once 'includes/header.php';

$errors = [];
$success = '';

// Pre-fill form for stickiness on error
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$student_id_number = $_POST['student_id_number'] ?? '';
$username = $_POST['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Invalid request. Please try again.";
    } else {
        // Sanitize and retrieve POST data
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $student_id_number = trim($_POST['student_id_number']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // --- Validation ---
        if (empty($full_name)) $errors['full_name'] = "Full name is required.";
        if (empty($student_id_number)) $errors['student_id_number'] = "Student ID Number is required.";
        if (empty($username)) $errors['username'] = "Username is required.";
        if (empty($password)) {
            $errors['password'] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters long.";
        }

        // Email validation (including uniqueness check)
        if (empty($email)) {
            $errors['email'] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        } else {
            // Here is the system-wide email uniqueness validation
            $sql_check_email = "
                SELECT 1 FROM teachers WHERE email = ?
                UNION ALL
                SELECT 1 FROM students WHERE email = ?
                UNION ALL
                SELECT 1 FROM staffs WHERE email = ?
                UNION ALL
                SELECT 1 FROM superadmins WHERE email = ?
            ";
            $stmt_check = $conn->prepare($sql_check_email);
            $stmt_check->bind_param("ssss", $email, $email, $email, $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors['email'] = "This email address is already in use by another account.";
            }
            $stmt_check->close();
        }

        // Username uniqueness check
        if (empty($errors['username'])) {
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors['username'] = "This username is already taken.";
            }
            $stmt_check->close();
        }
        
        // Student ID Number uniqueness check
        if (empty($errors['student_id_number'])) {
            $stmt_check = $conn->prepare("SELECT id FROM students WHERE student_id_number = ?");
            $stmt_check->bind_param("s", $student_id_number);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors['student_id_number'] = "This Student ID Number is already registered.";
            }
            $stmt_check->close();
        }

        // If no errors, proceed with insertion
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // 1. Insert into users table
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_type = 'student';
                $sql_user = "INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("sss", $username, $hashed_password, $user_type);
                $stmt_user->execute();
                $new_user_id = $conn->insert_id;
                $stmt_user->close();

                // 2. Insert into students table
                $sql_student = "INSERT INTO students (user_id, full_name, student_id_number, email) VALUES (?, ?, ?, ?)";
                $stmt_student = $conn->prepare($sql_student);
                $stmt_student->bind_param("isss", $new_user_id, $full_name, $student_id_number, $email);
                $stmt_student->execute();
                $stmt_student->close();

                $conn->commit();
                $success = "Student '{$full_name}' has been added successfully!";
                log_activity($conn, $_SESSION['user_id'], "Added new student: {$full_name} ({$username})");
                // Clear form fields after success
                $full_name = $email = $student_id_number = $username = '';
            } catch (Exception $e) {
                $conn->rollback();
                $errors['form'] = "An error occurred during registration: " . $e->getMessage();
            }
        }
    }
}

// These would be in the superadmin's includes folder
require_once 'includes/sidebar.php';
?>
<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Add Students</h5>
    </div>

<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">Add New Student</h5>
            
            <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?php echo $errors['form']; ?></div><?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="full_name" class="form-label">Full Name</label><input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required><?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?php echo $errors['full_name']; ?></div><?php endif; ?></div>
                    <div class="col-md-6 mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?php echo $errors['email']; ?></div><?php endif; ?></div>
                </div>
                <div class="mb-3"><label for="student_id_number" class="form-label">Student ID Number</label><input type="text" class="form-control <?php echo isset($errors['student_id_number']) ? 'is-invalid' : ''; ?>" id="student_id_number" name="student_id_number" value="<?php echo htmlspecialchars($student_id_number); ?>" required><?php if (isset($errors['student_id_number'])): ?><div class="invalid-feedback"><?php echo $errors['student_id_number']; ?></div><?php endif; ?></div>
                <hr class="my-4">
                <h6 class="mb-3">Login Credentials</h6>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required><?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?php echo $errors['username']; ?></div><?php endif; ?></div>
                    <div class="col-md-6 mb-3"><label for="password" class="form-label">Password</label><input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required><div class="form-text">Password must be at least 8 characters long.</div><?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?php echo $errors['password']; ?></div><?php endif; ?></div>
                </div>
                <button type="submit" class="btn btn-primary">Add Student</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </form>
        </div>
    </div>
</div>

<?php
// This assumes you have a footer file in a superadmin/includes/ directory
require_once 'includes/footer.php';
?>