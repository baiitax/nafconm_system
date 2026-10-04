<?php
/**
 * NAFCONM - Admin Session Control
 * Features: Session Lifecycle Management, Global Registration Toggle, and Active State Switching
 */
require_once '../config/db.php';
session_start();

$message = $error = '';

// --- 1. HANDLE POST ACTIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Action: Create New Session
    if (isset($_POST['create_session'])) {
        $name = mysqli_real_escape_string($conn, $_POST['session_name']);
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];

        $sql = "INSERT INTO sessions (name, start_date, end_date, is_active) VALUES (?, ?, ?, 0)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $name, $start, $end);
            if ($stmt->execute()) {
                $message = "Academic session '$name' created successfully.";
            } else {
                $error = "Error: Session name might already exist.";
            }
        }
    }

    // Action: Set Active Session (The Master Switch)
    if (isset($_POST['set_active'])) {
        $session_id = (int)$_POST['session_id'];
        
        $conn->begin_transaction();
        try {
            // First, deactivate all
            $conn->query("UPDATE sessions SET is_active = 0");
            // Second, activate selected
            $stmt = $conn->prepare("UPDATE sessions SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            
            $conn->commit();
            $message = "Active session updated. Students are now redirected to this period.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Critical Error: Failed to switch sessions.";
        }
    }

    // Action: Toggle Registration (Global Setting)
    if (isset($_POST['toggle_registration'])) {
        $status = ($_POST['current_status'] == '1') ? '0' : '1';
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'registration_open'");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $message = "Global registration status updated.";
    }
}

// --- 2. FETCH DATA FOR DISPLAY ---
$sessions = $conn->query("SELECT * FROM sessions ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);

// Get Global Registration Status
$reg_status = 0;
$res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'registration_open'");
if($res && $row = $res->fetch_assoc()) $reg_status = $row['setting_value'];

$page_title = "Session Management";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Academic Sessions</h3>
            <p class="text-muted mb-0">Control the institutional timeline and portal accessibility.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#newSessionModal">
            <i class="ti ti-plus me-1"></i> New Session
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4"><?= $message ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4 text-center">
                    <div class="bg-<?= ($reg_status == '1') ? 'success' : 'danger' ?>-subtle rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                        <i class="ti ti-power fs-1 text-<?= ($reg_status == '1') ? 'success' : 'danger' ?>"></i>
                    </div>
                    <h5 class="fw-bold">Course Registration</h5>
                    <p class="small text-muted px-3">This toggle opens or closes the course registration portal for all students globally.</p>
                    <form method="POST">
                        <input type="hidden" name="current_status" value="<?= $reg_status ?>">
                        <button type="submit" name="toggle_registration" class="btn btn-<?= ($reg_status == '1') ? 'outline-danger' : 'success' ?> rounded-pill px-4 fw-bold">
                            <?= ($reg_status == '1') ? 'Close Registration' : 'Open Registration' ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
                <div class="card-body p-4">
                    <h6 class="text-white-50 fw-bold small text-uppercase">System Note</h6>
                    <p class="small mb-0">Setting a session as <strong>Active</strong> will filter all student invoices, course lists, and grades to that specific period. Only one session can be active at a time.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Academic Session</th>
                                <th>Period</th>
                                <th class="text-center">State</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($s['name']) ?></span>
                                </td>
                                <td class="small">
                                    <?= date("M Y", strtotime($s['start_date'])) ?> — <?= date("M Y", strtotime($s['end_date'])) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['is_active']): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted rounded-pill px-3 py-2">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-end">
                                    <?php if (!$s['is_active']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                            <button type="submit" name="set_active" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                                Activate
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-success small fw-bold"><i class="ti ti-check"></i> Current</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newSessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0 rounded-4 shadow" method="POST">
            <div class="modal-header border-bottom p-4">
                <h5 class="fw-bold mb-0">Create Academic Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Session Name</label>
                    <input type="text" name="session_name" class="form-control" placeholder="e.g. 2025/2026 Academic Session" required>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top p-4">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="create_session" class="btn btn-primary rounded-pill px-4">Create Session</button>
            </div>
        </form>
    </div>
</div>

<style>
    .fw-black { font-weight: 900 !important; }
</style>

<?php include 'includes/footer.php'; ?>