<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

$page_title = 'Teacher Profile';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../index.php");
    exit;
}

// Check if teacher ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: manage_teachers.php");
    exit;
}

$teacher_id = (int)$_GET['id'];

// Fetch the teacher's data
$stmt = $conn->prepare("
    SELECT t.*, u.username, d.name as department_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.id = ?
");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    // Redirect if teacher not found
    header("location: manage_teachers.php");
    exit;
}

// Fetch courses assigned to the teacher
$stmt_courses = $conn->prepare("SELECT course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name");
$stmt_courses->bind_param('i', $teacher_id);
$stmt_courses->execute();
$courses_result = $stmt_courses->get_result();
$assigned_courses = $courses_result->fetch_all(MYSQLI_ASSOC);


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4"><?php echo htmlspecialchars($teacher['full_name']); ?>'s Profile</h5>
    
    <div class="card">
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 text-center">
            <img src="<?php echo !empty($teacher['profile_picture_path']) ? '../' . htmlspecialchars($teacher['profile_picture_path']) : '../assets/images/profile/user-1.jpg'; ?>" alt="Profile Picture" class="rounded-circle img-fluid mb-3" style="width: 150px; height: 150px; object-fit: cover;">
            <h4><?php echo htmlspecialchars($teacher['full_name']); ?></h4>
            <p class="text-muted"><?php echo htmlspecialchars($teacher['specialization'] ?? 'No specialization set'); ?></p>
          </div>
          <div class="col-md-9">
            <h5 class="card-title">Profile Details</h5>
            <hr>
            <div class="row">
              <div class="col-md-6">
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($teacher['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
              </div>
              <div class="col-md-6">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($teacher['username']); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?></p>
              </div>
            </div>
            
            <h5 class="card-title mt-4">Assigned Courses</h5>
            <hr>
            <?php if (!empty($assigned_courses)): ?>
              <ul class="list-group">
                <?php foreach ($assigned_courses as $course): ?>
                  <li class="list-group-item">
                    <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p>No courses are currently assigned to this teacher.</p>
            <?php endif; ?>

            <div class="mt-4">
                <a href="manage_teachers.php" class="btn btn-light">Back to Teachers List</a>
                <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>" class="btn btn-primary">Edit Profile</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>