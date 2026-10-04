<?php
/**
 * NAFCONM - Faculty Class Roster (My Students)
 * Features: Fixed Schema Crash, Live Search, Print Roster UI, Active Session Sync
 */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once '../config/db.php';

// Security & Role Check
$user_type = strtolower($_SESSION['user_type'] ?? '');
if (!isset($_SESSION["loggedin"]) || !in_array($user_type, ['teacher', 'staff', 'superadmin', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// 1. Fetch Active Session
$current_session_id = 1;
$current_session_name = "2025/2026 Session";
$sess_query = $conn->query("SELECT id, name FROM sessions WHERE is_active = 1 LIMIT 1");
if ($sess_query && $sess_query->num_rows > 0) {
    $active_session = $sess_query->fetch_assoc();
    $current_session_id = (int)$active_session['id'];
    $current_session_name = $active_session['name'];
}

// 2. Fetch Teacher Profile safely
$teacher_id = 0;
$stmt_t = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
if ($stmt_t) {
    $stmt_t->bind_param("i", $user_id);
    $stmt_t->execute();
    $res_t = $stmt_t->get_result();
    if ($res_t->num_rows > 0) {
        $teacher_id = (int)$res_t->fetch_assoc()['id'];
    }
    $stmt_t->close();
}

// 3. Fetch Courses & Enrolled Students
$courses_with_students = [];
$total_students = 0;

if ($teacher_id > 0) {
    // Step A: Get all courses assigned to this teacher for the active session
    $sql_courses = "
        SELECT c.id as course_id, c.course_title as course_name, c.course_code 
        FROM course_teachers ct 
        JOIN courses c ON ct.course_id = c.id 
        WHERE ct.teacher_id = ? AND ct.session_id = ?
        ORDER BY c.course_code ASC
    ";
    
    if ($stmt_c = $conn->prepare($sql_courses)) {
        $stmt_c->bind_param("ii", $teacher_id, $current_session_id);
        $stmt_c->execute();
        $courses_result = $stmt_c->get_result();
        
        while ($course = $courses_result->fetch_assoc()) {
            $course_id = $course['course_id'];
            $course_code = $course['course_code'];
            
            $courses_with_students[$course_id] = [
                'course_name' => $course['course_name'],
                'course_code' => $course_code,
                'students' => []
            ];

            // Step B: Get students registered for this specific course via JSON matching
            $sql_students = "
                SELECT s.id, s.full_name as student_name, s.student_id_number, s.email as student_email, s.photo_path, s.current_level, p.name as program_name
                FROM course_registrations cr
                JOIN students s ON cr.student_id = s.id
                LEFT JOIN programs p ON s.program_id = p.id
                WHERE cr.session = ? AND cr.status = 'approved' AND cr.course_codes LIKE ?
                ORDER BY s.full_name ASC
            ";
            
            if ($stmt_s = $conn->prepare($sql_students)) {
                $like_code = '%' . $course_code . '%';
                $stmt_s->bind_param("ss", $current_session_name, $like_code);
                $stmt_s->execute();
                $students_result = $stmt_s->get_result();
                
                while ($student = $students_result->fetch_assoc()) {
                    $courses_with_students[$course_id]['students'][] = $student;
                    $total_students++;
                }
                $stmt_s->close();
            }
        }
        $stmt_c->close();
    }
}

$page_title = "Class Roster";
include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<style>
    .fw-black { font-weight: 900 !important; }
    
    .search-container { position: relative; max-width: 400px; width: 100%; }
    .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #64748b; }
    .search-container input { padding-left: 45px; border-radius: 50px; background-color: #f8fafc; border: 1px solid #e2e8f0; transition: 0.3s; }
    .search-container input:focus { border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.1); background-color: #fff; }

    .accordion-button:not(.collapsed) { background-color: #f8f9ff; color: #5D87FF; box-shadow: none; border-bottom: 1px solid #e2e8f0; font-weight: 900; }
    .accordion-button { font-weight: 700; color: #1e293b; padding: 20px; }
    .accordion-item { border: 1px solid #e2e8f0; border-radius: 16px !important; overflow: hidden; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
    
    .roster-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .roster-table th, .roster-table td { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    .roster-table th { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 800; background: #f8fafc; }
    .roster-table tbody tr:last-child td { border-bottom: none; }
    .roster-table tbody tr:hover { background-color: #f8fafc; }

    .print-btn { transition: 0.3s; }
    .print-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

    /* Print Styles */
    @media print {
        body { background: #fff; padding: 0; color: #000; font-family: 'Times New Roman', Times, serif; }
        .no-print, .sidebar, header, .search-container, .accordion-button::after { display: none !important; }
        .container-fluid, .page-wrapper { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .accordion-item { border: none !important; box-shadow: none !important; margin-bottom: 30px; page-break-inside: avoid; }
        .accordion-collapse { display: block !important; }
        .accordion-button { padding: 0 0 10px 0 !important; background: transparent !important; color: #000 !important; border-bottom: 2px solid #000 !important; margin-bottom: 15px; }
        .roster-table th, .roster-table td { border: 1px solid #000 !important; color: #000 !important; padding: 8px !important; font-size: 12px; }
        .roster-table th { background: #f1f1f1 !important; }
    }
</style>

<div class="container-fluid py-4">
    
    <div class="d-md-flex align-items-center justify-content-between mb-4 no-print">
        <div>
            <h3 class="fw-black text-dark mb-1">My Students & Class Rosters</h3>
            <p class="text-muted small mb-0">Manage and view enrolled students for the <strong><?= htmlspecialchars($current_session_name) ?></strong>.</p>
        </div>
        <div class="mt-3 mt-md-0 d-flex gap-3 align-items-center">
            <div class="text-end me-3">
                <div class="small fw-bold text-muted text-uppercase">Total Enrolled</div>
                <div class="fs-5 fw-black text-primary"><?= number_format($total_students) ?> Students</div>
            </div>
            <div class="search-container">
                <i class="ti ti-search"></i>
                <input type="text" id="rosterSearch" class="form-control" placeholder="Search by name, matric, or program...">
            </div>
        </div>
    </div>

    <div class="accordion" id="studentsAccordion">
        <?php if (!empty($courses_with_students)): ?>
            <?php 
            $is_first = true;
            foreach ($courses_with_students as $course_id => $course): 
                $collapse_id = "collapse_" . $course_id;
                $student_count = count($course['students']);
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading_<?= $course_id ?>">
                        <button class="accordion-button <?= $is_first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                            <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                <div>
                                    <span class="badge bg-dark text-white font-monospace me-2 px-2 py-1"><?= htmlspecialchars($course['course_code']) ?></span>
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 no-print">
                                    <i class="ti ti-users me-1"></i><?= $student_count ?> Enrolled
                                </span>
                            </div>
                        </button>
                    </h2>
                    <div id="<?= $collapse_id ?>" class="accordion-collapse collapse <?= $is_first ? 'show' : '' ?>" data-bs-parent="#studentsAccordion">
                        <div class="accordion-body p-0">
                            
                            <!-- Class Action Bar -->
                            <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center no-print">
                                <span class="small fw-bold text-muted text-uppercase"><i class="ti ti-list-details me-1"></i> Official Class Roster</span>
                                <button onclick="window.print()" class="btn btn-sm btn-outline-dark fw-bold rounded-pill print-btn px-3">
                                    <i class="ti ti-printer me-1"></i> Print Roster
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table roster-table mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%">S/N</th>
                                            <th width="35%">Student Profile</th>
                                            <th width="20%">Matriculation No.</th>
                                            <th width="25%">Program / Level</th>
                                            <th width="15%">Contact</th>
                                        </tr>
                                    </thead>
                                    <tbody class="roster-tbody">
                                        <?php if($student_count > 0): $sn = 1; foreach ($course['students'] as $student): ?>
                                            <tr class="student-row">
                                                <td class="fw-bold text-muted"><?= $sn++ ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if(!empty($student['photo_path'])): ?>
                                                            <img src="../uploads/admissions/<?= htmlspecialchars($student['photo_path']) ?>" class="rounded-circle object-fit-cover me-3 shadow-sm border no-print" style="width:40px; height:40px;" onerror="this.src='../assets/images/profile/user-1.jpg'">
                                                        <?php else: ?>
                                                            <div class="bg-primary text-white rounded-circle fw-bold fs-6 me-3 d-flex align-items-center justify-content-center shadow-sm no-print" style="width:40px; height:40px;">
                                                                <?= strtoupper(substr($student['student_name'],0,1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <h6 class="fw-bold text-dark mb-0 student-name"><?= htmlspecialchars(strtoupper($student['student_name'])) ?></h6>
                                                    </div>
                                                </td>
                                                <td style="font-family: monospace; font-size: 14px; font-weight: bold;" class="student-matric">
                                                    <?= htmlspecialchars($student['student_id_number']) ?>
                                                </td>
                                                <td class="student-program">
                                                    <span class="d-block fw-bold text-dark mb-1"><?= htmlspecialchars($student['program_name'] ?? 'Unassigned Program') ?></span>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($student['current_level']) ?></span>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?= htmlspecialchars($student['student_email']) ?>" class="text-decoration-none text-primary small fw-medium d-block no-print"><i class="ti ti-mail me-1"></i>Email Student</a>
                                                    <span class="d-none d-print-block small"><?= htmlspecialchars($student['student_email']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted no-print"><i class="ti ti-users-minus fs-1 d-block mb-2 opacity-50"></i>No students have registered for this course yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                $is_first = false; 
            endforeach; 
            ?>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <div class="bg-primary-subtle rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <i class="ti ti-folder-off fs-1 text-primary"></i>
                </div>
                <h4 class="fw-black text-dark">No Assigned Courses</h4>
                <p class="text-muted max-w-md mx-auto">You have not been assigned to any courses for the <?= htmlspecialchars($current_session_name) ?>. Please contact the administrator to allocate your classes.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Live Search Engine for the Roster
    const searchInput = document.getElementById('rosterSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.student-row');
            
            // Expand all accordions during search so results are visible
            if (filter.length > 0) {
                document.querySelectorAll('.accordion-collapse').forEach(el => {
                    el.classList.add('show');
                });
                document.querySelectorAll('.accordion-button').forEach(el => {
                    el.classList.remove('collapsed');
                });
            }

            rows.forEach(row => {
                const name = row.querySelector('.student-name').innerText.toLowerCase();
                const matric = row.querySelector('.student-matric').innerText.toLowerCase();
                const program = row.querySelector('.student-program').innerText.toLowerCase();
                
                if (name.includes(filter) || matric.includes(filter) || program.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>