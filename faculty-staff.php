<?php
  include 'includes/header.php';
?>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Faculty & Staff</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Faculty & Staff</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Faculty  Staff Section -->
    <section id="faculty--staff" class="faculty--staff section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row mb-5">
          <div class="col-lg-8 mx-auto">
            <div class="faculty-search">
              <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by name, department or specialization...">
                <button class="search-btn" type="button">
                  <i class="bi bi-search"></i>
                </button>
              </div>
              <div class="search-filters mt-3 d-flex flex-wrap">
                <div class="filter-item active">All</div>
                <div class="filter-item">Nursing Tutors</div>
                <div class="filter-item">Midwifery Tutors</div>
                <div class="filter-item">Clinical Instructors</div>
                <div class="filter-item">Admin Staff</div>
              </div>
            </div>
          </div>
        </div>

        <div class="faculty-grid">
          <div class="row g-4">
            <!-- Faculty Member 1 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-m-3.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Dr. Musa Aliyu</h3>
                  <p class="position">Head of Nursing Department</p>
                  <div class="department">School of Nursing</div>
                  <div class="research-tags">
                    <span>Medical-Surgical</span>
                    <span>Nursing Education</span>
                    <span>Oncology</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->

            <!-- Faculty Member 2 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-f-5.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Mrs. Fatima Bello</h3>
                  <p class="position">Senior Midwifery Tutor</p>
                  <div class="department">School of Midwifery</div>
                  <div class="research-tags">
                    <span>Maternal Health</span>
                    <span>Neonatal Care</span>
                    <span>Reproductive Health</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->

            <!-- Faculty Member 3 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-m-7.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Mr. David Eze</h3>
                  <p class="position">Clinical Instructor</p>
                  <div class="department">Clinical Practice</div>
                  <div class="research-tags">
                    <span>Emergency Care</span>
                    <span>Critical Care</span>
                    <span>Patient Safety</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->

            <!-- Faculty Member 4 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-f-8.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Dr. Nkechi Okoro</h3>
                  <p class="position">Tutor, Public Health Nursing</p>
                  <div class="department">School of Nursing</div>
                  <div class="research-tags">
                    <span>Epidemiology</span>
                    <span>Community Health</span>
                    <span>Health Promotion</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->

            <!-- Faculty Member 5 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-m-11.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Mr. Samuel Adebayo</h3>
                  <p class="position">Tutor, Anatomy & Physiology</p>
                  <div class="department">Basic Sciences</div>
                  <div class="research-tags">
                    <span>Human Anatomy</span>
                    <span>Pathophysiology</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->

            <!-- Faculty Member 6 -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
              <div class="faculty-card">
                <div class="faculty-image">
                  <img src="assets/img/person/person-f-12.webp" class="img-fluid" alt="Faculty Member">
                  <div class="social-links">
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-envelope"></i></a>
                  </div>
                </div>
                <div class="faculty-info">
                  <h3>Mrs. Hadiza Mohammed</h3>
                  <p class="position">Tutor, Pediatric Nursing</p>
                  <div class="department">School of Nursing</div>
                  <div class="research-tags">
                    <span>Child Health</span>
                    <span>Immunization</span>
                    <span>Adolescent Health</span>
                  </div>
                  <a href="#" class="profile-link">View Profile</a>
                </div>
              </div>
            </div><!-- End Faculty Member -->
          </div>
        </div>

        <div class="pagination-container mt-5 d-flex justify-content-center" data-aos="fade-up">
          <nav aria-label="Faculty pagination">
            <ul class="pagination">
              <li class="page-item disabled">
                <a class="page-link" href="#" aria-label="Previous">
                  <i class="bi bi-chevron-left"></i>
                </a>
              </li>
              <li class="page-item active"><a class="page-link" href="#">1</a></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item">
                <a class="page-link" href="#" aria-label="Next">
                  <i class="bi bi-chevron-right"></i>
                </a>
              </li>
            </ul>
          </nav>
        </div>

      </div>

    </section><!-- /Faculty  Staff Section -->

  </main>

<?php
  include 'includes/footer.php';
?>