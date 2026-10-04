<?php
  include 'includes/header.php';
?>

  <main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Campus &amp; Facilities</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Campus Facilities</li>
          </ol>
        </nav>
      </div>
    </div><!-- End Page Title -->

    <!-- Campus Facilities Section -->
    <section id="campus-facilities" class="campus-facilities section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <!-- Hero Introduction Row -->
        <div class="hero-intro">
          <div class="row g-0 align-items-center">
            <div class="col-lg-8" data-aos="fade-right" data-aos-delay="200">
              <div class="content-wrapper">
                <div class="badge-highlight">Campus Excellence</div>
                <h1>A Modern Campus Built for Healthcare Education</h1>
                <p class="lead-text">Our campus is designed to provide a conducive learning environment with state-of-the-art facilities that support both theoretical knowledge and practical skills development for our nursing and midwifery students.</p>
                <div class="feature-highlights">
                  <div class="highlight-item">
                    <i class="bi bi-hospital"></i>
                    <div>
                      <span class="number">5+</span>
                      <span class="label">Simulation Labs</span>
                    </div>
                  </div>
                  <div class="highlight-item">
                    <i class="bi bi-book-half"></i>
                    <div>
                      <span class="number">10k+</span>
                      <span class="label">Library Volumes</span>
                    </div>
                  </div>
                  <div class="highlight-item">
                    <i class="bi bi-people"></i>
                    <div>
                      <span class="number">2</span>
                      <span class="label">Student Hostels</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4" data-aos="fade-left" data-aos-delay="300">
              <div class="hero-visual">
                <div class="image-stack">
                  <img src="assets/img/education/campus-6.webp" alt="Campus View" class="img-fluid primary-img">
                  <div class="floating-card">
                    <i class="bi bi-heart-pulse"></i>
                    <span>Clinical Excellence</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Facilities Categories Grid -->
        <div class="facilities-grid" data-aos="fade-up" data-aos-delay="200">
          <div class="category-card academic" data-aos="zoom-in" data-aos-delay="100">
            <div class="card-header">
              <div class="icon-wrapper">
                <i class="bi bi-book"></i>
              </div>
              <h3>Academic Facilities</h3>
            </div>
            <div class="card-content">
              <div class="facility-image">
                <img src="assets/img/education/campus-7.webp" alt="Academic Building" class="img-fluid">
              </div>
              <div class="facility-list">
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Spacious Lecture Halls</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Well-Equipped Library</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>E-Learning Center</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Quiet Study Areas</span>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <a href="#" class="explore-btn">Explore Academic <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>

          <div class="category-card residence" data-aos="zoom-in" data-aos-delay="200">
            <div class="card-header">
              <div class="icon-wrapper">
                <i class="bi bi-clipboard2-pulse"></i>
              </div>
              <h3>Clinical &amp; Simulation Labs</h3>
            </div>
            <div class="card-content">
              <div class="facility-image">
                <img src="assets/img/education/campus-8.webp" alt="Sports Complex" class="img-fluid">
              </div>
              <div class="facility-list">
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>High-Fidelity Manikins</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Nursing Skills Lab</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Anatomy & Physiology Lab</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Midwifery Practice Ward</span>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <a href="#" class="explore-btn">Explore Labs <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>

          <div class="category-card sports" data-aos="zoom-in" data-aos-delay="300">
            <div class="card-header">
              <div class="icon-wrapper">
                <i class="bi bi-house-heart"></i>
              </div>
              <h3>Student Life &amp; Hostels</h3>
            </div>
            <div class="card-content">
              <div class="facility-image">
                <img src="assets/img/education/campus-9.webp" alt="Student Housing" class="img-fluid">
              </div>
              <div class="facility-list">
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Secure Student Hostels</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Common Areas</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Cafeteria & Dining Hall</span>
                </div>
                <div class="facility-item">
                  <i class="bi bi-check2-circle"></i>
                  <span>Sports & Recreation</span>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <a href="students-life.php" class="explore-btn">Explore Student Life <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <!-- Interactive Campus Tour -->
        <div class="campus-tour-section" data-aos="fade-up" data-aos-delay="100">
          <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right" data-aos-delay="200">
              <div class="tour-content">
                <h2>Experience NAFCONM from Anywhere</h2>
                <p>Can't visit us in person? Take a virtual tour to explore our lecture halls, advanced simulation labs, library, and student hostels from the comfort of your home.</p>
                <div class="tour-features">
                  <div class="tour-feature">
                    <i class="bi bi-binoculars"></i>
                    <div>
                      <strong>360° Views</strong>
                      <p>Immersive campus experience</p>
                    </div>
                  </div>
                  <div class="tour-feature">
                    <i class="bi bi-map"></i>
                    <div>
                      <strong>Interactive Map</strong>
                      <p>Navigate with ease</p>
                    </div>
                  </div>
                  <div class="tour-feature">
                    <i class="bi bi-info-circle"></i>
                    <div>
                      <strong>Detailed Info</strong>
                      <p>Learn about each facility</p>
                    </div>
                  </div>
                </div>
                <div class="tour-actions">
                  <a href="#campus-facilities" class="btn-primary">Start Virtual Tour</a>
                  <a href="contact.php" class="btn-outline">Schedule a Visit</a>
                </div>
              </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
              <div class="tour-visual">
                <div class="video-container">
                  <video autoplay="" muted="" loop="">
                    <source src="assets/img/education/video-5.mp4" type="video/mp4">
                  </video>
                  <div class="play-overlay">
                    <button class="play-btn">
                      <i class="bi bi-play-fill"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Campus Highlights Carousel -->
        <div class="highlights-carousel" data-aos="fade-up" data-aos-delay="200">
          <div class="section-header">
            <h2>Campus Highlights</h2>
            <p>A glimpse into the key facilities that make our campus a center for learning and development.</p>
          </div>

          <div class="campus-slider swiper init-swiper">
            <script type="application/json" class="swiper-config">
              {
                "loop": true,
                "speed": 800,
                "autoplay": {
                  "delay": 4000
                },
                "slidesPerView": 1,
                "spaceBetween": 30,
                "navigation": {
                  "nextEl": ".swiper-button-next",
                  "prevEl": ".swiper-button-prev"
                },
                "breakpoints": {
                  "768": {
                    "slidesPerView": 2
                  },
                  "1024": {
                    "slidesPerView": 3
                  }
                }
              }
            </script>
            <div class="swiper-wrapper">
              <div class="swiper-slide">
                <div class="highlight-card">
                  <div class="card-image">
                    <img src="assets/img/education/campus-10.webp" alt="Central Library" class="img-fluid" loading="lazy">
                    <div class="image-overlay">
                      <span class="category-tag">Academic</span>
                    </div>
                  </div>
                  <div class="card-body">
                    <h4>Central Library</h4>
                    <p>A quiet and resourceful space for study and research, with a vast collection of medical and nursing texts.</p>
                    <div class="card-stats">
                      <div class="stat">
                        <i class="bi bi-book"></i>
                        <span>10K+ Volumes</span>
                      </div>
                      <div class="stat">
                        <i class="bi bi-wifi"></i>
                        <span>High-Speed WiFi</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="swiper-slide">
                <div class="highlight-card">
                  <div class="card-image">
                    <img src="assets/img/education/campus-11.webp" alt="Innovation Hub" class="img-fluid" loading="lazy">
                    <div class="image-overlay">
                      <span class="category-tag">Research</span>
                    </div>
                  </div>
                  <div class="card-body">
                    <h4>Simulation Center</h4>
                    <p>Our high-fidelity simulation labs allow students to practice clinical skills in a safe, controlled environment.</p>
                    <div class="card-stats">
                      <div class="stat">
                        <i class="bi bi-person-bounding-box"></i>
                        <span>Smart Manikins</span>
                      </div>
                      <div class="stat">
                        <i class="bi bi-camera-video"></i>
                        <span>AV Recording</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="swiper-slide">
                <div class="highlight-card">
                  <div class="card-image">
                    <img src="assets/img/education/campus-1.webp" alt="Student Center" class="img-fluid" loading="lazy">
                    <div class="image-overlay">
                      <span class="category-tag">Community</span>
                    </div>
                  </div>
                  <div class="card-body">
                    <h4>Student Common Room</h4>
                    <p>A vibrant space for students to relax, socialize, and collaborate on group projects outside the classroom.</p>
                    <div class="card-stats">
                      <div class="stat">
                        <i class="bi bi-cup-hot"></i>
                        <span>Cafeteria</span>
                      </div>
                      <div class="stat">
                        <i class="bi bi-controller"></i>
                        <span>Recreation</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="swiper-slide">
                <div class="highlight-card">
                  <div class="card-image">
                    <img src="assets/img/education/campus-2.webp" alt="Wellness Center" class="img-fluid" loading="lazy">
                    <div class="image-overlay">
                      <span class="category-tag">Wellness</span>
                    </div>
                  </div>
                  <div class="card-body">
                    <h4>Female Hostel</h4>
                    <p>A safe, secure, and comfortable living space for our female students, fostering a sense of community.</p>
                    <div class="card-stats">
                      <div class="stat">
                        <i class="bi bi-shield-lock"></i>
                        <span>24/7 Security</span>
                      </div>
                      <div class="stat">
                        <i class="bi bi-people"></i>
                        <span>Common Rooms</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
          </div>
        </div>

        <!-- Campus Map Integration -->
        <div class="map-integration" data-aos="fade-up" data-aos-delay="100">
          <div class="row">
            <div class="col-lg-4" data-aos="fade-right" data-aos-delay="200">
              <div class="map-sidebar">
                <h3>Find Your Way Around</h3>
                <p>Explore our campus map to locate key buildings, including lecture halls, the library, hostels, and administrative offices.</p>

                <div class="location-categories">
                  <div class="category-filter active" data-category="all">
                    <i class="bi bi-grid"></i>
                    <span>All Locations</span>
                  </div>
                  <div class="category-filter" data-category="academic">
                    <i class="bi bi-book"></i>
                    <span>Academic</span>
                  </div>
                  <div class="category-filter" data-category="dining">
                    <i class="bi bi-cup-hot"></i>
                    <span>Cafeteria</span>
                  </div>
                  <div class="category-filter" data-category="parking">
                    <i class="bi bi-building"></i>
                    <span>Hostels</span>
                  </div>
                  <div class="category-filter" data-category="recreation">
                    <i class="bi bi-person-workspace"></i>
                    <span>Admin</span>
                  </div>
                </div>

                <div class="map-actions">
                  <a href="#" class="action-link">
                    <i class="bi bi-download"></i>
                    Download Campus Map
                  </a>
                  <a href="admissions-info.php" class="action-link">
                    <i class="bi bi-person-check"></i>
                    Admission Office
                  </a>
                  <a href="contact.php" class="action-link">
                    <i class="bi bi-info-circle"></i>
                    Visitor Information
                  </a>
                </div>
              </div>
            </div>
            <div class="col-lg-8" data-aos="fade-left" data-aos-delay="300">
              <div class="map-embed">
                <div class="ratio ratio-4x3">
                  <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3940.518872051056!2d7.443928314786474!3d9.01500899353269!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x104e74912064146d%3A0x89a5893b33c7f6d!2sKwali%2C%20Abuja%2C%20Federal%20Capital%20Territory!5e0!3m2!1sen!2sng!4v1676961268712" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <div class="map-overlay-info">
                  <div class="info-card">
                    <h5>NAFCONM Main Campus</h5>
                    <p>Kwali, Abuja, FCT, Nigeria</p>
                    <div class="quick-stats">
                      <span><i class="bi bi-geo-alt"></i> Kwali</span>
                      <span><i class="bi bi-building"></i> 10+ Buildings</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Campus Facilities Section -->

  </main>
<?php
  include 'includes/footer.php';
?>