<?php
require_once '../config/db_connection.php';
require_once 'includes/auth_check.php';

// Guard page against unauthorized users
auth_guard(['superadmin', 'staff']);

$pageTitle = "Manage Admission Applications";
include 'includes/header.php'; // Assumes a standard admin header

// Fetch all applications, joining with users table to get reviewer's name
try {
    $stmt = $pdo->query("
        SELECT 
            aa.id, 
            CONCAT(aa.first_name, ' ', aa.last_name) AS full_name,
            aa.email, 
            aa.program_of_interest, 
            aa.application_date, 
            aa.status,
            u.username as reviewed_by
        FROM admission_applications aa
        LEFT JOIN users u ON aa.reviewed_by_user_id = u.id
        ORDER BY aa.application_date DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a real app, log this error and show a user-friendly message
    die("Error: Could not fetch applications. " . $e->getMessage());
}

// Helper function to style status badges
function get_status_badge($status) {
    $map = [
        'pending' => 'secondary',
        'under_review' => 'info',
        'accepted' => 'success',
        'rejected' => 'danger',
        'waitlisted' => 'warning',
    ];
    $class = $map[$status] ?? 'light';
    $text = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$class}\">{$text}</span>";
}
?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">All Applications</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Applicant Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Date Applied</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No applications found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['id']); ?></td>
                                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['email']); ?></td>
                                <td><?php echo htmlspecialchars($app['program_of_interest']); ?></td>
                                <td><?php echo date('M j, Y, g:i a', strtotime($app['application_date'])); ?></td>
                                <td><?php echo get_status_badge($app['status']); ?></td>
                                <td><?php echo htmlspecialchars($app['reviewed_by'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="view_application.php?id=<?php echo $app['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye"></i> Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; // Assumes a standard admin footer ?>