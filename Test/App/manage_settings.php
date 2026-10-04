<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Settings Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_settings'])) {
    
    $conn->begin_transaction();
    try {
        // Prepare statement for updating settings
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $conn->prepare($sql);

        // Portal Name
        $portal_name = trim($_POST['portal_name']);
        $stmt->bind_param("ss", $key, $value);
        $key = 'portal_name';
        $value = $portal_name;
        $stmt->execute();

        // Registration Open
        $registration_open = isset($_POST['registration_open']) ? '1' : '0';
        $key = 'registration_open';
        $value = $registration_open;
        $stmt->execute();

        $stmt->close();
        $conn->commit();
        $action_success = "Settings updated successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "Error updating settings. Please try again.";
    }
}


// Fetch all settings for display
$settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM settings";
$result = $conn->query($sql_settings);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Portal Settings</h5>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3"><label for="portalName" class="form-label">Portal Name</label><input type="text" class="form-control" id="portalName" name="portal_name" value="<?php echo htmlspecialchars($settings['portal_name'] ?? 'N-Portal'); ?>" required><div class="form-text">This name will appear in the browser tab and on login pages.</div></div>
                <div class="mb-4"><label class="form-label">Student Registration</label><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="registrationOpen" name="registration_open" value="1" <?php echo (isset($settings['registration_open']) && $settings['registration_open'] == 1) ? 'checked' : ''; ?>><label class="form-check-label" for="registrationOpen">Allow new students to register an account.</label></div></div>
                <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>