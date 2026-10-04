<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Bulk Register Teachers';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error_rows = [];
$success_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_register'])) {
    $full_names = $_POST['full_name'];
    $usernames = $_POST['username'];
    $emails = $_POST['email'];
    $passwords = $_POST['password'];

    $conn->begin_transaction();
    try {
        for ($i = 0; $i < count($full_names); $i++) {
            $full_name = trim($full_names[$i]);
            $username = trim($usernames[$i]);
            $email = trim($emails[$i]);
            $password = $passwords[$i];

            // Skip empty rows
            if (empty($full_name) && empty($username) && empty($email) && empty($password)) {
                continue;
            }

            // Basic validation
            if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                $error_rows[] = "Row " . ($i + 1) . ": All fields are required. Data: " . htmlspecialchars("$full_name, $username, $email");
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_rows[] = "Row " . ($i + 1) . ": Invalid email format for " . htmlspecialchars($email);
                continue;
            }

            // Check for existing username or email
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt_check->bind_param('ss', $username, $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $error_rows[] = "Row " . ($i + 1) . ": Username or email already exists. Data: " . htmlspecialchars("$username, $email");
                continue;
            }

            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = 'teacher';
            $stmt_user = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)");
            $stmt_user->bind_param('sss', $username, $hashed_password, $user_type);
            $stmt_user->execute();
            $new_user_id = $conn->insert_id;
            if ($new_user_id === 0) {
                throw new Exception("Failed to create user for " . htmlspecialchars($username));
            }

            // Create teacher profile
            $stmt_teacher = $conn->prepare("INSERT INTO teachers (user_id, full_name, email) VALUES (?, ?, ?)");
            $stmt_teacher->bind_param('iss', $new_user_id, $full_name, $email);
            $stmt_teacher->execute();
            if ($stmt_teacher->affected_rows === 0) {
                throw new Exception("Failed to create teacher profile for " . htmlspecialchars($full_name));
            }

            $success_count++;
        }

        if (empty($error_rows)) {
            $conn->commit();
            if ($success_count > 0) {
                log_activity($conn, $user_id, "Bulk registered $success_count teachers.");
                $message = "<div class='alert alert-success'>Successfully registered $success_count teachers.</div>";
            } else {
                $message = "<div class='alert alert-info'>No new teachers were registered.</div>";
            }
        } else {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>Registration failed. Please fix the errors below and try again. No teachers were registered.</div>";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>An error occurred: " . $e->getMessage() . "</div>";
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Main wrapper -->
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>
    <?php echo $message; ?>
    <?php if (!empty($error_rows)): ?>
        <div class="alert alert-warning">
            <h6>Errors Found:</h6>
            <ul>
                <?php foreach ($error_rows as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="">
            <h5 class="card-title">Teacher Details</h5>
            <p class="card-subtitle mb-3">Add rows for each teacher you want to register.</p>
            <table class="table table-bordered" id="teacher-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows will be added here by JavaScript -->
                </tbody>
            </table>
            <button type="button" class="btn btn-info mt-3" id="add-row-btn">Add Another Teacher</button>
            <button type="submit" name="bulk_register" class="btn btn-primary mt-3">Register All Teachers</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const addRowBtn = document.getElementById('add-row-btn');
    const tableBody = document.querySelector('#teacher-table tbody');

    function createRow() {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="full_name[]" class="form-control" required></td>
            <td><input type="text" name="username[]" class="form-control" required></td>
            <td><input type="email" name="email[]" class="form-control" required></td>
            <td><input type="text" name="password[]" class="form-control" required></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row-btn">Remove</button></td>
        `;
        tableBody.appendChild(row);
    }

    // Add first row on page load
    createRow();

    addRowBtn.addEventListener('click', createRow);

    tableBody.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-row-btn')) {
            e.target.closest('tr').remove();
        }
    });
});
</script>