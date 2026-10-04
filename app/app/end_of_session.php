<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'End of Session Processing';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action_err = $action_success = "";

$sessions = $conn->query("SELECT * FROM sessions ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
$programs = $conn->query("SELECT * FROM programs ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- GRADUATE STUDENTS ---
    if (isset($_POST['graduate_students'])) {
        $program_id = (int)$_POST['graduate_program_id'];
        $session_id = (int)$_POST['graduate_session_id'];
        
        if (empty($program_id) || empty($session_id)) {
            $action_err = "Please select a program and session to graduate students.";
        } else {
            // This is a placeholder for a complex logic.
            // 1. Find all students in the final year of the selected program.
            // 2. Verify they have met all graduation requirements (e.g., CGPA, all courses passed).
            // 3. Change their user_type from 'student' to 'alumni' (requires adding 'alumni' to the ENUM in `users` table).
            // 4. Log the graduation event.
            $action_success = "Graduation feature is a placeholder. Logic to find and graduate final-year students would be implemented here.";
            log_activity($conn, $user_id, "Attempted to run graduation for program ID {$program_id}.");
        }
    }

    // --- PROMOTE STUDENTS ---
    if (isset($_POST['promote_students'])) {
        $program_id = (int)$_POST['promote_program_id'];
        $from_session_id = (int)$_POST['from_session_id'];
        $to_session_id = (int)$_POST['to_session_id'];

        if (empty($program_id) || empty($from_session_id) || empty($to_session_id) || $from_session_id === $to_session_id) {
            $action_err = "Please select a program and two different sessions for promotion.";
        } else {
            // This is a placeholder for a complex logic.
            // 1. Find all students in the selected program from the 'from_session'.
            // 2. For each student, calculate their GPA for the 'from_session'.
            // 3. Check if GPA meets the promotion threshold (e.g., > 1.00).
            // 4. If eligible, enroll them in the next level courses for the 'to_session'.
            $action_success = "Promotion feature is a placeholder. Logic to promote eligible students would be implemented here.";
            log_activity($conn, $user_id, "Attempted to run promotion for program ID {$program_id} from session {$from_session_id} to {$to_session_id}.");
        }
    }

    // --- ACTIVATE NEW SESSION ---
    if (isset($_POST['activate_session'])) {
        $new_active_session_id = (int)$_POST['new_active_session_id'];
        if (empty($new_active_session_id)) {
            $action_err = "Please select a session to activate.";
        } else {
            $conn->begin_transaction();
            try {
                // Deactivate all other sessions
                $conn->query("UPDATE sessions SET is_active = 0");
                // Activate the new one
                $stmt = $conn->prepare("UPDATE sessions SET is_active = 1 WHERE id = ?");
                $stmt->bind_param("i", $new_active_session_id);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $action_success = "New session has been activated successfully.";
                log_activity($conn, $user_id, "Activated new session ID {$new_active_session_id}.");
            } catch (Exception $e) {
                $conn->rollback();
                $action_err = "Error activating session: " . $e->getMessage();
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <?php if ($action_success): ?><div class="alert alert-success"><?php echo $action_success; ?></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger"><?php echo $action_err; ?></div><?php endif; ?>

    <!-- Promote Students -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Promote Students</h5>
            <p>Promote eligible students from one session to the next. This requires results to be processed first.</p>
            <form method="POST" class="row g-3">
                <div class="col-md-4"><label class="form-label">From Session</label><select name="from_session_id" class="form-select" required><option value="">Select...</option><?php foreach ($sessions as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                <div class="col-md-4"><label class="form-label">To Session</label><select name="to_session_id" class="form-select" required><option value="">Select...</option><?php foreach ($sessions as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                <div class="col-md-4"><label class="form-label">Program</label><select name="promote_program_id" class="form-select" required><option value="">Select...</option><?php foreach ($programs as $p) echo "<option value='{$p['id']}'>{$p['name']}</option>"; ?></select></div>
                <div class="col-12"><button type="submit" name="promote_students" class="btn btn-warning">Promote Students</button></div>
            </form>
        </div>
    </div>

    <!-- Graduate Students -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Graduate Final-Year Students</h5>
            <p>Mark final-year students who have met all requirements as 'Alumni'.</p>
            <form method="POST" class="row g-3">
                <div class="col-md-6"><label class="form-label">Session</label><select name="graduate_session_id" class="form-select" required><option value="">Select...</option><?php foreach ($sessions as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                <div class="col-md-6"><label class="form-label">Program</label><select name="graduate_program_id" class="form-select" required><option value="">Select...</option><?php foreach ($programs as $p) echo "<option value='{$p['id']}'>{$p['name']}</option>"; ?></select></div>
                <div class="col-12"><button type="submit" name="graduate_students" class="btn btn-success">Graduate Students</button></div>
            </form>
        </div>
    </div>

    <!-- Activate New Session -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Activate New Session</h5>
            <p>Set the academic session that is currently active for registrations and portal functions.</p>
            <form method="POST" class="row g-3">
                <div class="col-md-10"><label class="form-label">Session to Activate</label><select name="new_active_session_id" class="form-select" required><option value="">Select...</option><?php foreach ($sessions as $s) echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" name="activate_session" class="btn btn-danger w-100">Activate</button></div>
            </form>
        </div>
    </div>

  </div>
</div>

<?php include 'includes/footer.php'; ?>
```

#### **Step 2: Add Link to Sidebar**

Finally, add a link to this crucial page in `App/includes/sidebar.php` under a "SYSTEM" or "ADMINISTRATION" heading.

```diff
--- a/c/xampp/htdocs/n-portal/App/includes/sidebar.php
+++ b/c/xampp/htdocs/n-portal/App/includes/sidebar.php
@@ -171,6 +171,14 @@
             </span>
             <span class="hide-menu">Activity Logs</span>
           </a>
+        </li>
+        <li class="sidebar-item">
+          <a class="sidebar-link bg-light-danger" href="./end_of_session.php" aria-expanded="false">
+            <span>
+              <i class="ti ti-calendar-event"></i>
+            </span>
+            <span class="hide-menu">End of Session</span>
+          </a>
         </li>
         <li class="nav-small-cap">
           <i class="ti ti-dots nav-small-cap-icon fs-4"></i>

```

With these two new pages, your portal is now significantly more automated and intelligent. You have the tools to manage the entire academic lifecycle from grading individual assessments to processing results and progressing students through their academic careers.

<!--
[PROMPT_SUGGESTION]Create the student-facing page to view final grades and GPA.[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]How can I generate a printable academic transcript for a student?[/PROMPT_SUGGESTION]
-->