<?php
/**
 * NAFCONM - Fee Structure Manager
 * Features: UPSERT Database Matrix, Program/Level Financial Mapping, Flexible Installments
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$message = $error = "";

// Fetch Active Session Name globally
$act_sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$active_session = $act_sess_query->num_rows > 0 ? $act_sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// --- 1. HANDLE UPSERT FEE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_fee'])) {
    $program_id = intval($_POST['program_id']);
    $level = htmlspecialchars(trim($_POST['level']));
    $payment_name = htmlspecialchars(trim($_POST['payment_name']));
    $amount = floatval($_POST['amount']);
    
    // Installment setup
    $allow_installment = isset($_POST['allow_installment']) ? 1 : 0;
    $min_first_installment = $allow_installment ? floatval($_POST['min_first_installment']) : 0.00;
    
    // Check if configuration already exists for this Program + Level + Session + Payment Name
    $stmt_check = $conn->prepare("SELECT id FROM fee_structures WHERE program_id = ? AND level = ? AND session = ? AND payment_name = ?");
    $stmt_check->bind_param("isss", $program_id, $level, $active_session, $payment_name);
    $stmt_check->execute();
    $res = $stmt_check->get_result();
    
    if ($res->num_rows > 0) {
        // UPDATE existing fee
        $fee_id = $res->fetch_assoc()['id'];
        $stmt_upd = $conn->prepare("UPDATE fee_structures SET amount = ?, allow_installment = ?, min_first_installment = ? WHERE id = ?");
        $stmt_upd->bind_param("didi", $amount, $allow_installment, $min_first_installment, $fee_id);
        if ($stmt_upd->execute()) $message = "success|Fee structure updated successfully.";
        $stmt_upd->close();
    } else {
        // INSERT new fee
        $stmt_ins = $conn->prepare("INSERT INTO fee_structures (program_id, level, session, payment_name, amount, allow_installment, min_first_installment) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("isssdid", $program_id, $level, $active_session, $payment_name, $amount, $allow_installment, $min_first_installment);
        if ($stmt_ins->execute()) $message = "success|New fee structure mapped successfully.";
        $stmt_ins->close();
    }
    $stmt_check->close();
}

// --- 2. HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM fee_structures WHERE id = $del_id");
    $message = "success|Fee configuration deleted.";
}

// --- 3. FETCH DATA ---
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");

$sql = "
    SELECT f.*, p.name as program_name 
    FROM fee_structures f 
    JOIN programs p ON f.program_id = p.id 
    WHERE f.session = '$active_session'
    ORDER BY p.name ASC, f.level ASC, f.payment_name ASC
";
$fees = $conn->query($sql);

$page_title = "Fee Matrix";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Financial Setup Matrix</h3>
            <p class="text-muted small mb-0">Configure school fees and installments strictly for the active session.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center">
            <span class="badge bg-primary-subtle text-primary border px-3 py-2 fw-bold fs-6 rounded-pill"><?= $active_session ?></span>
            <button class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#feeModal">
                <i class="ti ti-plus me-1"></i> Add / Edit Matrix
            </button>
        </div>
    </div>

    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check me-2"></i><?= $txt ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Academic Program & Type</th>
                            <th class="py-3 fw-bold text-center">Level</th>
                            <th class="py-3 fw-bold text-center">Total Billed (NGN)</th>
                            <th class="py-3 fw-bold text-center">Installment Policy</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($fees->num_rows > 0): while ($f = $fees->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <h6 class="fw-bold text-dark mb-0 fs-6"><?= htmlspecialchars($f['program_name']) ?></h6>
                                    <span class="badge bg-secondary-subtle text-secondary border mt-1"><?= htmlspecialchars($f['payment_name']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3 py-1 font-monospace fs-6"><?= htmlspecialchars($f['level']) ?></span>
                                </td>
                                <td class="text-center">
                                    <h5 class="fw-black text-success mb-0">₦<?= number_format($f['amount'], 2) ?></h5>
                                </td>
                                <td class="text-center">
                                    <?php if($f['allow_installment']): ?>
                                        <div class="text-primary fw-bold small">Allowed</div>
                                        <div class="text-muted small">Min 1st: ₦<?= number_format($f['min_first_installment'], 2) ?></div>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border">Full Payment Only</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="?delete_id=<?= $f['id'] ?>" onclick="return confirm('Delete this fee configuration? Students will not be able to pay until it is replaced.')" class="btn btn-sm btn-light text-danger rounded-circle p-2">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No fee matrix defined for the current session.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Fee Modal -->
<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-3">
                <h5 class="modal-title fw-bold">Configure Fee Structure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 bg-light">
                    <div class="alert bg-warning-subtle text-dark border-0 small fw-bold mb-4">
                        <i class="ti ti-info-circle me-1"></i> If a fee already exists for the selected Program, Level, and Payment Name, this will overwrite the existing setup.
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Select Program</label>
                            <select name="program_id" class="form-select bg-white border-0" required>
                                <option value="">-- Choose Program --</option>
                                <?php $programs->data_seek(0); while($p = $programs->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Payment Name</label>
                            <select name="payment_name" class="form-select bg-white border-0" required>
                                <option value="Tuition Fee">Tuition Fee</option>
                                <option value="Acceptance Fee">Acceptance Fee</option>
                                <option value="Hostel Accommodation">Hostel Accommodation</option>
                                <option value="Departmental Dues">Departmental Dues</option>
                                <option value="IT/Practicum Fee">IT/Practicum Fee</option>
                                <option value="Graduation Fee">Graduation Fee</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Academic Level</label>
                            <select name="level" class="form-select bg-white border-0" required>
                                <option value="100L">100L</option>
                                <option value="200L">200L</option>
                                <option value="300L">300L</option>
                                <option value="400L">400L</option>
                                <option value="500L">500L</option>
                                <option value="ND 1">ND 1</option>
                                <option value="ND 2">ND 2</option>
                                <option value="HND 1">HND 1</option>
                                <option value="HND 2">HND 2</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Total Billed Amount (NGN)</label>
                            <input type="number" step="0.01" name="amount" id="total_amount" class="form-control bg-white border-0 fw-bold" placeholder="e.g. 150000" required>
                        </div>
                    </div>

                    <!-- Flexible Installment Block -->
                    <div class="card border-0 bg-white rounded-3 p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="allowInstallmentToggle" name="allow_installment" value="1">
                            <label class="form-check-label fw-bold text-dark" for="allowInstallmentToggle">Enable Flexible Installments</label>
                        </div>
                        <p class="text-muted small mb-3">Turn this on to allow students to pay this specific fee in parts.</p>
                        
                        <div id="installmentConfigPanel" style="display: none;">
                            <label class="form-label fw-bold text-muted small text-uppercase">Minimum First Installment (NGN)</label>
                            <input type="number" step="0.01" name="min_first_installment" id="min_first_installment" class="form-control bg-light border-0" placeholder="e.g. 80000">
                            <small class="text-primary mt-1 d-block"><i class="ti ti-info-circle"></i> The student must pay at least this amount to activate their registration/portal.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top p-3">
                    <button type="submit" name="save_fee" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">Save Fee Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>.fw-black{font-weight:900 !important;}</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('allowInstallmentToggle');
    const panel = document.getElementById('installmentConfigPanel');
    const minInput = document.getElementById('min_first_installment');
    const totalInput = document.getElementById('total_amount');

    toggle.addEventListener('change', function() {
        if(this.checked) {
            panel.style.display = 'block';
            minInput.setAttribute('required', 'required');
            // Suggest 50% as default minimum
            if(totalInput.value && !minInput.value) {
                minInput.value = (parseFloat(totalInput.value) / 2).toFixed(2);
            }
        } else {
            panel.style.display = 'none';
            minInput.removeAttribute('required');
            minInput.value = '';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
