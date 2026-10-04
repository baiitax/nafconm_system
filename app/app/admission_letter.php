<?php
session_start(); // It's good practice to start a session
include_once '../config/db.php';

// --- Fetch Student Data ---
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = null;

if ($student_id > 0) {
    // Use a prepared statement to prevent SQL injection
    $sql = "SELECT s.*, d.name as department_name 
            FROM students s 
            LEFT JOIN departments d ON s.department_id = d.id 
            WHERE s.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id); // "i" means the parameter is an integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
    $stmt->close();
}

$conn->close();

if (!$student) {
    echo "Admission details not found. Please provide a valid student ID.";
    exit;
}

// Format the date for display
$admission_date = date("jS F, Y");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provisional Admission Offer - Nafisatu College of Nursing Sciences</title>
    <style>
        body {
            font-family: 'Garamond', 'Times New Roman', Times, serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        .page-container {
            max-width: 850px;
            margin: 30px auto;
            padding: 40px;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-top: 10px solid #004080; /* A professional color */
            position: relative;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }
        .header img {
            max-height: 90px;
        }
        .header .school-info h3 {
            margin: 0;
            color: #004080;
            font-size: 1.5em;
        }
        .header .school-info p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .student-photo {
            position: absolute;
            top: 40px;
            right: 40px;
            width: 120px;
            height: 140px;
            border: 3px solid #eee;
            object-fit: cover;
        }
        .details-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .details-table td {
            padding: 4px 0;
        }
        .student-details-table {
            width: 70%; /* Don't overlap with photo */
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .student-details-table td {
            padding: 8px 5px;
            border-bottom: 1px dotted #aaa;
        }
        .student-details-table td:first-child {
            font-weight: bold;
            width: 150px;
        }
        .section-heading {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 25px 0 15px 0;
            font-size: 1.2em;
            color: #333;
        }
        .content p {
            text-align: justify;
        }
        .conditions ol {
            padding-left: 20px;
        }
        .conditions li {
            margin-bottom: 12px;
            text-align: justify;
        }
        .signature-block {
            margin-top: 60px;
            text-align: left;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-top: 40px;
            display: block;
        }
        .note {
            font-style: italic;
            margin-top: 20px;
            border-left: 4px solid #004080;
            padding-left: 15px;
            background-color: #f8f9fa;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        hr {
            border: 0;
            height: 1px;
            background-color: #ddd;
            margin: 30px 0;
        }
        @media print {
            body { background-color: #fff; }
            .page-container {
                border: none;
                margin: 0;
                box-shadow: none;
                max-width: 100%;
            }
            .student-photo {
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<div class="page-container">

    <div class="header">
        <!-- Make sure the path to your logo is correct -->
        <img src="../assets/images/logo.png" alt="College Logo">
        <div class="school-info">
            <h3>NAFISATU COLLEGE OF NURSING SCIENCES KWANKWASO</h3>
            <p>Address: Kwankwaso Town Kano State | Liaison Office: No. 39A Justice Dahiru Mustapha Street, Farm Centre, P.O.Box 365, Kano</p>
            <p>E-mail: <strong>nafconm@gmail.com</strong> | Tel: <strong>08025337300</strong></p>
        </div>
    </div>

    <!-- Make sure the path to the student's photo is correct -->
    <img src="<?php echo htmlspecialchars($student['photo_path'] ?? '../assets/images/default_avatar.png'); ?>" alt="Student Photo" class="student-photo">

    <table class="details-table">
        <tr>
            <td>Our Ref: NCNS/KKS/SOM/25/57/<?php echo htmlspecialchars($student['id']); ?></td>
            <td style="text-align: right;">Date: <strong><?php echo $admission_date; ?></strong></td>
        </tr>
    </table>

    <table class="student-details-table">
        <tr>
            <td><strong>NAME:</strong></td>
            <td><?php echo htmlspecialchars(strtoupper($student['full_name'])); ?></td>
        </tr>
        <tr>
            <td><strong>STUDENT ID:</strong></td>
            <td><?php echo htmlspecialchars($student['student_id_number']); ?></td>
        </tr>
        <tr>
            <td><strong>ADDRESS:</strong></td>
            <td><?php echo htmlspecialchars(strtoupper($student['address'] ?? 'N/A')); ?></td>
        </tr>
    </table>

    <h3 class="section-heading">PROVISIONAL OFFER OF ADMISSION INTO <?php echo htmlspecialchars(strtoupper($student['program_of_study'] ?? 'BASIC MIDWIFERY')); ?> PROGRAMME</h3>

    <div class="content">
        <p>I am pleased to inform you that, following the selection interview, you have been offered <strong>provisional admission</strong> into the Nafisatu College of Nursing Sciences to pursue a three (3) year <strong><?php echo htmlspecialchars(strtoupper($student['program_of_study'] ?? 'BASIC MIDWIFERY')); ?> PROGRAMME</strong>.</p>
        <p>You are required to report to the school on <strong>14th April, 2025</strong> for registration and documentation, bringing the original copies of your credentials.</p>
    </div>
    
    <hr>

    <h3 class="section-heading" style="text-decoration:none;">Conditions of Offer</h3>
    <div class="conditions">
        <ol>
            <li>You must present the <strong>ORIGINAL and one PHOTOCOPY</strong> of each of the following credentials: Primary School Leaving Certificate, SSCE Statement of Result (WAEC/NECO), School Testimonial, Birth Certificate/Declaration of Age, Certificate of Medical Fitness, and two (2) recent passport photographs.</li>
            <li>The particulars you provided on your application form must be correct. If it is discovered that you do not possess the required qualifications, you will be withdrawn from the program immediately.</li>
            <li>No change of name will be accepted by the College throughout your training period after registration.</li>
            <li>Registration commences from <strong>14th April, 2025 to 2nd May, 2025</strong>. Failure to register within this period may render this offer invalid.</li>
        </ol>
        <p style="text-align: center; font-weight: bold; margin-top: 20px; font-size: 1.2em;">Congratulations!</p>
    </div>

    <hr>

    <h3 class="section-heading" style="text-decoration:none;">School Fees and Payment Details</h3>
    <div class="content">
        <p>An initial part payment of <strong>Two Hundred and Seventy Thousand Naira (N270,000)</strong> is required on or before the reporting date. The remaining balance must be settled before the end of the session. <strong>Cash payments will not be accepted.</strong></p>
        <p>All payments should be made through the following bank details:</p>
        <div class="note">
            <strong>BANK:</strong> U.B.A. PLC<br>
            <strong>ACCOUNT NAME:</strong> NAFISATU COLLEGE OF NURSING SCIENCES LIMITED<br>
            <strong>ACCOUNT NUMBER:</strong> 1022290138
        </div>
        <p>Please note that you will be responsible for all statutory charges from the <strong>Nursing and Midwifery Council of Nigeria</strong> (e.g., indexing fee, external examination fee, uniform fee, and Clinical Log Book).</p>
    </div>

    <div class="signature-block">
        <p>Sincerely,</p>
        <span class="signature-line"></span>
        <strong>MAL. RAKIYA SULE TAHIR</strong><br>
        DIRECTOR, SCHOOL OF MIDWIFERY
    </div>

</div>

</body>
</html>

