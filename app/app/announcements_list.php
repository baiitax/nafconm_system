<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// Handle Delete Logic
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: announcements_list.php?msg=deleted");
    exit;
}

// FETCH LOGIC (Line 27 area)
// The JOIN ensures we get the username of the person who posted it
$sql = "SELECT a.*, u.username 
        FROM announcements a 
        LEFT JOIN users u ON a.created_by = u.id 
        ORDER BY a.is_pinned DESC, a.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    // If it still fails, show the exact error for debugging
    die("Database Query Failed: " . $conn->error);
}

$announcements = $result->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-semibold">Notice Board Management</h4>
            <a href="announcement_editor.php" class="btn btn-primary btn-sm">
                <i class="ti ti-plus"></i> Create Notice
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Priority</th>
                                <th>Title</th>
                                <th>Audience</th>
                                <th>Date Posted</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($announcements as $row): ?>
                            <tr>
                                <td>
                                    <?php if($row['is_pinned']): ?>
                                        <span class="badge bg-warning text-dark">Pinned</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $row['audience'] == 'public' ? 'bg-primary' : 'bg-info' ?>">
                                        <?= strtoupper($row['audience']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td class="text-center">
                                    <a href="announcement_editor.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light">Edit</a>
                                    <a href="announcements_list.php?delete_id=<?= $row['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Delete notice?')">Delete</a>
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

<?php include 'includes/footer.php'; ?>