<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Add New Teacher';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $specialization = trim($_POST['specialization']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $message = "<div class='alert alert-danger'>Full Name, Username, Email, and Password are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert alert-danger'>Invalid email format.</div>";
    } else {
        // Check for existing username or email
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param('ss', $username, $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Username or email already exists.</div>";
        } else {
            $conn->begin_transaction();
            try {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_type = 'teacher';
                $stmt_user = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)");
                $stmt_user->bind_param('sss', $username, $hashed_password, $user_type);
                $stmt_user->execute();
                $new_user_id = $conn->insert_id;

                // Create teacher profile
                $stmt_teacher = $conn->prepare("INSERT INTO teachers (user_id, full_name, email, specialization, department_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_teacher->bind_param('isssi', $new_user_id, $full_name, $email, $specialization, $department_id);
                $stmt_teacher->execute();

                $conn->commit();
                log_activity($conn, $user_id, "Registered new teacher: $full_name (Username: $username)");
                $message = "<div class='alert alert-success'>Teacher registered successfully.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='alert alert-danger'>An error occurred: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Fetch departments for dropdown
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>
    <?php echo $message; ?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Teacher Details</h5>
        <p class="card-subtitle mb-3">Fill out the form to add a new teacher to the system.</p>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="specialization" class="form-label">Specialization</label>
                <input type="text" class="form-control" id="specialization" name="specialization" placeholder="e.g., Pharmacology, Pediatric Nursing">
            </div>
            <div class="mb-3">
                <label for="department_id" class="form-label">Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>