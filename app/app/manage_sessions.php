<?php
/**
 * NAFCONM - Global Session Control
 * Features: Master Toggle Switch, Transactional Syncing
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$message = $error = "";

// --- 1. HANDLE ADD NEW SESSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_session'])) {
    $name = htmlspecialchars(trim($_POST['session_name']));
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO sessions (name, start_date, end_date, is_active) VALUES (?, ?, ?, 0)");
    $stmt->bind_param("sss", $name, $start_date, $end_date);
    if ($stmt->execute()) {
        $message = "success|New academic session '$name' created successfully.";
    } else {
        $error = "Failed to create session. It may already exist.";
    }
    $stmt->close();
}

// --- 2. HANDLE SET ACTIVE SESSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_active'])) {
    $session_id = intval($_POST['session_id']);
    
    // Use a transaction to ensure all switches happen flawlessly
    $conn->begin_transaction();
    try {
        // A. Deactivate all sessions
        $conn->query("UPDATE sessions SET is_active = 0");
        
        // B. Activate the selected one
        $stmt_act = $conn->prepare("UPDATE sessions SET is_active = 1 WHERE id = ?");
        $stmt_act->bind_param("i", $session_id);
        $stmt_act->execute();
        $stmt_act->close();
        
        // C. Sync with the global 'settings' table so legacy pages don't break
        $stmt_sync = $conn->prepare("UPDATE settings SET setting_value = (SELECT name FROM sessions WHERE id = ?) WHERE setting_key = 'academic_session'");
        $stmt_sync->bind_param("i", $session_id);
        $stmt_sync->execute();
        $stmt_sync->close();
        
        $conn->commit();
        $message = "success|Global Academic Session updated. The entire portal has been rolled over.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Critical error shifting session: " . $e->getMessage();
    }
}

// --- 3. FETCH SESSIONS ---
$sessions = $conn->query("SELECT * FROM sessions ORDER BY start_date DESC");

$page_title = "Session Control";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Session Management</h3>
            <p class="text-muted small mb-0">Control the active academic year for the entire institution.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold mt-3 mt-md-0" data-bs-toggle="modal" data-bs-target="#sessionModal">
            <i class="ti ti-plus me-2"></i> Create New Session
        </button>
    </div>

    <?php if ($message): list($color, $txt) = explode('|', $message); ?>
        <div class="alert alert-<?= $color ?> border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-check me-2"></i><?= $txt ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-4 px-4 py-3 shadow-sm mb-4"><i class="ti ti-alert-triangle me-2"></i><?= $error ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 fw-bold">Academic Session</th>
                            <th class="py-3 fw-bold">Duration</th>
                            <th class="py-3 fw-bold text-center">System Status</th>
                            <th class="text-end pe-4 py-3 fw-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sessions->num_rows > 0): while ($s = $sessions->fetch_assoc()): ?>
                            <tr class="<?= $s['is_active'] ? 'bg-primary-subtle' : '' ?>">
                                <td class="ps-4 py-3">
                                    <h6 class="fw-black text-dark mb-0 fs-5"><?= htmlspecialchars($s['name']) ?></h6>
                                </td>
                                <td>
                                    <span class="text-muted fw-medium"><i class="ti ti-calendar-event me-1"></i><?= date('M Y', strtotime($s['start_date'])) ?> — <?= date('M Y', strtotime($s['end_date'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['is_active']): ?>
                                        <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="ti ti-power me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-3 py-2 rounded-pill"><i class="ti ti-power-off me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (!$s['is_active']): ?>
                                        <form method="POST" onsubmit="return confirm('WARNING: Activating this session will instantly roll over the entire college portal. Ensure fees are configured for this session first. Proceed?');">
                                            <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                            <button type="submit" name="set_active" class="btn btn-sm btn-dark rounded-pill fw-bold px-3">Activate Session</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success rounded-pill fw-bold px-3 disabled">Currently Active</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted">No academic sessions configured.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white p-3">
                <h5 class="modal-title fw-bold">Create Academic Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Session Name</label>
                        <input type="text" name="session_name" class="form-control bg-light border-0" placeholder="e.g. 2026/2027 Session" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">Start Date</label>
                            <input type="date" name="start_date" class="form-control bg-light border-0" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase">End Date</label>
                            <input type="date" name="end_date" class="form-control bg-light border-0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="submit" name="add_session" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">Save Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>.fw-black{font-weight:900 !important;}</style>
<?php include 'includes/footer.php'; ?>