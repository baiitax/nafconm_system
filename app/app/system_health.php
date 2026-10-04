<?php
$page_title = 'System Management';
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";

// Handle Clear Activity Logs
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_logs'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $action_err = "Request validation failed. Please try again.";
    } else {
        log_activity($conn, $_SESSION['user_id'], "Cleared the activity logs.");
        $sql_truncate = "TRUNCATE TABLE activity_logs";
        if ($conn->query($sql_truncate) === TRUE) {
            $action_success = "Activity logs have been cleared successfully.";
        } else {
            $action_err = "Error clearing activity logs: " . $conn->error;
        }
    }
}

// Handle Database Backup
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['backup_db'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // This will be displayed on the reloaded page, which is not ideal for a download action.
        // A better approach would be a dedicated script, but for this request, we'll proceed.
        die("CSRF token validation failed.");
    } else {
        mkdir('/../backups');
        $backup_dir = realpath(__DIR__ . '/../backups');
        if ($backup_dir === false) {
            // Attempt to create it if it doesn't exist
            $backup_dir = realpath(__DIR__ . '/..') . '/backups';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
        }

        $backup_file = $backup_dir . '/n_portal_backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Construct the mysqldump command carefully
        $password_arg = DB_PASSWORD ? sprintf("--password=%s", escapeshellarg(DB_PASSWORD)) : '';
        $command = sprintf(
            'mysqldump --user=%s %s --host=%s %s > %s',
            escapeshellarg(DB_USERNAME),
            $password_arg,
            escapeshellarg(DB_SERVER),
            escapeshellarg(DB_NAME),
            escapeshellarg($backup_file)
        );

        exec($command, $output, $return_var);

        if ($return_var === 0 && file_exists($backup_file)) {
            log_activity($conn, $_SESSION['user_id'], "Created and downloaded a database backup.");
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_file));
            readfile($backup_file);
            unlink($backup_file); // Clean up
            exit;
        } else {
            $action_err = "Error creating database backup. Please ensure 'mysqldump' is accessible via your system's PATH and the 'backups' directory is writable.";
        }
    }
}

require_once 'includes/header.php';

// --- Gather System Information ---

// Server Information
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$server_os = php_uname('s') . ' ' . php_uname('r');

// PHP Information
$php_version = phpversion();
$memory_limit = ini_get('memory_limit');
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
$max_execution_time = ini_get('max_execution_time');

// Database Information
$db_version = $conn->server_info;
$db_name = DB_NAME;
$db_size = 0;
$table_details = [];

// Get total DB size
$sql_db_size = "SELECT SUM(data_length + index_length) / 1024 / 1024 AS db_size_mb FROM information_schema.TABLES WHERE table_schema = ?";
if ($stmt = $conn->prepare($sql_db_size)) {
    $stmt->bind_param("s", $db_name);
    $stmt->execute();
    $stmt->bind_result($size);
    if ($stmt->fetch()) {
        $db_size = $size;
    }
    $stmt->close();
}

// Get individual table sizes
$sql_table_sizes = "SELECT table_name, table_rows, ROUND(((data_length + index_length) / 1024), 2) AS size_kb FROM information_schema.TABLES WHERE table_schema = ? ORDER BY size_kb DESC";
if ($stmt = $conn->prepare($sql_table_sizes)) {
    $stmt->bind_param("s", $db_name);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $table_details[] = $row;
    }
    $stmt->close();
}

$conn->close();

require_once 'includes/sidebar.php';
include 'includes/topbar.php';

?>


<!--  Main wrapper -->
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">System Management</h5>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    

<div class="row">
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Server Environment</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Server Software <span><?php echo htmlspecialchars($server_software); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Operating System <span><?php echo htmlspecialchars($server_os); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">PHP Version <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($php_version); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Memory Limit <span><?php echo htmlspecialchars($memory_limit); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Max Upload Size <span><?php echo htmlspecialchars($upload_max_filesize); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Max POST Size <span><?php echo htmlspecialchars($post_max_size); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Max Execution Time <span><?php echo htmlspecialchars($max_execution_time); ?>s</span></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-6 d-flex align-items-stretch">
        <div class="card w-100">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Database Information</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Database Name <span><?php echo htmlspecialchars($db_name); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Database Version <span><?php echo htmlspecialchars($db_version); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Total Size <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars(number_format($db_size, 2)); ?> MB</span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Total Tables <span class="badge bg-info rounded-pill"><?php echo count($table_details); ?></span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Database Table Details</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light"><tr><th>Table Name</th><th class="text-end">Rows (Approx.)</th><th class="text-end">Size (KB)</th></tr></thead>
                <tbody>
                    <?php foreach ($table_details as $table): ?>
                        <tr><td><?php echo htmlspecialchars($table['table_name']); ?></td><td class="text-end"><?php echo htmlspecialchars(number_format($table['table_rows'])); ?></td><td class="text-end"><?php echo htmlspecialchars(number_format($table['size_kb'], 2)); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title fw-semibold mb-4">Maintenance Actions</h5>
        <div class="alert alert-warning"><strong>Warning:</strong> Some of these actions are irreversible. Please proceed with caution.</div>
        
        <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
            <div>
                <h6 class="fw-semibold mb-0">Clear Activity Logs</h6>
                <span class="fw-normal">This will permanently delete all records from the activity logs table.</span>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to clear all activity logs? This action cannot be undone.');" class="d-print-none">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="clear_logs" class="btn btn-danger">Clear Logs</button>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-3">
            <div><h6 class="fw-semibold mb-0">Database Backup</h6><span class="fw-normal">Download a .sql file of the entire database.</span></div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-print-none">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="backup_db" class="btn btn-secondary">Backup & Download</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>