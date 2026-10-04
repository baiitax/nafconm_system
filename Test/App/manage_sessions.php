<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action_err = $action_success = "";

// --- CRUD Operations ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- SESSION CRUD ---
    if (isset($_POST['save_session'])) {
        $name = trim($_POST['name']);
        $start_date = trim($_POST['start_date']);
        $end_date = trim($_POST['end_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $session_id = $_POST['session_id'] ?? null;

        if (empty($name) || empty($start_date) || empty($end_date)) {
            $action_err = "Session name, start date, and end date are required.";
        } else {
            $conn->begin_transaction();
            try {
                // If setting a session to active, deactivate all others first
                if ($is_active == 1) {
                    $conn->query("UPDATE sessions SET is_active = 0");
                }

                if (empty($session_id)) { // Create
                    $sql = "INSERT INTO sessions (name, start_date, end_date, is_active) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssi", $name, $start_date, $end_date, $is_active);
                    if ($stmt->execute()) {
                        $action_success = "Session created successfully.";
                        log_activity($conn, $user_id, "Created session '{$name}'.");
                    } else { $action_err = "Error creating session."; }
                } else { // Update
                    $sql = "UPDATE sessions SET name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssii", $name, $start_date, $end_date, $is_active, $session_id);
                    if ($stmt->execute()) {
                        $action_success = "Session updated successfully.";
                        log_activity($conn, $user_id, "Updated session ID: {$session_id}.");
                    } else { $action_err = "Error updating session."; }
                }
                $stmt->close();
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $action_err = "Database error: " . $e->getMessage();
            }
        }
    }

    if (isset($_POST['delete_session'])) {
        $session_id = $_POST['session_id'];
        $sql = "DELETE FROM sessions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $session_id);
        if ($stmt->execute()) {
            $action_success = "Session deleted successfully.";
            log_activity($conn, $user_id, "Deleted session ID: {$session_id}.");
        } else {
            if ($conn->errno == 1451) { $action_err = "Cannot delete session with associated fees or payments."; }
            else { $action_err = "Error deleting session."; }
        }
        $stmt->close();
    }

    // --- FEE CRUD ---
    if (isset($_POST['save_fee'])) {
        $session_id = $_POST['session_id'];
        $fee_name = trim($_POST['fee_name']);
        $amount = (float)$_POST['amount'];
        $fee_id = $_POST['fee_id'] ?? null;

        if (empty($session_id) || empty($fee_name) || empty($amount)) {
            $action_err = "Session, fee name, and amount are required.";
        } else {
            if (empty($fee_id)) { // Create
                $sql = "INSERT INTO fees (session_id, fee_name, amount) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isd", $session_id, $fee_name, $amount);
                if ($stmt->execute()) {
                    $action_success = "Fee created successfully.";
                    log_activity($conn, $user_id, "Created fee '{$fee_name}'.");
                } else { $action_err = "Error creating fee."; }
            } else { // Update
                $sql = "UPDATE fees SET session_id = ?, fee_name = ?, amount = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isdi", $session_id, $fee_name, $amount, $fee_id);
                if ($stmt->execute()) {
                    $action_success = "Fee updated successfully.";
                    log_activity($conn, $user_id, "Updated fee ID: {$fee_id}.");
                } else { $action_err = "Error updating fee."; }
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_fee'])) {
        $fee_id = $_POST['fee_id'];
        $sql = "DELETE FROM fees WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $fee_id);
        if ($stmt->execute()) {
            $action_success = "Fee deleted successfully.";
            log_activity($conn, $user_id, "Deleted fee ID: {$fee_id}.");
        } else {
            $action_err = "Error deleting fee.";
        }
        $stmt->close();
    }
}

// --- Fetch Data for Display ---
$sessions = $conn->query("SELECT * FROM sessions ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
$fees = $conn->query("SELECT f.*, s.name as session_name FROM fees f JOIN sessions s ON f.session_id = s.id ORDER BY s.start_date DESC, f.fee_name ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Manage Sessions & Fees</h5>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="sessions-tab" data-bs-toggle="tab" data-bs-target="#sessions-pane" type="button" role="tab">Sessions</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="fees-tab" data-bs-toggle="tab" data-bs-target="#fees-pane" type="button" role="tab">Fees</button></li>
        </ul>
        <div class="tab-content" id="myTabContent">
          <!-- Sessions Tab -->
          <div class="tab-pane fade show active" id="sessions-pane" role="tabpanel">
            <div class="d-flex justify-content-end my-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal" onclick="prepareSessionCreate()">Add New Session</button></div>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Start Date</th><th>End Date</th><th>Is Active</th><th>Actions</th></tr></thead><tbody>
              <?php foreach ($sessions as $session): ?>
                <tr>
                    <td><?php echo htmlspecialchars($session['name']); ?></td>
                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($session['start_date']))); ?></td>
                    <td><?php echo htmlspecialchars(date("M d, Y", strtotime($session['end_date']))); ?></td>
                    <td><?php echo $session['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>'; ?></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick='prepareSessionEdit(<?php echo json_encode($session); ?>)'>Edit</button>
                        <button class="btn btn-sm btn-danger" onclick='prepareDelete("session", <?php echo $session["id"]; ?>)'>Delete</button>
                    </td>
                </tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </div>
          <!-- Fees Tab -->
          <div class="tab-pane fade" id="fees-pane" role="tabpanel">
            <div class="d-flex justify-content-end my-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal" onclick="prepareFeeCreate()">Add New Fee</button></div>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Fee Name</th><th>Amount</th><th>Session</th><th>Actions</th></tr></thead><tbody>
              <?php foreach ($fees as $fee): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                    <td><?php echo htmlspecialchars(number_format($fee['amount'], 2)); ?></td>
                    <td><?php echo htmlspecialchars($fee['session_name']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick='prepareFeeEdit(<?php echo json_encode($fee); ?>)'>Edit</button>
                        <button class="btn btn-sm btn-danger" onclick='prepareDelete("fee", <?php echo $fee["id"]; ?>)'>Delete</button>
                    </td>
                </tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="sessionModalLabel">Add Session</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="session_id" id="sessionId"><div class="mb-3"><label for="sessionName" class="form-label">Name</label><input type="text" class="form-control" id="sessionName" name="name" required></div><div class="mb-3"><label for="startDate" class="form-label">Start Date</label><input type="date" class="form-control" id="startDate" name="start_date" required></div><div class="mb-3"><label for="endDate" class="form-label">End Date</label><input type="date" class="form-control" id="endDate" name="end_date" required></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="isActive" name="is_active" value="1"><label class="form-check-label" for="isActive">Set as Active Session</label><div class="form-text">Note: Setting this session as active will deactivate any other currently active session.</div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_session" class="btn btn-primary">Save</button></div></form></div></div></div>

<!-- Fee Modal -->
<div class="modal fade" id="feeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="feeModalLabel">Add Fee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="fee_id" id="feeId"><div class="mb-3"><label for="feeSessionId" class="form-label">Session</label><select class="form-select" id="feeSessionId" name="session_id" required><option value="">Select Session...</option><?php foreach ($sessions as $session): ?><option value="<?php echo $session['id']; ?>"><?php echo htmlspecialchars($session['name']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label for="feeName" class="form-label">Fee Name</label><input type="text" class="form-control" id="feeName" name="fee_name" required></div><div class="mb-3"><label for="feeAmount" class="form-label">Amount</label><input type="number" step="0.01" class="form-control" id="feeAmount" name="amount" required min="0"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_fee" class="btn btn-primary">Save</button></div></form></div></div></div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Are you sure you want to delete this item? This action might fail if there is associated data.</p><input type="hidden" id="deleteId" name=""><input type="hidden" id="deleteType" name=""></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" id="deleteButton" class="btn btn-danger">Delete</button></div></form></div></div></div>

<script>
const sessionModal = new bootstrap.Modal(document.getElementById('sessionModal'));
const feeModal = new bootstrap.Modal(document.getElementById('feeModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

function prepareSessionCreate() {
    document.getElementById('sessionModalLabel').innerText = 'Add Session';
    document.getElementById('sessionModal').querySelector('form').reset();
    document.getElementById('sessionId').value = '';
    sessionModal.show();
}

function prepareSessionEdit(session) {
    document.getElementById('sessionModalLabel').innerText = 'Edit Session';
    document.getElementById('sessionId').value = session.id;
    document.getElementById('sessionName').value = session.name;
    document.getElementById('startDate').value = session.start_date;
    document.getElementById('endDate').value = session.end_date;
    document.getElementById('isActive').checked = session.is_active == 1;
    sessionModal.show();
}

function prepareFeeCreate() {
    document.getElementById('feeModalLabel').innerText = 'Add Fee';
    document.getElementById('feeModal').querySelector('form').reset();
    document.getElementById('feeId').value = '';
    feeModal.show();
}

function prepareFeeEdit(fee) {
    document.getElementById('feeModalLabel').innerText = 'Edit Fee';
    document.getElementById('feeId').value = fee.id;
    document.getElementById('feeSessionId').value = fee.session_id;
    document.getElementById('feeName').value = fee.fee_name;
    document.getElementById('feeAmount').value = fee.amount;
    feeModal.show();
}

function prepareDelete(type, id) {
    const deleteIdInput = document.getElementById('deleteId');
    const deleteButton = document.getElementById('deleteButton');
    
    deleteIdInput.name = type + '_id';
    deleteIdInput.value = id;
    deleteButton.name = 'delete_' + type;
    deleteButton.innerText = 'Delete ' + type.charAt(0).toUpperCase() + type.slice(1);
    
    deleteModal.show();
}
</script>

<?php
include 'includes/footer.php';
?>