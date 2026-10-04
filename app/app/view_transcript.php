<?php
session_start();
require_once '../config/db.php';

$student_id = intval($_GET['id']);
$session_id = intval($_GET['session']);

// 1. Fetch Student/Program Details
$stmt = $conn->prepare("SELECT s.*, p.name as program_name, sess.name as session_name 
                        FROM students s 
                        JOIN programs p ON s.program_id = p.id 
                        JOIN sessions sess ON sess.id = ?
                        WHERE s.id = ?");
$stmt->bind_param("ii", $session_id, $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// 2. Fetch Results
$sql_res = "SELECT e.*, c.course_name, c.course_code, c.credit_units 
            FROM enrollments e 
            JOIN courses c ON e.course_id = c.id 
            WHERE e.student_id = ? AND e.session_id = ? AND e.final_grade IS NOT NULL";
$res_stmt = $conn->prepare($sql_res);
$res_stmt->bind_param("ii", $student_id, $session_id);
$res_stmt->execute();
$results = $res_stmt->get_result();

// 3. GPA Calculation Variables
$total_units = 0;
$total_weighted_points = 0;

include 'includes/header.php';
?>

<style>
    @media print { .btn, .sidebar-nav, .topbar { display: none !important; } .container-fluid { padding: 0; } .card { border: none !important; } }
    .transcript-header { border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 25px; }
</style>

<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="text-end mb-4">
                <button class="btn btn-dark fw-bold px-4" onclick="window.print()"><i class="ti ti-printer"></i> Print Official Transcript</button>
            </div>
            
            <div class="card shadow-none border p-5">
                <div class="transcript-header text-center">
                    <img src="../assets/images/logos/favicon.png" width="80" class="mb-2">
                    <h3 class="fw-bolder mb-0">NAFISATU COLLEGE OF NURSING & MIDWIFERY</h3>
                    <p class="text-uppercase tracking-widest fs-2 fw-bold mb-0">Official Academic Transcript</p>
                </div>

                <div class="row mb-4 border-bottom pb-4">
                    <div class="col-6">
                        <small class="text-muted uppercase fw-bold fs-1">Student Name</small>
                        <h5 class="fw-bold mb-3"><?= strtoupper($student['full_name']) ?></h5>
                        <small class="text-muted uppercase fw-bold fs-1">Academic Program</small>
                        <h6 class="fw-bold"><?= $student['program_name'] ?></h6>
                    </div>
                    <div class="col-6 text-end">
                        <small class="text-muted uppercase fw-bold fs-1">Matriculation Number</small>
                        <h5 class="fw-bold mb-3"><?= $student['student_id_number'] ?></h5>
                        <small class="text-muted uppercase fw-bold fs-1">Session</small>
                        <h6 class="fw-bold"><?= $student['session_name'] ?></h6>
                    </div>
                </div>

                <table class="table table-bordered align-middle">
                    <thead class="bg-light">
                        <tr class="text-center fs-2 fw-bold">
                            <th>COURSE CODE</th>
                            <th class="text-start">COURSE TITLE</th>
                            <th>UNITS</th>
                            <th>GRADE</th>
                            <th>POINTS</th>
                        </tr>
                    </thead>
                    <tbody class="text-center fs-3">
                        <?php while($row = $results->fetch_assoc()): 
                            $units = intval($row['credit_units']);
                            $gp = floatval($row['grade_point']);
                            $weighted = $units * $gp;
                            
                            $total_units += $units;
                            $total_weighted_points += $weighted;
                        ?>
                        <tr>
                            <td><?= $row['course_code'] ?></td>
                            <td class="text-start"><?= $row['course_name'] ?></td>
                            <td><?= $units ?></td>
                            <td class="fw-bold"><?= $row['final_grade'] ?></td>
                            <td><?= number_format($gp, 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="bg-light-subtle">
                        <?php $gpa = ($total_units > 0) ? $total_weighted_points / $total_units : 0; ?>
                        <tr class="fw-bolder">
                            <td colspan="2" class="text-end pe-4">TOTALS:</td>
                            <td class="text-center"><?= $total_units ?></td>
                            <td colspan="2" class="text-end pe-4 fs-5 text-primary">SESSION GPA: <?= number_format($gpa, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="row mt-5 pt-5">
                    <div class="col-4 border-top text-center pt-2">Registrar Signature</div>
                    <div class="col-4"></div>
                    <div class="col-4 border-top text-center pt-2">Date of Issue</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>