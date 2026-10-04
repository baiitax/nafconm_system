<?php
session_start();
require_once 'config/db.php';

// Fetch settings for dynamic content
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$page_title = "Admissions Closed";
include 'includes/header.php';
?>

<main class="main">

    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Admissions Portal</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Portal Closed</li>
          </ol>
        </nav>
      </div>
    </div><section id="admission-closed" class="history section">
      <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row align-items-center g-5">
          
          <div class="col-lg-6">
            <div class="about-content" data-aos="fade-up" data-aos-delay="200">
              <span class="text-primary fw-bold text-uppercase mb-2 d-block">Notice to Applicants</span>
              <h2 class="fw-bolder">Applications for the <?php echo $settings['academic_session'] ?? date('Y'); ?> Session are Currently Closed</h2>
              
              <p class="mt-3">Thank you for your interest in <strong>Nafisatu College of Nursing and Midwifery (NAFCONM)</strong>. Please be informed that the portal is no longer accepting new applications at this time as the deadline has passed or the session is full.</p>
              
              <p>We maintain a rigorous selection process to ensure that our students receive the highest quality of healthcare education. We encourage you to check back during the next admission cycle.</p>

              <div class="timeline mt-4">
                <div class="timeline-item">
                  <div class="timeline-dot bg-warning"></div>
                  <div class="timeline-content border-start ps-4">
                    <h4>Already Applied?</h4>
                    <p>If you have already submitted an application, you can still track your admission status using your unique tracking token.</p>
                    <a href="check_admission_status.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2">Track Application Status</a>
                  </div>
                </div>

                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content border-start ps-4">
                    <h4>Upcoming Sessions</h4>
                    <p>Information regarding the next intake will be published on our official social media handles and this website.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="about-image text-center" data-aos="zoom-in" data-aos-delay="300">
              <img src="assets/img/education/IMG_4657-scaled.jpg" alt="NAFCONM Campus" class="img-fluid rounded shadow-lg mb-4">
              
              <div class="mission-vision bg-white p-4 shadow-sm rounded border-start border-primary border-4" data-aos="fade-up" data-aos-delay="400">
                <div class="mission text-start">
                  <h3 class="fs-5 fw-bold text-dark"><i class="bi bi-envelope-at me-2"></i>Contact Admissions Office</h3>
                  <p class="mb-1 small">For inquiries regarding admission requirements or future dates, please contact us:</p>
                  <p class="fw-bold text-primary mb-0"><?php echo $settings['contact_email'] ?? 'admissions@nafconm.edu.ng'; ?></p>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="row mt-5 pt-5 border-top">
          <div class="col-lg-12 text-center">
            <div class="core-values" data-aos="fade-up" data-aos-delay="500">
              <h3 class="mb-4">Why Choose NAFCONM?</h3>
              <div class="row g-4 text-start">
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded shadow-sm border-bottom border-primary border-3">
                        <h5 class="fw-bold"><i class="bi bi-mortarboard me-2"></i> Excellence</h5>
                        <p class="small text-muted mb-0">Our curriculum meets the highest regulatory standards of the NMCN.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded shadow-sm border-bottom border-primary border-3">
                        <h5 class="fw-bold"><i class="bi bi-heart-pulse me-2"></i> Compassion</h5>
                        <p class="small text-muted mb-0">We train healthcare leaders with deep empathy and ethical service.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-white rounded shadow-sm border-bottom border-primary border-3">
                        <h5 class="fw-bold"><i class="bi bi-hospital me-2"></i> Facilities</h5>
                        <p class="small text-muted mb-0">Access to modern laboratories and hands-on clinical experience.</p>
                    </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </section>

</main>

<?php
include 'includes/footer.php';
?>