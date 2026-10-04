<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin or staff
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION["user_type"], ['superadmin', 'staff'])) {
    header("location: ../login.php");
    exit;
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    die("No student specified.");
}

// Fetch student details
$sql = "SELECT s.*, d.name as department_name, sess.end_date as session_end_date
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN sessions sess ON sess.is_active = 1
        WHERE s.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student not found.");
}

$page_title = 'Student ID Card';
require_once 'includes/header.php';
?>

<style>
    .id-card-container {
        width: 3.375in;
        height: 2.125in;
        border: 1px solid #000;
        margin: 20px auto;
        padding: 0.1in;
        background: #f4f4f4;
        font-family: Arial, sans-serif;
        position: relative;
        box-sizing: border-box;
    }
    .id-card-header {
        text-align: center;
        font-size: 10pt;
        font-weight: bold;
    }
    .id-card-photo {
        width: 1in;
        height: 1.2in;
        border: 1px solid #333;
        margin-top: 0.1in;
        object-fit: cover;
        float: left;
    }
    .id-card-details {
        float: left;
        margin-left: 0.1in;
        margin-top: 0.1in;
        font-size: 8pt;
    }
    .id-card-details p {
        margin: 2px 0;
    }
    .id-card-footer {
        position: absolute;
        bottom: 0.1in;
        left: 0.1in;
        right: 0.1in;
        font-size: 7pt;
        text-align: center;
    }
    @media print {
        body * { visibility: hidden; }
        .id-card-container, .id-card-container * { visibility: visible; }
        .id-card-container { position: absolute; left: 0; top: 0; }
        .no-print { display: none; }
    }
</style>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h5 class="card-title fw-semibold">Student ID Card</h5>
            <button onclick="window.print();" class="btn btn-primary"><i class="ti ti-printer me-1"></i>Print ID Card</button>
        </div>

        <div class="id-card-container">
            <div class="id-card-header">N-PORTAL UNIVERSITY</div>
            
            <?php if ($student['photo_path']): ?>
                <img src="/<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Student Photo" class="id-card-photo">
            <?php else: ?>
                <div class="id-card-photo" style="text-align:center; padding-top: 0.5in;">No Photo</div>
            <?php endif; ?>

            <div class="id-card-details">
                <p><strong><?php echo htmlspecialchars(strtoupper($student['full_name'])); ?></strong></p>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($student['student_id_number']); ?></p>
                <p><strong>Program:</strong> <?php echo htmlspecialchars($student['program_of_study'] ?? 'N/A'); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></p>
            </div>

            <div class="id-card-footer">
                Expires: <?php echo date("M Y", strtotime($student['session_end_date'] ?? '+1 year')); ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>