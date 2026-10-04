<?php
session_start();
require_once '../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

// Fetch all announcements, joining with the users table to get the poster's name
$announcements = [];
$sql = "SELECT an.title, an.content, an.posted_at, u.username as poster_name 
        FROM announcements an 
        JOIN users u ON an.posted_at = u.id 
        ORDER BY an.posted_at DESC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Announcements</h5>
    
    <?php if (!empty($announcements)) : ?>
        <?php foreach ($announcements as $announcement) : ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    <p class="card-text"><small class="text-muted">Posted by <?php echo htmlspecialchars($announcement['poster_name']); ?> on <?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($announcement['posted_at']))); ?></small></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="alert alert-info" role="alert">There are no announcements at this time.</div>
    <?php endif; ?>
  </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>