<?php
session_start();
require_once '../config/db.php';

// Auth Check (Assuming 'superadmin' or 'staff' can manage news)
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// Handle Delete Logic
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM news WHERE id = $id");
    header("Location: news_list.php?msg=Deleted");
    exit;
}

$news_items = $conn->query("SELECT * FROM news ORDER BY created_at DESC");
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-semibold">News Management</h4>
            <a href="news_editor.php" class="btn btn-primary"><i class="ti ti-plus"></i> Add New Article</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $news_items->fetch_assoc()): ?>
                            <tr>
                                <td><img src="../<?= $row['featured_image'] ?>" width="60" class="rounded"></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['title']) ?></div>
                                    <small class="text-muted">Slug: <?= $row['slug'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <?php if($row['is_featured']): ?><span class="badge bg-primary me-1">Hero</span><?php endif; ?>
                                    <?php if($row['is_trending']): ?><span class="badge bg-info me-1">Trending</span><?php endif; ?>
                                    <?php if($row['is_top_story']): ?><span class="badge bg-success">Top</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="news_editor.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="ti ti-edit"></i></a>
                                    <a href="news_list.php?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this article?')"><i class="ti ti-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>