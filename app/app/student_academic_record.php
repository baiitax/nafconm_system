<?php
/**
 * NAFCONM - Master Academic Dossier
 * Features: Live Student Search, Chronological Academic History, Print-Ready UI
 */
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$search_query = isset($_GET['matric']) ? trim($_GET['matric']) : '';
$student = null;
$academic_history = [];
$total_tcu = 0; // Total Credit Units

if (!empty($search_query)) {
    // 1. Fetch Student Profile
    $stmt = $conn->prepare("
        SELECT s.id, s.student_id_number, s.full_name, s.current_level, s.photo_path, s.email, s.phone, p.name as program_name 
        FROM students s 
        LEFT JOIN programs p ON s.program_id = p.id 
        WHERE s.student_id_number = ? LIMIT 1
    ");
    $stmt->bind_param("s", $search_query);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student) {
        // 2. Fetch Course Registrations (Session by Session)
        $stmt_reg = $conn->prepare("
            SELECT session, course_codes, total_credits 
            FROM course_registrations 
            WHERE student_id = ? AND status = 'approved' 
            ORDER BY session DESC
        ");
        $stmt_reg->bind_param("i", $student['id']);
        $stmt_reg->execute();
        $regs = $stmt_reg->get_result();
        
        while ($reg = $regs->fetch_assoc()) {
            $session_name = $reg['session'];
            $course_codes = json_decode($reg['course_codes'], true);
            $courses = [];
            
            if (!empty($course_codes)) {
                $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
                $types = str_repeat('s', count($course_codes));
                
                $stmt_c = $conn->prepare("SELECT course_code, course_title, credit_units, is_core FROM courses WHERE course_code IN ($placeholders) ORDER BY course_code ASC");
                $stmt_c->bind_param($types, ...$course_codes);
                $stmt_c->execute();
                $res_c = $stmt_c->get_result();
                
                while($c = $res_c->fetch_assoc()) {
                    $courses[] = $c;
                    $total_tcu += (int)$c['credit_units'];
                }
                $stmt_c->close();
            }
            
            $academic_history[$session_name] = [
                'total_credits' => $reg['total_credits'],
                'courses' => $courses
            ];
        }
        $stmt_reg->close();
    } else {
        $_SESSION['msg'] = "error|No student found with Matriculation Number: " . htmlspecialchars($search_query);
    }
}

$page_title = "Academic Record";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    
    /* Search Bar */
    .search-box { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); margin-bottom: 30px; border: 1px solid #e2e8f0; }
    .search-input-group { position: relative; }
    .search-input-group i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.2rem; }
    .search-input-group input { padding-left: 55px; border-radius: 50px; height: 60px; font-size: 16px; font-weight: bold; font-family: monospace; background: #f8fafc; border: 2px solid transparent; transition: 0.3s; }
    .search-input-group input:focus { background: #fff; border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); outline: none; }
    .search-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); border-radius: 50px; padding: 8px 25px; font-weight: bold; }

    /* Profile Card */
    .profile-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.03); overflow: hidden; margin-bottom: 30px; }
    .profile-header { background: #1e293b; color: #fff; padding: 30px; position: relative; }
    .profile-photo { width: 120px; height: 120px; border-radius: 16px; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.2); object-fit: cover; background: #f8fafc; position: absolute; bottom: -40px; left: 30px; }
    .profile-body { padding: 50px 30px 30px 30px; }

    /* Academic Tables */
    .accordion-button:not(.collapsed) { background-color: #f8f9ff; color: #5D87FF; box-shadow: none; border-bottom: 1px solid #e2e8f0; font-weight: 900; }
    .accordion-button { font-weight: 700; color: #1e293b; }
    .course-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .course-table th, .course-table td { border: 1px solid #e2e8f0; padding: 12px 15px; }
    .course-table th { background: #f8fafc; text-transform: uppercase; font-size: 12px; color: #64748b; font-weight: 800; letter-spacing: 0.5px; }
    .course-table tbody tr:hover { background-color: #f8fafc; }
    .grade-pending { color: #94a3b8; font-style: italic; font-weight: bold; }

    .print-btn { background: #1e293b; color: #fff; border-radius: 50px; font-weight: bold; padding: 10px 25px; transition: 0.3s; }
    .print-btn:hover { background: #0f172a; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); color: #fff; }
</style>

<div class="container-fluid py-4">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="fw-black text-dark mb-1">Student Academic Record</h3>
            <p class="text-muted small mb-0">Search and view complete academic dossiers and transcripts.</p>
        </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="search-box">
        <form method="GET" action="">
            <div class="search-input-group">
                <i class="ti ti-id"></i>
                <input type="text" name="matric" class="form-control" placeholder="Enter Matriculation Number (e.g., NAF/NUR/2026/001)" value="<?= htmlspecialchars($search_query) ?>" required>
                <button type="submit" class="btn btn-primary search-btn shadow-sm">Fetch Record</button>
            </div>
        </form>
    </div>

    <?php if ($student): ?>
        <!-- STUDENT PROFILE CARD -->
        <div class="profile-card">
            <div class="profile-header d-flex justify-content-between align-items-start">
                <div>
                    <?php if(!empty($student['photo_path'])): ?>
                        <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" class="profile-photo" alt="Passport">
                    <?php else: ?>
                        <div class="profile-photo d-flex align-items-center justify-content-center text-muted flex-column">
                            <i class="ti ti-user-x fs-1"></i><span style="font-size: 10px; font-weight: bold;">NO PHOTO</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill fw-bold mb-2 d-inline-block">ACTIVE STUDENT</span>
                    <h4 class="fw-black text-white mb-0" style="font-family: monospace;"><?= htmlspecialchars($student['student_id_number']) ?></h4>
                </div>
            </div>
            
            <div class="profile-body">
                <div class="row align-items-end">
                    <div class="col-md-7">
                        <h2 class="fw-black text-dark mb-1"><?= strtoupper(htmlspecialchars($student['full_name'])) ?></h2>
                        <p class="text-primary fw-bold mb-3"><i class="ti ti-certificate me-1"></i><?= htmlspecialchars($student['program_name'] ?? 'Unassigned Program') ?></p>
                        
                        <div class="d-flex gap-4">
                            <div><i class="ti ti-mail text-muted me-1"></i> <span class="fw-medium"><?= htmlspecialchars($student['email']) ?></span></div>
                            <div><i class="ti ti-phone text-muted me-1"></i> <span class="fw-medium"><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></span></div>
                        </div>
                    </div>
                    
                    <div class="col-md-5 mt-4 mt-md-0">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="bg-light rounded-3 p-3 border">
                                    <div class="text-muted small fw-bold text-uppercase mb-1">Current Level</div>
                                    <div class="fs-4 fw-black text-dark"><?= htmlspecialchars($student['current_level']) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-primary-subtle rounded-3 p-3 border border-primary-subtle">
                                    <div class="text-primary small fw-bold text-uppercase mb-1">Cumulative TCU</div>
                                    <div class="fs-4 fw-black text-primary"><?= $total_tcu ?> Units</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACADEMIC HISTORY (ACCORDION) -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="ti ti-history me-2 text-primary"></i>Academic Trajectory</h5>
            <button onclick="window.print()" class="print-btn border-0"><i class="ti ti-printer me-2"></i>Print Dossier</button>
        </div>

        <?php if (!empty($academic_history)): ?>
            <div class="accordion shadow-sm rounded-4 overflow-hidden border" id="academicAccordion">
                <?php 
                $i = 0;
                foreach ($academic_history as $session => $data): 
                    $collapse_id = "collapse_" . md5($session);
                    $is_first = ($i === 0);
                ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $is_first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                                <div class="d-flex justify-content-between w-100 me-3 align-items-center">
                                    <span><i class="ti ti-calendar-stats me-2"></i><?= htmlspecialchars($session) ?></span>
                                    <span class="badge bg-dark text-white rounded-pill px-3"><?= htmlspecialchars($data['total_credits']) ?> Credits Registered</span>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $collapse_id ?>" class="accordion-collapse collapse <?= $is_first ? 'show' : '' ?>" data-bs-parent="#academicAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="course-table">
                                        <thead>
                                            <tr>
                                                <th width="15%">Course Code</th>
                                                <th width="45%">Course Title</th>
                                                <th width="10%">Type</th>
                                                <th width="10%">Units</th>
                                                <th width="10%">Score</th>
                                                <th width="10%">Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data['courses'] as $c): ?>
                                                <tr>
                                                    <td style="font-family: monospace; font-weight: bold; font-size: 14px;"><?= htmlspecialchars($c['course_code']) ?></td>
                                                    <td class="fw-bold text-dark"><?= htmlspecialchars($c['course_title']) ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?= $c['is_core'] ? 'Core' : 'Elective' ?></span></td>
                                                    <td class="fw-black"><?= $c['credit_units'] ?></td>
                                                    <td class="grade-pending">N/A</td>
                                                    <td class="grade-pending">-</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php $i++; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-3">
                <i class="ti ti-folder-off fs-1 text-muted opacity-25 mb-3 d-block"></i>
                <h5 class="fw-black text-dark">No Academic Records Found</h5>
                <p class="text-muted mb-0">This student has not completed any approved course registrations yet.</p>
            </div>
        <?php endif; ?>

    <?php elseif (!empty($search_query)): ?>
        <!-- EMPTY STATE (NOT FOUND) -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-4">
            <div class="bg-danger-subtle rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                <i class="ti ti-user-exclamation fs-1 text-danger"></i>
            </div>
            <h4 class="fw-black text-dark">Record Not Found</h4>
            <p class="text-muted max-w-md mx-auto">No student matches the matriculation number <strong>"<?= htmlspecialchars($search_query) ?>"</strong>. Please check the number and try again.</p>
        </div>
    <?php else: ?>
        <!-- INITIAL EMPTY STATE -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-4" style="border: 2px dashed #e2e8f0 !important;">
            <i class="ti ti-search fs-1 text-muted opacity-25 mb-3 d-block"></i>
            <h5 class="fw-bold text-dark">Search for a Student</h5>
            <p class="text-muted mb-0">Enter a Matriculation Number above to pull up their complete academic dossier.</p>
        </div>
    <?php endif; ?>

</div>

<!-- Print Styles & SweetAlert -->
<style>
    @media print {
        body { background: #fff; padding: 0; }
        .no-print, .sidebar, header, .search-box, .print-btn { display: none !important; }
        .container-fluid, .page-wrapper { padding: 0 !important; margin: 0 !important; }
        .profile-card { border: 2px solid #000; box-shadow: none; }
        .profile-header { background: transparent !important; color: #000 !important; border-bottom: 2px solid #000; padding: 15px; }
        .profile-header h4 { color: #000 !important; }
        .badge { border: 1px solid #000 !important; color: #000 !important; }
        .accordion-button::after { display: none; }
        .collapse { display: block !important; }
        .accordion-item { border: 1px solid #000 !important; margin-bottom: 20px; page-break-inside: avoid; }
        .course-table th, .course-table td { border: 1px solid #000; color: #000 !important; }
        .course-table th { background: #f1f1f1 !important; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (isset($_SESSION['msg'])): ?>
        <?php 
            list($type, $msg) = explode('|', $_SESSION['msg']); 
            unset($_SESSION['msg']); 
        ?>
        Swal.fire({
            icon: '<?= $type === "success" ? "success" : ($type === "error" ? "error" : "warning") ?>',
            title: '<?= $type === "error" ? "Not Found" : "Notification" ?>',
            text: '<?= addslashes($msg) ?>',
            confirmButtonColor: '#1e293b'
        });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>