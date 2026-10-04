<?php
$page_title = 'My Admission Letter';
require_once 'includes/header.php'; // Handles session, DB, and student auth

// The student's primary ID is set in the session by the header include
$student_id = $_SESSION['student_id'] ?? 0;

if ($student_id === 0) {
    // This case should ideally be handled by header.php, but as a fallback:
    echo "<div class='container-fluid'><div class='alert alert-danger'>Error: Could not retrieve student profile. Please log in again.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <h5 class="card-title fw-semibold mb-4">My Admission Letter</h5>
            <p>Your official admission letter is displayed below. You can use your browser's print function (Ctrl+P or Cmd+P) to print or save it as a PDF.</p>
            <iframe style="width: 100%; height: 85vh; border: 1px solid #dee2e6;" src="admission_letter.php?id=<?php echo $student_id; ?>" title="Admission Letter"></iframe>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>