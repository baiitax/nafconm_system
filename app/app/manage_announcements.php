<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Manage Announcements';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_err = $action_success = "";
$user_id = $_SESSION['user_id'];

// Handle Delete Announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement'])) {
    $announcement_id_to_delete = $_POST['announcement_id'];
    $sql = "DELETE FROM announcements WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $announcement_id_to_delete);
        if ($stmt->execute()) {
            $action_success = "Announcement deleted successfully.";
            log_activity($conn, $user_id, "Deleted announcement ID: {$announcement_id_to_delete}");
        } else {
            $action_err = "Error deleting announcement. Please try again.";
        }
        $stmt->close();
    }
}

// Handle Create/Edit Announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $announcement_id = isset($_POST['announcement_id']) ? $_POST['announcement_id'] : null;

    if (empty($title) || empty($content)) {
        $action_err = "Please fill in both title and content.";
    } else {
        // If announcement_id is present, it's an EDIT operation
        if (!empty($announcement_id)) {
            $sql = "UPDATE announcements SET title = ?, content = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssi", $title, $content, $announcement_id);
                if ($stmt->execute()) {
                    $action_success = "Announcement updated successfully.";
                    log_activity($conn, $user_id, "Updated announcement: {$title}");
                } else {
                    $action_err = "Error updating announcement.";
                }
                $stmt->close();
            }
        } 
        // Otherwise, it's a CREATE operation
        else {
            $sql = "INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssi", $title, $content, $user_id);
                if ($stmt->execute()) {
                    $action_success = "Announcement created successfully.";
                    log_activity($conn, $user_id, "Created announcement: {$title}");
                } else {
                    $action_err = "Error creating announcement.";
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all announcements for display
$announcements = [];
$sql_announcements = "SELECT a.id, a.title, a.content, a.posted_at, u.username as poster_name 
                      FROM announcements a 
                      JOIN users u ON a.posted_at = u.id 
                      ORDER BY a.posted_at DESC";
$result = $conn->query($sql_announcements);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>
<!--  Main wrapper -->
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold"><?php echo $page_title; ?></h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal" onclick="prepareCreateModal()">Create New Announcement</button>
    </div>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="list-group">
                <?php if (!empty($announcements)): foreach ($announcements as $announcement): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                            <small>Posted on: <?php echo htmlspecialchars(date("M d, Y", strtotime($announcement['posted_at']))); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <small class="text-muted">Posted by: <?php echo htmlspecialchars($announcement['poster_name']); ?></small>
                            <div>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#announcementModal" onclick='prepareEditModal(<?php echo json_encode($announcement, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Edit</button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-announcementid="<?php echo $announcement['id']; ?>">Delete</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="alert alert-info">No announcements have been posted yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Announcement Create/Edit Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="announcementModalLabel">Create Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="announcement_id" id="announcementId">
          <div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" class="form-control" id="title" name="title" required></div>
          <div class="mb-3"><label for="content" class="form-label">Content</label><textarea class="form-control" id="content" name="content" rows="10" required></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="save_announcement" class="btn btn-primary">Save Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="announcement_id" id="deleteAnnouncementId">
          <p>Are you sure you want to delete this announcement?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_announcement" class="btn btn-danger">Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function prepareCreateModal() {
    document.getElementById('announcementModalLabel').innerText = 'Create Announcement';
    const form = document.getElementById('announcementModal').querySelector('form');
    form.reset();
    document.getElementById('announcementId').value = '';
}

function prepareEditModal(announcement) {
    document.getElementById('announcementModalLabel').innerText = 'Edit Announcement';
    const form = document.getElementById('announcementModal').querySelector('form');
    form.reset();

    document.getElementById('announcementId').value = announcement.id;
    document.getElementById('title').value = announcement.title;
    document.getElementById('content').value = announcement.content;
}

const deleteModal = document.getElementById('deleteModal');
deleteModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const announcementId = button.getAttribute('data-announcementid');
    const modalAnnouncementIdInput = deleteModal.querySelector('#deleteAnnouncementId');
    modalAnnouncementIdInput.value = announcementId;
});
</script>

<?php
$conn->close();
include 'includes/footer.php';
?>