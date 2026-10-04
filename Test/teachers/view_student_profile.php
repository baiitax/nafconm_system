<?php
$page_title = 'Student Profile';
require_once 'includes/header.php';

$teacher_id = $_SESSION['teacher_id'] ?? null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$student_data = null;
$error = '';

if (!$teacher_id || !$student_id) {
    header("location: my_courses.php");
    exit;
}

// 1. Security Check: Verify the student is enrolled in one of the teacher's courses.
$sql_verify = "SELECT COUNT(*) 
               FROM enrollments e
               JOIN courses c ON e.course_id = c.id
               WHERE e.student_id = ? AND c.teacher_id = ?";
if ($stmt_verify = $conn->prepare($sql_verify)) {
    $stmt_verify->bind_param("ii", $student_id, $teacher_id);
    $stmt_verify->execute();
    $stmt_verify->bind_result($count);
    $stmt_verify->fetch();
    if ($count === 0) {
        $error = "You are not authorized to view this student's profile.";
    }
    $stmt_verify->close();
} else {
    $error = "Database error: Could not verify authorization.";
}

// 2. Fetch student data if authorized
if (empty($error)) {
    $sql_student = "SELECT * FROM students WHERE id = ?";
    if ($stmt_student = $conn->prepare($sql_student)) {
        $stmt_student->bind_param("i", $student_id);
        $stmt_student->execute();
        $result = $stmt_student->get_result();
        if ($result->num_rows === 1) {
            $student_data = $result->fetch_assoc();
        } else {
            $error = "Student profile not found.";
        }
        $stmt_student->close();
    } else {
        $error = "Error fetching student data: " . $conn->error;
    }
}

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">Student Profile</h5>
                    <a href="javascript:history.back()" class="btn btn-outline-primary">Back</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php elseif ($student_data): ?>
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="text-center">
                                <img src="<?php echo BASE_URL; ?>assets/images/profile/user-1.jpg" alt="Student" class="img-fluid rounded-circle" width="120">
                                <h5 class="mt-3"><?php echo htmlspecialchars($student_data['full_name']); ?></h5>
                                <p class="text-muted">Student ID: <?php echo htmlspecialchars($student_data['student_id_number']); ?></p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <h6 class="card-title fw-semibold mb-3">Personal Information</h6>
                            <table class="table table-borderless"><tbody>
                                <tr><th scope="row" style="width: 200px;">Full Name</th><td><?php echo htmlspecialchars($student_data['full_name']); ?></td></tr>
                                <tr><th scope="row">Email Address</th><td><a href="mailto:<?php echo htmlspecialchars($student_data['email']); ?>"><?php echo htmlspecialchars($student_data['email']); ?></a></td></tr>
                                <tr><th scope="row">Phone Number</th><td><?php echo htmlspecialchars($student_data['phone'] ?? 'N/A'); ?></td></tr>
                                <tr><th scope="row">Date of Birth</th><td><?php echo htmlspecialchars($student_data['date_of_birth'] ? date("F j, Y", strtotime($student_data['date_of_birth'])) : 'N/A'); ?></td></tr>
                                <tr><th scope="row">Address</th><td><?php echo htmlspecialchars($student_data['address'] ?? 'N/A'); ?></td></tr>
                            </tbody></table>

                            <h6 class="card-title fw-semibold my-4">Emergency Contact</h6>
                             <table class="table table-borderless"><tbody>
                                <tr><th scope="row" style="width: 200px;">Contact Name</th><td><?php echo htmlspecialchars($student_data['emergency_contact_name'] ?? 'N/A'); ?></td></tr>
                                <tr><th scope="row">Contact Phone</th><td><?php echo htmlspecialchars($student_data['emergency_contact_phone'] ?? 'N/A'); ?></td></tr>
                            </tbody></table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>