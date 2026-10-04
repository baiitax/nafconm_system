<?php
/**
 * NAFCONM - Admission Application Portal (Step 1)
 * Features: Dynamic Program Validation, O-Level Capturing, Secure Uploads, Tier-1 UI
 */
session_start();
require_once 'config/db.php';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch available programs
$programs = $conn->query("SELECT id, name FROM programs ORDER BY name ASC");

// Define standard O-Level Subjects
$standard_subjects = ['Mathematics', 'English Language', 'Biology', 'Chemistry', 'Physics'];
$grade_options = ['A1', 'B2', 'B3', 'C4', 'C5', 'C6', 'D7', 'E8', 'F9'];

$page_title = "Start Application";
include 'includes/header.php';
?>

<style>
    body { background-color: #f4f7fa; font-family: 'Inter', system-ui, sans-serif; }
    .fw-black { font-weight: 900 !important; }
    
    .application-card {
        background: #fff;
        border: none;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .card-header-premium {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: white;
        padding: 40px 30px;
        position: relative;
    }
    
    .form-floating > label { color: #64748b; font-weight: 600; font-size: 0.9rem; }
    .form-control, .form-select { 
        border-radius: 12px; border: 1.5px solid #e2e8f0; 
        padding-left: 1.25rem; font-weight: 600; color: #1e293b;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus { 
        border-color: #5D87FF; box-shadow: 0 0 0 4px rgba(93, 135, 255, 0.15); 
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px dashed #e2e8f0;
        display: flex;
        align-items: center;
    }
    .section-title i { color: #5D87FF; margin-right: 10px; font-size: 1.3rem; }

    /* Custom File Input */
    .file-upload-wrapper { position: relative; overflow: hidden; display: block; cursor: pointer; }
    .file-upload-wrapper input[type="file"] {
        position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .file-upload-ui {
        border: 2px dashed #cbd5e1; border-radius: 16px; padding: 25px 20px; text-align: center;
        background: #f8fafc; transition: 0.3s;
    }
    .file-upload-wrapper:hover .file-upload-ui { border-color: #5D87FF; background: #eff3ff; }
    .file-upload-icon { font-size: 2rem; color: #94a3b8; margin-bottom: 10px; transition: 0.3s; }
    .file-upload-wrapper:hover .file-upload-icon { color: #5D87FF; transform: translateY(-3px); }

</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

            <!-- Progress Stepper -->
            <div class="d-flex justify-content-between mb-5 position-relative px-md-5">
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-lg border border-2 border-white" style="width: 45px; height: 45px;"><i class="ti ti-user-edit fs-5"></i></div>
                    <div class="small fw-bold text-primary">Bio-Data</div>
                </div>
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2 border border-2 border-white shadow-sm" style="width: 45px; height: 45px;"><i class="ti ti-cash fs-5"></i></div>
                    <div class="small fw-bold text-muted">Payment</div>
                </div>
                <div class="text-center z-1" style="width: 33%;">
                    <div class="bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2 border border-2 border-white shadow-sm" style="width: 45px; height: 45px;"><i class="ti ti-rosette-discount-check fs-5"></i></div>
                    <div class="small text-muted fw-medium">Complete</div>
                </div>
                <div class="position-absolute top-50 start-0 translate-middle-y w-100 px-md-5" style="height: 3px; background: #e2e8f0; z-index: 0;"></div>
            </div>

            <?php if (isset($_SESSION['msg'])): ?>
                <?php list($type, $msg) = explode('|', $_SESSION['msg']); ?>
                <div class="alert alert-<?= $type === 'danger' ? 'danger' : 'success' ?> border-0 rounded-4 p-4 shadow-sm mb-4 fw-bold d-flex align-items-center">
                    <i class="ti <?= $type === 'danger' ? 'ti-alert-triangle' : 'ti-check' ?> fs-3 me-3"></i>
                    <div><?= htmlspecialchars($msg) ?></div>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <div class="application-card">
                <div class="card-header-premium">
                    <h2 class="fw-black mb-2">Admission Application Form</h2>
                    <p class="mb-0 text-white-50 fw-medium">Please fill in your authentic details carefully. Ensure all documents are clear and legible.</p>
                    <div class="position-absolute top-0 end-0 p-4 opacity-25 d-none d-md-block">
                        <i class="ti ti-file-certificate" style="font-size: 80px;"></i>
                    </div>
                </div>
                
                <form action="process_step1.php" method="POST" enctype="multipart/form-data" id="applicationForm" class="p-4 p-md-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="alert bg-primary-subtle text-primary border-0 rounded-3 mb-5 p-3 d-flex align-items-center">
                        <i class="ti ti-info-circle fs-3 me-3"></i>
                        <div>
                            <strong class="d-block">Application Fee: ₦16,430.00</strong>
                            <small>You will be redirected to the secure payment gateway upon completing this form.</small>
                        </div>
                    </div>

                    <!-- 1. PERSONAL INFORMATION -->
                    <h4 class="section-title"><i class="ti ti-user-scan"></i> Personal Information</h4>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First Name" required>
                                <label for="first_name">First Name <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Surname" required>
                                <label for="last_name">Surname (Last Name) <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
                                <label for="email">Active Email Address <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" required pattern="[0-9]{11}">
                                <label for="phone">Phone Number (11 digits) <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ACADEMIC PROGRAM & IDENTIFICATION -->
                    <h4 class="section-title"><i class="ti ti-school"></i> Academic Program</h4>
                    <div class="row g-4 mb-5">
                        <div class="col-12">
                            <div class="form-floating">
                                <select class="form-select fw-bold text-primary" id="program_id" name="program_id" required>
                                    <option value="">-- Select Preferred Program --</option>
                                    <?php if ($programs): while ($p = $programs->fetch_assoc()): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                                <label for="program_id">Program of Study <span class="text-danger">*</span></label>
                            </div>
                        </div>

                        <!-- Dynamic ID Fields (Controlled via JS) -->
                        <div class="col-md-6" id="identification_container" style="display: none;">
                            <div class="form-floating">
                                <input type="text" class="form-control text-uppercase font-monospace" id="jamb_number" name="jamb_number" placeholder="ID Number">
                                <label for="jamb_number" id="id_label">Identification Number <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-6" id="score_container" style="display: none;">
                            <div class="form-floating">
                                <input type="number" class="form-control fw-bold text-success" id="jamb_score" name="jamb_score" placeholder="Score">
                                <label for="jamb_score">JAMB UTME Score</label>
                            </div>
                        </div>
                    </div>

                    <!-- 3. O-LEVEL RESULTS -->
                    <h4 class="section-title"><i class="ti ti-certificate"></i> O-Level Results (WAEC/NECO/NABTEB)</h4>
                    <p class="text-muted small fw-medium mb-4">Please enter your grades for the core science subjects required for Nursing & Midwifery.</p>
                    <div class="row g-3 mb-5">
                        <?php foreach($standard_subjects as $index => $subject): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card border border-light-subtle shadow-sm">
                                <div class="card-body p-2 d-flex align-items-center">
                                    <input type="text" class="form-control bg-transparent border-0 fw-bold px-2 py-1 w-50" name="subjects[]" value="<?= $subject ?>" readonly>
                                    <select class="form-select border-0 bg-light fw-bold text-dark w-50" name="grades[]" required>
                                        <option value="">Grade</option>
                                        <?php foreach($grade_options as $grade): ?>
                                            <option value="<?= $grade ?>"><?= $grade ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 4. DOCUMENT UPLOADS -->
                    <h4 class="section-title"><i class="ti ti-cloud-upload"></i> Document Uploads</h4>
                    <div class="alert bg-warning-subtle text-dark border-0 small fw-bold mb-4">
                        <i class="ti ti-bulb me-1 text-warning"></i> Maximum file size is <strong>2MB</strong> per document. Allowed formats: JPG, PNG, PDF.
                    </div>
                    
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="fw-bold mb-2 small text-muted text-uppercase">Recent Passport Photograph <span class="text-danger">*</span></label>
                            <label class="file-upload-wrapper">
                                <input type="file" name="photo" accept=".jpg, .jpeg, .png" required onchange="updateFileName(this, 'photo_name')">
                                <div class="file-upload-ui">
                                    <i class="ti ti-photo file-upload-icon"></i>
                                    <h6 class="fw-bold text-dark mb-1">Click to Upload Passport</h6>
                                    <small class="text-muted fw-medium" id="photo_name">JPG or PNG only</small>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold mb-2 small text-muted text-uppercase">O-Level Result (SSCE) <span class="text-danger">*</span></label>
                            <label class="file-upload-wrapper">
                                <input type="file" name="ssce_attachment" accept=".jpg, .jpeg, .png, .pdf" required onchange="updateFileName(this, 'ssce_name')">
                                <div class="file-upload-ui">
                                    <i class="ti ti-file-certificate file-upload-icon"></i>
                                    <h6 class="fw-bold text-dark mb-1">Click to Upload Result</h6>
                                    <small class="text-muted fw-medium" id="ssce_name">JPG, PNG, or PDF</small>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold mb-2 small text-muted text-uppercase">Indigene Certificate <span class="text-danger">*</span></label>
                            <label class="file-upload-wrapper">
                                <input type="file" name="indigene_attachment" accept=".jpg, .jpeg, .png, .pdf" required onchange="updateFileName(this, 'indigene_name')">
                                <div class="file-upload-ui">
                                    <i class="ti ti-map-pin file-upload-icon"></i>
                                    <h6 class="fw-bold text-dark mb-1">Click to Upload Certificate</h6>
                                    <small class="text-muted fw-medium" id="indigene_name">JPG, PNG, or PDF</small>
                                </div>
                            </label>
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold mb-2 small text-muted text-uppercase">Birth Certificate / Declaration <span class="text-danger">*</span></label>
                            <label class="file-upload-wrapper">
                                <input type="file" name="birth_attachment" accept=".jpg, .jpeg, .png, .pdf" required onchange="updateFileName(this, 'birth_name')">
                                <div class="file-upload-ui">
                                    <i class="ti ti-id file-upload-icon"></i>
                                    <h6 class="fw-bold text-dark mb-1">Click to Upload Certificate</h6>
                                    <small class="text-muted fw-medium" id="birth_name">JPG, PNG, or PDF</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <div class="text-center pt-3 border-top">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg rounded-pill fw-black shadow-lg px-5 py-3 w-100 transition-all">
                            <span id="btnText">Proceed to Payment (₦16,430)</span> 
                            <i id="btnIcon" class="ti ti-arrow-right ms-2 fs-5"></i>
                            <div id="btnSpinner" class="spinner-border spinner-border-sm text-white ms-2 d-none" role="status"></div>
                        </button>
                        <p class="mt-3 small text-muted fw-medium"><i class="ti ti-lock me-1"></i> Your data is transmitted securely via 256-bit encryption.</p>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<script>
// Dynamic Intelligence for Program Selection
document.getElementById('program_id').addEventListener('change', function() {
    const selectedText = this.options[this.selectedIndex].text.toLowerCase();
    const idContainer = document.getElementById('identification_container');
    const idLabel = document.getElementById('id_label');
    const idInput = document.getElementById('jamb_number');
    const scoreContainer = document.getElementById('score_container');

    // ND requires JAMB Number and Score
    if (selectedText.includes('nd') || selectedText.includes('national diploma')) {
        idContainer.style.display = 'block';
        idLabel.innerHTML = 'JAMB UTME Registration Number <span class="text-danger">*</span>';
        idInput.required = true;
        
        scoreContainer.style.display = 'block';
        document.getElementById('jamb_score').required = true;
        
    // Mental Health requires Index Number only
    } else if (selectedText.includes('mental health')) {
        idContainer.style.display = 'block';
        idLabel.innerHTML = 'Mental Health Index Number <span class="text-danger">*</span>';
        idInput.required = true;
        
        scoreContainer.style.display = 'none';
        document.getElementById('jamb_score').required = false;
        document.getElementById('jamb_score').value = '';
        
    // Others do not strictly require it
    } else {
        idContainer.style.display = 'none';
        idInput.required = false;
        idInput.value = '';
        
        scoreContainer.style.display = 'none';
        document.getElementById('jamb_score').required = false;
        document.getElementById('jamb_score').value = '';
    }
});

// Update File Upload Labels
function updateFileName(input, textElementId) {
    const textElement = document.getElementById(textElementId);
    if (input.files && input.files.length > 0) {
        let fileName = input.files[0].name;
        // Truncate long filenames
        if (fileName.length > 25) {
            fileName = fileName.substring(0, 22) + '...';
        }
        textElement.innerHTML = `<span class="text-success fw-bold"><i class="ti ti-check me-1"></i> ${fileName}</span>`;
        input.parentElement.querySelector('.file-upload-ui').style.borderColor = '#13DEB9';
        input.parentElement.querySelector('.file-upload-icon').style.color = '#13DEB9';
    } else {
        textElement.innerHTML = "JPG, PNG, or PDF";
    }
}

// Form Submission Loading State
document.getElementById('applicationForm').addEventListener('submit', function(e) {
    if (this.checkValidity()) {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('btnText');
        const icon = document.getElementById('btnIcon');
        const spinner = document.getElementById('btnSpinner');

        btn.style.pointerEvents = 'none';
        btn.style.opacity = '0.8';
        text.innerText = "Processing Data & Generating Username...";
        icon.classList.add('d-none');
        spinner.classList.remove('d-none');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
