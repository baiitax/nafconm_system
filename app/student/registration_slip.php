<?php
/**
 * NAFCONM - Official Course Registration Slip
 * Features: Semester Grouping, Print Break Avoidance, Real QR Code Verification
 */
session_start();
$page_title = 'Course Registration Slip';
require_once '../config/db.php';

// Security Check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$student = null;
$courses = [];

// 1. Optimized Fetch: Student, Program, Department, and Active Session
$sql = "
    SELECT s.*, p.name as program_name, d.name as dept_name, ses.name as session_name, ses.id as session_id
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    JOIN sessions ses ON ses.is_active = 1
    WHERE s.user_id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();

    // 2. Fetch enrolled courses for the active session, ordered by semester first
    $course_stmt = $conn->prepare("
        SELECT c.course_code, c.course_name, c.credit_units, c.semester
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ? AND e.session_id = ?
        ORDER BY c.semester ASC, c.course_code ASC
    ");
    $course_stmt->bind_param("ii", $student['id'], $student['session_id']);
    $course_stmt->execute();
    $courses = $course_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $course_stmt->close();
}
$stmt->close();

// Handle missing enrollment / session
if (!$student || empty($courses)) {
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    require_once 'includes/topbar.php';
    echo '
    <div class="container-fluid py-5">
        <div class="card border-0 shadow-sm rounded-4 text-center p-5 max-w-50 mx-auto">
            <i class="ti ti-alert-triangle fs-1 text-warning mb-3 d-block"></i> 
            <h4 class="fw-bold">No Courses Found</h4>
            <p class="text-muted">You have not completed your course registration for the current session.</p>
            <a href="course_registration.php" class="btn btn-primary rounded-pill px-4 mt-2">Go to Registration</a>
        </div>
    </div>';
    require_once 'includes/footer.php';
    exit;
}

// 3. Resolve Image Path Safely
$photo_path = !empty($student['photo_path']) ? '../uploads/admissions/' . htmlspecialchars($student['photo_path']) : '../assets/images/profile/user-1.jpg';

// Generate a unique verification string for the QR Code
$qr_data = "NAFCONM-REG|" . $student['student_id_number'] . "|" . $student['session_id'];

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    /* --- Professional A4 Print Styling --- */
    .slip-wrapper { 
        background: #fff; 
        max-width: 210mm; /* A4 Width */
        min-height: 297mm; /* A4 Height */
        margin: 20px auto; 
        padding: 30mm 20mm; /* Professional margins */
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        position: relative;
        color: #000;
        font-family: 'Times New Roman', Times, serif; /* Formal serif font */
        line-height: 1.4;
        overflow: hidden;
    }
    
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 7rem;
        font-family: Arial, sans-serif;
        font-weight: 900;
        color: rgba(0, 64, 128, 0.03);
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
    }

    .slip-header { text-align: center; border-bottom: 3px double #004080; padding-bottom: 15px; margin-bottom: 30px; z-index: 1; position: relative; }
    .slip-header h2 { color: #004080; font-family: 'Arial Black', Impact, sans-serif; margin: 0; text-transform: uppercase; font-size: 24px; letter-spacing: 1px; }
    .slip-header p { margin: 2px 0; font-weight: bold; font-family: Arial, sans-serif; font-size: 14px;}

    .student-photo { 
        width: 120px; 
        height: 140px; 
        border: 2px solid #004080; 
        padding: 3px;
        object-fit: cover;
        background: #fff;
    }

    .info-table { width: 100%; margin-bottom: 30px; font-family: Arial, sans-serif; z-index: 1; position: relative; }
    .info-table td { padding: 8px 0; font-size: 13px; }
    .info-table .label { font-weight: bold; width: 140px; text-transform: uppercase; }
    .info-table .value { font-weight: bold; border-bottom: 1px dashed #000; }

    .course-table { width: 100%; border-collapse: collapse; margin-top: 10px; z-index: 1; position: relative; font-family: Arial, sans-serif; }
    .course-table th { background-color: #004080 !important; color: #fff !important; padding: 10px; text-transform: uppercase; font-size: 11px; text-align: left; border: 1px solid #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .course-table td { border: 1px solid #000; padding: 8px 10px; font-size: 12px; }
    .course-table tr { page-break-inside: avoid; } /* Prevents rows splitting across pages */
    
    .semester-divider { background-color: #e9ecef !important; font-weight: bold; text-align: center !important; font-size: 11px; text-transform: uppercase; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .total-row { background-color: #f0f0f0 !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    .slip-footer { margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end; z-index: 1; position: relative; font-family: Arial, sans-serif; page-break-inside: avoid;}
    .sig-box { text-align: center; width: 30%; }
    .sig-line { border-top: 1px solid #000; padding-top: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; }

    .no-print-btn { text-align: center; margin-bottom: 20px; }

    /* Print Specific Rules */
    @media print {
        body { background: white; margin: 0; }
        .no-print-btn, .left-sidebar, .app-header, footer { display: none !important; }
        .body-wrapper { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .slip-wrapper { border: none; box-shadow: none; margin: 0; padding: 0; width: 100%; min-height: auto; }
        
        /* Force background colors to print */
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="container-fluid py-4">
    <div class="no-print-btn">
        <button onclick="window.print()" class="btn btn-primary shadow rounded-pill px-5 py-2 fw-bold fs-5">
            <i class="ti ti-printer me-2"></i> Print Official Form
        </button>
        <p class="text-muted small mt-2"><i class="ti ti-info-circle"></i> Set paper size to A4 and enable "Background Graphics" in your print settings.</p>
    </div>

    <div class="slip-wrapper">
        <div class="watermark">NAFCONM OFFICIAL</div>

        <div class="slip-header">
            <img src="../assets/images/logos/favicon.png" height="80" class="mb-2" alt="College Logo">
            <h2>Nafisatu College of Nursing Sciences</h2>
            <p>Kwankwaso Town, Madobi LG, Kano State.</p>
            <p style="color: #004080; font-size: 16px; margin-top: 10px;">COURSE REGISTRATION FORM</p>
            <p style="font-size: 12px; color: #333;"><?= htmlspecialchars($student['session_name']); ?> ACADEMIC SESSION</p>
        </div>

        <div class="row align-items-start mb-4">
            <div class="col-9">
                <table class="info-table">
                    <tr>
                        <td class="label">Full Name:</td>
                        <td class="value fs-6"><?= htmlspecialchars(strtoupper($student['full_name'])); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Matric Number:</td>
                        <td class="value fs-6"><?= htmlspecialchars($student['student_id_number']); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Department:</td>
                        <td class="value"><?= htmlspecialchars(strtoupper($student['dept_name'] ?? 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Program:</td>
                        <td class="value"><?= htmlspecialchars(strtoupper($student['program_name'] ?? 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <td class="label">Level:</td>
                        <td class="value"><?= $student['current_level']; ?> LEVEL</td>
                    </tr>
                </table>
            </div>
            <div class="col-3 text-end">
                <img src="<?= $photo_path ?>" class="student-photo" alt="Student Photo">
            </div>
        </div>

        <h6 class="fw-bold text-dark border-bottom border-dark border-2 pb-1 mb-3" style="font-family: Arial, sans-serif;">REGISTERED COURSES</h6>

        <table class="course-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">S/N</th>
                    <th style="width: 120px;">Course Code</th>
                    <th>Course Title</th>
                    <th style="width: 80px; text-align: center;">Units</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_units = 0;
                $current_sem = null;
                $sn = 1;
                
                foreach ($courses as $course): 
                    $total_units += $course['credit_units'];
                    
                    // Grouping by Semester
                    if ($current_sem !== $course['semester']) {
                        $current_sem = $course['semester'];
                        $sem_text = ($current_sem == 1) ? 'First Semester' : 'Second Semester';
                        echo "<tr class='semester-divider'><td colspan='4'>— {$sem_text} —</td></tr>";
                    }
                ?>
                <tr>
                    <td style="text-align: center;"><?= $sn++; ?></td>
                    <td style="font-weight: bold;"><?= htmlspecialchars($course['course_code']); ?></td>
                    <td><?= htmlspecialchars($course['course_name']); ?></td>
                    <td style="text-align: center;"><?= $course['credit_units']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; padding-right: 15px;">TOTAL REGISTERED CREDIT UNITS:</td>
                    <td style="text-align: center; font-size: 14px;"><?= $total_units; ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="slip-footer">
            <div class="sig-box">
                <div class="sig-line">Student's Signature</div>
                <div style="font-size: 11px; margin-top: 5px;">Date: __________________</div>
            </div>
            
            <div class="sig-box">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode($qr_data) ?>" alt="Verification QR" style="border: 2px solid #000; padding: 2px;">
                <div style="font-size: 9px; font-weight: bold; margin-top: 5px;">SCAN TO VERIFY</div>
            </div>

            <div class="sig-box">
                <div class="sig-line">Academic Adviser / HOD</div>
                <div style="font-size: 11px; margin-top: 5px;">Date: __________________</div>
            </div>
        </div>

        <div style="margin-top: 50px; border-top: 1px solid #ccc; padding-top: 10px; text-align: center; font-size: 10px; color: #555; font-family: Arial, sans-serif; page-break-inside: avoid;">
            <strong>NOTE:</strong> This Course Registration Form (CRF) is a vital academic document. Any unauthorized alteration renders it completely invalid.<br>
            Computer Generated on <?= date('l, F j, Y \a\t h:i A') ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>