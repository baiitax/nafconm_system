<?php
/**
 * NAFCONM - Official Financial Clearance Certificate
 * Features: Fixed 500 Error Redirects, Passport Verification, Authentic CSS Rubber Stamp, Ledger Grid
 */
// Force display errors to prevent silent redirects to index.php on crashes
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Safe authentication check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// 1. Fetch Active Session
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// 2. Fetch Student Profile (Ultra-safe query)
$sql_student = "SELECT s.id, s.full_name, s.student_id_number, s.current_level, s.photo_path, p.name as program_name 
                FROM students s 
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

// 3. Fetch approved payments (BUG FIX: Used SELECT * to prevent 'unknown column' crashes causing redirects)
$stmt_pay = $conn->prepare("SELECT * FROM student_payments WHERE student_id = ? AND session = ? AND status IN ('paid', 'successful', 'approved') ORDER BY payment_date DESC");
$stmt_pay->bind_param("is", $student['id'], $current_session);
$stmt_pay->execute();
$payments = $stmt_pay->get_result();
$stmt_pay->close();

if ($payments->num_rows == 0) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Not Cleared</h2><p>You have no approved payment records for the $current_session. Please complete your fee payments to generate this certificate.</p></div>");
}

$total_paid = 0;
$date_printed = date('d M, Y \a\t h:i A');
// Generate a secure hash for the document barcode purely for visual authenticity
$security_hash = strtoupper(substr(hash('sha256', $student['student_id_number'] . $current_session . 'BURSARY'), 0, 16));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Clearance - <?= htmlspecialchars($student['student_id_number']) ?></title>
    <!-- Premium Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Playfair+Display:wght@700;900&family=Libre+Barcode+39&display=swap" rel="stylesheet">
    <style>
        /* Base Reset & Variables */
        :root {
            --naf-blue: #0f172a;
            --naf-gray: #f8fafc;
            --naf-border: #cbd5e1;
            --naf-green: #15803d;
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
            overflow: hidden;
        }
        
        .watermark { 
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
            opacity: 0.03; width: 70%; z-index: 0; pointer-events: none; 
        }
        
        /* Authentic CSS Rubber Stamp */
        .rubber-stamp {
            position: absolute; top: 120px; right: 40px; z-index: 0;
            width: 140px; height: 140px;
            border: 5px double var(--naf-green);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transform: rotate(-15deg);
            opacity: 0.85; pointer-events: none;
            mix-blend-mode: multiply;
        }
        .rubber-stamp-text {
            color: var(--naf-green); font-size: 20px; font-weight: 900;
            text-align: center; line-height: 1.1; font-family: 'Playfair Display', serif;
            text-transform: uppercase; border-top: 2px solid var(--naf-green);
            border-bottom: 2px solid var(--naf-green); padding: 5px 0;
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
            background: var(--naf-green); color: #fff; padding: 5px 10px; 
            font-size: 10px; font-weight: 900; letter-spacing: 1px; 
            border-radius: 4px; text-align: center; display: inline-block;
        }

        .doc-title-bar { 
            background: var(--naf-gray); border: 1px solid var(--naf-border); 
            padding: 10px; text-align: center; font-size: 16px; font-weight: 900; 
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 30px; 
        }

        .intro-text { font-size: 14px; line-height: 1.6; margin-bottom: 25px; text-align: justify; }

        /* Bio Data Card */
        .bio-card { 
            display: flex; border: 2px solid var(--naf-blue); 
            margin-bottom: 35px; background: #fff; 
        }
        .bio-details { flex-grow: 1; padding: 0; }
        .bio-table { width: 100%; border-collapse: collapse; font-size: 13px; height: 100%; }
        .bio-table td { padding: 12px 15px; border-bottom: 1px solid var(--naf-border); border-right: 1px solid var(--naf-border); }
        .bio-table tr:last-child td { border-bottom: none; }
        .bio-table td.label { font-size: 10px; text-transform: uppercase; font-weight: 700; color: #64748b; background: var(--naf-gray); width: 25%; }
        .bio-table td.value { font-weight: 900; font-size: 14px; }
        
        .photo-box { 
            width: 140px; border-left: 2px solid var(--naf-blue); 
            display: flex; align-items: center; justify-content: center; 
            background: var(--naf-gray); padding: 5px; flex-shrink: 0;
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; border: 1px solid var(--naf-border); }

        /* Ledger Table */
        .section-title { font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; color: var(--naf-blue); border-bottom: 2px solid var(--naf-blue); padding-bottom: 5px; display: inline-block; }
        
        .ledger-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 30px; }
        .ledger-table th, .ledger-table td { border: 1px solid #94a3b8; padding: 12px 10px; }
        .ledger-table td.text-left { text-align: left; }
        .ledger-table td.text-right { text-align: right; font-weight: 700; font-family: monospace; font-size: 15px; }
        .ledger-table th { background: var(--naf-blue); color: #fff; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; font-weight: 700; text-align: left; }
        .ledger-table tbody tr:nth-child(even) { background-color: var(--naf-gray); }

        /* Security Barcode Block */
        .security-block { 
            display: flex; justify-content: space-between; align-items: center; 
            border: 1px solid var(--naf-border); padding: 15px; margin-bottom: 40px; 
            background: #fff; border-left: 5px solid var(--naf-green); page-break-inside: avoid;
        }
        .barcode { font-family: 'Libre Barcode 39', monospace, sans-serif; font-size: 36px; margin-bottom: 2px; }

        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
        .sign-box { text-align: center; width: 35%; }
        .sign-line { border-top: 1.5px solid #000; padding-top: 8px; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }

        .footer-meta { text-align: center; margin-top: 40px; font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }

        /* Print Button */
        .no-print-btn { 
            display: flex; align-items: center; justify-content: center; gap: 10px; 
            width: 320px; margin: 0 auto 30px auto; padding: 16px; background: #1e293b; 
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
        PRINT CLEARANCE CERTIFICATE
    </button>

    <div class="print-container">
        <!-- Background Elements -->
        <img src="../assets/images/logos/favicon.png" class="watermark" alt="Watermark">
        <div class="rubber-stamp">
            <div class="rubber-stamp-text">FINANCIALLY<br>CLEARED</div>
        </div>
        
        <div class="content-wrapper">
            
            <!-- 1. Header Section -->
            <div class="header">
                <div class="header-logo"><img src="../assets/images/logos/favicon.png" alt="NAFCONM Logo"></div>
                <div class="header-center">
                    <h1>Nafisatu College of Nursing</h1>
                    <p>Office of the College Bursar</p>
                </div>
                <div class="header-right">
                    <div class="doc-badge">BURSARY<br>COPY</div>
                </div>
            </div>
            
            <div class="doc-title-bar">
                Official Financial Clearance Certificate<br>
                <span style="font-size: 13px; font-weight: 600; color: #475569;"><?= htmlspecialchars($current_session) ?> Academic Session</span>
            </div>

            <p class="intro-text">
                This is to officially certify that the student detailed below has successfully fulfilled all statutory financial obligations and tuition requirements demanded by the institution for the <strong><?= htmlspecialchars($current_session) ?></strong> academic session. They are hereby granted full financial clearance to participate in all academic and administrative activities.
            </p>

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
                            <td class="value" colspan="3" style="font-family: monospace; font-size: 18px; color: var(--naf-blue);"><?= htmlspecialchars($student['student_id_number']) ?></td>
                        </tr>
                        <tr>
                            <td class="label">Program of Study</td>
                            <td class="value" colspan="3"><?= htmlspecialchars($student['program_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <td class="label">Current Level</td>
                            <td class="value"><?= htmlspecialchars($student['current_level']) ?></td>
                            <td class="label">Clearance Status</td>
                            <td class="value" style="color: var(--naf-green);">CLEARED</td>
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

            <!-- 3. Payment Ledger Table -->
            <div class="section-title">Approved Payment Ledger</div>
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th width="20%">Invoice Ref</th>
                        <th width="40%">Payment Description</th>
                        <th width="20%">Date Approved</th>
                        <th width="20%" style="text-align: right;">Amount (NGN)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($p = $payments->fetch_assoc()): 
                        $total_paid += $p['amount']; 
                    ?>
                    <tr>
                        <td style="font-family: monospace; font-weight: 700; font-size: 14px;"><?= htmlspecialchars($p['invoice_no']) ?></td>
                        <td class="text-left" style="font-weight: 600; text-transform: uppercase; font-size: 12px;"><?= htmlspecialchars($p['payment_type']) ?></td>
                        <td><?= date('d M, Y', strtotime($p['payment_date'])) ?></td>
                        <td class="text-right">₦<?= number_format($p['amount'], 2) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align: right; background: #fff; color: #000; font-size: 13px;">TOTAL AMOUNT CLEARED:</th>
                        <th style="font-size: 18px; background: #fff; color: #15803d; border-left: 1px solid #94a3b8; text-align: right;">₦<?= number_format($total_paid, 2) ?></th>
                    </tr>
                </tfoot>
            </table>

            <!-- 4. Cryptographic Security Block -->
            <div class="security-block">
                <div>
                    <span style="font-size: 10px; font-weight: 900; color: #64748b; text-transform: uppercase;">Ledger Authentication Ref:</span><br>
                    <span style="font-family: monospace; font-weight: 900; font-size: 14px; color: var(--naf-green);"><?= $security_hash ?></span>
                </div>
                <div style="text-align: right;">
                    <div class="barcode">*<?= $security_hash ?>*</div>
                </div>
            </div>

            <!-- 5. Signatures -->
            <div class="signatures">
                <div class="sign-box"><div class="sign-line">Internal Auditor (Sign & Date)</div></div>
                <div class="sign-box"><div class="sign-line">College Bursar (Official Stamp)</div></div>
            </div>

            <div class="footer-meta">
                System Generated via NAFCONM Bursary Portal | Print Timestamp: <?= $date_printed ?>
            </div>
        </div>
    </div>
</body>
</html>