<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Student Details';
require_once 'includes/header.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if (!$student_id) {
    die("Invalid student ID.");
}

// Fetch student's main profile data
$sql_student = "SELECT s.*, u.username FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$student = $stmt_student->get_result()->fetch_assoc();
$stmt_student->close();

if (!$student) {
    die("Student not found.");
}

// Fetch active session
$active_session_id = $conn->query("SELECT id FROM sessions WHERE is_active = 1 LIMIT 1")->fetch_assoc()['id'] ?? null;

// Fetch enrolled courses for active session
$enrolled_courses = [];
if ($active_session_id) {
    $sql_courses = "SELECT c.course_code, c.course_name, t.full_name as teacher_name 
                    FROM enrollments e 
                    JOIN courses c ON e.course_id = c.id 
                    LEFT JOIN teachers t ON c.teacher_id = t.id
                    WHERE e.student_id = ? AND e.session_id = ?";
    $stmt_courses = $conn->prepare($sql_courses);
    $stmt_courses->bind_param("ii", $student_id, $active_session_id);
    $stmt_courses->execute();
    $result_courses = $stmt_courses->get_result();
    while ($row = $result_courses->fetch_assoc()) {
        $enrolled_courses[] = $row;
    }
    $stmt_courses->close();
}

// Fetch recent submissions
$submissions = [];
$sql_subs = "SELECT a.title, c.course_code, s.submitted_at, s.grade 
             FROM submissions s 
             JOIN assignments a ON s.assignment_id = a.id 
             JOIN courses c ON a.course_id = c.id
             WHERE s.student_id = ? ORDER BY s.submitted_at DESC LIMIT 10";
$stmt_subs = $conn->prepare($sql_subs);
$stmt_subs->bind_param("i", $student_id);
$stmt_subs->execute();
$result_subs = $stmt_subs->get_result();
while ($row = $result_subs->fetch_assoc()) {
    $submissions[] = $row;
}
$stmt_subs->close();

// Fetch payment history
$payments = [];
$sql_payments = "SELECT p.payment_date, p.amount_paid, p.status, sess.name as session_name 
                 FROM payments p 
                 JOIN sessions sess ON p.session_id = sess.id
                 WHERE p.student_id = ? ORDER BY p.payment_date DESC";
$stmt_payments = $conn->prepare($sql_payments);
$stmt_payments->bind_param("i", $student_id);
$stmt_payments->execute();
$result_payments = $stmt_payments->get_result();
while ($row = $result_payments->fetch_assoc()) {
    $payments[] = $row;
}
$stmt_payments->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">Student Details: <?php echo htmlspecialchars($student['full_name']); ?></h5>
                    <a href="manage_users.php" class="btn btn-outline-primary">Back to Users</a>
                </div>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title fw-semibold">Profile</h6>
                                <p><strong>ID:</strong> <?php echo htmlspecialchars($student['student_id_number']); ?></p>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
                                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></a></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></p>
                                <p><strong>Course Reg. Enabled:</strong> <?php echo $student['can_register_courses'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>'; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title fw-semibold">Enrolled Courses (Active Session)</h6>
                                <?php if (!empty($enrolled_courses)): ?>
                                    <ul class="list-group list-group-flush"><?php foreach ($enrolled_courses as $course): ?><li class="list-group-item"><?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?> - <small class="text-muted">Prof. <?php echo htmlspecialchars($course['teacher_name']); ?></small></li><?php endforeach; ?></ul>
                                <?php else: ?><div class="alert alert-info">Not enrolled in any courses for the active session.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow-sm"><div class="card-body"><h6 class="card-title fw-semibold">Recent Submissions</h6><div class="table-responsive"><table class="table table-sm">
                            <thead><tr><th>Assignment</th><th>Course</th><th>Grade</th></tr></thead>
                            <tbody><?php if(!empty($submissions)): foreach($submissions as $sub): ?><tr><td><?php echo htmlspecialchars($sub['title']); ?></td><td><?php echo htmlspecialchars($sub['course_code']); ?></td><td><?php echo $sub['grade'] !== null ? htmlspecialchars($sub['grade']).'%' : 'Not Graded'; ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="text-center">No submissions.</td></tr><?php endif; ?></tbody>
                        </table></div></div></div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card shadow-sm"><div class="card-body"><h6 class="card-title fw-semibold">Payment History</h6><div class="table-responsive"><table class="table table-sm">
                            <thead><tr><th>Date</th><th>Session</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody><?php if(!empty($payments)): foreach($payments as $payment): ?><tr><td><?php echo htmlspecialchars(date("Y-m-d", strtotime($payment['payment_date']))); ?></td><td><?php echo htmlspecialchars($payment['session_name']); ?></td><td><?php echo htmlspecialchars(number_format($payment['amount_paid'], 2)); ?></td><td><span class="badge bg-<?php echo $payment['status'] == 'approved' ? 'success' : ($payment['status'] == 'rejected' ? 'danger' : 'warning'); ?>"><?php echo ucfirst($payment['status']); ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="text-center">No payment history.</td></tr><?php endif; ?></tbody>
                        </table></div></div></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>