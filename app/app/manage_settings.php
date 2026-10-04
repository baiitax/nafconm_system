<?php
session_start();
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// --- Handle Settings Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    
    $conn->begin_transaction();
    try {
        $sql = "INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $conn->prepare($sql);

        $academic_session = $_POST['academic_session'];

        $settings_to_save = [
            'portal_name' => $_POST['portal_name'],
            'registration_open' => isset($_POST['registration_open']) ? '1' : '0',
            'admission_portal_active' => isset($_POST['admission_portal_active']) ? '1' : '0',
            'academic_session' => $academic_session,
            'contact_email' => $_POST['contact_email']
        ];

        foreach ($settings_to_save as $key => $value) {
            $val = trim($value);
            $stmt->bind_param("ss", $key, $val);
            $stmt->execute();
        }

        // --- IMPORTANT: Sync with sessions table ---
        // Deactivate all first, then activate the selected one
        $conn->query("UPDATE sessions SET is_active = 0");
        $stmt_session = $conn->prepare("UPDATE sessions SET is_active = 1 WHERE name = ?");
        $stmt_session->bind_param("s", $academic_session);
        $stmt_session->execute();

        $stmt->close();
        $conn->commit();
        $action_success = "Portal configurations and academic session successfully updated.";

    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "System Error: Unable to save changes. " . $e->getMessage();
    }
}

// Fetch all settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="card bg-light-info shadow-none position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h4 class="fw-bolder mb-0">System Configuration</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Settings</li>
                        </ol>
                    </nav>
                </div>
                <i class="ti ti-settings fs-9 text-info opacity-50"></i>
            </div>
        </div>
    </div>

    <?php if ($action_success): ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center" role="alert">
            <i class="ti ti-circle-check me-2 fs-5"></i> <?php echo $action_success; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="card shadow-none border">
                    <div class="card-header bg-transparent border-bottom">
                        <ul class="nav nav-tabs card-header-tabs border-bottom-0" id="settingsTab" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active fw-bold" data-bs-toggle="tab" href="#general" role="tab">General & Identity</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fw-bold" data-bs-toggle="tab" href="#admissions" role="tab">Access Controls</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-4">
                        <div class="tab-content">
                            
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Portal Display Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ti ti-world"></i></span>
                                        <input type="text" class="form-control" name="portal_name" value="<?php echo htmlspecialchars($settings['portal_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Global Academic Session</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ti ti-calendar-event"></i></span>
                                        <select class="form-select" name="academic_session">
                                            <?php
                                            $sessions = $conn->query("SELECT name FROM sessions ORDER BY id DESC");
                                            while($s = $sessions->fetch_assoc()) {
                                                $selected = ($settings['academic_session'] == $s['name']) ? 'selected' : '';
                                                echo "<option value='{$s['name']}' $selected>{$s['name']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <small class="text-info mt-1 d-block"><i class="ti ti-info-circle"></i> Selecting a session here updates results and fee structures portal-wide.</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label fw-bold">Administrator Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="ti ti-mail"></i></span>
                                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="admissions" role="tabpanel">
                                <div class="card bg-light-warning border-0 mb-3">
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="admPortal" name="admission_portal_active" <?php echo ($settings['admission_portal_active'] == '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold text-dark" for="admPortal">Enable Public Admissions</label>
                                        </div>
                                        <p class="small text-muted mb-0">Toggles visibility of the external application form for new applicants.</p>
                                    </div>
                                </div>

                                <div class="card bg-light-info border-0 mb-0">
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" role="switch" id="regOpen" name="registration_open" <?php echo ($settings['registration_open'] == '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold text-dark" for="regOpen">Allow Course Registration</label>
                                        </div>
                                        <p class="small text-muted mb-0">Controls whether enrolled students can select courses for the active session.</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="card-footer bg-white border-top p-4">
                        <button type="submit" name="save_settings" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                            <i class="ti ti-device-floppy me-1"></i> Synchronize Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-primary text-white mb-4">
                <div class="card-body">
                    <h5 class="fw-bold text-white"><i class="ti ti-help"></i> Need Help?</h5>
                    <p class="fs-2 opacity-75">Settings updated here take effect immediately for all users. Ensure the Academic Session matches your current school calendar to avoid result processing errors.</p>
                </div>
            </div>
            <div class="card border shadow-none">
                <div class="card-body p-3">
                    <h6 class="fw-bold mb-3">Environment Info</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fs-2">PHP Version</span>
                        <span class="fw-bold fs-2"><?php echo phpversion(); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fs-2">Active Session</span>
                        <span class="badge bg-success-subtle text-success"><?php echo $settings['academic_session'] ?? 'None'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted fs-2">Server Time</span>
                        <span class="fw-bold fs-2"><?php echo date("H:i T"); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>