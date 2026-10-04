<?php
require_once 'includes/header.php';

$program_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$program_id) {
    header('Location: academics.php');
    exit();
}

try {
    $stmt = $pdo->prepare(
        "SELECT p.*, d.name as department_name 
         FROM programs p 
         LEFT JOIN departments d ON p.department_id = d.id 
         WHERE p.id = ?"
    );
    $stmt->execute([$program_id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        header('Location: academics.php');
        exit();
    }

    $pageTitle = htmlspecialchars($program['name']);

} catch (PDOException $e) {
    error_log("Could not fetch program details: " . $e->getMessage());
    die("An error occurred while fetching program details. Please try again later.");
}
?>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0"><?php echo htmlspecialchars($program['name']); ?></h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li><a href="academics.php">Programs</a></li>
            <li class="current"><?php echo htmlspecialchars($program['name']); ?></li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Program Details Section -->
    <section id="program-details" class="program-details section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row gy-4">

          <div class="col-lg-8">
            <div class="program-details-content">

              <div class="program-hero mb-4">
                <img src="assets/img/education/education-1.webp" class="img-fluid rounded" alt="General Nursing Program">
                <h2 class="mt-4"><?php echo htmlspecialchars($program['name']); ?></h2>
              </div>

              <div class="program-overview">
                <h3>Program Overview</h3>
                <p><?php echo nl2br(htmlspecialchars($program['description'])); ?></p>
              </div>

              <div class="curriculum-details mt-5">
                <h3>Curriculum Highlights</h3>
                <p>Our curriculum is structured to build a strong foundation in the first year, followed by specialized and advanced clinical courses. Key areas of study include:</p>
                <div class="accordion" id="curriculumAccordion">
                  
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        Year 1: Foundational Studies
                      </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#curriculumAccordion">
                      <div class="accordion-body">
                        <ul>
                          <li><i class="bi bi-check-circle-fill"></i> Foundational Health Sciences</li>
                          <li><i class="bi bi-check-circle-fill"></i> Core Principles of Patient Care</li>
                          <li><i class="bi bi-check-circle-fill"></i> Introduction to Clinical Environments</li>
                          <li><i class="bi bi-check-circle-fill"></i> Professional Ethics</li>
                          <li><i class="bi bi-check-circle-fill"></i> Basic Health Assessment</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Year 2: Specialized & Advanced Modules
                      </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#curriculumAccordion">
                      <div class="accordion-body">
                        <ul>
                          <li><i class="bi bi-check-circle-fill"></i> Core Specialty Area I (e.g., Medical-Surgical)</li>
                          <li><i class="bi bi-check-circle-fill"></i> Core Specialty Area II (e.g., Pediatrics)</li>
                          <li><i class="bi bi-check-circle-fill"></i> Community Health Practices</li>
                          <li><i class="bi bi-check-circle-fill"></i> Advanced Pharmacology</li>
                          <li><i class="bi bi-check-circle-fill"></i> Intermediate Clinical Practice</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Final Year: Advanced Practice & Leadership
                      </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#curriculumAccordion">
                      <div class="accordion-body">
                        <ul>
                          <li><i class="bi bi-check-circle-fill"></i> Healthcare Leadership & Management</li>
                          <li><i class="bi bi-check-circle-fill"></i> Advanced Clinical Preceptorship</li>
                          <li><i class="bi bi-check-circle-fill"></i> Research Project</li>
                          <li><i class="bi bi-check-circle-fill"></i> Professional Development</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <div class="career-opportunities mt-5">
                <h3>Career Opportunities</h3>
                <p>Upon successful completion of the program and licensure examinations, graduates are qualified to work in a variety of settings, including:</p>
                <div class="row">
                  <div class="col-md-6">
                    <ul>
                      <li><i class="bi bi-arrow-right-circle"></i> General and Specialist Hospitals</li>
                      <li><i class="bi bi-arrow-right-circle"></i> Primary Health Care Centers</li>
                      <li><i class="bi bi-arrow-right-circle"></i> Private Clinics</li>
                    </ul>
                  </div>
                  <div class="col-md-6">
                    <ul>
                      <li><i class="bi bi-arrow-right-circle"></i> Non-Governmental Organizations (NGOs)</li>
                      <li><i class="bi bi-arrow-right-circle"></i> Industrial Clinics</li>
                      <li><i class="bi bi-arrow-right-circle"></i> Further Education and Specialization</li>
                    </ul>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div class="col-lg-4">
            <div class="program-sidebar">

              <div class="sidebar-widget program-summary">
                <h3 class="sidebar-title">Program at a Glance</h3>
                <ul>
                  <li><strong><i class="bi bi-building"></i> Department:</strong> <?php echo htmlspecialchars($program['department_name'] ?? 'N/A'); ?></li>
                  <li><strong><i class="bi bi-clock-history"></i> Duration:</strong> <?php echo htmlspecialchars($program['duration_years']); ?> Years</li>
                  <li><strong><i class="bi bi-building-check"></i> Accreditation:</strong> Fully accredited by the Nursing and Midwifery Council of Nigeria (NMCN)</li>
                  <li><strong><i class="bi bi-calendar-event"></i> Next Intake:</strong> August/September 2026</li>
                </ul>
                <a href="apply.php" class="btn btn-primary w-100">Apply Now</a>
              </div><!-- End Program Summary Widget -->

              <div class="sidebar-widget admission-req">
                <h3 class="sidebar-title">Admission Requirements</h3>
                <p>Candidates must possess a minimum of five (5) O'Level credits in not more than two sittings. Core subjects typically include:</p>
                <ul>
                  <li><i class="bi bi-check-lg"></i> English Language</li>
                  <li><i class="bi bi-check-lg"></i> Mathematics</li>
                  <li><i class="bi bi-check-lg"></i> Physics</li>
                  <li><i class="bi bi-check-lg"></i> Chemistry</li>
                  <li><i class="bi bi-check-lg"></i> Biology</li>
                </ul>
                <p class="text-muted small">Specific subject requirements may vary by program. All candidates must pass the college's entrance examination and interview.</p>
              </div><!-- End Admission Requirements Widget -->

              <div class="sidebar-widget">
                <h3 class="sidebar-title">Tuition & Fees</h3>
                <p>We are committed to providing affordable, high-quality education. For a detailed breakdown of fees for this program, please see our admissions page.</p>
                <a href="admissions-info.php" class="btn btn-outline-secondary w-100">View Fee Schedule</a>
              </div>

              <div class="sidebar-widget brochure-download">
                <h3 class="sidebar-title">Download Brochure</h3>
                <p>Get all the details about our programs, campus life, and admission process in our comprehensive brochure.</p>
                <a href="#" class="btn btn-outline-primary w-100"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
              </div><!-- End Brochure Download Widget -->

              <div class="sidebar-widget related-programs">
                <h3 class="sidebar-title">Related Programs</h3>
                <?php
                  // Fetch related programs from the same department
                  $related_stmt = $pdo->prepare("SELECT id, name FROM programs WHERE department_id = ? AND id != ? LIMIT 3");
                  $related_stmt->execute([$program['department_id'], $program_id]);
                  $related_programs = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php if ($related_programs): ?>
                  <div class="list-group">
                    <?php foreach ($related_programs as $related_program): ?>
                      <a href="program-details.php?id=<?php echo $related_program['id']; ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-chevron-right"></i> <?php echo htmlspecialchars($related_program['name']); ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="text-muted">No other programs in this department.</p>
                <?php endif; ?>
              </div><!-- End Related Programs Widget -->

            </div>
          </div>

        </div>

      </div>

    </section><!-- /Program Details Section -->

  </main>

<?php
  include 'includes/footer.php';
?>