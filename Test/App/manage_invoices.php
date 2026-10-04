<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Invoice Deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_invoice'])) {
    $invoice_id = $_POST['invoice_id'];
    $sql_delete = "DELETE FROM invoices WHERE id = ?";
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $invoice_id);
        if ($stmt_delete->execute()) {
            $action_success = "Invoice deleted successfully.";
        } else {
            $action_err = "Error deleting invoice.";
        }
        $stmt_delete->close();
    } else {
        $action_err = "Database error.";
    }
}

// Handle Invoice Creation (Simplified)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_invoice'])) {
    $student_id = $_POST['student_id'];
    $session_id = $_POST['session_id'];
    $total_amount = 0; // You would calculate this based on the selected fees

    // Basic Validation
    if (empty($student_id) || empty($session_id)) {
        $action_err = "Student and Session are required.";
    } else {
        // In a real implementation, you would:
        // 1. Fetch the selected fees from the form
        // 2. Calculate the total amount based on those fees
        // 3. Create the invoice record in the invoices table
        // 4. Create invoice items in the invoice_items table

        // For this simplified example, I'm just creating an invoice with a placeholder amount
        $sql_insert = "INSERT INTO invoices (student_id, session_id, total_amount) VALUES (?, ?, 0)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("ii", $student_id, $session_id);
            if ($stmt_insert->execute()) {
                $action_success = "Invoice created successfully.";
            } else {
                $action_err = "Error creating invoice.";
            }
            $stmt_insert->close();
        } else {
            $action_err = "Database error.";
        }
    }
}

// Fetch all invoices
$invoices = [];
$sql_invoices = "SELECT i.id, i.invoice_date, i.total_amount, i.status, s.full_name as student_name, se.name as session_name 
                 FROM invoices i
                 JOIN students s ON i.student_id = s.id
                 JOIN sessions se ON i.session_id = se.id
                 ORDER BY i.invoice_date DESC";
$result_invoices = $conn->query($sql_invoices);
if ($result_invoices) {
    while ($row = $result_invoices->fetch_assoc()) {
        $invoices[] = $row;
    }
}

// Fetch all students for the dropdown
$students = [];
$sql_students = "SELECT id, full_name FROM students ORDER BY full_name";
$result_students = $conn->query($sql_students);
if ($result_students) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
}

// Fetch all sessions for the dropdown
$sessions = [];
$sql_sessions = "SELECT id, name FROM sessions ORDER BY name";
$result_sessions = $conn->query($sql_sessions);
if ($result_sessions) {
    while ($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Manage Invoices</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">Create New Invoice</button>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>ID</th><th>Student</th><th>Session</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (!empty($invoices)): foreach ($invoices as $invoice): ?>
                <tr>
                  <td><?php echo $invoice['id']; ?></td>
                  <td><?php echo htmlspecialchars($invoice['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($invoice['session_name']); ?></td>
                  <td><?php echo date("M d, Y", strtotime($invoice['invoice_date'])); ?></td>
                  <td>Ksh <?php echo number_format($invoice['total_amount'], 2); ?></td>
                  <td><?php echo htmlspecialchars($invoice['status']); ?></td>
                  <td>
                    <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">View</a>
                    <form action="" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                      <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                      <button type="submit" name="delete_invoice" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center">No invoices found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="invoiceModalLabel">Create New Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label for="student_id" class="form-label">Student</label><select class="form-select" id="student_id" name="student_id" required><option value="">Select Student</option><?php foreach ($students as $student): ?><option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['full_name']); ?></option><?php endforeach; ?></select></div>
          <div class="mb-3"><label for="session_id" class="form-label">Session</label><select class="form-select" id="session_id" name="session_id" required><option value="">Select Session</option><?php foreach ($sessions as $session): ?><option value="<?php echo $session['id']; ?>"><?php echo htmlspecialchars($session['name']); ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="create_invoice" class="btn btn-primary">Create Invoice</button></div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>