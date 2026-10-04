<?php
/**
 * NAFCONM - Print Admission Letter & Institutional Rules
 * Features: Resilient SQL Logic, Multi-page CSS Print formatting, Official Regulations
 */
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    die("Unauthorized access.");
}

$user_id = $_SESSION["user_id"];

// BUG FIX: Removed strict JOIN to admission_applications. 
// If they have a student profile, they are officially admitted.
$sql = "SELECT s.id as student_profile_id, s.student_id_number, s.full_name, 
               p.name as program_name, u.created_at 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN programs p ON s.program_id = p.id 
        WHERE s.user_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Profile Not Found</h2><p>We could not locate your official student profile. Please contact the ICT department.</p></div>");
}

// Format the admission date and academic year dynamically
$admission_date = date('jS F, Y', strtotime($app['created_at']));
$academic_year = date('Y', strtotime($app['created_at'])) . "/" . (date('Y', strtotime($app['created_at'])) + 1);
$program_name = $app['program_name'] ? htmlspecialchars($app['program_name']) : 'Nursing/Midwifery Program';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admission Packet - <?= htmlspecialchars($app['student_id_number']) ?></title>
    <style>
        body { font-family: 'Times New Roman', Times, serif; background: #e2e8f0; margin: 0; padding: 20px; color: #000; }
        
        /* A4 Page Styling */
        .print-page { background: #fff; width: 210mm; min-height: 297mm; margin: 0 auto 20px auto; padding: 20mm; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative; box-sizing: border-box; }
        
        /* Watermark */
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; width: 60%; z-index: 0; pointer-events: none; }
        
        .content { position: relative; z-index: 1; }
        
        /* Typography & Layout */
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { font-size: 24px; text-transform: uppercase; margin: 0; color: #1e293b; letter-spacing: 1px; }
        .header p { font-size: 13px; font-weight: bold; margin: 5px 0 0 0; text-transform: uppercase; }
        
        .ref-section { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 30px; font-size: 14px; }
        .title { text-align: center; text-decoration: underline; text-transform: uppercase; font-size: 18px; font-weight: bold; margin-bottom: 25px; }
        .sub-title { font-size: 16px; font-weight: bold; text-decoration: underline; margin-top: 25px; margin-bottom: 10px; }
        
        .body-text { font-size: 15px; line-height: 1.6; text-align: justify; }
        .body-text p { margin-bottom: 15px; }
        
        /* Lists for Rules */
        .rules-list { padding-left: 20px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; text-align: justify; }
        .rules-list li { margin-bottom: 10px; }
        
        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; }
        .sign-box { text-align: center; width: 35%; }
        .sign-line { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; font-size: 14px; }
        
        /* Print Button */
        .no-print-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 250px; margin: 0 auto 20px auto; padding: 14px; background: #1e293b; color: white; border-radius: 50px; text-decoration: none; font-family: Arial, sans-serif; font-weight: bold; cursor: pointer; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.3s; }
        .no-print-btn:hover { background: #0f172a; transform: translateY(-2px); }
        
        /* Print Rules */
        @media print { 
            body { background: #fff; padding: 0; } 
            .print-page { box-shadow: none; margin: 0; padding: 15mm; width: 100%; min-height: 100vh; } 
            .no-print-btn { display: none; } 
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>

    <button class="no-print-btn" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print Admission Packet
    </button>

    <!-- PAGE 1: ADMISSION LETTER -->
    <div class="print-page">
        <img src="../assets/images/logos/favicon.png" class="watermark" alt="">
        <div class="content">
            <div class="header">
                <img src="../assets/images/logos/favicon.png" alt="Logo" width="80" style="margin-bottom: 10px;">
                <h1>Nafisatu College of Nursing & Midwifery</h1>
                <p>Kano State, Nigeria | Excellence in Healthcare Education</p>
            </div>
            
            <div class="ref-section">
                <div>Ref: NAF/ADM/<?= date('Y') ?>/<?= str_pad($app['student_profile_id'], 4, '0', STR_PAD_LEFT) ?></div>
                <div>Date: <?= $admission_date ?></div>
            </div>
            
            <div class="body-text">
                <p>Dear <strong><?= strtoupper(htmlspecialchars($app['full_name'])) ?></strong>,</p>
                
                <div class="title">Offer of Provisional Admission</div>
                
                <p>Following your successful performance in our recent entrance examination and interview, I am pleased to inform you that you have been offered provisional admission into the <strong><?= $program_name ?></strong> program at Nafisatu College of Nursing and Midwifery for the <strong><?= $academic_year ?></strong> Academic Session.</p>
                
                <p>Your official Matriculation Number is: <strong><?= htmlspecialchars($app['student_id_number']) ?></strong></p>
                
                <p>Please note that this offer is strictly provisional and is subject to the following conditions:</p>
                <ol class="rules-list">
                    <li>Verification of your O'Level credentials and other documents provided during your application.</li>
                    <li>Payment of the non-refundable acceptance fee and full tuition fees within the stipulated period.</li>
                    <li>Successful medical fitness clearance by the College Clinic.</li>
                    <li>Strict adherence to the Nursing and Midwifery Council of Nigeria (NMCN) guidelines and College rules.</li>
                </ol>
                
                <p>If at any point it is discovered that false information or forged credentials were provided, this admission will be automatically withdrawn and you may be handed over to law enforcement agencies.</p>
                
                <p>You are required to log into the Student Portal using your Matriculation Number to generate your Fee Invoice and proceed with your course registration immediately.</p>
                
                <p>Congratulations, and welcome to NAFCONM.</p>
            </div>
            
            <div class="signatures">
                <div class="sign-box"><div class="sign-line">Admissions Officer</div></div>
                <div class="sign-box"><div class="sign-line">College Registrar</div></div>
            </div>
        </div>
    </div>

    <!-- PAGE 2: RULES AND REGULATIONS (Forced to new page on print) -->
    <div class="print-page page-break">
        <div class="content">
            <div class="header">
                <h1>Code of Conduct & Regulations</h1>
                <p>Institutional Rules for Students</p>
            </div>
            
            <div class="body-text">
                <p>As a student of Nafisatu College of Nursing & Midwifery, you are expected to uphold the highest standards of academic integrity, professional ethics, and moral conduct. By accepting this admission, you agree to abide by the following rules:</p>

                <div class="sub-title">1. Academic Regulations</div>
                <ul class="rules-list">
                    <li><strong>Attendance:</strong> A minimum of 75% attendance is required in all lectures, practicals, and clinical postings to be eligible to sit for semester examinations.</li>
                    <li><strong>Examinations:</strong> Any form of examination malpractice (cheating, impersonation, or bringing unauthorized materials into the hall) will result in immediate expulsion.</li>
                    <li><strong>Course Registration:</strong> All courses must be registered online within the first two weeks of resumption. Late registration will attract a penalty fee.</li>
                </ul>

                <div class="sub-title">2. Professional & Clinical Conduct</div>
                <ul class="rules-list">
                    <li><strong>Uniforms:</strong> Students must appear in the approved College uniform at all times during official hours, clinical postings, and college events. The uniform must be kept clean, neat, and professional.</li>
                    <li><strong>Patient Confidentiality:</strong> Strict adherence to patient privacy is mandatory. Taking photographs of patients or sharing clinical information on social media is a severe offense punishable by expulsion.</li>
                    <li><strong>Insubordination:</strong> Respect for lecturers, clinical instructors, hospital staff, and fellow students is mandatory. Acts of insubordination or physical altercation will not be tolerated.</li>
                </ul>

                <div class="sub-title">3. General Campus & Hostel Regulations</div>
                <ul class="rules-list">
                    <li><strong>Curfew:</strong> Students residing in the College Hostel must adhere to the 10:00 PM curfew. No student is allowed outside the hostel premises after this time without official written permission.</li>
                    <li><strong>Contraband:</strong> Possession, use, or distribution of illicit drugs, alcohol, weapons, or any hazardous materials on campus or in the hostel is strictly prohibited.</li>
                    <li><strong>Property Damage:</strong> Willful damage to college property, laboratory equipment, or hostel facilities will result in severe disciplinary action, including the cost of replacement.</li>
                </ul>
                
                <p style="font-style: italic; font-size: 13px; text-align: center; margin-top: 30px;">
                    * The College Management reserves the right to amend these rules and regulations at any time. Ignorance of the rules is not an excuse.
                </p>
            </div>
        </div>
    </div>

    <!-- PAGE 3: ACCEPTANCE & DECLARATION FORM -->
    <div class="print-page page-break">
        <div class="content">
            <div class="header" style="margin-bottom: 50px;">
                <h1>Acceptance of Offer & Declaration</h1>
                <p>To be completed, signed, and submitted to the Registry</p>
            </div>
            
            <div class="body-text" style="line-height: 2.2;">
                <p>I, <strong>________________________________________________________________________________</strong>,</p>
                <p>with Matriculation Number <strong><?= htmlspecialchars($app['student_id_number']) ?></strong>, hereby acknowledge the receipt of the Offer of Provisional Admission into the <strong><?= $program_name ?></strong> program for the <strong><?= $academic_year ?></strong> Academic Session.</p>
                
                <p>I declare that I have carefully read and fully understood the <strong>Code of Conduct & Regulations</strong> of Nafisatu College of Nursing & Midwifery attached to this admission letter.</p>
                
                <p>I solemnly pledge to:</p>
                <ul class="rules-list" style="line-height: 2.0; font-weight: bold;">
                    <li>Abide by all the rules, regulations, and clinical ethics of the College.</li>
                    <li>Pay all required fees within the stipulated deadlines.</li>
                    <li>Devote my time strictly to my studies and clinical practice.</li>
                    <li>Accept any disciplinary action, including expulsion, should I violate any of the College's regulations or be found guilty of forging my admission credentials.</li>
                </ul>

                <div style="margin-top: 60px;">
                    <p><strong>Student's Signature:</strong> ___________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>Date:</strong> ___________________________</p>
                </div>
                
                <div style="margin-top: 50px; padding: 20px; border: 2px dashed #000; background: #fafafa;">
                    <h3 style="text-align:center; margin-top:0; text-decoration:underline; font-size:16px;">FOR OFFICIAL USE ONLY</h3>
                    <p><strong>Cleared By (Name):</strong> ______________________________________________________________</p>
                    <p><strong>Designation:</strong> _______________________________________________________________________</p>
                    <p><strong>Signature & Stamp:</strong> ___________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>Date:</strong> ___________________________</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>