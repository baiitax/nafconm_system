<?php
/**
 * NAFCONM - Official Admission Screening Pass
 * Features: A4 Print Engine, Gate Verification QR, O-Level Matrix
 */
session_start();
$page_title = 'Official Screening Pass';
require_once 'config/db.php'; // Adjust path if needed depending on where this file lives

// 1. Security & Session Check
if (!isset($_SESSION['last_app_id']) || !isset($_SESSION['applicant_reg_no'])) {
    // If they bypass the flow, send them back to the start
    header("Location: apply.php");
    exit;
}

$app_id = (int)$_SESSION['last_app_id'];

// 2. Fetch the Core Application Data
$sql = "
    SELECT a.*, p.name as program_name 
    FROM admission_applications a
    LEFT JOIN programs p ON a.program_id = p.id
    WHERE a.id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $app_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Application record not found.");
}

$applicant = $result->fetch_assoc();
$stmt->close();

// 3. Financial Gatekeeper: Ensure they actually paid
if ($applicant['payment_status'] !== 'paid' && $applicant['payment_status'] !== 'approved') {
    header("Location: payment_details.php");
    exit;
}

// 4. Fetch O-Level Results
$results = [];
$res_stmt = $conn->prepare("SELECT subject, grade FROM admission_results WHERE application_id = ? ORDER BY subject ASC");
$res_stmt->bind_param("i", $app_id);
$res_stmt->execute();
$res_data = $res_stmt->get_result();
while ($row = $res_data->fetch_assoc()) {
    $results[] = $row;
}
$res_stmt->close();

// 5. Asset Path Resolution
// Using the upload directory we defined in process_step.php
$photo_path = !empty($applicant['photo']) ? 'app/uploads/admissions/' . htmlspecialchars($applicant['photo']) : 'assets/images/default_avatar.png';

// Generate QR Code Data (For gate security scanners)
$qr_data = "NAFCONM-SCREENING|" . $applicant['reg_no'] . "|" . $applicant['program_name'];

require_once 'includes/frontend_header.php'; 
?>

<style>
    /* --- Professional A4 Print Engine --- */
    .slip-wrapper { 
        background: #fff; 
        max-width: 210mm; /* Strict A4 Width */
        min-height: 297mm; /* Strict A4 Height */
        margin: 20px auto; 
        padding: 25mm 20mm; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        position: relative;
        color: #111;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        line-height: 1.5;
        overflow: hidden;
    }
    
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-size: 7rem;
        font-weight: 900;
        color: rgba(0, 64, 128, 0.03);
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
        text-align: center;
        line-height: 1;
    }

    .slip-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #004080; padding-bottom: 20px; margin-bottom: 25px; z-index: 2; position: relative; }
    .slip-header h2 { color: #004080; font-family: 'Arial Black', Impact, sans-serif; margin: 0; text-transform: uppercase; font-size: 22px; letter-spacing: 1px; }
    .slip-header p { margin: 2px 0; font-size: 13px; color: #444; }
    
    .pass-title {
        text-align: center;
        font-size: 20px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 25px;
        color: #fff;
        background: #004080;
        padding: 10px;
        border-radius: 5px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        z-index: 1;
        position: relative;
    }

    .meta-box { display: flex; justify-content: space-between; margin-bottom: 25px; font-size: 13px; z-index: 1; position: relative; }
    .meta-col { width: 70%; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 6px 0; border-bottom: 1px solid #eee; }
    .meta-table .label { font-weight: bold; width: 150px; color: #555; text-transform: uppercase; font-size: 11px; }
    .meta-table .val { font-weight: bold; color: #000; font-size: 14px;}

    .student-photo-box { width: 25%; text-align: right; }
    .student-photo { width: 130px; height: 150px; border: 3px solid #004080; padding: 3px; object-fit: cover; background: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

    .section-title { font-weight: 900; color: #004080; text-transform: uppercase; border-bottom: 2px solid #004080; padding-bottom: 5px; margin-bottom: 15px; font-size: 14px; z-index: 1; position: relative;}

    .results-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; z-index: 1; position: relative; }
    .results-table th { background-color: #f0f4f8 !important; color: #004080 !important; padding: 8px 12px; text-transform: uppercase; font-size: 11px; text-align: left; border: 1px solid #ccc; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .results-table td { border: 1px solid #ccc; padding: 8px 12px; font-size: 13px; font-weight: bold; text-transform: uppercase;}

    .instructions-box { background: #fdfdfd; border: 1px dashed #dc3545; padding: 15px; border-radius: 5px; font-size: 11px; margin-bottom: 30px; z-index: 1; position: relative; }
    .instructions-box h6 { color: #dc3545; font-weight: 900; margin-bottom: 8px; text-transform: uppercase; }
    .instructions-box ul { margin: 0; padding-left: 15px; color: #333; }
    .instructions-box li { margin-bottom: 4px; }

    .slip-footer { display: flex; justify-content: space-between; align-items: flex-end; z-index: 1; position: relative; margin-top: 40px; }
    .sig-box { text-align: center; width: 30%; }
    .sig-line { border-top: 1px solid #000; padding-top: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
    
    .stamp-area {
        width: 100px;
        height: 100px;
        border: 2px dashed #004080;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #004080;
        font-weight: bold;
        font-size: 10px;
        text-align: center;
        opacity: 0.3;
        margin: 0 auto 10px auto;
    }

    .no-print-btn { text-align: center; margin-bottom: 20px; }

    @media print {
        body { background: white; margin: 0; }
        .no-print-btn, .app-header, footer { display: none !important; }
        .slip-wrapper { border: none; box-shadow: none; margin: 0; padding: 0; width: 100%; min-height: auto; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="container py-4">
    <div class="no-print-btn">
        <button onclick="window.print()" class="btn btn-primary shadow rounded-pill px-5 py-3 fw-bold fs-5">
            <i class="ti ti-printer me-2"></i> Print Screening Pass
        </button>
        <p class="text-muted small mt-2"><i class="ti ti-info-circle"></i> Please print this document in <strong>COLOR</strong> on A4 paper.</p>
    </div>

    <div class="slip-wrapper">
        <div class="watermark">SCREENING<br>PASS</div>

        <div class="slip-header">
            <div>
                <img src="assets/images/logos/favicon.png" height="75" class="mb-2" alt="College Logo">
                <h2>Nafisatu College of Nursing Sciences</h2>
                <p>Kwankwaso Town, Madobi LG, Kano State.</p>
                <p><strong>ADMISSIONS & REGISTRY DIVISION</strong></p>
            </div>
            <div style="text-align: right;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=90x90&data=<?= urlencode($qr_data) ?>" alt="QR Code" style="border: 2px solid #004080; padding: 3px; border-radius: 5px;">
                <div style="font-size: 9px; font-weight: bold; margin-top: 5px;">SCAN AT GATE</div>
            </div>
        </div>

        <div class="pass-title">OFFICIAL ADMISSION SCREENING PASS</div>

        <div class="section-title">1. Applicant Biodata</div>
        <div class="meta-box">
            <div class="meta-col">
                <table class="meta-table">
                    <tr><td class="label">Registration No:</td><td class="val" style="color:#dc3545; font-size: 18px;"><?= htmlspecialchars($applicant['reg_no']); ?></td></tr>
                    <tr><td class="label">Full Name:</td><td class="val text-uppercase"><?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></td></tr>
                    <tr><td class="label">Program Applied:</td><td class="val text-primary"><?= htmlspecialchars(strtoupper($applicant['program_name'] ?? 'N/A')); ?></td></tr>
                    <tr><td class="label">Phone Number:</td><td class="val"><?= htmlspecialchars($applicant['phone']); ?></td></tr>
                    <tr><td class="label">Email Address:</td><td class="val" style="text-transform: lowercase;"><?= htmlspecialchars($applicant['email']); ?></td></tr>
                    <?php if(!empty($applicant['jamb_number'])): ?>
                    <tr><td class="label">JAMB Reg No:</td><td class="val"><?= htmlspecialchars($applicant['jamb_number']); ?></td></tr>
                    <tr><td class="label">JAMB Score:</td><td class="val"><?= htmlspecialchars($applicant['jamb_score']); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="student-photo-box">
                <img src="<?= $photo_path ?>" class="student-photo" alt="Applicant Passport">
            </div>
        </div>

        <div class="section-title">2. Declared O-Level Qualifications</div>
        <?php if (empty($results)): ?>
            <p style="font-size: 13px; font-style: italic; color: #dc3545;">No O-Level subjects were submitted during application.</p>
        <?php else: ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">S/N</th>
                        <th>Subject</th>
                        <th style="width: 150px; text-align: center;">Grade Obtained</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $index => $res): ?>
                    <tr>
                        <td style="text-align: center;"><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($res['subject']) ?></td>
                        <td style="text-align: center; color: #004080;"><?= htmlspecialchars($res['grade']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="instructions-box">
            <h6><i class="ti ti-alert-circle me-1"></i> Screening Exercise Instructions & Rules</h6>
            <ul>
                <li>You must present this printed pass at the college gate. <strong>No Pass, No Entry.</strong></li>
                <li>Bring the <strong>ORIGINAL copies</strong> of your credentials (SSCE/NECO, Birth Certificate, Indigene Certificate).</li>
                <li>Any discrepancy between the grades declared on this form and your physical certificates will lead to automatic disqualification.</li>
                <li>Mobile phones, smartwatches, and programmable calculators are strictly prohibited in the exam/screening hall.</li>
                <li>Please arrive at the College campus at least <strong>1 hour</strong> before your scheduled screening time.</li>
            </ul>
        </div>

        <div class="slip-footer">
            <div class="sig-box">
                <div class="sig-line">Applicant's Signature</div>
                <div style="font-size: 11px; margin-top: 5px;">Sign in presence of Officer</div>
            </div>

            <div class="sig-box">
                <div class="stamp-area">
                    OFFICIAL<br>SCREENING<br>STAMP & DATE
                </div>
            </div>

            <div class="sig-box">
                <div class="sig-line">Screening Officer</div>
                <div style="font-size: 11px; margin-top: 5px;">Sign & Date</div>
            </div>
        </div>

        <div style="margin-top: 40px; border-top: 1px solid #ccc; padding-top: 10px; text-align: center; font-size: 10px; color: #777;">
            <strong>DISCLAIMER:</strong> This pass does not guarantee admission. It is strictly an invitation for the screening exercise.<br>
            Generated on <?= date('l, F j, Y \a\t h:i A') ?>
        </div>
    </div>
</div>

<?php require_once 'includes/frontend_footer.php'; ?>