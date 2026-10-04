<?php
/**
 * NAFCONM - Professional Academic Transcript
 * Features: GPA/CGPA Calculation, Letter Grade Mapping, and Print Optimization
 */
$page_title = 'Academic Transcript';
require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];
$student = null;
$transcript_data = [];

// 1. Fetch Student & Program Details
$stmt = $conn->prepare("
    SELECT s.*, p.name as program_name, d.name as dept_name 
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.user_id = ? LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    require_once 'includes/sidebar.php';
    require_once 'includes/topbar.php';
    echo '<div class="container-fluid"><div class="alert alert-danger">Student profile not found.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

// 2. Fetch Performance Data with Letter Grades (Based on Grading Policy)
$score_stmt = $conn->prepare("
    SELECT 
        ses.name as session_name, 
        c.course_code, 
        c.course_name, 
        c.credit_units,
        AVG(sc.score / a.max_marks * 100) as final_percentage,
        gp.letter_grade,
        gp.grade_point
    FROM assessment_scores sc
    JOIN assessments a ON sc.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN sessions ses ON a.session_id = ses.id
    LEFT JOIN grading_policy gp ON (sc.score / a.max_marks * 100) BETWEEN gp.min_score AND gp.max_score
    WHERE sc.student_id = ?
    GROUP BY c.id, ses.id
    ORDER BY ses.start_date DESC, c.course_code ASC
");
$score_stmt->bind_param("i", $student['id']);
$score_stmt->execute();
$scores_result = $score_stmt->get_result();

while($row = $scores_result->fetch_assoc()) {
    $transcript_data[$row['session_name']][] = $row;
}
$score_stmt->close();

require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<style>
    .transcript-wrapper { 
        background: #fff; 
        max-width: 900px; 
        margin: 20px auto; 
        padding: 50px; 
        border: 1px solid #ddd; 
        position: relative;
        font-family: 'Times New Roman', serif;
    }
    
    .watermark {
        position: absolute;
        top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 100px; font-weight: bold; color: rgba(0, 64, 128, 0.03);
        white-space: nowrap; z-index: 0; pointer-events: none;
    }

    .t-header { text-align: center; border-bottom: 2px solid #004080; padding-bottom: 15px; margin-bottom: 30px; }
    .t-header h2 { color: #004080; font-family: Arial, sans-serif; margin: 0; text-transform: uppercase; font-size: 24px; }
    
    .info-table { width: 100%; margin-bottom: 25px; border: none; font-size: 14px; }
    .info-table td { padding: 4px 0; }
    .label { font-weight: bold; width: 140px; text-transform: uppercase; color: #555; }
    
    .session-title { 
        background: #f8f9fa; border-left: 5px solid #004080; 
        padding: 8px 15px; font-weight: bold; margin-top: 30px;
        text-transform: uppercase; font-size: 16px;
    }

    .course-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .course-table th { border-bottom: 2px solid #333; padding: 10px; text-align: left; font-size: 12px; text-transform: uppercase; }
    .course-table td { border-bottom: 1px solid #eee; padding: 10px; font-size: 14px; }
    
    .cgpa-box { 
        margin-top: 20px; text-align: right; font-size: 18px; font-weight: bold;
        border-top: 2px solid #004080; padding-top: 10px;
    }

    .no-print-btn { position: sticky; top: 90px; z-index: 1000; }

    @media print {
        .no-print-btn, .left-sidebar, .app-header, footer { display: none !important; }
        .body-wrapper { margin: 0 !important; padding: 0 !important; }
        .transcript-wrapper { border: none; box-shadow: none; width: 100%; padding: 10px; }
        body { background: white; }
    }
</style>

<div class="container-fluid">
    <div class="no-print-btn text-end mb-3">
        <button onclick="window.print()" class="btn btn-dark shadow rounded-pill px-4">
            <i class="ti ti-printer me-2"></i> Print Official Transcript
        </button>
    </div>

    <div class="transcript-wrapper shadow-sm">
        <div class="watermark">OFFICIAL TRANSCRIPT</div>

        <div class="t-header">
            <img src="<?= BASE_URL ?>assets/images/logos/favicon.png" height="70" class="mb-2">
            <h2>Nafisatu College of Nursing and Midwifery</h2>
            <p class="mb-0">Office of the Registrar (Academic Records)</p>
            <p class="small text-muted">Kano State, Nigeria</p>
        </div>

        <table class="info-table">
            <tr>
                <td class="label">Student Name:</td>
                <td class="fw-bold"><?= strtoupper($student['full_name']) ?></td>
                <td class="label text-end">ID Number:</td>
                <td class="fw-bold text-end"><?= $student['student_id_number'] ?></td>
            </tr>
            <tr>
                <td class="label">Program:</td>
                <td><?= strtoupper($student['program_name']) ?></td>
                <td class="label text-end">Dept:</td>
                <td class="text-end"><?= strtoupper($student['dept_name'] ?? 'General') ?></td>
            </tr>
            <tr>
                <td class="label">Date Issued:</td>
                <td><?= date("d-M-Y") ?></td>
                <td class="label text-end">Status:</td>
                <td class="text-end text-success fw-bold">ACTIVE</td>
            </tr>
        </table>

        <?php 
        $grand_total_gp = 0;
        $grand_total_units = 0;

        if (!empty($transcript_data)): 
            foreach ($transcript_data as $session => $rows): 
                $session_gp = 0;
                $session_units = 0;
        ?>
            <div class="session-title"><?= $session ?> Academic Session</div>
            <table class="course-table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 50%;">Course Description</th>
                        <th style="width: 10%; text-align: center;">Units</th>
                        <th style="width: 10%; text-align: center;">Grade</th>
                        <th style="width: 15%; text-align: center;">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $course): 
                        $units = $course['credit_units'] ?: 0;
                        $gp = $course['grade_point'] ?: 0;
                        $weighted_gp = $gp * $units;

                        $session_gp += $weighted_gp;
                        $session_units += $units;
                        
                        $grand_total_gp += $weighted_gp;
                        $grand_total_units += $units;
                    ?>
                    <tr>
                        <td class="fw-bold"><?= $course['course_code'] ?></td>
                        <td><?= $course['course_name'] ?></td>
                        <td class="text-center"><?= $units ?></td>
                        <td class="text-center fw-bold"><?= $course['letter_grade'] ?? 'F' ?></td>
                        <td class="text-center"><?= number_format($gp, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-end small text-muted mb-4">
                GPA for Session: <?= ($session_units > 0) ? number_format($session_gp / $session_units, 2) : '0.00' ?>
            </div>
        <?php endforeach; ?>

        <div class="cgpa-box">
            <span class="text-muted small text-uppercase me-3">Cumulative Grade Point Average (CGPA):</span>
            <span class="text-primary"><?= ($grand_total_units > 0) ? number_format($grand_total_gp / $grand_total_units, 2) : '0.00' ?> / 5.00</span>
        </div>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="ti ti-database-x fs-1 text-muted"></i>
                <p class="text-muted mt-2">No academic records found for this student.</p>
            </div>
        <?php endif; ?>

        <div class="mt-5 pt-4 border-top">
            <div class="row">
                <div class="col-4 text-center">
                    <div style="border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 11px;">Exams Officer</div>
                </div>
                <div class="col-4 text-center">
                    <div style="border: 2px dashed #eee; width: 80px; height: 80px; margin: 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #eee; font-size: 10px;">OFFICIAL STAMP</div>
                </div>
                <div class="col-4 text-center">
                    <div style="border-top: 1px solid #333; margin-top: 40px; padding-top: 5px; font-size: 11px;">College Registrar</div>
                </div>
            </div>
        </div>

        <div class="footer mt-4" style="font-size: 10px; color: #999;">
            <p>1. This transcript is valid only when it bears the official college seal and registrar's signature.<br>
            2. Grading Key: A(70-100), B(60-69), C(50-59), D(45-49), E(40-44), F(0-39).<br>
            3. Any alteration or erasure renders this document null and void.</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>