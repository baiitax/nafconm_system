<?php
session_start();

// Helper function to get dashboard path based on user type
function get_dashboard_path($user_type) {
    // This mapping centralizes the redirection logic for easier maintenance.
    // It's recommended to use consistent, lowercase folder names.
    $paths = [
        'student'    => 'student/index.php',
        'teacher'    => 'teachers/index.php',
        'staff'      => 'portal/index.php',
        'superadmin' => 'App/index.php'
    ];
    return $paths[$user_type] ?? 'index.php'; // Fallback to login page
}

// Check if the user is already logged in, if yes then redirect them to the appropriate dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . get_dashboard_path($_SESSION["user_type"]));
    exit;
}

require_once "config/db.php";

// Helper function to log user activity.
// In a real application, this should be in a shared functions file.
function log_activity($conn, $user_id, $action) {
    $sql = "INSERT INTO activity_logs (user_id, action) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("is", $user_id, $action);
        $stmt->execute();
        $stmt->close();
    }
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$username = $password = "";
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_err = "Request validation failed. Please try again.";
    } else {
        // 2. Process login credentials
        $username = trim($_POST["username"]);
        $password = trim($_POST["password"]);

        if (empty($username) || empty($password)) {
            $login_err = "Please enter both username and password.";
        } else {
            $sql = "SELECT id, username, password, user_type FROM users WHERE username = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_username);
                $param_username = $username;

                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($id, $db_username, $hashed_password, $user_type);
                        if ($stmt->fetch()) {
                            if (password_verify($password, $hashed_password)) {
                                // Password is correct, start a new secure session
                                session_regenerate_id(true);

                                // Store base data in session variables
                                $_SESSION["loggedin"] = true;
                                $_SESSION["user_id"] = $id;
                                $_SESSION["username"] = $db_username;
                                $_SESSION["user_type"] = $user_type;

                                // Fetch profile-specific information (e.g., student_id, full_name)
                                $profile_table = $user_type . 's'; // students, teachers, staffs
                                if ($user_type === 'superadmin') $profile_table = 'superadmins';

                                $profile_sql = "SELECT id, full_name FROM " . $profile_table . " WHERE user_id = ?";
                                if ($profile_stmt = $conn->prepare($profile_sql)) {
                                    $profile_stmt->bind_param("i", $id);
                                    $profile_stmt->execute();
                                    $profile_stmt->bind_result($profile_id, $full_name);
                                    if ($profile_stmt->fetch()) {
                                        $_SESSION[$user_type . "_id"] = $profile_id; // e.g., $_SESSION['student_id']
                                        $_SESSION["full_name"] = $full_name;
                                    }
                                    $profile_stmt->close();
                                }

                                // Generate a new CSRF token for the new session
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                                // Log successful login
                                log_activity($conn, $id, "User logged in successfully.");

                                // Redirect user to their dashboard
                                header("location: " . get_dashboard_path($user_type));
                                exit;
                            } else {
                                // Password is not valid
                                $login_err = "Invalid username or password.";
                                // Log failed login attempt for existing user
                                log_activity($conn, $id, "Failed login attempt (incorrect password).");
                            }
                        }
                    } else {
                        // Username doesn't exist
                        $login_err = "Invalid username or password.";
                        // Note: We don't log attempts with invalid usernames to prevent username enumeration from logs.
                    }
                } else {
                    $login_err = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
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
  <title>N-Portal Login</title>
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
                  <img src="assets/images/logos/favicon.png" width="180" alt="">
                </a>
                <p class="text-center">Student & Staff Portal</p>
                <?php 
                if(!empty($login_err)){
                    echo '<div class="alert alert-danger">' . $login_err . '</div>';
                }        
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>">
                  </div>
                  <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" id="password">
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                      <input class="form-check-input primary" type="checkbox" value="" id="flexCheckChecked" checked>
                      <label class="form-check-label text-dark" for="flexCheckChecked">
                        Remember this Device
                      </label>
                    </div>
                    <a class="text-primary fw-bold" href="./forgot_password.php">Forgot Password ?</a>
                  </div>
                  <button type="submit" class="btn btn-primary w-100 py-8 fs-4 mb-4 rounded-2">Sign In</button>
                  
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