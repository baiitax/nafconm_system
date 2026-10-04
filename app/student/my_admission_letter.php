<?php
$page_title = 'My Admission Letter';
require_once 'includes/header.php'; // Handles session, DB, and student auth

// Fetch the student ID from the session (populated by header.php)
$student_id = $_SESSION['student_id'] ?? 0;

if ($student_id === 0) {
    echo "<div class='container-fluid py-4'><div class='alert alert-danger'>Error: Could not retrieve student profile. Please log in again.</div></div>";
    require_once 'includes/footer.php';
    exit;
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="container-fluid py-4">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bolder text-dark mb-0">Official Admission Letter</h5>
                <p class="text-muted small mb-0">View or download your provisional admission offer.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4 fw-bold" onclick="printIframe()">
                <i class="ti ti-printer me-2"></i> Print Letter
            </button>
        </div>
        <div class="card-body p-0">
            <iframe id="letterFrame" style="width: 100%; height: 80vh; border: none;" 
                    src="../admission_letter.php?id=<?php echo $student_id; ?>" 
                    title="Admission Letter"></iframe>
        </div>
    </div>
</div>

<script>
    function printIframe() {
        var iframe = document.getElementById('letterFrame');
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
</script>

<?php
require_once 'includes/footer.php';
?>