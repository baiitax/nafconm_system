<?php
require_once '../config/db_connection.php';
require_once 'includes/auth_check.php';

// Guard page against unauthorized users
auth_guard(['superadmin', 'staff']);

$application_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$application_id) {
    header('Location: admin_admissions.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submission for status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $remarks = trim($_POST['review_remarks'] ?? '');
    $reviewer_id = $_SESSION['user_id'];

    $allowed_statuses = ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted'];
    if (in_array($status, $allowed_statuses, true)) {
        try {
            $sql = "UPDATE admission_applications 
                    SET status = ?, review_remarks = ?, reviewed_by_user_id = ?, review_date = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $remarks, $reviewer_id, $application_id]);
            
            $_SESSION['flash_message'] = "Application status updated successfully.";
            $_SESSION['flash_message_type'] = 'success';

            // Redirect to prevent form resubmission
            header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $application_id);
            exit();

        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = "Invalid status selected.";
        $message_type = 'danger';
    }
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_message_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}

// Fetch application details
try {
    $stmt = $pdo->prepare("SELECT aa.*, u.username as reviewer_name FROM admission_applications aa LEFT JOIN users u ON aa.reviewed_by_user_id = u.id WHERE aa.id = ?");
    $stmt->execute([$application_id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        header('Location: admin_admissions.php');
        exit();
    }

    // Fetch associated documents
    $doc_stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
    $doc_stmt->execute([$application_id]);
    $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: Could not fetch application details. " . $e->getMessage());
}

$pageTitle = "Review Application: " . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']);
include 'includes/header.php';

$olevel_results = json_decode($app['olevel_results'], true);
$allowed_statuses = ['pending', 'under_review', 'accepted', 'rejected', 'waitlisted'];
?>

<?php if ($message): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Applicant Details -->
    <div class="col-lg-8">
        <!-- Bio Data -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Applicant Information</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><strong>Full Name:</strong></div>
                    <div class="col-md-8"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['middle_name'] . ' ' . $app['last_name']); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4"><strong>Email:</strong></div>
                    <div class="col-md-8"><?php echo htmlspecialchars($app['email']); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4"><strong>Phone:</strong></div>
                    <div class="col-md-8"><?php echo htmlspecialchars($app['phone']); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4"><strong>Date of Birth:</strong></div>
                    <div class="col-md-8"><?php echo date('F j, Y', strtotime($app['date_of_birth'])); ?></div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-4"><strong>Address:</strong></div>
                    <div class="col-md-8"><?php echo nl2br(htmlspecialchars($app['address'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Academic Info -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Academic Information</h5></div>
            <div class="card-body">
                <p><strong>Program of Interest:</strong> <?php echo htmlspecialchars($app['program_of_interest']); ?></p>
                <p><strong>Previous School:</strong> <?php echo htmlspecialchars($app['previous_school']); ?></p>
                <h6>O'Level Results:</h6>
                <?php if (is_array($olevel_results) && !empty($olevel_results)): ?>
                    <ul class="list-group">
                        <?php foreach ($olevel_results as $result): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($result['subject']); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($result['grade']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No O'Level results provided.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parent Info -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Parent/Guardian Information</h5></div>
            <div class="card-body">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($app['parent_name']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($app['parent_phone']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($app['parent_email'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Documents -->
    <div class="col-lg-4">
        <!-- Review Status -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Review & Update Status</h5></div>
            <div class="card-body">
                <form action="view_application.php?id=<?php echo $application_id; ?>" method="POST">
                    <div class="mb-3">
                        <label for="status" class="form-label"><strong>Application Status</strong></label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($allowed_statuses as $status_option): ?>
                                <option value="<?php echo $status_option; ?>" <?php if ($app['status'] == $status_option) echo 'selected'; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $status_option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review_remarks" class="form-label"><strong>Review Remarks</strong></label>
                        <textarea class="form-control" id="review_remarks" name="review_remarks" rows="4"><?php echo htmlspecialchars($app['review_remarks'] ?? ''); ?></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Update Status</button>
                    </div>
                </form>
                <hr>
                <p class="small text-muted mb-1">
                    Last reviewed by: <strong><?php echo htmlspecialchars($app['reviewer_name'] ?? 'N/A'); ?></strong>
                </p>
                <p class="small text-muted mb-0">
                    Review date: <strong><?php echo $app['review_date'] ? date('M j, Y, g:i a', strtotime($app['review_date'])) : 'N/A'; ?></strong>
                </p>
            </div>
        </div>

        <!-- Documents -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Uploaded Documents</h5></div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <p class="text-muted">No documents were uploaded.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($documents as $doc): ?>
                            <li class="list-group-item">
                                <a href="/nafconm/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank">
                                    <i class="bi bi-file-earmark-arrow-down"></i> 
                                    <?php echo ucwords(str_replace('_', ' ', $doc['document_type'])); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<a href="admin_admissions.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to All Applications</a>

<?php include 'includes/footer.php'; ?>