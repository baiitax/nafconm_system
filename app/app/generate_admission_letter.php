<?php
/**
 * NAFCONM - Official Admission Letter Generator
 * Features: Print-optimized CSS, Watermark, Official Letterhead Layout
 */
session_start();
require_once '../config/db.php';

if (!isset($_GET['id']) || !isset($_GET['token'])) {
    die("Invalid request parameters.");
}

$app_id = intval($_GET['id']);
$token = mysqli_real_escape_string($conn, $_GET['token']);

// Fetch Application & Student details
$sql = "SELECT aa.*, p.name as program_name, s.student_id_number 
        FROM admission_applications aa 
        JOIN programs p ON aa.program_id = p.id 
        LEFT JOIN students s ON aa.student_id = s.id 
        WHERE aa.id = ? AND aa.token = ? AND aa.status = 'accepted' LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $app_id, $token);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    die("Unauthorized access or admission not yet approved.");
}

$academic_year = date('Y') . "/" . (date('Y') + 1);
$current_date = date('jS F, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admission Letter - <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #f4f4f4; margin: 0; padding: 20px; }
        .print-container { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 40mm 20mm; box-shadow: 0 0 15px rgba(0,0,0,0.1); position: relative; box-sizing: border-box; }
        
        /* Watermark */
        .watermark { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; width: 400px; z-index: 0; pointer-events: none; }
        
        .content-layer { position: relative; z-index: 1; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 20px; margin-bottom: 30px; }
        .header img { width: 100px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 26px; text-transform: uppercase; letter-spacing: 1px; color: #1e4db7; }
        .header p { margin: 5px 0 0 0; font-size: 14px; font-weight: bold; }
        
        .ref-section { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 14px; font-weight: bold; }
        .title { text-align: center; text-decoration: underline; text-transform: uppercase; font-size: 18px; font-weight: bold; margin-bottom: 30px; }
        
        .body-text { font-size: 16px; line-height: 1.8; text-align: justify; }
        .bold { font-weight: bold; }
        .highlight { background: #eef2ff; padding: 2px 5px; border-radius: 3px; font-family: monospace; font-size: 18px; color: #1e4db7; }
        
        .signatures { margin-top: 80px; display: flex; justify-content: space-between; }
        .sign-box { text-align: center; width: 250px; }
        .sign-line { border-top: 1px solid #000; margin-top: 50px; padding-top: 5px; font-weight: bold; }
        
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn-print { background: #1e4db7; color: white; border: none; padding: 10px 25px; font-size: 16px; cursor: pointer; border-radius: 5px; font-weight: bold; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .print-container { box-shadow: none; padding: 20mm; width: auto; min-height: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print Admission Letter</button>
    </div>

    <div class="print-container">
        <!-- Update the src to your actual transparent logo -->
        <img src="../assets/images/logos/favicon.png" class="watermark">
        
        <div class="content-layer">
            <div class="header">
                <img src="../assets/images/logos/favicon.png" alt="Logo">
                <h1>Nafisatu College of Nursing & Midwifery</h1>
                <p>Kano State, Nigeria | Excellence in Healthcare Education</p>
            </div>

            <div class="ref-section">
                <div>Ref: NAF/ADM/<?= date('Y') ?>/<?= $app['id'] ?></div>
                <div>Date: <?= $current_date ?></div>
            </div>

            <div class="body-text">
                <p>Dear <span class="bold"><?= strtoupper($app['first_name'] . ' ' . $app['last_name']) ?></span>,</p>
                
                <div class="title">Offer of Provisional Admission</div>

                <p>I am pleased to inform you that you have been offered provisional admission into the <span class="bold"><?= $app['program_name'] ?></span> program at Nafisatu College of Nursing and Midwifery for the <span class="bold"><?= $academic_year ?></span> Academic Session.</p>
                
                <p>Your official Matriculation Number is: <span class="highlight bold"><?= $app['student_id_number'] ?></span></p>

                <p>This offer is strictly provisional and is subject to the verification of the credentials you provided during your application. If at any point it is discovered that false information was provided, this admission will be automatically withdrawn and you will be liable to prosecution.</p>

                <p>You are required to log into the Student Portal using your Matriculation Number to generate your Fee Invoice and proceed with your course registration immediately. Resumption details and the academic calendar can be found on your dashboard.</p>

                <p>Congratulations, and welcome to NAFCONM.</p>
            </div>

            <div class="signatures">
                <div class="sign-box">
                    <div class="sign-line">Admissions Officer</div>
                </div>
                <div class="sign-box">
                    <!-- Optional: Add a scanned signature image here -->
                    <div class="sign-line">Registrar</div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>