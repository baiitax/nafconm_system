<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if a specific application is being viewed
$view_id = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_NUMBER_INT);
$application_data = null;
$documents = [];
$applications = [];

if ($view_id) {
    // Fetch single application details along with the reviewer's username if available
    $stmt = $pdo->prepare(
        "SELECT aa.*, u.username as reviewer_username 
         FROM admission_applications aa 
         LEFT JOIN users u ON aa.reviewed_by_user_id = u.id 
         WHERE aa.id = ?"
    );
    $stmt->execute([$view_id]);
    $application_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // If status is 'pending' when viewed, automatically update it to 'under_review'
    if ($application_data && $application_data['status'] === 'pending') {
        try {
            $update_stmt = $pdo->prepare(
                "UPDATE admission_applications 
                 SET status = 'under_review', reviewed_by_user_id = ?, review_date = NOW() 
                 WHERE id = ? AND status = 'pending'"
            );
            $update_stmt->execute([$_SESSION['user_id'], $view_id]);

            // Update the data in memory to reflect the change on the current page load
            $application_data['status'] = 'under_review';
            $application_data['reviewed_by_user_id'] = $_SESSION['user_id'];
            $application_data['reviewer_username'] = $_SESSION['username']; // Assumes username is in session
            $application_data['review_date'] = date("Y-m-d H:i:s"); // Set current time for display
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Could not update application status: " . $e->getMessage();
        }
    }

    if ($application_data) {
        // Fetch associated documents
        $doc_stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
        $doc_stmt->execute([$view_id]);
        $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Fetch all applications for the list view
    $stmt = $pdo->query("SELECT id, first_name, last_name, program_of_interest, application_date, status FROM admission_applications ORDER BY application_date DESC");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "Student Admissions";
require_once 'header.php'; // Assumes you have a standard admin header
?>

<div class="page-wrapper">
    <div class="container-xl">
        <!-- Page title -->
        <div class="page-header d-print-none">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title">
                        <?php echo $view_id ? 'Application Details' : 'Student Admissions'; ?>
                    </h2>
                    <?php if ($view_id): ?>
                        <div class="text-muted mt-1"><a href="admissions.php" class="text-reset">← Back to all applications</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="page-body">
        <div class="container-xl">
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
            <?php endif; ?>

            <?php if ($application_data): // Detail View ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Applicant Information</h3></div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-5">Full Name:</dt>
                                    <dd class="col-7"><?php echo htmlspecialchars(trim($application_data['first_name'] . ' ' . $application_data['middle_name'] . ' ' . $application_data['last_name'])); ?></dd>
                                    <dt class="col-5">Email:</dt>
                                    <dd class="col-7"><?php echo htmlspecialchars($application_data['email']); ?></dd>
                                    <dt class="col-5">Phone:</dt>
                                    <dd class="col-7"><?php echo htmlspecialchars($application_data['phone']); ?></dd>
                                    <dt class="col-5">Date of Birth:</dt>
                                    <dd class="col-7"><?php echo date('F j, Y', strtotime($application_data['date_of_birth'])); ?></dd>
                                    <dt class="col-5">Address:</dt>
                                    <dd class="col-7"><?php echo nl2br(htmlspecialchars($application_data['address'])); ?></dd>
                                    <dt class="col-5">Program of Interest:</dt>
                                    <dd class="col-7"><?php echo htmlspecialchars($application_data['program_of_interest']); ?></dd>
                                    <dt class="col-5">Previous School:</dt>
                                    <dd class="col-7"><?php echo htmlspecialchars($application_data['previous_school'] ?? 'N/A'); ?></dd>
                                    <dt class="col-5">Application Date:</dt>
                                    <dd class="col-7"><?php echo date('M j, Y, g:i a', strtotime($application_data['application_date'])); ?></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="card mt-4">
                            <div class="card-header"><h3 class="card-title">Uploaded Documents</h3></div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                    <a href="/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-file-text me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><line x1="9" y1="9" x2="10" y2="9" /><line x1="9" y1="13" x2="15" y2="13" /><line x1="9" y1="17" x2="15" y2="17" /></svg>
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['document_type']))); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title">Actions</h3>
                                <p class="text-muted">Review the application and take an action. This cannot be undone.</p>
                                <?php if ($application_data['status'] === 'pending' || $application_data['status'] === 'under_review'): ?>
                                <div class="d-grid gap-2">
                                    <form action="process_admission.php" method="POST" onsubmit="return confirm('Are you sure you want to APPROVE this application and create a student account?');">
                                        <input type="hidden" name="approve_application_id" value="<?php echo $application_data['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100">Approve Application</button>
                                    </form>
                                    <form action="process_rejection.php" method="POST" onsubmit="return confirm('Are you sure you want to REJECT this application?');">
                                        <input type="hidden" name="reject_application_id" value="<?php echo $application_data['id']; ?>">
                                        <div class="my-3"><label class="form-label">Rejection Remarks (Optional)</label><textarea name="remarks" class="form-control" rows="3"></textarea></div>
                                        <button type="submit" class="btn btn-danger w-100">Reject Application</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        This application has already been processed. <br>
                                        <strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($application_data['status'])); ?> <br>
                                        <?php if ($application_data['reviewed_by_user_id']): ?>
                                            <strong>Reviewed by:</strong> <?php echo htmlspecialchars($application_data['reviewer_username'] ?? 'N/A'); ?> on <?php echo date('M j, Y', strtotime($application_data['review_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($view_id): ?>
                <div class="alert alert-danger">Application with ID <?php echo htmlspecialchars($view_id); ?> not found.</div>
            <?php else: // List View ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">All Applications</h3></div>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter text-nowrap datatable">
                            <thead><tr><th>Applicant Name</th><th>Program of Interest</th><th>Application Date</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <?php if (empty($applications)): ?>
                                    <tr><td colspan="5" class="text-center">No applications found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['program_of_interest']); ?></td>
                                        <td><?php echo date('M j, Y, g:i a', strtotime($app['application_date'])); ?></td>
                                        <td>
                                            <?php $status_class = ['pending' => 'bg-yellow-lt', 'accepted' => 'bg-green-lt', 'rejected' => 'bg-red-lt', 'under_review' => 'bg-blue-lt'][$app['status']] ?? 'bg-secondary-lt'; ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $app['status']))); ?></span>
                                        </td>
                                        <td class="text-end"><a href="?view=<?php echo $app['id']; ?>" class="btn btn-sm">View Details</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>