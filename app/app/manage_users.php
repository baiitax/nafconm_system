<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Delete User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = $_POST['user_id'];
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $action_err = "Error: You cannot delete your own account.";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                $action_success = "User deleted successfully.";
            } else {
                $action_err = "Error deleting user. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Handle Create/Edit User
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['create_user']) || isset($_POST['edit_user']))) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    // When editing, user_type is from a hidden field because the select is disabled.
    if (isset($_POST['edit_user'])) {
        $user_type = $_POST['hidden_user_type'];
    } else {
        $user_type = $_POST['user_type'];
    }
    $password = $_POST['password'];
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;

    // Role-specific fields
    $student_id_number = trim($_POST['student_id_number']);
    $specialization = trim($_POST['specialization']);
    $department = trim($_POST['department']);

    // --- Validation ---
    if (empty($username) || empty($full_name) || empty($email) || empty($user_type)) {
        $action_err = "Please fill all required fields.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $action_err = "Password must have at least 6 characters.";
    } else {
        $conn->begin_transaction();
        try {
            // --- Handle User Edit ---
            if (isset($_POST['edit_user']) && !empty($user_id)) {
                // Update users table
                if (!empty($password)) {
                    $param_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    $stmt_user->bind_param("ssi", $username, $param_password, $user_id);
                } else {
                    $stmt_user = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt_user->bind_param("si", $username, $user_id);
                }
                $stmt_user->execute();
                $stmt_user->close();

                // Update profile table
                switch ($user_type) {
                    case 'student': $stmt_profile = $conn->prepare("UPDATE students SET full_name = ?, email = ?, student_id_number = ? WHERE user_id = ?"); $stmt_profile->bind_param("sssi", $full_name, $email, $student_id_number, $user_id); break;
                    case 'teacher': $stmt_profile = $conn->prepare("UPDATE teachers SET full_name = ?, email = ?, specialization = ? WHERE user_id = ?"); $stmt_profile->bind_param("sssi", $full_name, $email, $specialization, $user_id); break;
                    case 'staff': $stmt_profile = $conn->prepare("UPDATE staffs SET full_name = ?, email = ?, department = ? WHERE user_id = ?"); $stmt_profile->bind_param("sssi", $full_name, $email, $department, $user_id); break;
                    case 'superadmin': $stmt_profile = $conn->prepare("UPDATE superadmins SET full_name = ?, email = ? WHERE user_id = ?"); $stmt_profile->bind_param("ssi", $full_name, $email, $user_id); break;
                }
                if (isset($stmt_profile)) {
                    $stmt_profile->execute();
                    $stmt_profile->close();
                }
                $action_success = "User updated successfully.";
            } 
            // --- Handle User Create ---
            else {
                if (empty($password)) { throw new Exception("Password is required for new users."); }
                // Insert into users table
                $param_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)");
                $stmt_user->bind_param("sss", $username, $param_password, $user_type);
                $stmt_user->execute();
                $new_user_id = $conn->insert_id;
                $stmt_user->close();

                // Insert into profile table
                switch ($user_type) {
                    case 'student': $stmt_profile = $conn->prepare("INSERT INTO students (user_id, full_name, email, student_id_number, can_register_courses) VALUES (?, ?, ?, ?, 0)"); $stmt_profile->bind_param("isss", $new_user_id, $full_name, $email, $student_id_number); break;
                    case 'teacher': $stmt_profile = $conn->prepare("INSERT INTO teachers (user_id, full_name, email, specialization) VALUES (?, ?, ?, ?)"); $stmt_profile->bind_param("isss", $new_user_id, $full_name, $email, $specialization); break;
                    case 'staff': $stmt_profile = $conn->prepare("INSERT INTO staffs (user_id, full_name, email, department) VALUES (?, ?, ?, ?)"); $stmt_profile->bind_param("isss", $new_user_id, $full_name, $email, $department); break;
                    case 'superadmin': $stmt_profile = $conn->prepare("INSERT INTO superadmins (user_id, full_name, email) VALUES (?, ?, ?)"); $stmt_profile->bind_param("iss", $new_user_id, $full_name, $email); break;
                }
                if (isset($stmt_profile)) {
                    $stmt_profile->execute();
                    $stmt_profile->close();
                } else { throw new Exception("Invalid user type specified."); }
                $action_success = "User created successfully.";
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $action_err = "Database error: " . $e->getMessage();
            if ($conn->errno == 1062) { // Duplicate entry
                $action_err = "Error: Username, email, or student ID already exists.";
            }
        }
    }
}

// Fetch all users for display
$users = [];
$sql = "SELECT u.id, u.username, u.user_type, u.created_at,
               COALESCE(s.full_name, t.full_name, st.full_name, sa.full_name) as full_name, s.id as student_profile_id,
               COALESCE(s.email, t.email, st.email, sa.email) as email,
               s.student_id_number, t.specialization, d.name as department
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id AND u.user_type = 'student'
        LEFT JOIN teachers t ON u.id = t.user_id AND u.user_type = 'teacher'
        LEFT JOIN staffs st ON u.id = st.user_id AND u.user_type = 'staff'
        LEFT JOIN departments d ON st.department_id = d.id
        LEFT JOIN superadmins sa ON u.id = sa.user_id AND u.user_type = 'superadmin'
        ORDER BY u.id";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Users</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="prepareCreateModal()">Create New User</button>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success"><?php echo $action_success; ?></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger"><?php echo $action_err; ?></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>User Type</th><th>Created At</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($users)) : foreach ($users as $user) : ?>
                <tr>
                  <td><?php echo $user['id']; ?></td>
                  <td><?php echo htmlspecialchars($user['username']); ?></td>
                  <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($user['user_type'])); ?></span></td>
                  <td><?php echo htmlspecialchars(date("Y-m-d", strtotime($user['created_at']))); ?></td>
                  <td>
                    <?php if ($user['user_type'] === 'student' && !empty($user['student_profile_id'])): ?>
                        <a href="view_student_details.php?student_id=<?php echo $user['student_profile_id']; ?>" class="btn btn-sm btn-outline-primary">Details</a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#userModal" onclick='prepareEditModal(<?php echo json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-userid="<?php echo $user['id']; ?>">Delete</button>
                  </td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="7" class="text-center">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- User Create/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="userModalLabel">Create User</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="userId">
          <input type="hidden" name="hidden_user_type" id="hiddenUserType">
          <div class="mb-3"><label for="username" class="form-label">Username</label><input type="text" class="form-control" id="username" name="username" required></div>
          <div class="mb-3"><label for="fullName" class="form-label">Full Name</label><input type="text" class="form-control" id="fullName" name="full_name" required></div>
          <div class="mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" required></div>
          <div class="mb-3"><label for="password" class="form-label">Password</label><input type="password" class="form-control" id="password" name="password"><small id="passwordHelp" class="form-text text-muted">Leave blank to keep current password.</small></div>
          <div class="mb-3"><label for="userType" class="form-label">User Type</label><select class="form-select" id="userType" name="user_type" required onchange="toggleRoleFields()"><option value="student">Student</option><option value="teacher">Teacher</option><option value="staff">Staff</option><option value="superadmin">Superadmin</option></select></div>
          <div id="student-fields" class="role-fields mb-3"><label for="studentIdNumber" class="form-label">Student ID</label><input type="text" class="form-control" id="studentIdNumber" name="student_id_number"></div>
          <div id="teacher-fields" class="role-fields mb-3" style="display: none;"><label for="specialization" class="form-label">Specialization</label><input type="text" class="form-control" id="specialization" name="specialization"></div>
          <div id="staff-fields" class="role-fields mb-3" style="display: none;"><label for="department" class="form-label">Department</label><input type="text" class="form-control" id="department" name="department"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="create_user" id="createBtn" class="btn btn-primary">Create User</button>
          <button type="submit" name="edit_user" id="editBtn" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="deleteUserId">
          <p>Are you sure you want to delete this user? This action cannot be undone and will remove all associated records.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_user" class="btn btn-danger">Delete User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleRoleFields() {
    const userType = document.getElementById('userType').value;
    document.getElementById('student-fields').style.display = 'none';
    document.getElementById('teacher-fields').style.display = 'none';
    document.getElementById('staff-fields').style.display = 'none';

    if (userType === 'student') {
        document.getElementById('student-fields').style.display = 'block';
    } else if (userType === 'teacher') {
        document.getElementById('teacher-fields').style.display = 'block';
    } else if (userType === 'staff') {
        document.getElementById('staff-fields').style.display = 'block';
    }
}

function prepareCreateModal() {
    document.getElementById('userModalLabel').innerText = 'Create User';
    document.getElementById('userModal').querySelector('form').reset();
    document.getElementById('userId').value = '';
    document.getElementById('hiddenUserType').value = '';
    document.getElementById('password').setAttribute('required', 'required');
    document.getElementById('passwordHelp').style.display = 'none';
    document.getElementById('createBtn').style.display = 'block';
    document.getElementById('editBtn').style.display = 'none';
    document.getElementById('userType').removeAttribute('disabled');
    toggleRoleFields();
}

function prepareEditModal(user) {
    document.getElementById('userModalLabel').innerText = 'Edit User';
    document.getElementById('userModal').querySelector('form').reset();

    document.getElementById('userId').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('fullName').value = user.full_name;
    document.getElementById('email').value = user.email;
    document.getElementById('userType').value = user.user_type;
    document.getElementById('hiddenUserType').value = user.user_type; // Set hidden field for edit
    
    // For edit, password is not required
    document.getElementById('password').removeAttribute('required');
    document.getElementById('passwordHelp').style.display = 'block';

    // Set role-specific fields
    document.getElementById('studentIdNumber').value = user.student_id_number || '';
    document.getElementById('specialization').value = user.specialization || '';
    document.getElementById('department').value = user.department || '';

    document.getElementById('createBtn').style.display = 'none';
    document.getElementById('editBtn').style.display = 'block';
    
    // Prevent changing user type on edit to avoid data inconsistencies
    document.getElementById('userType').setAttribute('disabled', 'disabled');
    
    toggleRoleFields();
}

const deleteModal = document.getElementById('deleteModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const userId = button.getAttribute('data-userid');
    const modalUserIdInput = deleteModal.querySelector('#deleteUserId');
    modalUserIdInput.value = userId;
});
</script>

<?php
include 'includes/footer.php';
?>