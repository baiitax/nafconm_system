<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$page_title = 'Student Financial Statement';
include 'includes/header.php';

// --- Data Logic ---
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$selected_session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

$students = $conn->query("SELECT id, full_name, student_id_number FROM students ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
$sessions = $conn->query("SELECT id, name FROM sessions ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);

$student_details = null;
$session_fees = [];
$payment_history = [];
$total_due = 0; $total_paid = 0;

if ($selected_student_id && $selected_session_id) {
    // 1. Student Info
    $stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.id = ?");
    $stmt->bind_param("i", $selected_student_id);
    $stmt->execute();
    $student_details = $stmt->get_result()->fetch_assoc();

    // 2. Fetch Session Fees (Program-specific or Global)
    $stmt_fees = $conn->prepare("SELECT fee_name, amount FROM fees WHERE session_id = ? AND (program_id IS NULL OR program_id = ?)");
    $stmt_fees->bind_param("ii", $selected_session_id, $student_details['program_id']);
    $stmt_fees->execute();
    $res_fees = $stmt_fees->get_result();
    while ($f = $res_fees->fetch_assoc()) {
        $session_fees[] = $f;
        $total_due += $f['amount'];
    }

    // 3. Payment History
    $stmt_pay = $conn->prepare("SELECT amount_paid, payment_date, status, transaction_id, payment_method FROM payments WHERE student_id = ? AND session_id = ? ORDER BY payment_date DESC");
    $stmt_pay->bind_param("ii", $selected_student_id, $selected_session_id);
    $stmt_pay->execute();
    $res_pay = $stmt_pay->get_result();
    while ($p = $res_pay->fetch_assoc()) {
        $payment_history[] = $p;
        if ($p['status'] == 'approved') $total_paid += $p['amount_paid'];
    }
}

$balance = $total_due - $total_paid;

include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="card bg-light-info shadow-none border-0 mb-4 overflow-hidden">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-bolder mb-0">Financial Audit</h4>
                    <p class="text-muted small mb-0">Detailed statement of account per student and session.</p>
                </div>
                <i class="ti ti-receipt-2 fs-8 text-info opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="card shadow-none border mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-uppercase">Student Identity</label>
                    <select class="form-select select2" name="student_id" required>
                        <option value="">Select student...</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($selected_student_id == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['full_name']) ?> (<?= $s['student_id_number'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-uppercase">Academic Session</label>
                    <select class="form-select" name="session_id" required>
                        <option value="">Select session...</option>
                        <?php foreach ($sessions as $sess): ?>
                            <option value="<?= $sess['id'] ?>" <?= ($selected_session_id == $sess['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sess['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="ti ti-refresh me-1"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($student_details): ?>
        <div class="card shadow-none border mb-4">
            <div class="card-body">
                <div class="d-md-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="bg-light-primary text-primary rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width:65px; height:65px;">
                            <i class="ti ti-user fs-7"></i>
                        </div>
                        <div>
                            <h5 class="fw-bolder mb-0 text-dark"><?= strtoupper($student_details['full_name']) ?></h5>
                            <span class="text-muted fs-3"><?= $student_details['program_name'] ?> | <?= $student_details['student_id_number'] ?></span>
                        </div>
                    </div>
                    <div class="text-md-end">
                        <button class="btn btn-sm btn-outline-dark fw-bold px-3" onclick="window.print()">
                            <i class="ti ti-printer me-1"></i> Print Statement
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card border-0 bg-light-primary h-100 shadow-none">
                    <div class="card-body">
                        <span class="text-uppercase fs-1 fw-bold ls-1 text-primary">Session Billing</span>
                        <h3 class="fw-bolder mb-0 mt-1">₦<?= number_format($total_due, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card border-0 bg-light-success h-100 shadow-none">
                    <div class="card-body">
                        <span class="text-uppercase fs-1 fw-bold ls-1 text-success">Total Recovered</span>
                        <h3 class="fw-bolder mb-0 mt-1">₦<?= number_format($total_paid, 2) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 <?= ($balance > 0) ? 'bg-light-danger text-danger' : 'bg-dark text-white' ?> h-100 shadow-none">
                    <div class="card-body">
                        <span class="text-uppercase fs-1 fw-bold ls-1 opacity-75">Outstanding Balance</span>
                        <h3 class="fw-bolder mb-0 mt-1">₦<?= number_format($balance, 2) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card shadow-none border">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">Expected Fees</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <tbody class="fs-2">
                                <?php foreach ($session_fees as $f): ?>
                                    <tr>
                                        <td class="ps-4 fw-medium text-dark"><?= $f['fee_name'] ?></td>
                                        <td class="text-end pe-4">₦<?= number_format($f['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light-subtle">
                                <tr>
                                    <th class="ps-4">Total Due</th>
                                    <th class="text-end pe-4">₦<?= number_format($total_due, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-none border">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h6 class="fw-bold mb-0">Verified Transactions</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light text-uppercase fs-1">
                                    <tr>
                                        <th class="ps-4">Date / Reference</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th class="text-end pe-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-2">
                                    <?php if (!empty($payment_history)): foreach ($payment_history as $p): 
                                        $st_color = ($p['status'] == 'approved') ? 'bg-success' : (($p['status'] == 'pending') ? 'bg-warning' : 'bg-danger');
                                    ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-bold d-block"><?= date("d M, Y", strtotime($p['payment_date'])) ?></span>
                                                <code class="text-muted small"><?= $p['transaction_id'] ?></code>
                                            </td>
                                            <td class="text-uppercase fw-semibold"><?= $p['payment_method'] ?></td>
                                            <td class="fw-bold text-dark">₦<?= number_format($p['amount_paid'], 2) ?></td>
                                            <td class="text-end pe-4">
                                                <span class="badge <?= $st_color ?> rounded-pill px-3"><?= ucfirst($p['status']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted small">No payment records found for this period.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (isset($_GET['student_id'])): ?>
        <div class="text-center py-5">
            <i class="ti ti-mood-empty fs-9 text-muted d-block mb-3"></i>
            <h5 class="fw-bold text-muted">No records found for the selected criteria.</h5>
            <p class="small text-muted">Ensure the student is enrolled in the selected academic session.</p>
        </div>
    <?php endif; ?>
</div>


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: "bootstrap-5" });
});
</script>

<?php include 'includes/footer.php'; ?>