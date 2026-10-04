<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

if (isset($_SESSION['action_success'])) {
    $action_success = $_SESSION['action_success'];
    unset($_SESSION['action_success']);
}
if (isset($_SESSION['action_error'])) {
    $action_err = $_SESSION['action_error'];
    unset($_SESSION['action_error']);
}

// Fetch all hostel applications
$applications = [];
$sql = "SELECT ha.id, ha.application_date, ha.status, s.full_name, s.student_id_number 
        FROM hostel_applications ha
        JOIN students s ON ha.student_id = s.id
        ORDER BY ha.application_date DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Hostel Applications</h5>

    <?php if (!empty($action_success)): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if (!empty($action_err)): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Student ID</th>
                <th>Application Date</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($applications)): ?>
                <?php foreach ($applications as $app): ?>
                  <tr>
                    <td><?php echo $app['id']; ?></td>
                    <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($app['student_id_number']); ?></td>
                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($app['application_date']))); ?></td>
                    <td>
                      <?php $status_class = 'bg-secondary'; if ($app['status'] === 'Approved') $status_class = 'bg-success'; elseif ($app['status'] === 'Rejected') $status_class = 'bg-danger'; ?>
                      <span class="badge rounded-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                    </td>
                    <td><a href="view_hostel_application.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-info">View Details</a></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center">No hostel applications found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>