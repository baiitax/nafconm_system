<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Manage Fees';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle Add Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $session_id = (int)$_POST['session_id'];
    $fee_name = trim($_POST['fee_name']);
    $amount = (float)$_POST['amount'];

    if (!empty($session_id) && !empty($fee_name) && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO fees (session_id, fee_name, amount) VALUES (?, ?, ?)");
        $stmt->bind_param('isd', $session_id, $fee_name, $amount);
        if ($stmt->execute()) {
            log_activity($conn, $user_id, "Added new fee: $fee_name");
            $message = "<div class='alert alert-success'>Fee added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error adding fee.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    }
}

// Handle Edit Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fee'])) {
    $fee_id = (int)$_POST['fee_id'];
    $session_id = (int)$_POST['session_id'];
    $fee_name = trim($_POST['fee_name']);
    $amount = (float)$_POST['amount'];

    if (!empty($fee_id) && !empty($session_id) && !empty($fee_name) && $amount > 0) {
        $stmt = $conn->prepare("UPDATE fees SET session_id = ?, fee_name = ?, amount = ? WHERE id = ?");
        $stmt->bind_param('isdi', $session_id, $fee_name, $amount, $fee_id);
        if ($stmt->execute()) {
            log_activity($conn, $user_id, "Updated fee: $fee_name (ID: $fee_id)");
            $message = "<div class='alert alert-success'>Fee updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating fee.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    }
}

// Handle Delete Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fee'])) {
    $fee_id = (int)$_POST['fee_id'];
    if (!empty($fee_id)) {
        $stmt = $conn->prepare("DELETE FROM fees WHERE id = ?");
        $stmt->bind_param('i', $fee_id);
        if ($stmt->execute()) {
            log_activity($conn, $user_id, "Deleted fee with ID: $fee_id");
            $message = "<div class='alert alert-success'>Fee deleted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error deleting fee.</div>";
        }
    }
}

// Fetch data for display
$fees = $conn->query("SELECT f.*, s.name as session_name FROM fees f JOIN sessions s ON f.session_id = s.id ORDER BY s.name, f.fee_name")->fetch_all(MYSQLI_ASSOC);
$sessions = $conn->query("SELECT * FROM sessions WHERE is_active = 1 ORDER BY name DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();

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
        <div class="d-flex justify-content-end mb-3">
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="prepareFeeCreate()">Add New Fee</button>
        </div>
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Fee Name</th>
                <th>Session</th>
                <th>Amount (₦)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fees as $fee): ?>
                <tr>
                  <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                  <td><?php echo htmlspecialchars($fee['session_name']); ?></td>
                  <td><?php echo number_format($fee['amount'], 2); ?></td>
                  <td>
                    <button class="btn btn-sm btn-info" onclick='prepareFeeEdit(<?php echo json_encode($fee); ?>)'>Edit</button>
                    <button class="btn btn-sm btn-danger" onclick='prepareDelete("fee", <?php echo $fee["id"]; ?>)'>Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($fees)): ?>
                <tr><td colspan="4" class="text-center">No fees found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Fee Modal -->
<div class="modal fade" id="feeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title" id="feeModalLabel">Add Fee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="fee_id" id="fee_id">
          <div class="mb-3">
            <label for="session_id" class="form-label">Session</label>
            <select class="form-select" id="session_id" name="session_id" required>
              <option value="">Select Session</option>
              <?php foreach ($sessions as $session): ?>
                <option value="<?php echo $session['id']; ?>"><?php echo htmlspecialchars($session['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="fee_name" class="form-label">Fee Name</label>
            <input type="text" class="form-control" id="fee_name" name="fee_name" required>
          </div>
          <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="add_fee" id="feeSubmitButton" class="btn btn-primary">Add Fee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteForm" method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item?</p>
                    <input type="hidden" name="fee_id" id="deleteId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="deleteButton" name="delete_fee" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
const feeModal = new bootstrap.Modal(document.getElementById('feeModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

function prepareFeeCreate() {
    document.getElementById('feeModalLabel').textContent = 'Add Fee';
    document.getElementById('fee_id').value = '';
    document.getElementById('session_id').value = '';
    document.getElementById('fee_name').value = '';
    document.getElementById('amount').value = '';
    document.getElementById('feeSubmitButton').name = 'add_fee';
    document.getElementById('feeSubmitButton').textContent = 'Add Fee';
    feeModal.show();
}

function prepareFeeEdit(fee) {
    document.getElementById('feeModalLabel').textContent = 'Edit Fee';
    document.getElementById('fee_id').value = fee.id;
    document.getElementById('session_id').value = fee.session_id;
    document.getElementById('fee_name').value = fee.fee_name;
    document.getElementById('amount').value = fee.amount;
    document.getElementById('feeSubmitButton').name = 'edit_fee';
    document.getElementById('feeSubmitButton').textContent = 'Save Changes';
    feeModal.show();
}

function prepareDelete(type, id) {
    document.getElementById('deleteId').value = id;
    deleteModal.show();
}
</script>