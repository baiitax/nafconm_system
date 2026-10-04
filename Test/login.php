<?php
// Initialize the session
session_start();
 
// Check if the user is already logged in, if yes then redirect them to their respective dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    switch($_SESSION["user_type"]) {
        case 'superadmin': header("location: App/index.php"); break;
        case 'student': header("location: student/index.php"); break;
        case 'teacher': header("location: Teacher/index.php"); break;
        case 'staff': header("location: staff/index.php"); break; // Assuming a staff portal exists
        default: header("location: login.php"); break;
    }
    exit;
}
 
require_once "config/db.php";
 
$username = $password = "";
$username_err = $password_err = $login_err = "";
 
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    if(empty($username_err) && empty($password_err)){
        $sql = "SELECT id, username, password, user_type FROM users WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){                    
                    $stmt->bind_result($id, $username, $hashed_password, $user_type);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store base data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["user_type"] = $user_type;                            
                            
                            // --- NEW LOGIC: Fetch role-specific data ---
                            $profile_sql = "";
                            switch ($user_type) {
                                case 'student': $profile_sql = "SELECT id, full_name FROM students WHERE user_id = ?"; break;
                                case 'teacher': $profile_sql = "SELECT id, full_name FROM teachers WHERE user_id = ?"; break;
                                case 'staff': $profile_sql = "SELECT id, full_name FROM staffs WHERE user_id = ?"; break;
                                case 'superadmin': $profile_sql = "SELECT id, full_name FROM superadmins WHERE user_id = ?"; break;
                            }

                            if (!empty($profile_sql)) {
                                if ($profile_stmt = $conn->prepare($profile_sql)) {
                                    $profile_stmt->bind_param("i", $id);
                                    if ($profile_stmt->execute()) {
                                        $profile_stmt->store_result();
                                        if ($profile_stmt->num_rows == 1) {
                                            $profile_stmt->bind_result($profile_id, $full_name);
                                            if ($profile_stmt->fetch()) {
                                                $_SESSION["full_name"] = $full_name;
                                                // Store role-specific ID (e.g., the ID from the `students` table, not the `users` table)
                                                if ($user_type == 'student') $_SESSION["student_id"] = $profile_id;
                                                if ($user_type == 'teacher') $_SESSION["teacher_id"] = $profile_id;
                                                if ($user_type == 'staff') $_SESSION["staff_id"] = $profile_id;
                                            }
                                        }
                                    }
                                    $profile_stmt->close();
                                }
                            }

                            // Redirect user to appropriate dashboard
                            switch($user_type) {
                                case 'superadmin': header("location: App/index.php"); break;
                                case 'student': header("location: student/index.php"); break;
                                case 'teacher': header("location: Teacher/index.php"); break;
                                case 'staff': header("location: staff/index.php"); break;
                                default: $login_err = "User role not configured for redirection."; break;
                            }
                        } else{
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    $login_err = "Invalid username or password.";
                }
            } else{
                $login_err = "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
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
  <title>N-Portal - Login</title>
  <link rel="shortcut icon" type="image/png" href="assets/images/logos/favicon.png" />
  <link rel="stylesheet" href="assets/css/styles.min.css" />
</head>
<body>
  <div class="page-wrapper" id="main-wrapper" data-layout="vertical" data-navbarbg="skin6" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
    <div class="position-relative overflow-hidden radial-gradient min-vh-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-6 col-xxl-3">
            <div class="card mb-0">
              <div class="card-body">
                <a href="./index.php" class="text-nowrap logo-img text-center d-block py-3 w-100">
                  <img src="assets/images/logos/dark-logo.svg" width="180" alt="">
                </a>
                <p class="text-center">Your School Management Solution</p>
                <?php 
                if(!empty($login_err)){
                    echo '<div class="alert alert-danger">' . $login_err . '</div>';
                }        
                ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                  <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>" id="username">
                    <span class="invalid-feedback"><?php echo $username_err; ?></span>
                  </div>
                  <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
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