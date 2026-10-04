<?php
/**
 * NAFCONM - Faculty Announcements Center
 * Features: CSRF Protection, Delete Capability, SweetAlert2, Premium UI
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Authorization Guard
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_loggedin = $_SESSION['loggedin'] ?? false;

if (!$is_loggedin || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

// --- 1. HANDLE ANNOUNCEMENT CREATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_announcement'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed. Please try again.";
    } else {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        if (empty($title) || empty($content)) {
            $_SESSION['msg'] = "warning|Please fill in both the title and content.";
        } else {
            $sql_insert = "INSERT INTO announcements (title, content, posted_by, posted_at) VALUES (?, ?, ?, NOW())";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("ssi", $title, $content, $user_id);
                if ($stmt_insert->execute()) {
                    $_SESSION['msg'] = "success|Announcement posted successfully to the portal.";
                } else {
                    $_SESSION['msg'] = "error|System error: Could not post announcement.";
                }
                $stmt_insert->close();
            }
        }
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 2. HANDLE ANNOUNCEMENT DELETION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['msg'] = "error|Security validation failed.";
    } else {
        $announcement_id = intval($_POST['announcement_id']);
        
        // Ensure teachers can only delete their OWN announcements
        $stmt_del = $conn->prepare("DELETE FROM announcements WHERE id = ? AND posted_by = ?");
        $stmt_del->bind_param("ii", $announcement_id, $user_id);
        
        if ($stmt_del->execute()) {
            $_SESSION['msg'] = "success|Announcement deleted successfully.";
        } else {
            $_SESSION['msg'] = "error|Failed to delete the announcement.";
        }
        $stmt_del->close();
    }
    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit;
}

// --- 3. FETCH ANNOUNCEMENTS ---
// We join with both `users` and `teachers` to display the actual Full Name of the poster
$announcements = [];
$sql_announcements = "
    SELECT an.id, an.title, an.content, an.posted_at, an.posted_by,
           COALESCE(t.full_name, u.username) as poster_name,
           t.photo_path
    FROM announcements an 
    JOIN users u ON an.posted_by = u.id 
    LEFT JOIN teachers t ON u.id = t.user_id 
    ORDER BY an.posted_at DESC
";

if ($result = $conn->query($sql_announcements)) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$page_title = "Announcements";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php'; // Included topbar which was missing
?>

<style>
    .fw-black { font-weight: 900 !important; }
    .form-floating > label { color: #64748b; font-weight: 500; }
    .form-control { border-radius: 12px; border: 1px solid #e2e8f0; }
    .form-control:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); }
    
    .announcement-card { border: 1px solid #e2e8f0; border-radius: 16px; transition: 0.3s; background: #fff; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.02); }
    .announcement-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: #cbd5e1; }
    .announcement-header { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; border-radius: 16px 16px 0 0; }
    .announcement-body { padding: 20px; color: #334155; line-height: 1.6; }
    .announcement-footer { padding: 12px 20px; border-top: 1px dashed #e2e8f0; background: #fff; border-radius: 0 0 16px 16px; }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Notice Board</h3>
            <p class="text-muted small mb-0">Broadcast updates, reminders, and official notices to students.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- CREATE ANNOUNCEMENT COLUMN -->
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 position-sticky" style="top: 90px;">
                <div class="card-header bg-primary p-4 border-0 rounded-top-4 text-white">
                    <h5 class="fw-bold mb-0 text-white"><i class="ti ti-speakerphone me-2"></i>Post Notice</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control fw-bold" id="title" name="title" placeholder="Notice Title" required>
                            <label for="title">Announcement Title</label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <textarea class="form-control" id="content" name="content" placeholder="Content" style="height: 200px; resize: none;" required></textarea>
                            <label for="content">Detailed Content</label>
                        </div>
                        
                        <button type="submit" name="create_announcement" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow-sm">
                            <i class="ti ti-send me-1"></i> Broadcast Announcement
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ANNOUNCEMENTS FEED -->
        <div class="col-xl-8 col-lg-7">
            <h5 class="fw-bold text-dark mb-3"><i class="ti ti-history me-2 text-primary"></i>Recent Broadcasts</h5>
            
            <?php if (!empty($announcements)): foreach ($announcements as $an): ?>
                <div class="announcement-card">
                    <div class="announcement-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if(!empty($an['photo_path'])): ?>
                                <img src="../uploads/admissions/<?= htmlspecialchars($an['photo_path']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border" style="width:40px; height:40px;">
                            <?php else: ?>
                                <div class="bg-primary-subtle text-primary rounded-circle fw-bold fs-6 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width:40px; height:40px;">
                                    <?= strtoupper(substr($an['poster_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($an['poster_name']) ?></h6>
                                <small class="text-muted fw-medium"><i class="ti ti-clock me-1"></i><?= date("d M, Y \a\t h:i A", strtotime($an['posted_at'])) ?></small>
                            </div>
                        </div>
                        
                        <!-- Delete Button (Only shows if current user is the poster) -->
                        <?php if ($an['posted_by'] == $user_id): ?>
                            <form method="POST" class="delete-form m-0">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="announcement_id" value="<?= $an['id'] ?>">
                                <button type="button" class="btn btn-sm btn-light text-danger rounded-circle p-2 border delete-btn" title="Delete Announcement">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="announcement-body">
                        <h5 class="fw-black text-dark mb-3"><?= htmlspecialchars($an['title']) ?></h5>
                        <p class="mb-0 text-wrap text-break"><?= nl2br(htmlspecialchars($an['content'])) ?></p>
                    </div>
                    
                    <div class="announcement-footer d-flex justify-content-between align-items-center">
                        <span class="badge bg-light text-muted border px-2 py-1"><i class="ti ti-tag me-1"></i>Official Notice</span>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="bg-primary-subtle rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="ti ti-speakerphone fs-1 text-primary"></i>
                    </div>
                    <h4 class="fw-black text-dark">Notice Board Empty</h4>
                    <p class="text-muted max-w-md mx-auto mb-0">No announcements have been broadcasted yet. Use the form to post the first update.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SweetAlert2 for Notifications & Confirmations -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Delete Confirmation
    const deleteBtns = document.querySelectorAll('.delete-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('.delete-form');
            
            Swal.fire({
                title: 'Delete Announcement?',
                text: "This notice will be permanently removed from the student portal.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create hidden action trigger
                    const hiddenAction = document.createElement('input');
                    hiddenAction.type = 'hidden';
                    hiddenAction.name = 'delete_announcement';
                    hiddenAction.value = '1';
                    form.appendChild(hiddenAction);
                    form.submit();
                }
            });
        });
    });

    // Toast Notifications from PHP Sessions
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); 
        ?>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: '<?= $type === "success" ? "success" : ($type === "error" ? "error" : "warning") ?>',
            title: '<?= addslashes($msg) ?>'
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>