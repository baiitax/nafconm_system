<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$invoice_id) {
    header("location: manage_invoices.php");
    exit;
}

// Fetch invoice details
$invoice = null;
$sql_invoice = "SELECT i.id, i.invoice_date, i.total_amount, i.status, s.full_name as student_name, s.student_id_number, se.name as session_name 
               FROM invoices i
               JOIN students s ON i.student_id = s.id
               JOIN sessions se ON i.session_id = se.id
               WHERE i.id = ?";
$stmt_invoice = $conn->prepare($sql_invoice);
$stmt_invoice->bind_param("i", $invoice_id);
$stmt_invoice->execute();
$result_invoice = $stmt_invoice->get_result();
if ($result_invoice->num_rows === 1) {
    $invoice = $result_invoice->fetch_assoc();
} else {
    die("Invoice not found.");
}
$stmt_invoice->close();

// Fetch invoice items
$invoice_items = [];
$sql_items = "SELECT ii.amount, f.fee_name FROM invoice_items ii JOIN fees f ON ii.fee_id = f.id WHERE ii.invoice_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $invoice_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($row = $result_items->fetch_assoc()) {
    $invoice_items[] = $row;
}
$stmt_items->close();

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold m-0">Invoice Details</h5>
        <a href="manage_invoices.php" class="btn btn-outline-primary">Back to Invoices</a>
    </div>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Invoice #<?php echo $invoice['id']; ?></h5>
        <div class="row">
          <div class="col-md-6">
            <p><strong>Student:</strong> <?php echo htmlspecialchars($invoice['student_name']); ?> (<?php echo htmlspecialchars($invoice['student_id_number']); ?>)</p>
            <p><strong>Session:</strong> <?php echo htmlspecialchars($invoice['session_name']); ?></p>
          </div>
          <div class="col-md-6">
            <p><strong>Invoice Date:</strong> <?php echo date("M d, Y", strtotime($invoice['invoice_date'])); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($invoice['status']); ?></p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered">
            <thead><tr><th>Fee Name</th><th>Amount</th></tr></thead>
            <tbody>
              <?php if (!empty($invoice_items)): ?>
                <?php foreach ($invoice_items as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['fee_name']); ?></td>
                    <td>Ksh <?php echo number_format($item['amount'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="2" class="text-center">No items in this invoice.</td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot>
              <tr>
                <th>Total Amount:</th>
                <th>Ksh <?php echo number_format($invoice['total_amount'], 2); ?></th>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="mt-4">
          <?php if ($invoice['status'] === 'pending'): ?>
            <a href="#" class="btn btn-success">Mark as Paid</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>