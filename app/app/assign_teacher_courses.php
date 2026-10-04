<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Assign Courses & Department';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$selected_teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_courses'])) {
    $teacher_id_to_update = (int)$_POST['teacher_id'];
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $assigned_courses = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];

    $conn->begin_transaction();
    try {
        // Update teacher's department
        $stmt_dept = $conn->prepare("UPDATE teachers SET department_id = ? WHERE id = ?");
        $stmt_dept->bind_param('ii', $department_id, $teacher_id_to_update);
        $stmt_dept->execute();

        // Clear previous course assignments for this teacher
        $stmt_clear = $conn->prepare("UPDATE courses SET teacher_id = NULL WHERE teacher_id = ?");
        $stmt_clear->bind_param('i', $teacher_id_to_update);
        $stmt_clear->execute();

        // Assign new courses
        if (!empty($assigned_courses)) {
            $stmt_assign = $conn->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
            foreach ($assigned_courses as $course_id) {
                $course_id_int = (int)$course_id;
                $stmt_assign->bind_param('ii', $teacher_id_to_update, $course_id_int);
                $stmt_assign->execute();
            }
        }
        
        $conn->commit();
        log_activity($conn, $user_id, "Updated assignments for teacher ID: $teacher_id_to_update");
        $message = "<div class='alert alert-success'>Assignments updated successfully.</div>";
        $selected_teacher_id = $teacher_id_to_update; // Keep the teacher selected after update
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>An error occurred: " . $e->getMessage() . "</div>";
    }
}


// Fetch data for the page
$teachers = $conn->query("SELECT id, full_name FROM teachers ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$teacher_details = null;
$all_courses = [];
$assigned_course_ids = [];

if ($selected_teacher_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->bind_param('i', $selected_teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher_details = $result->fetch_assoc();

    // Fetch all courses
    $all_courses = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name")->fetch_all(MYSQLI_ASSOC);

    // Fetch courses assigned to this teacher
    $stmt_assigned = $conn->prepare("SELECT id FROM courses WHERE teacher_id = ?");
    $stmt_assigned->bind_param('i', $selected_teacher_id);
    $stmt_assigned->execute();
    $result_assigned = $stmt_assigned->get_result();
    while ($row = $result_assigned->fetch_assoc()) {
        $assigned_course_ids[] = $row['id'];
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Main wrapper -->
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo $page_title; ?></h5>
    <?php echo $message; ?>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Select a Teacher</h5>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-8">
                        <select class="form-select" name="teacher_id" onchange="this.form.submit()">
                            <option value="">-- Select a Teacher to Manage --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($selected_teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                         <button type="submit" class="btn btn-primary">Load Teacher Details</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_teacher_id > 0 && $teacher_details): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Manage Assignments for: <?php echo htmlspecialchars($teacher_details['full_name']); ?></h5>
            <form method="POST" action="">
                <input type="hidden" name="teacher_id" value="<?php echo $selected_teacher_id; ?>">
                
                <!-- Department Assignment -->
                <div class="mb-4">
                    <h6>Assign to Department</h6>
                    <select class="form-select" name="department_id">
                        <option value="">-- No Department --</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo (isset($teacher_details['department_id']) && $teacher_details['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Course Assignment -->
                <div class="mb-4">
                    <h6>Assign Courses</h6>
                    <p>Check the courses you want to assign to this teacher.</p>
                    <div class="row" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($all_courses as $course): ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="course_ids[]" value="<?php echo $course['id']; ?>" id="course_<?php echo $course['id']; ?>"
                                        <?php echo in_array($course['id'], $assigned_course_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="assign_courses" class="btn btn-success">Save Assignments</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php include 'includes/footer.php'; ?>