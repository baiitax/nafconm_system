<?php
session_start();
require_once 'config/db.php';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    die("Invalid Application Token.");
}

// Check if accessing via Token (Tracking page) or ID (Student Dashboard)
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$token = isset($_GET['token']) ? mysqli_real_escape_string($conn, $_GET['token']) : '';

// Fetch application details to ensure they are actually accepted
$stmt = $conn->prepare("SELECT a.*, p.name as program_name 
                        FROM admission_applications a 
                        JOIN programs p ON a.program_id = p.id 
                        WHERE a.token = ? AND a.status = 'accepted' LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Admission letter not found or application has not been accepted yet.");
}

$app = $result->fetch_assoc();

// Format variables for the letter
$full_name = strtoupper(htmlspecialchars($app['last_name'] . ', ' . $app['first_name'] . ' ' . $app['middle_name']));
$program_name = strtoupper(htmlspecialchars($app['program_name']));
$current_date = date('jS F, Y');
$academic_session = date('Y') . '/' . (date('Y') + 1); // e.g., 2026/2027

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - <?= htmlspecialchars($app['token']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* A4 Paper Styling */
        body {
            background-color: #f0f0f0;
            font-family: 'Times New Roman', Times, serif; /* Official document font */
            color: #000;
        }
        .a4-paper {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: #fff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            width: 400px;
            z-index: 0;
            pointer-events: none;
        }
        .content {
            position: relative;
            z-index: 1;
        }
        .letterhead {
            border-bottom: 2px solid #b30000;
            padding-bottom: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .letterhead h1 {
            color: #b30000;
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 5px;
            font-family: Arial, sans-serif;
        }
        .letterhead p {
            font-size: 14px;
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .ref-bar {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 40px;
        }
        .letter-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 18px;
            margin-bottom: 30px;
        }
        .letter-body p {
            line-height: 1.6;
            text-align: justify;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .sign-block {
            margin-top: 60px;
            width: 250px;
        }
        .sign-block hr {
            border-top: 2px solid #000;
            margin-bottom: 5px;
            opacity: 1;
        }
        /* Print Styles to hide background and shadow */
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .a4-paper { margin: 0; box-shadow: none; border: none; padding: 15mm; width: 100%; height: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-dark fw-bold px-4 py-2 rounded-pill shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer me-2" viewBox="0 0 16 16">
              <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
              <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Print Document
        </button>
    </div>

    <div class="a4-paper">
        <div class="content">
            <div class="letterhead">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <img src="assets/img/logo.png" alt="NAFCONM Logo" style="height: 80px;" onerror="this.style.display='none'">
                    <div>
                        <h1>NAFISATU COLLEGE OF NURSING SCIENCES</h1>
                        <p><strong>Motto:</strong> Knowledge, Compassion, and Excellence</p>
                        <p>Kano State, Nigeria | Email: info@nafconm.edu.ng | Phone: +234 (0) 123 456 7890</p>
                    </div>
                </div>
            </div>

            <div class="ref-bar">
                <div>Ref No: NAFCONM/ADM/<?= $academic_session ?>/<?= htmlspecialchars($app['token']) ?></div>
                <div>Date: <?= $current_date ?></div>
            </div>

            <div class="mb-4">
                <strong>TO:</strong><br>
                <?= $full_name ?><br>
                <?= htmlspecialchars($app['phone']) ?><br>
                <?= htmlspecialchars($app['email']) ?>
            </div>

            <div class="letter-title">
                OFFER OF PROVISIONAL ADMISSION FOR <?= $academic_session ?> ACADEMIC SESSION
            </div>

            <div class="letter-body">
                <p>Dear <?= htmlspecialchars(ucfirst(strtolower($app['first_name']))) ?>,</p>

                <p>Following your recent application and subsequent screening exercise, I am pleased to inform you that you have been offered <strong>Provisional Admission</strong> into the Nafisatu College of Nursing Sciences (NAFCONM) for the <?= $academic_session ?> Academic Session.</p>

                <p>You have been admitted to pursue a course of study leading to the award of a Certificate/Diploma in:</p>
                
                <p class="text-center fw-bold fs-5 my-3">"<?= $program_name ?>"</p>

                <p>Please note that this admission is strictly provisional and is subject to your possessing the minimum entry requirements as prescribed by the College and the Nursing and Midwifery Council of Nigeria (NMCN).</p>

                <p><strong>CONDITIONS OF ADMISSION:</strong></p>
                <ol>
                    <li class="mb-2">You are required to log into your Student Portal immediately using your Registration Token to print your fee schedule and proceed with the payment of your acceptance and tuition fees.</li>
                    <li class="mb-2">You must present the original copies of your academic credentials (WAEC/NECO/NABTEB), Birth Certificate, and Indigene Form for verification during the physical clearance exercise.</li>
                    <li class="mb-2">This offer will be revoked if it is discovered at any time that you presented forged or falsified documents.</li>
                    <li class="mb-2">Failure to complete your registration and pay the required fees within two (2) weeks of this notice will result in the automatic forfeiture of this admission offer.</li>
                </ol>

                <p>On behalf of the Management and Academic Board, I congratulate you on your successful admission and welcome you to the College.</p>
            </div>

            <div class="sign-block">
                <br><br><br>
                <hr>
                <strong>Hajiya Fatima Abdullah</strong><br>
                <em>Registrar</em><br>
                Nafisatu College of Nursing Sciences
            </div>
            
        </div>
    </div>

    <script>
        // Automatically trigger print dialog when the page loads (optional)
        window.onload = function() {
            // setTimeout(() => window.print(), 500);
        };
    </script>
</body>
</html>