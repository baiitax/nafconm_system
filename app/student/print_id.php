<?php
/**
 * NAFCONM - Student ID Card Generator
 * Features: CR80 PVC Dimensions, Live QR Code, Front & Back Layout
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT s.*, p.name as program_name FROM students s LEFT JOIN programs p ON s.program_id = p.id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) die("Student record not found.");

// QR Code containing matric number for library/gate access
$qr_data = urlencode($student['student_id_number']);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . $qr_data;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ID Card - <?= $student['student_id_number'] ?></title>
    <style>
        body { background: #e9ecef; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* CR80 Card Dimensions: 85.6mm x 53.98mm (Using standard ID orientation - Portrait) */
        .id-card { width: 54mm; height: 86mm; background: #fff; border-radius: 5px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); overflow: hidden; position: relative; margin-bottom: 20px; box-sizing: border-box; border: 1px solid #ccc; }
        
        /* FRONT SIDE */
        .id-front .header { background: #004080; color: #fff; text-align: center; padding: 10px 5px; font-size: 8px; font-weight: bold; border-bottom: 3px solid #13deb9; }
        .id-front .header span { display: block; font-size: 6px; color: #13deb9; letter-spacing: 1px; margin-top: 2px; }
        
        .id-front .photo-area { text-align: center; margin-top: 15px; }
        .id-front .photo-area img { width: 25mm; height: 25mm; border-radius: 5px; object-fit: cover; border: 2px solid #004080; }
        
        .id-front .details { text-align: center; padding: 5px 10px; }
        .id-front .name { font-size: 11px; font-weight: 900; color: #000; margin: 5px 0 2px 0; line-height: 1.1; text-transform: uppercase; }
        .id-front .program { font-size: 8px; color: #004080; font-weight: bold; margin-bottom: 5px; }
        
        .id-front .matric-box { background: #f2f2f2; padding: 4px; font-size: 10px; font-weight: 900; color: #d32f2f; margin-top: 5px; border-radius: 3px; border: 1px solid #e0e0e0; letter-spacing: 1px; }
        
        .id-front .footer { position: absolute; bottom: 0; width: 100%; background: #004080; color: #fff; text-align: center; padding: 4px 0; font-size: 7px; font-weight: bold; }

        /* BACK SIDE */
        .id-back { background: #f8f9fa; padding: 15px 10px; display: flex; flex-direction: column; justify-content: space-between; }
        .id-back h5 { margin: 0 0 5px 0; font-size: 9px; color: #004080; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .id-back p { font-size: 7px; color: #333; margin: 3px 0; line-height: 1.3; }
        .id-back .qr-box { text-align: center; margin-top: 5px; }
        .id-back .qr-box img { width: 15mm; height: 15mm; }
        .id-back .signature { text-align: center; margin-top: 10px; }
        .id-back .signature img { height: 10mm; } /* Placeholder for Registrar Signature */
        .id-back .signature div { border-top: 1px solid #000; display: inline-block; padding-top: 2px; font-size: 7px; font-weight: bold; width: 60%; margin-top: 5px; }

        @media print {
            body { background: none; display: block; }
            .id-card { box-shadow: none; margin: 0 0 20px 0; page-break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <h4 style="color:#333;">Student ID Preview</h4>
        <button onclick="window.print()" style="padding: 10px 20px; background: #004080; color: white; border: none; cursor: pointer; border-radius: 5px; font-weight: bold;">🖨️ Print PVC Card</button>
    </div>

    <!-- FRONT OF CARD -->
    <div class="id-card id-front">
        <div class="header">
            NAFISATU COLLEGE
            <span>NURSING & MIDWIFERY</span>
        </div>
        <div class="photo-area">
            <?php if(!empty($student['photo_path'])): ?>
                <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" alt="Student Photo">
            <?php else: ?>
                <div style="width: 25mm; height: 25mm; border: 1px solid #000; display:inline-block; font-size:8px; line-height:25mm;">No Photo</div>
            <?php endif; ?>
        </div>
        <div class="details">
            <div class="name"><?= htmlspecialchars($student['full_name']) ?></div>
            <div class="program"><?= htmlspecialchars($student['program_name']) ?></div>
            <div class="matric-box"><?= htmlspecialchars($student['student_id_number']) ?></div>
        </div>
        <div class="footer">STUDENT</div>
    </div>

    <!-- BACK OF CARD -->
    <div class="id-card id-back">
        <div>
            <h5>Terms & Conditions</h5>
            <p>1. This card remains the property of NAFCONM.</p>
            <p>2. It is strictly non-transferable and must be worn within the college premises at all times.</p>
            <p>3. If found, please return to the Registrar's Office, NAFCONM, Kwankwaso, Kano State.</p>
        </div>
        
        <div class="qr-box">
            <img src="<?= $qr_url ?>" alt="Verification QR">
        </div>

        <div class="signature">
            <div style="height: 5mm;"></div> <!-- Replace with `<img src="registrar-sig.png">` later -->
            <div>Registrar's Signature</div>
        </div>
    </div>

</body>
</html>