<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php'; // For log_activity

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') { // Basic Auth
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle application review
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_application'])) {
    $application_id = (int)$_POST['application_id'];
    $status = $_POST['status'];
    $review_remarks = trim($_POST['review_remarks']); 
    $reviewed_by_user_id = $_SESSION['user_id'];

    if (!in_array($status, ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted'])) {
        $action_err = "Invalid status provided.";
    } else {
        $sql_update = "UPDATE admission_applications SET status = ?, review_remarks = ?, reviewed_by_user_id = ?, review_date = NOW() WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssii", $status, $review_remarks, $reviewed_by_user_id, $application_id);
            if ($stmt_update->execute()) {
                $action_success = "Application status updated successfully.";
                log_activity($conn, $_SESSION['user_id'], "Reviewed admission application ID {$application_id}, status set to {$status}.");
            } else { $action_err = "Error updating application status."; }
            $stmt_update->close();
        } else { $action_err = "Database error."; }
    }
}

// Fetch all applications for display
$applications = [];
$sql_applications = "SELECT * FROM admission_applications ORDER BY application_date DESC";
$result_applications = $conn->query($sql_applications);
if ($result_applications) { while ($row = $result_applications->fetch_assoc()) { $applications[] = $row; } }

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">All Admission Applications</h5>
        <a href="admissions_overview.php" class="btn btn-outline-primary">Back to Overview</a>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Applicant</th>
                <th>Email</th>
                <th>Program</th>
                <th>Application Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($applications)) : ?>
                <?php foreach ($applications as $application) : ?>
                  <tr>
                    <td><?php echo $application['id']; ?></td>
                    <td><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></td>
                    <td><a href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><?php echo htmlspecialchars($application['email']); ?></a></td>
                    <td><?php echo htmlspecialchars($application['program_of_interest'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($application['application_date']))); ?></td>
                    <td>
                      <?php
                        $status_class = 'bg-secondary';
                        if ($application['status'] === 'accepted') $status_class = 'bg-success';
                        elseif ($application['status'] === 'rejected') $status_class = 'bg-danger';
                        elseif ($application['status'] === 'under_review') $status_class = 'bg-info';
                        elseif ($application['status'] === 'waitlisted') $status_class = 'bg-warning';
                      ?>
                      <span class="badge rounded-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($application['status'])); ?></span>
                    </td>
                    <td>
                      <a href="view_admission_application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-info">View Details</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else : ?>
                <tr>
                  <td colspan="7" class="text-center">No applications found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>