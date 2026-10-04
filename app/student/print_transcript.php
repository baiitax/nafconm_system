<?php
/**
 * NAFCONM - Print Unofficial Session Transcript
 * Features: Enterprise Print UI, Grading Scale Legend, Watermarking, Official Layout
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];

// Fetch Active Session
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// Fetch Student Profile
$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Not Found</h2><p>We could not locate your official student profile.</p></div>");
}

// Fetch approved courses for the active session
$stmt_reg = $conn->prepare("SELECT course_codes FROM course_registrations WHERE student_id = ? AND session = ? AND status = 'approved' LIMIT 1");
$stmt_reg->bind_param("is", $student['id'], $current_session);
$stmt_reg->execute();
$reg_data = $stmt_reg->get_result()->fetch_assoc();
$stmt_reg->close();

if (!$reg_data) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>No Records Found</h2><p>You have no approved courses for the $current_session to generate a transcript.</p></div>");
}

$course_codes = json_decode($reg_data['course_codes'], true);
$transcript_data = [];

// Fetch Course Details
if (!empty($course_codes)) {
    $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
    $types = str_repeat('s', count($course_codes));
    
    // In a fully integrated system, you would LEFT JOIN this with a `student_grades` table.
    // For now, we fetch the courses and prepare the structure for grades.
    $stmt_c = $conn->prepare("SELECT course_code, course_title, credit_units FROM courses WHERE course_code IN ($placeholders) ORDER BY course_code ASC");
    $stmt_c->bind_param($types, ...$course_codes);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    while($row = $res_c->fetch_assoc()) {
        $transcript_data[] = $row;
    }
    $stmt_c->close();
}

$date_printed = date('d M, Y \a\t h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transcript - <?= htmlspecialchars($student['student_id_number']) ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; background: #e2e8f0; margin: 0; padding: 20px; color: #000; }
        
        .print-container { 
            background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; 
            padding: 15mm 20mm; box-sizing: border-box; position: relative; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        
        .watermark { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
            opacity: 0.05; width: 65%; z-index: 0; pointer-events: none; 
        }
        
        .content-wrapper { position: relative; z-index: 1; }

        /* Header Styling */
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 20px; }
        .header img { width: 80px; margin-bottom: 10px; }
        .header h1 { margin: 0; text-transform: uppercase; font-size: 26px; color: #1e293b; letter-spacing: 1px; }
        .header p { margin: 5px 0 0 0; font-weight: bold; font-size: 13px; text-transform: uppercase; }

        .doc-title { text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; margin-bottom: 20px; letter-spacing: 1px; }

        .badge-warning { background: #fff3cd; color: #856404; padding: 8px 15px; border: 1px solid #ffeeba; display: inline-block; font-size: 12px; margin-bottom: 25px; font-family: Arial, sans-serif; border-radius: 4px; }

        /* Student Info Grid */
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; line-height: 1.8; border: 1px solid #000; padding: 15px; background: #fafafa; }
        
        /* Table Styling */
        .table-data { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 30px; }
        .table-data th, .table-data td { border: 1px solid #000; padding: 10px 8px; text-align: center; }
        .table-data td.text-left { text-align: left; }
        .table-data th { background: #f8fafc; text-transform: uppercase; font-size: 12px; }
        .table-data tbody tr:nth-child(even) { background-color: #fcfcfc; }
        
        .awaiting { color: #64748b; font-style: italic; font-family: Arial, sans-serif; font-size: 11px; }

        /* Summary Box */
        .summary-box { width: 45%; margin-left: auto; border-collapse: collapse; font-size: 13px; margin-bottom: 40px; }
        .summary-box th, .summary-box td { border: 1px solid #000; padding: 8px; }
        .summary-box th { text-align: left; background: #f8fafc; }
        .summary-box td { text-align: right; font-weight: bold; }

        /* Grading Legend */
        .legend-box { font-size: 11px; border: 1px solid #000; padding: 10px; width: 100%; box-sizing: border-box; display: flex; justify-content: space-between; margin-bottom: 50px; background: #fafafa; }
        .legend-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; text-transform: uppercase; }

        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; }
        .sign-box { text-align: center; width: 35%; }
        .sign-line { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; font-size: 13px; text-transform: uppercase; }

        .footer-meta { text-align: center; margin-top: 30px; font-size: 11px; color: #64748b; font-family: Arial, sans-serif; }

        /* Print Button */
        .no-print-btn { 
            display: flex; align-items: center; justify-content: center; gap: 8px; 
            width: 250px; margin: 0 auto 20px auto; padding: 12px; background: #1e293b; 
            color: white; text-decoration: none; border-radius: 50px; cursor: pointer; 
            border: none; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.3s;
        }
        .no-print-btn:hover { background: #0f172a; transform: translateY(-2px); }

        @media print { 
            body { background: #fff; padding: 0; } 
            .print-container { padding: 0; width: 100%; box-shadow: none; min-height: auto; border: none; } 
            .no-print-btn, .badge-warning { display: none; } 
            .table-data tbody tr:nth-child(even) { background-color: transparent; }
            .info-grid, .legend-box { background: transparent; }
        }
    </style>
</head>
<body>

    <button class="no-print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print Transcript
    </button>

    <div class="print-container">
        <img src="../assets/images/logos/favicon.png" class="watermark" alt="Watermark">
        
        <div class="content-wrapper">
            <div class="header">
                <img src="../assets/images/logos/favicon.png" alt="NAFCONM Logo">
                <h1>Nafisatu College of Nursing & Midwifery</h1>
                <p>Kano State, Nigeria | Office of the Registrar</p>
            </div>
            
            <div class="doc-title">STATEMENT OF ACADEMIC RESULT (UNOFFICIAL)</div>

            <div style="text-align: center;">
                <div class="badge-warning">
                    <strong>Notice:</strong> This is a student-generated copy intended for personal reference only. It does not carry the official university seal.
                </div>
            </div>

            <div class="info-grid">
                <div>
                    <strong>Student Name:</strong> <?= strtoupper(htmlspecialchars($student['full_name'])) ?><br>
                    <strong>Program of Study:</strong> <?= htmlspecialchars($student['program_name'] ?? 'N/A') ?><br>
                    <strong>Current Level:</strong> <?= htmlspecialchars($student['current_level'] ?? 'N/A') ?>
                </div>
                <div style="text-align: right;">
                    <strong>Matriculation No:</strong> <span style="font-family: monospace; font-size: 15px;"><?= htmlspecialchars($student['student_id_number']) ?></span><br>
                    <strong>Academic Session:</strong> <?= htmlspecialchars($current_session) ?><br>
                    <strong>Date Issued:</strong> <?= date('d/m/Y') ?>
                </div>
            </div>

            <table class="table-data">
                <thead>
                    <tr>
                        <th width="15%">Course Code</th>
                        <th width="45%">Course Title</th>
                        <th width="10%">Credit<br>Units (CU)</th>
                        <th width="10%">Score<br>(100%)</th>
                        <th width="10%">Letter<br>Grade</th>
                        <th width="10%">Grade<br>Point (GP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_units = 0;
                    if (!empty($transcript_data)) {
                        foreach($transcript_data as $c): 
                            $total_units += (int)$c['credit_units'];
                    ?>
                    <tr>
                        <td style="font-weight: bold; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($c['course_code']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($c['course_title']) ?></td>
                        <td><?= $c['credit_units'] ?></td>
                        <td class="awaiting">Awaiting</td>
                        <td class="awaiting">--</td>
                        <td class="awaiting">--</td>
                    </tr>
                    <?php 
                        endforeach; 
                    } else {
                        echo "<tr><td colspan='6' style='padding: 30px;'>No courses found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- GPA Summary Box -->
            <table class="summary-box">
                <tr>
                    <th>Total Credit Units Registered (TCU)</th>
                    <td><?= $total_units ?></td>
                </tr>
                <tr>
                    <th>Total Credit Units Passed (TCP)</th>
                    <td class="awaiting">Pending</td>
                </tr>
                <tr>
                    <th>Total Weighted Grade Points (TWGP)</th>
                    <td class="awaiting">Pending</td>
                </tr>
                <tr>
                    <th style="background: #e2e8f0;">SESSION GPA (SGPA)</th>
                    <td style="background: #e2e8f0;" class="awaiting">Pending Approval</td>
                </tr>
            </table>

            <!-- Grading System Legend -->
            <div class="legend-box">
                <div>
                    <div class="legend-title">Grading System Key</div>
                    <table style="font-size: 11px; text-align: center; border-collapse: collapse;">
                        <tr><td style="border: 1px solid #ccc; padding: 2px 8px;">70 - 100</td><td style="border: 1px solid #ccc; padding: 2px 8px;">A (Excellent)</td><td style="border: 1px solid #ccc; padding: 2px 8px;">5.0 GP</td></tr>
                        <tr><td style="border: 1px solid #ccc; padding: 2px 8px;">60 - 69</td><td style="border: 1px solid #ccc; padding: 2px 8px;">B (Very Good)</td><td style="border: 1px solid #ccc; padding: 2px 8px;">4.0 GP</td></tr>
                        <tr><td style="border: 1px solid #ccc; padding: 2px 8px;">50 - 59</td><td style="border: 1px solid #ccc; padding: 2px 8px;">C (Good)</td><td style="border: 1px solid #ccc; padding: 2px 8px;">3.0 GP</td></tr>
                        <tr><td style="border: 1px solid #ccc; padding: 2px 8px;">45 - 49</td><td style="border: 1px solid #ccc; padding: 2px 8px;">D (Pass)</td><td style="border: 1px solid #ccc; padding: 2px 8px;">2.0 GP</td></tr>
                        <tr><td style="border: 1px solid #ccc; padding: 2px 8px;">0 - 44</td><td style="border: 1px solid #ccc; padding: 2px 8px;">F (Fail)</td><td style="border: 1px solid #ccc; padding: 2px 8px;">0.0 GP</td></tr>
                    </table>
                </div>
                <div style="max-width: 40%; text-align: justify; line-height: 1.5;">
                    <strong>Authenticity Note:</strong><br>
                    This document is generated directly from the NAFCONM Student Information System. Alteration or forgery of this document is a criminal offense punishable by expulsion and prosecution. For official verified copies, please contact the Registry.
                </div>
            </div>

            <div class="signatures">
                <div class="sign-box">
                    <div class="sign-line">Head of Department</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line">Director of Academic Planning</div>
                </div>
            </div>

            <div class="footer-meta">
                Document Generated from NAFCONM Portal | Printed on: <?= $date_printed ?>
            </div>
        </div>
    </div>
</body>
</html>