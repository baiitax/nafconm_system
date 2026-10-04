<?php
/**
 * NAFCONM - Print Student Information Form (SIF)
 * Features: Enterprise Print UI, Watermarking, Categorized Data, Official Declaration
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];

// Fetch comprehensive student data
$sql = "SELECT s.*, p.name as program_name, u.created_at 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN programs p ON s.program_id = p.id 
        WHERE s.user_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Not Found</h2><p>We could not locate your official student profile.</p></div>");
}

$date_printed = date('d M, Y \a\t h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SIF - <?= htmlspecialchars($student['student_id_number']) ?></title>
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

        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 20px; }
        .header img { width: 80px; margin-bottom: 10px; }
        .header h2 { margin: 0; text-transform: uppercase; font-size: 24px; color: #1e293b; letter-spacing: 1px; }
        .header p { margin: 5px 0 0 0; font-weight: bold; font-size: 14px; text-transform: uppercase; }

        .doc-title { text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; margin-bottom: 25px; text-transform: uppercase; }

        .bio-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        
        .photo-box { 
            width: 120px; height: 140px; border: 2px solid #1e293b; display: flex; 
            align-items: center; justify-content: center; background: #f8fafc; 
            padding: 3px;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; }
        
        .section-title { 
            background: #1e293b; color: #fff; padding: 6px 12px; font-size: 14px; 
            font-weight: bold; text-transform: uppercase; margin-bottom: 0; 
            border: 1px solid #1e293b;
        }

        .table-data { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 25px; }
        .table-data td { border: 1px solid #000; padding: 8px 10px; }
        .table-data td.label { font-weight: bold; background: #f8fafc; width: 25%; text-transform: uppercase; font-size: 11px; color: #334155; }
        .table-data td.value { width: 25%; font-weight: bold; color: #000; }

        .declaration { border: 2px dashed #000; padding: 15px; background: #fcfcfc; font-size: 13px; line-height: 1.6; text-align: justify; margin-bottom: 40px; }
        
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .sign-box { text-align: center; width: 40%; }
        .sign-line { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; font-size: 13px; }

        .footer-meta { text-align: center; margin-top: 30px; font-size: 11px; color: #64748b; font-family: Arial, sans-serif; }

        .no-print-btn { 
            display: flex; align-items: center; justify-content: center; gap: 8px; 
            width: 250px; margin: 0 auto 20px auto; padding: 12px; background: #1e293b; 
            color: white; text-decoration: none; border-radius: 50px; cursor: pointer; 
            border: none; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.3s;
        }
        .no-print-btn:hover { background: #0f172a; transform: translateY(-2px); }

        @media print { 
            body { background: #fff; padding: 0; } 
            .print-container { padding: 0; width: 100%; box-shadow: none; min-height: auto; } 
            .no-print-btn { display: none; } 
        }
    </style>
</head>
<body>

    <button class="no-print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print SIF Form
    </button>

    <div class="print-container">
        <img src="../assets/images/logos/favicon.png" class="watermark" alt="Watermark">
        
        <div class="content-wrapper">
            <div class="header">
                <img src="../assets/images/logos/favicon.png" alt="NAFCONM Logo">
                <h2>Nafisatu College of Nursing & Midwifery</h2>
                <p>STUDENT INFORMATION FORM (SIF) - OFFICIAL COPY</p>
            </div>

            <div class="bio-header">
                <div style="width: 75%;">
                    <div class="section-title">Section A: Academic Profile</div>
                    <table class="table-data" style="margin-bottom: 0; border-top: none;">
                        <tr>
                            <td class="label">Matriculation No.</td>
                            <td class="value" colspan="3" style="font-size: 16px; color: #b91c1c; font-family: monospace;"><?= htmlspecialchars($student['student_id_number']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Program of Study</td>
                            <td class="value" colspan="3"><?= htmlspecialchars($student['program_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Current Level</td>
                            <td class="value"><?= htmlspecialchars($student['current_level']) ?></td>
                            <td class="label">Enrollment Date</td>
                            <td class="value"><?= date('d M, Y', strtotime($student['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="photo-box">
                    <?php if(!empty($student['photo_path'])): ?>
                        <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" alt="Passport">
                    <?php else: ?>
                        <div style="text-align: center; color: #94a3b8; font-family: Arial, sans-serif;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 5px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><br>
                            <span style="font-size: 10px; font-weight: bold;">PASSPORT<br>PHOTOGRAPH</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-title">Section B: Personal Bio-Data</div>
            <table class="table-data">
                <tr>
                    <td class="label" style="width: 20%;">Full Name</td>
                    <td class="value" colspan="3"><?= strtoupper(htmlspecialchars($student['full_name'])) ?></td>
                </tr>
                <tr>
                    <td class="label" style="width: 20%;">Gender</td>
                    <td class="value" style="width: 30%;"><?= htmlspecialchars($student['gender'] ?? 'Not Specified') ?></td>
                    <td class="label" style="width: 20%;">Date of Birth</td>
                    <td class="value" style="width: 30%;"><?= !empty($student['dob']) ? date('d M, Y', strtotime($student['dob'])) : 'Not Specified' ?></td>
                </tr>
                <tr>
                    <td class="label">State of Origin</td>
                    <td class="value"><?= htmlspecialchars($student['state_of_origin'] ?? 'Not Specified') ?></td>
                    <td class="label">Nationality</td>
                    <td class="value"><?= htmlspecialchars($student['nationality'] ?? 'Nigerian') ?></td>
                </tr>
            </table>

            <div class="section-title">Section C: Contact Information</div>
            <table class="table-data">
                <tr>
                    <td class="label" style="width: 20%;">Email Address</td>
                    <td class="value" style="width: 30%;"><?= htmlspecialchars($student['email']) ?></td>
                    <td class="label" style="width: 20%;">Phone Number</td>
                    <td class="value" style="width: 30%;"><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="label">Residential Address</td>
                    <td class="value" colspan="3"><?= htmlspecialchars($student['address'] ?? 'Not Provided') ?></td>
                </tr>
            </table>

            <div class="section-title">Section D: Emergency / Next of Kin</div>
            <table class="table-data">
                <tr>
                    <td class="label" style="width: 20%;">Name</td>
                    <td class="value" style="width: 30%;"><?= htmlspecialchars($student['nok_name'] ?? 'Not Provided') ?></td>
                    <td class="label" style="width: 20%;">Relationship</td>
                    <td class="value" style="width: 30%;"><?= htmlspecialchars($student['nok_relationship'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="label">Phone Number</td>
                    <td class="value" colspan="3"><?= htmlspecialchars($student['nok_phone'] ?? 'N/A') ?></td>
                </tr>
            </table>

            <div class="declaration">
                <strong style="font-size: 14px; text-decoration: underline; display: block; margin-bottom: 5px;">STUDENT DECLARATION:</strong>
                I, <strong><?= strtoupper(htmlspecialchars($student['full_name'])) ?></strong>, hereby declare that all information provided in this form is true and correct to the best of my knowledge. I understand that the discovery of any false information or forged credentials will lead to immediate withdrawal of my admission and expulsion from the College. I pledge to abide by all the rules and regulations of Nafisatu College of Nursing & Midwifery.
            </div>

            <div class="signatures">
                <div class="sign-box">
                    <div class="sign-line">Student's Signature & Date</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line">Registrar's Official Stamp & Sign</div>
                </div>
            </div>

            <div class="footer-meta">
                Document Generated from NAFCONM Portal | Printed on: <?= $date_printed ?>
            </div>
        </div>
    </div>
</body>
</html>