<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// 1. Auth Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action_err = "";
$data = [
    'title' => '', 
    'content' => '', 
    'audience' => 'public', 
    'is_pinned' => 0
];

// 2. Fetch existing data if Editing
if ($id > 0) {
    $stmt = $conn->prepare("SELECT title, content, audience, is_pinned FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $data = $row;
    }
    $stmt->close();
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $audience = $_POST['audience'];
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    // CRITICAL: Get user_id from session (The "Parent" record)
    // If your login session uses $_SESSION['id'], use that instead of 'user_id'
    $creator_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

    if (empty($title) || empty($content)) {
        $action_err = "Please fill in all required fields.";
    } elseif (!$creator_id) {
        $action_err = "Fatal Error: Admin session lost. Please log in again.";
    } else {
        try {
            if ($id > 0) {
                // UPDATE - No need to change user_id on edit
                $sql = "UPDATE announcements SET title = ?, content = ?, audience = ?, is_pinned = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $title, $content, $audience, $is_pinned, $id);
            } else {
                // INSERT - Using 'user_id' to match your foreign key constraint
                $sql = "INSERT INTO announcements (title, content, audience, is_pinned, user_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssii", $title, $content, $audience, $is_pinned, $creator_id);
            }

            if ($stmt->execute()) {
                header("Location: announcements_list.php?msg=saved");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $action_err = "Database Error: " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-semibold"><?= $id > 0 ? 'Edit' : 'New' ?> Announcement</h4>
            <a href="announcements_list.php" class="btn btn-outline-secondary">Back</a>
        </div>

        <?php if($action_err): ?>
            <div class="alert alert-danger"><?= $action_err ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="" method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Title</label>
                                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($data['title']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Message Content</label>
                                <textarea name="content" class="form-control" rows="10" required><?= htmlspecialchars($data['content']) ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold mb-3">Publication Info</h6>
                                <div class="mb-3">
                                    <label class="form-label">Audience</label>
                                    <select name="audience" class="form-select">
                                        <option value="public" <?= $data['audience'] == 'public' ? 'selected' : '' ?>>Public Website</option>
                                        <option value="internal" <?= $data['audience'] == 'internal' ? 'selected' : '' ?>>Student Portal</option>
                                    </select>
                                </div>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" name="is_pinned" id="pin" <?= $data['is_pinned'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pin">Pin this notice</label>
                                </div>
                                <button type="submit" name="save_announcement" class="btn btn-primary w-100">Publish Now</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>