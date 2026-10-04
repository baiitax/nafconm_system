<?php
/**
 * NAFCONM - Professional Course Materials Portal
 * Features: Variable Fixes, JSON Schema Sync, Live Search Filtering, Analytics Header, Premium File UI
 */
session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/'); // Adjust if needed
require_once '../config/db.php';

// --- 1. SECURITY ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$error = '';
$courses_with_materials = [];
$current_semester = "First Semester"; // Adjust dynamically if you have a settings table

// --- 2. FETCH STUDENT DATA (FIXED: Added current_level) ---
$stmt_student = $conn->prepare("SELECT id, current_level FROM students WHERE user_id = ? LIMIT 1");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$student_data = $stmt_student->get_result()->fetch_assoc();
$student_id = $student_data['id'] ?? 0;
$level = $student_data['current_level'] ?? 'N/A';
$stmt_student->close();

// --- 3. FETCH ACTIVE SESSION ---
$sess_query = $conn->query("SELECT name FROM sessions WHERE is_active = 1 LIMIT 1");
$current_session = $sess_query->num_rows > 0 ? $sess_query->fetch_assoc()['name'] : '2025/2026 Session';

// --- 4. FETCH APPROVED COURSE REGISTRATIONS (JSON Parsing) ---
$stmt_reg = $conn->prepare("SELECT course_codes FROM course_registrations WHERE student_id = ? AND session = ? AND status = 'approved' LIMIT 1");
$stmt_reg->bind_param("is", $student_id, $current_session);
$stmt_reg->execute();
$reg_res = $stmt_reg->get_result();

$registered_course_codes = [];
if ($reg_res->num_rows > 0) {
    $registered_course_codes = json_decode($reg_res->fetch_assoc()['course_codes'], true);
}
$stmt_reg->close();

// --- 5. FETCH MATERIALS FOR REGISTERED COURSES ---
$total_courses = count($registered_course_codes);
$total_materials = 0;
$new_materials_count = 0;

if (!empty($registered_course_codes)) {
    // Dynamically build the IN clause for prepared statements
    $placeholders = implode(',', array_fill(0, count($registered_course_codes), '?'));
    $types = str_repeat('s', count($registered_course_codes));
    
    $sql = "SELECT 
                c.id as course_id, c.course_code, c.course_title as course_name,
                cm.id as material_id, cm.title, cm.description, cm.file_path, cm.uploaded_at 
            FROM courses c 
            LEFT JOIN course_materials cm ON c.id = cm.course_id 
            WHERE c.course_code IN ($placeholders) 
            ORDER BY c.course_code ASC, cm.uploaded_at DESC";
            
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$registered_course_codes);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $c_code = $row['course_code'];
            if (!isset($courses_with_materials[$c_code])) {
                $courses_with_materials[$c_code] = [
                    'course_code' => $row['course_code'],
                    'course_name' => $row['course_name'],
                    'materials'   => []
                ];
            }
            if ($row['material_id'] !== null) {
                $courses_with_materials[$c_code]['materials'][] = $row;
                $total_materials++;
                // Count materials uploaded in the last 7 days (604800 seconds)
                if (time() - strtotime($row['uploaded_at']) < 604800) {
                    $new_materials_count++;
                }
            }
        }
        $stmt->close();
    } else {
        $error = "System Error: Unable to sync course materials.";
    }
}

/**
 * Helper: Detect File Icon & Color based on extension
 */
function getFileVisuals($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return ['icon' => 'ti-file-type-pdf', 'color' => 'danger'];
        case 'doc':
        case 'docx': return ['icon' => 'ti-file-type-doc', 'color' => 'primary'];
        case 'ppt':
        case 'pptx': return ['icon' => 'ti-file-type-ppt', 'color' => 'warning'];
        case 'xls':
        case 'xlsx': return ['icon' => 'ti-file-type-xls', 'color' => 'success'];
        case 'jpg':
        case 'jpeg':
        case 'png': return ['icon' => 'ti-photo', 'color' => 'info'];
        case 'zip':
        case 'rar': return ['icon' => 'ti-file-zip', 'color' => 'secondary'];
        default: return ['icon' => 'ti-file-description', 'color' => 'dark'];
    }
}

$page_title = 'Course Materials';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php'; // Ensure topbar is included if it exists in your layout
?>

<style>
    .fw-black { font-weight: 900 !important; }
    
    /* Search Bar */
    .search-bar-container { position: relative; width: 100%; max-width: 450px; }
    .search-bar-container i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.2rem; }
    .search-bar-container input { padding-left: 50px; border-radius: 50px; border: 2px solid transparent; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; }
    .search-bar-container input:focus { border-color: #5D87FF; box-shadow: 0 4px 20px rgba(93,135,255,0.15); outline: none; }

    /* Accordion Customization */
    .accordion-button:not(.collapsed) { background-color: #f8f9ff; color: #5D87FF; box-shadow: none; border-bottom: 1px solid #e2e8f0; }
    .accordion-button:focus { box-shadow: none; }
    .course-block { border: 1px solid #e2e8f0; transition: 0.3s; }
    .course-block:hover { border-color: #cbd5e1; }
    
    /* File Card Styles */
    .material-item { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 12px; border: 1px solid #e2e8f0; text-decoration: none; display: flex; align-items: center; background: #fff; margin-bottom: 12px; padding: 16px; }
    .material-item:hover { transform: translateY(-3px); border-color: #5D87FF; box-shadow: 0 10px 20px rgba(93,135,255,0.1); background-color: #f8fafc; }
    
    .file-icon-box { width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; border-radius: 12px; flex-shrink: 0; }
    
    .download-btn { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #64748b; transition: 0.3s; border: none; }
    .material-item:hover .download-btn { background: #5D87FF; color: #fff; transform: scale(1.1); }

    /* Hover Lift for Analytics Cards */
    .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
</style>

<div class="container-fluid py-4">
    
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h3 class="fw-black text-dark mb-1">Learning Resources</h3>
            <p class="text-muted small mb-0"><?= htmlspecialchars($current_session) ?> • <?= htmlspecialchars($current_semester) ?></p>
        </div>
        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-4 py-2 fw-bold fs-6 rounded-pill shadow-sm">
                <i class="ti ti-chart-bar me-1"></i> Level: <?= htmlspecialchars($level) ?>
            </span>
            <div class="search-bar-container">
                <i class="ti ti-search"></i>
                <input type="text" id="materialSearch" class="form-control form-control-lg" placeholder="Search by code, title, or file...">
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm p-4 d-flex align-items-center mb-4">
            <i class="ti ti-alert-triangle fs-3 me-3"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- CONTENT SECTION -->
    <?php if (empty($registered_course_codes)): ?>
        <!-- EMPTY STATE: Not Registered -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-2">
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px;">
                <i class="ti ti-clipboard-x fs-1 text-muted"></i>
            </div>
            <h4 class="fw-black text-dark">No Course Registrations Found</h4>
            <p class="text-muted max-w-md mx-auto mb-4">You have not completed or received approval for your course registrations for the current session. Your learning materials are currently locked.</p>
            <a href="course_registration.php" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm hover-lift">Proceed to Course Registration</a>
        </div>
        
    <?php elseif (!empty($courses_with_materials)): ?>
        
        <!-- ANALYTICS HEADER -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card bg-primary-subtle border-0 rounded-4 p-3 shadow-sm h-100 hover-lift">
                    <div class="d-flex align-items-center">
                        <div class="bg-white text-primary rounded-circle p-2 me-3 shadow-sm"><i class="ti ti-books fs-4"></i></div>
                        <div><p class="text-primary small fw-bold mb-0 text-uppercase">Registered Courses</p><h4 class="fw-black text-primary mb-0"><?= $total_courses ?></h4></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success-subtle border-0 rounded-4 p-3 shadow-sm h-100 hover-lift">
                    <div class="d-flex align-items-center">
                        <div class="bg-white text-success rounded-circle p-2 me-3 shadow-sm"><i class="ti ti-files fs-4"></i></div>
                        <div><p class="text-success small fw-bold mb-0 text-uppercase">Total Files</p><h4 class="fw-black text-success mb-0"><?= $total_materials ?></h4></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning-subtle border-0 rounded-4 p-3 shadow-sm h-100 hover-lift">
                    <div class="d-flex align-items-center">
                        <div class="bg-white text-warning rounded-circle p-2 me-3 shadow-sm"><i class="ti ti-sparkles fs-4"></i></div>
                        <div><p class="text-warning small fw-bold mb-0 text-uppercase">Recent Updates</p><h4 class="fw-black text-warning mb-0"><?= $new_materials_count ?></h4></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCORDION LIST -->
        <div class="accordion" id="materialsAccordion">
            <?php foreach ($courses_with_materials as $c_code => $course): 
                $mat_count = count($course['materials']);
            ?>
                <div class="accordion-item border-0 shadow-sm rounded-4 mb-4 overflow-hidden course-block bg-white">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-bold py-4 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#course-<?= htmlspecialchars(str_replace(' ', '', $c_code)) ?>">
                            <div class="d-flex align-items-center justify-content-between w-100 me-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light text-dark rounded-3 px-3 py-2 me-3 font-monospace fw-bold border">
                                        <?= htmlspecialchars($c_code) ?>
                                    </div>
                                    <span class="text-dark fs-6 course-title-text fw-black"><?= htmlspecialchars($course['course_name']) ?></span> 
                                </div>
                                <span class="badge <?= $mat_count > 0 ? 'bg-primary text-white' : 'bg-light text-muted border' ?> rounded-pill px-3 py-2">
                                    <i class="ti ti-folder me-1"></i><?= $mat_count ?> Files
                                </span>
                            </div>
                        </button>
                    </h2>
                    
                    <div id="course-<?= htmlspecialchars(str_replace(' ', '', $c_code)) ?>" class="accordion-collapse collapse" data-bs-parent="#materialsAccordion">
                        <div class="accordion-body p-4 bg-light">
                            <?php if ($mat_count > 0): ?>
                                <div class="row g-3 material-list">
                                    <?php foreach ($course['materials'] as $mat): 
                                        $is_new = (time() - strtotime($mat['uploaded_at']) < 604800); // 7 Days
                                        // Ensure the file path includes the directory if not stored in the DB
                                        $safe_path = strpos($mat['file_path'], 'uploads/') !== false ? '../' . $mat['file_path'] : '../uploads/materials/' . $mat['file_path'];
                                        $visuals = getFileVisuals($mat['file_path']);
                                    ?>
                                        <div class="col-lg-6 material-file-item">
                                            <!-- Adding 'download' attribute prompts the browser to download -->
                                            <a href="<?= htmlspecialchars($safe_path) ?>" target="_blank" download class="material-item w-100">
                                                <div class="file-icon-box bg-<?= $visuals['color'] ?>-subtle text-<?= $visuals['color'] ?> me-3">
                                                    <i class="ti <?= $visuals['icon'] ?> fs-2"></i>
                                                </div>
                                                <div class="flex-grow-1 overflow-hidden">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <h6 class="fw-bold text-dark mb-0 text-truncate file-title-text pe-2"><?= htmlspecialchars($mat['title']) ?></h6>
                                                        <?php if($is_new): ?>
                                                            <span class="badge bg-danger rounded-pill flex-shrink-0" style="font-size: 9px; padding: 3px 6px; animation: pulse 2s infinite;">NEW</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-muted small mb-1 text-truncate file-desc-text"><?= htmlspecialchars($mat['description'] ?: 'Reference document') ?></p>
                                                    <div class="d-flex align-items-center text-muted fw-bold" style="font-size: 11px;">
                                                        <i class="ti ti-clock me-1"></i> <?= date("d M, Y", strtotime($mat['uploaded_at'])) ?>
                                                    </div>
                                                </div>
                                                <div class="ms-3">
                                                    <div class="download-btn shadow-sm">
                                                        <i class="ti ti-download fs-5"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width: 70px; height: 70px;">
                                        <i class="ti ti-folder-off fs-1 text-muted opacity-50"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark">No Materials Uploaded</h6>
                                    <p class="text-muted small mb-0">Your instructor has not uploaded any files for this course yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <!-- EMPTY STATE: Registered but no courses found in DB -->
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mt-2">
            <i class="ti ti-book-off fs-1 text-muted opacity-25 mb-3"></i>
            <h4 class="fw-black text-dark">No Course Data Available</h4>
            <p class="text-muted mb-0">We couldn't locate the learning resources for your registered courses. Please contact the ICT department.</p>
        </div>
    <?php endif; ?>
</div>

<style>
    /* CSS Pulse Animation for the NEW badge */
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Live Search Filtering Logic
    const searchInput = document.getElementById('materialSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const courseBlocks = document.querySelectorAll('.course-block');

            courseBlocks.forEach(block => {
                const courseTitle = block.querySelector('.course-title-text').innerText.toLowerCase();
                const courseCode = block.querySelector('.font-monospace').innerText.toLowerCase();
                const materials = block.querySelectorAll('.material-file-item');
                let hasVisibleMaterial = false;

                materials.forEach(mat => {
                    const matTitle = mat.querySelector('.file-title-text').innerText.toLowerCase();
                    const matDesc = mat.querySelector('.file-desc-text').innerText.toLowerCase();
                    
                    if (matTitle.includes(filter) || matDesc.includes(filter) || courseTitle.includes(filter) || courseCode.includes(filter)) {
                        mat.style.display = 'block'; // using block because it's a col-lg-6
                        hasVisibleMaterial = true;
                    } else {
                        mat.style.display = 'none';
                    }
                });

                // Show/hide entire course block based on search match
                if (courseTitle.includes(filter) || courseCode.includes(filter) || hasVisibleMaterial) {
                    block.style.display = 'block';
                    
                    // Auto-expand if searching and matches exist inside
                    if (filter.length > 2 && hasVisibleMaterial) {
                        const collapse = block.querySelector('.accordion-collapse');
                        if(!collapse.classList.contains('show')) {
                            new bootstrap.Collapse(collapse, {toggle: true});
                        }
                    }
                } else {
                    block.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>