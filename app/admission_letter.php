<?php
session_start();
require_once 'config/db.php';

/**
 * 1. RESOLVE IDENTITY
 * We check GET parameters first, then fall back to SESSION
 */
$student_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['student_id']) ? intval($_SESSION['student_id']) : 0);
$token = isset($_GET['token']) ? mysqli_real_escape_string($conn, $_GET['token']) : '';

// 2. DATA FETCHING
if ($student_id > 0) {
    // Accessing via Dashboard (ID)
    $stmt = $conn->prepare("SELECT a.*, p.name as program_name FROM admission_applications a 
                            JOIN programs p ON a.program_id = p.id 
                            WHERE a.student_id = ? AND a.status = 'accepted' LIMIT 1");
    $stmt->bind_param("i", $student_id);
} elseif (!empty($token)) {
    // Accessing via Tracking (Token)
    $stmt = $conn->prepare("SELECT a.*, p.name as program_name FROM admission_applications a 
                            JOIN programs p ON a.program_id = p.id 
                            WHERE a.token = ? AND a.status = 'accepted' LIMIT 1");
    $stmt->bind_param("s", $token);
} else {
    // If neither ID nor Token is found, block access
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h3>Access Denied</h3>
            <p>Please log in through the student portal to view this document.</p>
         </div>");
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>
            <h3>Document Not Found</h3>
            <p>Your admission letter is not available. This may be because your application status is not yet set to 'Accepted'.</p>
         </div>");
}

$app = $result->fetch_assoc();

// Dynamic Data
$full_name = strtoupper(htmlspecialchars($app['last_name'] . ', ' . $app['first_name'] . ' ' . $app['middle_name']));
$current_date = date('jS F, Y');
$academic_session = "2025/2026"; // Or fetch from a settings table
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admission Letter - <?= htmlspecialchars($app['token']) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { background-color: #F4F7F6; font-family: 'Times New Roman', Times, serif; margin: 0; padding: 0; }
        .page { width: 210mm; min-height: 297mm; padding: 20mm; margin: 20px auto; background: white; box-shadow: 0 0 20px rgba(0,0,0,0.1); position: relative; box-sizing: border-box; }
        
        /* Watermark */
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; color: rgba(179, 0, 0, 0.04); font-weight: bold; pointer-events: none; z-index: 0; white-space: nowrap; }

        /* Header */
        .header-container { text-align: center; border-bottom: 3px double #b30000; padding-bottom: 10px; margin-bottom: 30px; position: relative; z-index: 1; }
        .header-container h1 { color: #b30000; font-size: 26px; margin: 0; font-family: 'Arial Black', Gadget, sans-serif; }
        .header-container p { font-size: 13px; margin: 2px 0; font-family: Arial, sans-serif; }
        
        /* Letter Body */
        .meta-info { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 30px; font-size: 16px; }
        .address-block { line-height: 1.6; margin-bottom: 30px; font-size: 16px; }
        .subject-line { text-align: center; font-weight: bold; text-decoration: underline; font-size: 18px; margin-bottom: 25px; text-transform: uppercase; }
        .letter-content { line-height: 1.7; text-align: justify; font-size: 16px; }
        
        /* Signature */
        .signature-block { margin-top: 50px; }
        .sig-line { width: 200px; border-top: 1px solid #000; margin-top: 40px; }
        
        /* Print Fixes */
        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; height: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="watermark">NAFCONM OFFICIAL</div>
        
        <div class="header-container">
            <h1>NAFISATU COLLEGE OF NURSING SCIENCES</h1>
            <p>Knowledge, Compassion, and Excellence</p>
            <p>Kano State, Nigeria | Email: admissions@nafconm.edu.ng</p>
            <p><strong>OFFICE OF THE REGISTRAR</strong></p>
        </div>

        <div class="meta-info">
            <span>Ref: NAF/ADM/<?= date('Y') ?>/<?= htmlspecialchars($app['token']) ?></span>
            <span>Date: <?= $current_date ?></span>
        </div>

        <div class="address-block">
            <strong>TO:</strong><br>
            <strong><?= $full_name ?></strong><br>
            Applicant Token: <?= htmlspecialchars($app['token']) ?><br>
            Phone: <?= htmlspecialchars($app['phone']) ?>
        </div>

        <div class="subject-line">
            Offer of Provisional Admission - <?= $academic_session ?> Academic Session
        </div>

        <div class="letter-content">
            <p>Dear <?= htmlspecialchars($app['first_name']) ?>,</p>
            
            <p>I am pleased to inform you that following your application and subsequent performance in the entrance screening, you have been offered <strong>Provisional Admission</strong> into the Nafisatu College of Nursing Sciences (NAFCONM) to study:</p>
            
            <p style="text-align:center; font-size: 20px; font-weight: bold; color: #b30000; margin: 20px 0;">
                "<?= strtoupper(htmlspecialchars($app['program_name'])) ?>"
            </p>

            <p>This offer is subject to the following terms:</p>
            <ul>
                <li>Verification of your original O'Level results (WAEC/NECO/NABTEB).</li>
                <li>Presentation of a valid Medical Fitness Certificate.</li>
                <li>Payment of the non-refundable Acceptance Fee within fourteen (14) days of this offer.</li>
            </ul>

            <p>Please log in to your student portal to complete your registration and pay the required fees. Failure to meet these conditions within the stipulated time may lead to the forfeiture of this admission.</p>
            
            <p>Congratulations on your well-deserved success.</p>
        </div>

        <div class="signature-block">
            <p>Yours Faithfully,</p>
            <br>
            <div class="sig-line"></div>
            <strong>Hajiya Fatima Abdullah</strong><br>
            <em>Registrar</em>
        </div>
    </div>
</body>
</html>