<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Fetch all activity logs for display
$logs = [];
$sql = "SELECT al.action, al.created_at, u.username 
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!--  Main wrapper -->
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Portal Activity Logs</h5>
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Date & Time</th><th>User</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (!empty($logs)) : foreach ($logs as $log) : ?>
                <tr>
                  <td><?php echo htmlspecialchars(date("Y-m-d H:i:s", strtotime($log['created_at']))); ?></td>
                  <td><?php echo htmlspecialchars($log['username']); ?></td>
                  <td><?php echo htmlspecialchars($log['action']); ?></td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="3" class="text-center">No activity logs found.</td></tr>
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