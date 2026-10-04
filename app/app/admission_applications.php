<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php'; 

// 1. Enhanced Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// 2. Handle Application Review & Student Creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_application'])) {
    $application_id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $review_remarks = trim($_POST['review_remarks']); 
    $reviewed_by_user_id = $_SESSION['user_id'];

    if (!in_array($status, ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted'])) {
        $action_err = "Invalid status provided.";
    } else {
        // Start Database Transaction
        $conn->begin_transaction();

        try {
            // A. Update the Application Status
            $sql_update = "UPDATE admission_applications SET status = ?, review_remarks = ?, reviewed_by_user_id = ?, review_date = NOW() WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssii", $status, $review_remarks, $reviewed_by_user_id, $application_id);
            $stmt_update->execute();

            // B. If status is 'accepted', create the Student Profile automatically
            if ($status === 'accepted') {
                // Check if student already exists to prevent duplicate account creation
                $check_sql = "SELECT student_id FROM admission_applications WHERE id = ? AND student_id IS NOT NULL";
                $c_stmt = $conn->prepare($check_sql);
                $c_stmt->bind_param("i", $application_id);
                $c_stmt->execute();
                
                if ($c_stmt->get_result()->num_rows == 0) {
                    // Fetch full applicant data
                    $app_res = $conn->query("SELECT * FROM admission_applications WHERE id = $application_id");
                    $app = $app_res->fetch_assoc();

                    // 1. Create User Login Account
                    // Username format: first_name + application_id (e.g., mary45)
                    $username = strtolower($app['first_name']) . $app['id'];
                    $password = password_hash($app['phone'], PASSWORD_DEFAULT); // Default password is their phone number
                    $user_type = 'student';

                    $user_stmt = $conn->prepare("INSERT INTO users (username, password, user_type) VALUES (?, ?, ?)");
                    $user_stmt->bind_param("sss", $username, $password, $user_type);
                    $user_stmt->execute();
                    $new_user_id = $conn->insert_id;

                    // 2. Create Official Student Record
                    // Generate Student ID Number: Year + ID (e.g., 20250045)
                    $student_id_no = date("Y") . str_pad($app['id'], 4, "0", STR_PAD_LEFT);
                    $full_name = $app['first_name'] . ' ' . $app['last_name'];

                    $student_sql = "INSERT INTO students (student_id_number, user_id, full_name, email, phone, date_of_birth, address, program_id, parent_name, parent_phone, parent_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $s_stmt = $conn->prepare($student_sql);
                    $s_stmt->bind_param("sisssssisss", 
                        $student_id_no, $new_user_id, $full_name, $app['email'], $app['phone'], 
                        $app['date_of_birth'], $app['address'], $app['program_id'], 
                        $app['parent_name'], $app['parent_phone'], $app['parent_email']
                    );
                    $s_stmt->execute();
                    $new_student_db_id = $conn->insert_id;

                    // 3. Link Application to the New Student Record
                    $conn->query("UPDATE admission_applications SET student_id = $new_student_db_id WHERE id = $application_id");

                    $action_success = "Application accepted. Student account created! <br> <strong>Username:</strong> $username | <strong>Temp Password:</strong> (Applicant Phone)";
                } else {
                    $action_success = "Status updated (Student account already exists).";
                }
            } else {
                $action_success = "Application status updated to " . ucfirst($status);
            }

            // Commit all changes
            $conn->commit();
            log_activity($conn, $_SESSION['user_id'], "Reviewed admission application ID {$application_id}, status set to {$status}.");

        } catch (Exception $e) {
            $conn->rollback(); // Undo everything if any query fails
            $action_err = "System Error: Could not process status change. " . $e->getMessage();
        }
    }
}

// 3. Optimized Fetch Query (JOIN with programs table)
$applications = [];
$sql_applications = "SELECT a.*, p.name as program_name 
                    FROM admission_applications a 
                    LEFT JOIN programs p ON a.program_id = p.id 
                    ORDER BY a.application_date DESC";
$result_applications = $conn->query($sql_applications);
if ($result_applications) { 
    while ($row = $result_applications->fetch_assoc()) { 
        $applications[] = $row; 
    } 
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-semibold">Admission Management</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Applications</li>
                </ol>
            </nav>
        </div>
        <a href="admissions_overview.php" class="btn btn-outline-primary">Back to Overview</a>
    </div>

    <?php if ($action_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-circle-check fs-4"></i> <?php echo $action_success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($action_err): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle fs-4"></i> <?php echo $action_err; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="text-dark fs-4">
              <tr>
                <th><h6 class="fw-semibold mb-0">ID</h6></th>
                <th><h6 class="fw-semibold mb-0">Applicant Details</h6></th>
                <th><h6 class="fw-semibold mb-0">Program</h6></th>
                <th><h6 class="fw-semibold mb-0">Applied On</h6></th>
                <th><h6 class="fw-semibold mb-0">Status</h6></th>
                <th class="text-center"><h6 class="fw-semibold mb-0">Actions</h6></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($applications)) : ?>
                <?php foreach ($applications as $application) : ?>
                  <tr>
                    <td><p class="mb-0 fw-normal"><?php echo $application['id']; ?></p></td>
                    <td>
                        <h6 class="fw-semibold mb-1"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></h6>
                        <span class="fw-normal text-muted"><?php echo htmlspecialchars($application['email']); ?></span>
                    </td>
                    <td>
                        <p class="mb-0 fw-normal"><?php echo htmlspecialchars($application['program_name'] ?? 'Undefined'); ?></p>
                    </td>
                    <td>
                        <p class="mb-0 fw-normal"><?php echo htmlspecialchars(date("d M, Y", strtotime($application['application_date']))); ?></p>
                    </td>
                    <td>
                      <?php
                        $status_class = 'bg-light-secondary text-secondary';
                        if ($application['status'] === 'accepted') $status_class = 'bg-light-success text-success';
                        elseif ($application['status'] === 'rejected') $status_class = 'bg-light-danger text-danger';
                        elseif ($application['status'] === 'under_review') $status_class = 'bg-light-info text-info';
                        elseif ($application['status'] === 'waitlisted') $status_class = 'bg-light-warning text-warning';
                      ?>
                      <span class="badge <?php echo $status_class; ?> fw-semibold fs-2 text-uppercase">
                        <?php echo htmlspecialchars($application['status']); ?>
                      </span>
                    </td>
                    <td class="text-center">
                      <a href="view_admission_application.php?id=<?php echo $application['id']; ?>" class="btn btn-primary btn-sm rounded-2">
                        Review Application
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else : ?>
                <tr>
                  <td colspan="6" class="text-center py-4">No applications found in the records.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>