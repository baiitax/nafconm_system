<?php
/**
 * NAFCONM - Official Course Registration Form (CRF) & Exam Docket
 * Features: Ultra-Premium UI, Forced Print Colors, Security Barcode, Passport Verification
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];

// 1. Fetch Active Session
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';
$current_semester = "First Semester"; 

// 2. Fetch Student Profile 
$sql_student = "SELECT s.id, s.student_id_number, s.full_name, s.current_level, s.photo_path, 
                       p.name as program_name, u.created_at as account_created 
                FROM students s 
                JOIN users u ON s.user_id = u.id
                LEFT JOIN programs p ON s.program_id = p.id 
                WHERE s.user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql_student);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Not Found</h2><p>We could not locate your official student profile.</p></div>");
}

// 3. Fetch approved courses 
$stmt_reg = $conn->prepare("SELECT course_codes FROM course_registrations WHERE student_id = ? AND session = ? AND status = 'approved' LIMIT 1");
$stmt_reg->bind_param("is", $student['id'], $current_session);
$stmt_reg->execute();
$reg_data = $stmt_reg->get_result()->fetch_assoc();
$stmt_reg->close();

if (!$reg_data) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Form Not Ready</h2><p>Your course registration has not been completed or approved for the $current_session.</p></div>");
}

$course_codes = json_decode($reg_data['course_codes'], true);
$courses_data = [];
$total_credits = 0; 

// 4. Fetch detailed course data and calculate total credits
if (!empty($course_codes)) {
    $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
    $types = str_repeat('s', count($course_codes));
    
    $stmt_c = $conn->prepare("SELECT course_code, course_title, credit_units, is_core FROM courses WHERE course_code IN ($placeholders) ORDER BY is_core DESC, course_code ASC");
    $stmt_c->bind_param($types, ...$course_codes);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result();
    
    while($row = $res_c->fetch_assoc()) {
        $courses_data[] = $row;
        $total_credits += (int)$row['credit_units'];
    }
    $stmt_c->close();
}

$date_printed = date('d M, Y \a\t h:i A');
$registration_date = date('d M, Y', strtotime($student['account_created']));
$security_hash = strtoupper(substr(hash('sha256', $student['student_id_number'] . $current_session . time()), 0, 16));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CRF Docket - <?= htmlspecialchars($student['student_id_number']) ?></title>
    <!-- Premium Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Playfair+Display:wght@700;900&family=Libre+Barcode+39&display=swap" rel="stylesheet">
    <style>
        /* Base Reset & Variables */
        :root {
            --naf-blue: #0f172a;
            --naf-gray: #f8fafc;
            --naf-border: #cbd5e1;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #cbd5e1; 
            margin: 0; 
            padding: 40px 20px; 
            color: #000; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }
        
        .print-container { 
            background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; 
            padding: 15mm; box-sizing: border-box; position: relative; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
        }
        
        .watermark { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
            opacity: 0.04; width: 70%; z-index: 0; pointer-events: none; 
        }
        
        .content-wrapper { position: relative; z-index: 1; }

        /* Document Header */
        .header { 
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 4px solid var(--naf-blue); 
            padding-bottom: 15px; margin-bottom: 25px; 
        }
        .header-logo { width: 90px; }
        .header-center { text-align: center; flex-grow: 1; padding: 0 15px; }
        .header-center h1 { 
            font-family: 'Playfair Display', serif; margin: 0; 
            font-size: 26px; color: var(--naf-blue); text-transform: uppercase; 
            letter-spacing: 1px; line-height: 1.2;
        }
        .header-center p { margin: 5px 0 0 0; font-weight: 700; font-size: 12px; letter-spacing: 2px; }
        .header-right { text-align: right; width: 90px; }
        .doc-badge { 
            background: var(--naf-blue); color: #fff; padding: 5px 10px; 
            font-size: 10px; font-weight: 900; letter-spacing: 1px; 
            border-radius: 4px; text-align: center; display: inline-block;
        }

        .doc-title-bar { 
            background: var(--naf-gray); border: 1px solid var(--naf-border); 
            padding: 10px; text-align: center; font-size: 16px; font-weight: 900; 
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 30px; 
        }

        /* Bio Data Card */
        .bio-card { 
            display: flex; border: 2px solid var(--naf-blue); 
            margin-bottom: 30px; background: #fff; 
        }
        .bio-details { flex-grow: 1; padding: 0; }
        .bio-table { width: 100%; border-collapse: collapse; font-size: 13px; height: 100%; }
        .bio-table td { padding: 10px 15px; border-bottom: 1px solid var(--naf-border); border-right: 1px solid var(--naf-border); }
        .bio-table tr:last-child td { border-bottom: none; }
        .bio-table td.label { font-size: 10px; text-transform: uppercase; font-weight: 700; color: #64748b; background: var(--naf-gray); width: 25%; }
        .bio-table td.value { font-weight: 900; font-size: 14px; }
        
        .photo-box { 
            width: 140px; border-left: 2px solid var(--naf-blue); 
            display: flex; align-items: center; justify-content: center; 
            background: var(--naf-gray); padding: 5px; flex-shrink: 0;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; border: 1px solid var(--naf-border); }

        /* Course Table */
        .course-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 30px; }
        .course-table th, .course-table td { border: 1px solid #94a3b8; padding: 12px 10px; text-align: center; }
        .course-table td.text-left { text-align: left; font-weight: 600; }
        .course-table th { background: var(--naf-blue); color: #fff; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; font-weight: 700; }
        .course-table tbody tr:nth-child(even) { background-color: var(--naf-gray); }
        
        .course-type { font-size: 10px; font-weight: 900; text-transform: uppercase; padding: 3px 6px; border: 1px solid #ccc; border-radius: 3px; background: #fff; }

        /* Security Barcode Block */
        .security-block { 
            display: flex; justify-content: space-between; align-items: center; 
            border: 1px solid var(--naf-border); padding: 15px; margin-bottom: 30px; 
            background: #fff; border-left: 5px solid var(--naf-blue); page-break-inside: avoid;
        }
        .barcode { font-family: 'Libre Barcode 39', monospace, sans-serif; font-size: 32px; margin-bottom: 2px; }

        /* Notice Box */
        .notice-box { 
            border: 2px dashed #94a3b8; padding: 15px 20px; font-size: 12px; 
            background: var(--naf-gray); margin-bottom: 40px; text-align: justify; 
            line-height: 1.6; page-break-inside: avoid;
        }

        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; page-break-inside: avoid; }
        .sign-box { text-align: center; width: 28%; }
        .sign-line { border-top: 1.5px solid #000; padding-top: 8px; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }

        .footer-meta { text-align: center; margin-top: 40px; font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }

        /* Print Button */
        .no-print-btn { 
            display: flex; align-items: center; justify-content: center; gap: 10px; 
            width: 300px; margin: 0 auto 30px auto; padding: 16px; background: #1e293b; 
            color: white; text-decoration: none; border-radius: 50px; cursor: pointer; 
            border: none; font-weight: 900; font-family: 'Inter', sans-serif; font-size: 15px; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.2); transition: 0.3s;
        }
        .no-print-btn:hover { background: #0f172a; transform: translateY(-3px); box-shadow: 0 15px 25px rgba(0,0,0,0.3); }

        /* Print Specific Rules */
        @media print { 
            @page { size: A4; margin: 0; }
            body { background: #fff; padding: 0; } 
            .print-container { padding: 15mm; width: 100%; box-shadow: none; min-height: 100vh; } 
            .no-print-btn { display: none; } 
        }
    </style>
</head>
<body>

    <button class="no-print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        PRINT EXAM DOCKET
    </button>

    <div class="print-container">
        <img src="../assets/images/logos/favicon.png" class="watermark" alt="Watermark">
        
        <div class="content-wrapper">
            
            <!-- 1. Header Section -->
            <div class="header">
                <div class="header-logo"><img src="../assets/images/logos/favicon.png" alt="NAFCONM Logo"></div>
                <div class="header-center">
                    <h1>Nafisatu College of Nursing</h1>
                    <p>Office of the Academic Registrar</p>
                </div>
                <div class="header-right">
                    <div class="doc-badge">STUDENT<br>COPY</div>
                </div>
            </div>
            
            <div class="doc-title-bar">
                Course Registration Form & Exam Docket<br>
                <span style="font-size: 13px; font-weight: 600; color: #475569;"><?= htmlspecialchars($current_session) ?> — <?= htmlspecialchars($current_semester) ?></span>
            </div>

            <!-- 2. Institutional ID Layout -->
            <div class="bio-card">
                <div class="bio-details">
                    <table class="bio-table">
                        <tr>
                            <td class="label">Full Legal Name</td>
                            <td class="value" colspan="3"><?= strtoupper(htmlspecialchars($student['full_name'])) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Matriculation No.</td>
                            <td class="value" colspan="3" style="font-family: monospace; font-size: 18px; color: #b91c1c;"><?= htmlspecialchars($student['student_id_number']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Program of Study</td>
                            <td class="value" colspan="3"><?= htmlspecialchars($student['program_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="label">Current Level</td>
                            <td class="value"><?= htmlspecialchars($student['current_level']) ?></td>
                            <td class="label">Reg. Status</td>
                            <td class="value" style="color: #15803d;">APPROVED</td>
                        </tr>
                    </table>
                </div>
                
                <div class="photo-box">
                    <?php if(!empty($student['photo_path'])): ?>
                        <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" alt="Passport">
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8;">
                            <span style="font-size: 10px; font-weight: 900;">PASSPORT<br>REQUIRED</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Course Table -->
            <table class="course-table">
                <thead>
                    <tr>
                        <th width="8%">S/N</th>
                        <th width="15%">Course Code</th>
                        <th width="50%">Course Title</th>
                        <th width="15%">Type</th>
                        <th width="12%">Units</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($courses_data)) {
                        $i = 1; 
                        foreach($courses_data as $c): 
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td style="font-weight: 900; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($c['course_code']) ?></td>
                        <td class="text-left"><?= htmlspecialchars($c['course_title']) ?></td>
                        <td><span class="course-type"><?= $c['is_core'] ? 'Core' : 'Elec' ?></span></td>
                        <td style="font-weight: 900;"><?= $c['credit_units'] ?></td>
                    </tr>
                    <?php 
                        endforeach; 
                    } else {
                        echo "<tr><td colspan='5' style='padding: 30px;'>No courses found in registration data.</td></tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" style="text-align: right; background: #fff; color: #000; font-size: 13px;">TOTAL CREDIT UNITS REGISTERED:</th>
                        <th style="font-size: 16px; background: #fff; color: #000; border-left: 1px solid #94a3b8;"><?= $total_credits ?></th>
                    </tr>
                </tfoot>
            </table>

            <!-- 4. Cryptographic Security Block -->
            <div class="security-block">
                <div>
                    <span style="font-size: 10px; font-weight: 900; color: #64748b; text-transform: uppercase;">Doc. Control Number:</span><br>
                    <span style="font-family: monospace; font-weight: 900; font-size: 14px; color: #0f172a;"><?= $security_hash ?></span>
                </div>
                <div style="text-align: right;">
                    <div class="barcode">*<?= $security_hash ?>*</div>
                </div>
            </div>

            <!-- 5. Attestation -->
            <div class="notice-box">
                <strong style="color: #b91c1c; font-weight: 900;">ATTESTATION & EXAM CLEARANCE:</strong> I confirm that the courses listed above are the exact courses I intend to offer for the current semester. I understand that this form serves as my official examination docket and must be presented alongside my valid Student ID card to gain access to examination halls. Any unauthorized alteration, forgery, or mutilation renders this document null and void, and will result in strict disciplinary action.
            </div>

            <!-- 6. Signatures -->
            <div class="signatures">
                <div class="sign-box"><div class="sign-line">Student's Signature & Date</div></div>
                <div class="sign-box"><div class="sign-line">Course Adviser</div></div>
                <div class="sign-box"><div class="sign-line">Head of Department (Stamp)</div></div>
            </div>

            <div class="footer-meta">
                System Generated via NAFCONM Portal | Registration Timestamp: <?= $registration_date ?> | Print Date: <?= $date_printed ?>
            </div>
        </div>
    </div>
</body>
</html>