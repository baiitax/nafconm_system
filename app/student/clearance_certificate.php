<?php
/**
 * NAFCONM - Professional Student Clearance Certificate
 * Features: Secure Serial Generation, Relational Data Fetching, Official Branding
 */
$page_title = 'Clearance Certificate';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$student = null;

// Fetch Student, Program, and Department Details via JOINS
$sql = "
    SELECT s.*, p.name as program_name, d.name as dept_name 
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
}
$stmt->close();

if (!$student) {
    require_once 'includes/sidebar.php';
    require_once 'includes/topbar.php';
    echo '<div class="container-fluid py-5"><div class="alert alert-danger shadow-sm">Student profile not found. Access denied.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

// Generate a pseudo-unique Certificate Number
$cert_no = "NAF/CLR/" . date('Y') . "/" . str_pad($student['id'], 5, '0', STR_PAD_LEFT);

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    /* Professional Certificate Design */
    .cert-outer-wrapper {
        background-color: #f8f9fa;
        padding: 40px 20px;
        min-height: 100vh;
    }

    .cert-container {
        max-width: 850px;
        margin: 0 auto;
        padding: 60px;
        background-color: #fff;
        box-shadow: 0 0 30px rgba(0,0,0,0.15);
        border: 15px double #004080;
        position: relative;
        text-align: center;
        font-family: 'Times New Roman', serif;
        color: #333;
    }

    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 90px;
        font-weight: bold;
        color: rgba(0, 64, 128, 0.03);
        white-space: nowrap;
        pointer-events: none;
        z-index: 0;
    }

    .cert-header h1 {
        margin: 0;
        color: #004080;
        font-family: 'Georgia', serif;
        font-size: 2.2em;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .cert-header h2 {
        font-weight: bold;
        font-size: 1.6em;
        color: #b30000; /* Academic Crimson */
        margin-top: 10px;
        text-decoration: underline;
    }

    .student-photo-cert {
        width: 130px;
        height: 150px;
        object-fit: cover;
        border: 4px solid #004080;
        padding: 2px;
        margin: 30px 0;
        background: #fff;
        z-index: 2;
        position: relative;
    }

    .cert-body {
        margin: 40px 0;
        font-size: 1.25em;
        line-height: 1.8;
        position: relative;
        z-index: 1;
    }

    .cert-body .student-name {
        display: block;
        font-size: 1.6em;
        font-weight: bold;
        color: #000;
        margin: 15px 0;
        font-family: 'Garamond', serif;
    }

    .signature-section {
        display: flex;
        justify-content: space-between;
        margin-top: 60px;
        padding: 0 40px;
    }

    .sig-box {
        width: 200px;
        text-align: center;
    }

    .sig-line {
        border-top: 2px solid #333;
        margin-bottom: 5px;
    }

    .sig-box p {
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.85em;
        margin: 0;
    }

    .cert-footer {
        margin-top: 50px;
        font-size: 0.85em;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        color: #666;
    }

    .no-print-btn {
        position: sticky;
        top: 80px;
        left: 20px;
        z-index: 1000;
    }

    @media print {
        .cert-outer-wrapper { background-color: #fff; padding: 0; }
        .no-print-btn, .left-sidebar, .app-header, footer { display: none !important; }
        .body-wrapper { margin-left: 0 !important; padding: 0 !important; }
        .cert-container { 
            box-shadow: none; 
            border-width: 10px; 
            margin: 0; 
            width: 100%; 
            height: 95vh;
            padding: 40px;
        }
    }
</style>

<div class="cert-outer-wrapper">
    <div class="no-print-btn">
        <button onclick="window.print()" class="btn btn-primary shadow rounded-pill px-4">
            <i class="ti ti-printer me-2"></i> Print Official Certificate
        </button>
    </div>

    <div class="cert-container">
        <div class="watermark">NAFCONM OFFICIAL</div>

        <div class="cert-header">
            <img src="<?= BASE_URL ?>assets/images/logos/favicon.png" alt="Logo" class="mb-3" style="max-height: 100px;">
            <h1>Nafisatu College of Nursing and Midwifery</h1>
            <p class="mb-0 fw-bold">Kano State, Nigeria</p>
            <h2>Student Clearance Certificate</h2>
        </div>

        <div class="cert-body">
            <p class="mb-1">This is to certify that</p>
            
            <img src="<?= BASE_URL . htmlspecialchars($student['photo_path'] ?? 'assets/images/default_avatar.png'); ?>" class="student-photo-cert">

            <span class="student-name"><?= htmlspecialchars(strtoupper($student['full_name'])); ?></span>
            
            <p>
                With Student ID Number <strong><?= htmlspecialchars($student['student_id_number']); ?></strong>, 
                having successfully completed the course of study in the <br>
                <strong><?= htmlspecialchars(strtoupper($student['program_name'] ?? 'N/A')); ?></strong> department, 
                is hereby cleared of all <strong>Academic, Financial, and Administrative</strong> obligations 
                to this institution as of this date.
            </p>
        </div>

        <div class="signature-section">
            <div class="sig-box">
                <div class="sig-line"></div>
                <p>College Registrar</p>
            </div>
            
            <div style="width: 100px; height: 100px; border: 2px dashed #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ddd; font-size: 10px;">
                OFFICIAL SEAL
            </div>

            <div class="sig-box">
                <div class="sig-line"></div>
                <p>Bursar / Fin. Director</p>
            </div>
        </div>

        <div class="cert-footer">
            <div class="text-start">
                <strong>Serial No:</strong> <?= $cert_no ?><br>
                <strong>Date Issued:</strong> <?= date("F j, Y") ?>
            </div>
            <div class="text-end">
                <div style="border: 1px solid #ccc; padding: 5px; background: #f9f9f9; width: 70px; height: 70px; display: inline-block;">
                    <small style="font-size: 8px;">SCAN TO<br>VERIFY</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>