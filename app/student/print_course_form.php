<?php
/**
 * NAFCONM - Official Course Form Print Template
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];
$current_session = "2025/2026 Session"; // Sync with what you used in course_registration.php
$current_semester = "First Semester";

// Fetch Student Profile
$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();
$student_id = $student['id'];

// Fetch Registration
$stmt_reg = $conn->prepare("SELECT course_codes, total_credits FROM course_registrations WHERE student_id = ? AND session = ? AND semester = ? AND status = 'approved' LIMIT 1");
$stmt_reg->bind_param("iss", $student_id, $current_session, $current_semester);
$stmt_reg->execute();
$reg_data = $stmt_reg->get_result()->fetch_assoc();
$stmt_reg->close();

if (!$reg_data) {
    die("You have not completed your course registration for the current semester.");
}

$course_codes = json_decode($reg_data['course_codes'], true);
$courses_data = [];

// Fetch Full Course Details from JSON array
if (!empty($course_codes)) {
    $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
    $types = str_repeat('s', count($course_codes));
    
    $stmt_c = $conn->prepare("SELECT course_code, course_title, credit_units, is_core FROM courses WHERE course_code IN ($placeholders) ORDER BY is_core DESC, course_code ASC");
    $stmt_c->bind_param($types, ...$course_codes);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    while($row = $res_c->fetch_assoc()) {
        $courses_data[] = $row;
    }
    $stmt_c->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Course Form - <?= htmlspecialchars($student['student_id_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #000; background: #f4f4f4; margin: 0; padding: 20px; }
        .print-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm; box-shadow: 0 0 15px rgba(0,0,0,0.1); box-sizing: border-box; }
        
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; color: #000; }
        .header p { margin: 5px 0; font-size: 14px; font-weight: bold; }
        .title { text-align: center; text-transform: uppercase; font-size: 16px; font-weight: bold; margin-bottom: 20px; padding: 5px; background: #eee; }
        
        .bio-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .bio-table td { padding: 8px; font-size: 14px; border: 1px solid #ccc; }
        .bio-table td.label { font-weight: bold; background: #f9f9f9; width: 20%; }
        
        .course-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .course-table th, .course-table td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 13px; }
        .course-table th { background-color: #eee; text-transform: uppercase; font-weight: bold; }
        .course-table td.center { text-align: center; }
        
        .signatures { display: table; width: 100%; margin-top: 50px; }
        .sign-box { display: table-cell; width: 33%; text-align: center; }
        .sign-line { border-top: 1px solid #000; margin: 0 20px; padding-top: 5px; font-weight: bold; font-size: 13px; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn-print { background: #000; color: white; border: none; padding: 10px 25px; font-size: 16px; cursor: pointer; border-radius: 5px; font-weight: bold; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .print-container { box-shadow: none; padding: 10mm; width: auto; min-height: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Course Form</button>
    </div>

    <div class="print-container">
        <div class="header">
            <h1>Nafisatu College of Nursing & Midwifery</h1>
            <p>Official Course Registration Form</p>
        </div>

        <div class="title"><?= $current_session ?> - <?= $current_semester ?></div>

        <table class="bio-table">
            <tr>
                <td class="label">Full Name:</td>
                <td colspan="3"><strong><?= strtoupper($student['full_name']) ?></strong></td>
            </tr>
            <tr>
                <td class="label">Matric No:</td>
                <td><strong><?= htmlspecialchars($student['student_id_number']) ?></strong></td>
                <td class="label">Level:</td>
                <td><?= htmlspecialchars($student['current_level'] ?? '100L') ?></td>
            </tr>
            <tr>
                <td class="label">Program:</td>
                <td colspan="3"><?= htmlspecialchars($student['program_name']) ?></td>
            </tr>
        </table>

        <table class="course-table">
            <thead>
                <tr>
                    <th width="5%" class="center">S/N</th>
                    <th width="15%">Course Code</th>
                    <th width="50%">Course Title</th>
                    <th width="15%" class="center">Type</th>
                    <th width="15%" class="center">Credit Units</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1; 
                foreach($courses_data as $c): 
                ?>
                <tr>
                    <td class="center"><?= $count++ ?></td>
                    <td><strong><?= htmlspecialchars($c['course_code']) ?></strong></td>
                    <td><?= htmlspecialchars($c['course_title']) ?></td>
                    <td class="center"><?= $c['is_core'] ? 'CORE' : 'ELECTIVE' ?></td>
                    <td class="center"><?= $c['credit_units'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" style="text-align: right;">TOTAL REGISTERED UNITS:</th>
                    <th class="center" style="font-size: 16px;"><?= $reg_data['total_credits'] ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="signatures">
            <div class="sign-box">
                <div class="sign-line">Student's Signature & Date</div>
            </div>
            <div class="sign-box">
                <div class="sign-line">Course Adviser's Signature & Date</div>
            </div>
            <div class="sign-box">
                <div class="sign-line">HOD's Signature & Date</div>
            </div>
        </div>
        
        <p style="text-align: center; font-size: 11px; color: #555; margin-top: 50px;">Document Generated Electronically by NAFCONM Portal on <?= date('d-M-Y H:i:s') ?></p>
    </div>

</body>
</html>