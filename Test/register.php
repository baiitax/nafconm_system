<?php
require_once "config/db.php";

// Check if registration is open
$registration_open = false;
$sql_reg_check = "SELECT setting_value FROM settings WHERE setting_key = 'registration_open'";
if($result = $conn->query($sql_reg_check)){
    if($result->num_rows == 1){
        $row = $result->fetch_assoc();
        if($row['setting_value'] == '1'){
            $registration_open = true;
        }
    }
}

session_start();

// If user is already logged in, redirect
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . $_SESSION["user_type"] . "/index.php");
    exit;
}

// Define variables and initialize with empty values
$username = $full_name = $student_id = $email = $password = $confirm_password = "";
$username_err = $full_name_err = $student_id_err = $email_err = $password_err = $confirm_password_err = $general_err = "";
$registration_success = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!$registration_open) {
        $general_err = "Sorry, student registration is currently closed.";
    } else {
        // Validate username
        if (empty(trim($_POST["username"]))) {
            $username_err = "Please enter a username.";
        } else {
            $sql = "SELECT id FROM users WHERE username = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_username);
                $param_username = trim($_POST["username"]);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $username_err = "This username is already taken.";
                    } else {
                        $username = trim($_POST["username"]);
                    }
                } else {
                    $general_err = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }

        // Validate full name, student ID, and email
        if (empty(trim($_POST["full_name"]))) { $full_name_err = "Please enter your full name."; } else { $full_name = trim($_POST["full_name"]); }
        if (empty(trim($_POST["student_id"]))) { $student_id_err = "Please enter your student ID."; } else { $student_id = trim($_POST["student_id"]); }
        if (empty(trim($_POST["email"]))) { $email_err = "Please enter an email."; } else { $email = trim($_POST["email"]); }

        // Validate password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";
        } elseif (strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password must have at least 6 characters.";
        } else {
            $password = trim($_POST["password"]);
        }

        // Validate confirm password
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm password.";
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if (empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Passwords do not match.";
            }
        }

        // Check input errors before inserting in database
        if (empty($username_err) && empty($full_name_err) && empty($student_id_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($general_err)) {
            
            $conn->begin_transaction();

            // Prepare an insert statement for users table
            $sql_user = "INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')";
            if ($stmt_user = $conn->prepare($sql_user)) {
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user->bind_param("ss", $username, $param_password);
                
                if ($stmt_user->execute()) {
                    $user_id = $conn->insert_id;
                    $stmt_user->close();

                    // Prepare an insert statement for students table
                    $sql_student = "INSERT INTO students (user_id, full_name, student_id_number, email) VALUES (?, ?, ?, ?)";
                    if ($stmt_student = $conn->prepare($sql_student)) {
                        $stmt_student->bind_param("isss", $user_id, $full_name, $student_id, $email);
                        
                        if ($stmt_student->execute()) {
                            $conn->commit();
                            $registration_success = "Your account has been created successfully. You can now login.";
                            $_POST = array(); // Clear form fields on success
                            $username = $full_name = $student_id = $email = "";
                        } else {
                            $conn->rollback();
                            $general_err = "Something went wrong. Please try again later.";
                        }
                        $stmt_student->close();
                    }
                } else {
                    $conn->rollback();
                    $general_err = "Something went wrong. Please try again later.";
                }
            }
        }
    }
    $conn->close();
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register Account</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
</head>

<body>
  <!--  Body Wrapper -->
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full"
    data-sidebar-position="fixed" data-header-position="fixed">
    <div
      class="position-relative overflow-hidden radial-gradient min-vh-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-6 col-xxl-3">
            <div class="card mb-0">
              <div class="card-body">
                <a href="./index.php" class="text-nowrap logo-img text-center d-block py-3 w-100">
                  <img src="assets/images/logos/dark-logo.svg" width="180" alt="">
                </a>
                <p class="text-center">Create Your Student Account</p>                
                <?php if (!$registration_open && $_SERVER["REQUEST_METHOD"] != "POST"): ?>
                    <div class="alert alert-warning text-center" role="alert">
                        Student registration is currently closed. Please contact an administrator.
                    </div>
                <?php endif; ?>
                <?php 
                if(!empty($general_err)){ echo '<div class="alert alert-danger">' . $general_err . '</div>'; }        
                if(!empty($registration_success)){ echo '<div class="alert alert-success">' . $registration_success . '</div>'; }
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                  <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" value="<?php echo htmlspecialchars($full_name); ?>">
                    <span class="invalid-feedback"><?php echo $full_name_err; ?></span>
                  </div>
                  <div class="mb-3">
                    <label for="student_id" class="form-label">Student ID</label>
                    <input type="text" name="student_id" class="form-control <?php echo (!empty($student_id_err)) ? 'is-invalid' : ''; ?>" id="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                    <span class="invalid-feedback"><?php echo $student_id_err; ?></span>
                  </div>
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" value="<?php echo htmlspecialchars($email); ?>">
                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                  </div>
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" value="<?php echo htmlspecialchars($username); ?>">
                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                  </div>
                  <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                  </div>
                  <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password">
                    <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                  </div>
                  <button type="submit" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2" <?php if (!$registration_open) echo 'disabled'; ?>>Sign Up</button>
                  <div class="d-flex align-items-center justify-content-center">
                    <p class="fs-4 mb-0 fw-bold">Already have an Account?</p>
                    <a class="text-primary fw-bold ms-2" href="index.php">Sign In</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="assets/libs/jquery/dist/jquery.min.js"></script>
  <script src="assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>