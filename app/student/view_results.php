<?php
$page_title = 'My Results';
require_once 'includes/header.php'; // Assuming student-specific header

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student' || !isset($_SESSION['student_id'])) {
    header("location: ../../index.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch sessions the student was enrolled in
$sessions_query = "SELECT DISTINCT s.id, s.name FROM sessions s JOIN enrollments e ON s.id = e.session_id WHERE e.student_id = ? ORDER BY s.start_date DESC";
$stmt_sessions = $conn->prepare($sessions_query);
$stmt_sessions->bind_param("i", $student_id);
$stmt_sessions->execute();
$sessions = $stmt_sessions->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_sessions->close();

$selected_session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : ($sessions[0]['id'] ?? 0);

$results = [];
$gpa = null;

if ($selected_session_id > 0) {
    $sql = "SELECT c.course_name, c.course_code, c.credit_units, e.final_grade, e.grade_point
            FROM enrollments e
            JOIN courses c ON e.course_id = c.id
            WHERE e.student_id = ? AND e.session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $selected_session_id);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate GPA
    $total_points = 0;
    $total_units = 0;
    foreach ($results as $result) {
        if (is_numeric($result['grade_point']) && is_numeric($result['credit_units'])) {
            $total_points += $result['grade_point'] * $result['credit_units'];
            $total_units += $result['credit_units'];
        }
    }
    if ($total_units > 0) {
        $gpa = $total_points / $total_units;
    }
}

require_once 'includes/sidebar.php'; // Assuming student-specific sidebar
require_once 'includes/topbar.php';
?>

<div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-6">
                    <label for="session_id" class="form-label">Select Session</label>
                    <select name="session_id" id="session_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?php echo $session['id']; ?>" <?php if ($selected_session_id == $session['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($session['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_session_id > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title m-0">Results for Session</h5>
                <?php if ($gpa !== null): ?>
                    <div class="text-end">
                        <h6 class="m-0">Session GPA</h6>
                        <h4 class="fw-bold text-primary"><?php echo number_format($gpa, 2); ?></h4>
                    </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead><tr><th>Course Code</th><th>Course Name</th><th>Units</th><th>Grade</th></tr></thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr><td colspan="4" class="text-center">No results found for this session.</td></tr>
                        <?php else: foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($result['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['credit_units']); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($result['final_grade'] ?? 'N/A'); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>