<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'teacher') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$create_err = $create_success = "";

// Handle Announcement Creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        $create_err = "Please fill in both title and content.";
    } else {
        $sql_insert = "INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)";
        if ($stmt_insert = $conn->prepare($sql_insert)) {
            $stmt_insert->bind_param("ssi", $title, $content, $user_id);
            if ($stmt_insert->execute()) {
                $create_success = "Announcement posted successfully.";
            } else {
                $create_err = "Oops! Something went wrong. Please try again later.";
            }
            $stmt_insert->close();
        }
    }
}

// Fetch all announcements, joining with the users table to get the poster's name
$announcements = [];
$sql_announcements = "SELECT an.title, an.content, an.posted_at, u.username as poster_name 
                      FROM announcements an 
                      JOIN users u ON an.posted_by = u.id 
                      ORDER BY an.posted_at DESC";

if ($result = $conn->query($sql_announcements)) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="row">
        <div class="col-lg-5 d-flex align-items-stretch">
            <div class="card w-100">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-4">Create New Announcement</h5>
                    <?php if ($create_success): ?><div class="alert alert-success"><?php echo $create_success; ?></div><?php endif; ?>
                    <?php if ($create_err): ?><div class="alert alert-danger"><?php echo $create_err; ?></div><?php endif; ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" class="form-control" id="title" name="title" required></div>
                        <div class="mb-3"><label for="content" class="form-label">Content</label><textarea class="form-control" id="content" name="content" rows="10" required></textarea></div>
                        <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7 d-flex align-items-stretch">
            <div class="card w-100"><div class="card-body"><h5 class="card-title fw-semibold mb-4">Posted Announcements</h5><div class="list-group">
                <?php if (!empty($announcements)): foreach ($announcements as $announcement): ?>
                    <div class="list-group-item list-group-item-action flex-column align-items-start mb-2">
                        <div class="d-flex w-100 justify-content-between"><h5 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h5><small><?php echo htmlspecialchars(date("M d, Y", strtotime($announcement['posted_at']))); ?></small></div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p><small class="text-muted">Posted by <?php echo htmlspecialchars($announcement['poster_name']); ?></small>
                    </div>
                <?php endforeach; else: ?><div class="alert alert-info">No announcements have been posted yet.</div><?php endif; ?>
            </div></div></div>
        </div>
    </div>
  </div>
</div>

<?php
include 'includes/footer.php';
?>