<?php 
$page_title = 'Home';
$body_class = 'index-page';
require_once 'includes/header.php'; 
?>

<main class="main">
<?php if(!empty($ticker_items)): ?>
    <div class="news-ticker">
      <div class="container ticker-wrap">
        <div class="ticker-label"><i class="bi bi-megaphone-fill me-2"></i>BREAKING NEWS</div>
        <div class="ticker-content">
          <?php foreach($ticker_items as $item): ?>
            <span class="ticker-item"><?= htmlspecialchars($item) ?> • </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Hero Section -->
    <section id="hero" class="hero section">

      <div class="hero-wrapper">
        <div class="container">
          <div class="row align-items-center">
            <div class="col-lg-6 hero-content" data-aos="fade-right" data-aos-delay="100">
              <h1>Committed to Excellence in Nursing and Midwifery Education.</h1>
              <p>Welcome to Nafisatu College of Nursing and Midwifery (NAFCONM), a premier medical institution in Kwankwaso, Kano State, specializing in empowering aspiring nurses and midwives with the education, skills, and support for a successful healthcare career.</p>
              <div class="action-buttons">
                <a href="apply.php" class="btn-primary">Apply Now</a>
                <a href="academics.php" class="btn-secondary">Our Programs</a>
              </div>
            </div>
            <div class="col-lg-6 hero-media" data-aos="zoom-in" data-aos-delay="200">
              <img src="assets/img/education/IMG_4770-1-scaled.jpg" alt="Education" class="img-fluid main-image">
              <div class="image-overlay">
                <div class="badge-accredited">
                  <i class="bi bi-patch-check-fill"></i>
                  <span>Accredited Excellence</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="feature-cards-wrapper" data-aos="fade-up" data-aos-delay="300">
        <div class="container">
          <div class="row gy-4">
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
              <div class="feature-card">
                <div class="feature-icon">
                  <i class="bi bi-book-fill"></i>
                </div>
                <div class="feature-content">
                  <h3>Practical Clinical Proficiency</h3>
                  <p>Our methodology is rooted in hands-on training to guarantee that students acquire practical clinical proficiency for real-world challenges.</p>
                </div>
              </div>
            </div>

            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
              <div class="feature-card active">
                <div class="feature-icon">
                  <i class="bi bi-laptop-fill"></i>
                </div>
                <div class="feature-content">
                  <h3>Supportive Learning Environment</h3>
                  <p>We provide a nurturing environment with modern facilities designed to support your academic and professional growth.</p>
                </div>
              </div>
            </div>

            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
              <div class="feature-card">
                <div class="feature-icon">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div class="feature-content">
                  <h3>Experienced Faculty Mentorship</h3>
                  <p>Our faculty consists of experienced professionals committed to offering personalized guidance and expertise.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="upcoming-event" data-aos="fade-up" data-aos-delay="400">
        <div class="container">
          <div class="event-content">
            <div class="event-date">
              <span class="day">Now</span>
              <span class="month">Open</span>
            </div>
            <div class="event-info">
              <h3>Admission for 2025/2026 is Open!</h3>
              <p>Join the next generation of healthcare leaders. Applications for our esteemed nursing and midwifery programs are now open.</p>
            </div>
            <div class="event-action">
              <a href="apply.php" class="btn-event">Apply Now</a>
              <span class="countdown">Limited Slots Available</span>
            </div>
          </div>
        </div>
      </div>

    </section><!-- /Hero Section -->

    <!-- About Section -->
    <section id="about" class="about section">

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row align-items-center g-5">
          <div class="col-lg-6">
            <div class="about-content" data-aos="fade-up" data-aos-delay="200">
              <h3>Our Story</h3>
              <h2>A Legacy of Compassion and Excellence</h2>
              <p>Founded on the principles of service and excellence, NAFCONM has grown into a leading institution for healthcare education, known for producing graduates who are not only skilled but also deeply compassionate.</p>

              <div class="timeline">
                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <h4>2010 - Foundation</h4>
                    <p>Established with a vision to address the regional need for qualified nursing professionals.</p>
                  </div>
                </div>

                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <h4>2015 - First Graduating Class</h4>
                    <p>Celebrated our inaugural class of nurses and midwives, who immediately made an impact in the healthcare sector.</p>
                  </div>
                </div>

                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <h4>2020 - Full Accreditation</h4>
                    <p>Achieved full accreditation from the Nursing and Midwifery Council, a testament to our high standards.</p>
                  </div>
                </div>

                <div class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <h4>Commitment to Growth</h4>
                    <p>We continuously expand our offerings to include specialized post-basic programs and adapt to the evolving needs of the healthcare sector.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="about-image" data-aos="zoom-in" data-aos-delay="300">
              <img src="assets/img/education/IMG_4727-scaled.jpg" alt="Campus" class="img-fluid rounded">

              <div class="mission-vision" data-aos="fade-up" data-aos-delay="400">
                <div class="mission">
                  <h3>Our Mandate</h3>
                  <p>To be "Committed to Excellence in Nursing and Midwifery Education," empowering aspiring professionals with the necessary skills and support for a successful career.</p>
                </div>

                <div class="vision">
                  <h3>Our Philosophy</h3>
                  <p>Our training combines academic rigor with comprehensive hands-on experience, instilling both clinical skills and compassionate care in our students.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-5">
          <div class="col-lg-12">
            <div class="core-values" data-aos="fade-up" data-aos-delay="500">
              <h3 class="text-center mb-4">Core Values</h3>
              <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <div class="col">
                  <div class="value-card">
                    <div class="value-icon">
                      <i class="bi bi-book"></i>
                    </div>
                    <h4>Excellence</h4>
                    <p>Upholding the highest standards in teaching, clinical practice, and research.</p>
                  </div>
                </div>

                <div class="col">
                  <div class="value-card">
                    <div class="value-icon">
                      <i class="bi bi-people"></i>
                    </div>
                    <h4>Compassion</h4>
                    <p>Instilling a deep sense of empathy and care for patients and their families.</p>
                  </div>
                </div>

                <div class="col">
                  <div class="value-card">
                    <div class="value-icon">
                      <i class="bi bi-lightbulb"></i>
                    </div>
                    <h4>Integrity</h4>
                    <p>Committing to honesty, ethics, and professionalism in all our endeavors.</p>
                  </div>
                </div>

                <div class="col">
                  <div class="value-card">
                    <div class="value-icon">
                      <i class="bi bi-globe"></i>
                    </div>
                    <h4>Community Service</h4>
                    <p>Actively engaging with and serving our local communities to improve public health.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /About Section -->

    <!-- Featured Programs Section -->
    <section id="featured-programs" class="featured-programs section">

      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2>Featured Programs</h2>
        <p>Explore our range of accredited programs designed to launch your career in healthcare.</p>
      </div><!-- End Section Title -->

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row gy-5">
          <div class="col-lg-6" data-aos="fade-right" data-aos-delay="100">
            <div class="program-banner">
              <div class="banner-image">
                <img src="assets/img/education/IMG_4732-scaled.jpg" alt="Program" class="img-fluid">
                <div class="banner-badge">
                  <span class="badge-text">Popular</span>
                </div>
              </div>
              <div class="banner-info">
                <div class="program-header">
                  <h3>Nursing Program</h3>
                  <div class="program-stats">
                    <span><i class="bi bi-clock-fill"></i> Foundational Program</span>
                    <span><i class="bi bi-award-fill"></i> Fully Accredited</span>
                  </div>
                </div>
                <p>Our flagship Nursing program equips students with the clinical skills and theoretical knowledge necessary for professional success across diverse healthcare environments.</p>
                <div class="program-details">
                  <div class="detail-item">
                    <i class="bi bi-check-circle"></i>
                    <span>Hands-On Training</span>
                  </div>
                  <div class="detail-item">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>RN Qualification</span>
                  </div>
                </div>
                <a href="program-details.php?id=2" class="discover-btn">Discover Program</a>
              </div>
            </div>
          </div><!-- End Program Banner -->

          <div class="col-lg-6">
            <div class="programs-grid">
              <div class="row g-3">
                <div class="col-12" data-aos="fade-left" data-aos-delay="200">
                  <div class="program-item">
                    <div class="item-icon" onclick="window.location.href='program-details.php?id=1';">
                      <img src="assets/img/education/IMG_4632-scaled.jpg" alt="Program" class="img-fluid">
                    </div>
                    <div class="item-content">
                      <h4>Basic Midwifery</h4>
                      <p>Prepares students in maternal and neonatal care to guarantee safe experiences for mothers and babies.</p>
                      <div class="meta-info">
                        <span>Foundational</span>
                        <span>RM Qualification</span>
                      </div>
                    </div>
                    <div class="item-arrow">
                      <a href="program-details.php?id=1"><i class="bi bi-arrow-right"></i></a>
                    </div>
                  </div>
                </div>

                <div class="col-12" data-aos="fade-left" data-aos-delay="300">
                  <div class="program-item">
                    <div class="item-icon" onclick="window.location.href='program-details.php?id=4';">
                      <img src="assets/img/education/IMG_4727-scaled.jpg" alt="Program" class="img-fluid">
                    </div>
                    <div class="item-content">
                      <h4>Mental/Psychiatry Post Basic Nursing</h4>
                      <p>Advanced training for qualified nurses focusing on the assessment and treatment of psychiatric conditions.</p>
                      <div class="meta-info">
                        <span>Post-Basic</span>
                        <span>Specialty Certificate</span>
                      </div>
                    </div>
                    <div class="item-arrow">
                      <a href="program-details.php?id=4"><i class="bi bi-arrow-right"></i></a>
                    </div>
                  </div>
                </div>

                <div class="col-12" data-aos="fade-left" data-aos-delay="400">
                  <div class="program-item">
                    <div class="item-icon" onclick="window.location.href='program-details.php?id=3';">
                      <img src="assets/img/education/IMG_4657-1024x683.jpg" alt="Program" class="img-fluid">
                    </div>
                    <div class="item-content">
                      <h4>Community Nursing & Midwifery</h4>
                      <p>Develop vital skills for thriving in community healthcare, with a focus on comprehensive maternal and newborn care.</p>
                      <div class="meta-info">
                        <span>Community-Focused</span>
                        <span>CHEW Certificate</span>
                      </div>
                    </div>
                    <div class="item-arrow">
                      <a href="program-details.php?id=3"><i class="bi bi-arrow-right"></i></a>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div><!-- End Programs Grid -->

        </div>

      </div>

    </section><!-- /Featured Programs Section -->

    <!-- Students Life Block Section -->
    <section id="students-life-block" class="students-life-block section">

      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2>Students Life</h2>
        <p>Our vibrant campus life extends beyond the classroom, fostering community, leadership, and personal growth.</p>
      </div><!-- End Section Title -->

      <div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row align-items-center g-5 mb-5">
          <div class="col-lg-5" data-aos="fade-right" data-aos-delay="200">
            <div class="hero-image-wrapper">
              <img src="assets/img/education/IMG_4770-1-scaled.jpg" alt="Student Life" class="img-fluid main-image">
              <div class="floating-card" data-aos="zoom-in" data-aos-delay="400">
                <div class="card-icon">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div class="card-content">
                  <span class="card-number">Vibrant</span>
                  <span class="card-label">Community</span>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-7" data-aos="fade-left" data-aos-delay="300">
            <div class="content-wrapper">
              <div class="section-badge" data-aos="fade-up" data-aos-delay="350">
                <span>Student Life</span>
              </div>
              <h2 data-aos="fade-up" data-aos-delay="400">A Supportive and Engaging Campus Community</h2>
              <p class="lead-text" data-aos="fade-up" data-aos-delay="450">At NAFCONM, we believe in holistic development. Our campus life is rich with activities that foster leadership, teamwork, and a deep sense of service.</p>

              <div class="info-grid" data-aos="fade-up" data-aos-delay="500">
                <div class="info-item">
                  <div class="info-icon">
                    <i class="bi bi-calendar-event"></i>
                  </div>
                  <div class="info-text">
                    <strong>Student Associations</strong>
                    <span>Join the Student Nurses Association and other clubs.</span>
                  </div>
                </div>

                <div class="info-item">
                  <div class="info-icon">
                    <i class="bi bi-award"></i>
                  </div>
                  <div class="info-text">
                    <strong>Community Outreach</strong>
                    <span>Participate in health campaigns and community service.</span>
                  </div>
                </div>
              </div>

              <div class="cta-section" data-aos="fade-up" data-aos-delay="600">
                <a href="students-life.php" class="btn-primary">Discover More</a>
                <a href="campus-facilities.php" class="btn-link">
                  <i class="bi bi-play-circle"></i>
                  <span>Take Virtual Tour</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="activities-showcase">
          <div class="row g-4">
            <div class="col-lg-8" data-aos="fade-right" data-aos-delay="200">
              <div class="featured-activity">
                <div class="activity-media">
                  <img src="assets/img/education/IMG_4657-1024x683.jpg" alt="Featured Activity" class="img-fluid">
                  <div class="activity-overlay">
                    <div class="overlay-content">
                      <h4>Clinical Skills Competitions</h4>
                      <p>Sharpen your practical skills and compete with peers in a supportive environment.</p>
                      <a href="events.php" class="overlay-btn">
                        <i class="bi bi-arrow-right"></i>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-4" data-aos="fade-left" data-aos-delay="300">
              <div class="activities-list">
                <div class="activity-item" data-aos="slide-up" data-aos-delay="350">
                  <div class="activity-thumb">
                    <img src="assets/img/education/IMG_4656-1024x683.jpg" alt="Research Projects" class="img-fluid">
                  </div>
                  <div class="activity-info">
                    <h6>Health Awareness Campaigns</h6>
                    <p>Educate the public on vital health issues.</p>
                  </div>
                </div>

                <div class="activity-item" data-aos="slide-up" data-aos-delay="400">
                  <div class="activity-thumb">
                    <img src="assets/img/education/IMG_4632-scaled.jpg" alt="Annual Nurses' Week" class="img-fluid">
                  </div>
                  <div class="activity-info">
                    <h6>Annual Nurses' Week</h6>
                    <p>A week of celebration, learning, and recognition.</p>
                  </div>
                </div>

                <div class="activity-item" data-aos="slide-up" data-aos-delay="450">
                  <div class="activity-thumb">
                    <img src="assets/img/education/activities-4.webp" alt="Sports and Recreation" class="img-fluid">
                  </div>
                  <div class="activity-info">
                    <h6>Sports and Recreation</h6>
                    <p>Stay active with our various sports teams and facilities.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </section><!-- /Students Life Block Section -->

    
    <section id="recent-news" class="recent-news section">

    <div class="container section-title" data-aos="fade-up">
        <h2>Recent News</h2>
        <p>Stay updated with the latest happenings, achievements, and announcements from our college community.</p>
    </div><div class="container" data-aos="fade-up" data-aos-delay="100">

        <div class="row gy-4">

            <?php
            // Fetch the 4 most recent news articles
            $news_query = $conn->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 4");

            if ($news_query && $news_query->num_rows > 0):
                $delay = 100; // Initial AOS delay
                while ($news = $news_query->fetch_assoc()):
            ?>
                <div class="col-xl-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <article class="post-item d-flex h-100 shadow-sm rounded bg-white overflow-hidden">
                        <div class="post-img" style="width: 200px; min-width: 200px;">
                            <img src="<?php echo htmlspecialchars($news['featured_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($news['title']); ?>" 
                                 class="img-fluid" 
                                 style="height: 100%; object-fit: cover;" 
                                 loading="lazy">
                        </div>

                        <div class="post-content flex-grow-1 p-4">
                            <a href="news.php?category=<?php echo urlencode($news['category']); ?>" class="category text-primary fw-bold small text-uppercase">
                                <?php echo htmlspecialchars($news['category']); ?>
                            </a>

                            <h2 class="post-title h5 mt-2">
                                <a href="news-details.php?slug=<?php echo $news['slug']; ?>" class="text-dark hover-primary">
                                    <?php echo htmlspecialchars($news['title']); ?>
                                </a>
                            </h2>

                            <p class="post-description small text-muted">
                                <?php 
                                    // Strip HTML tags and limit text to 120 characters
                                    $excerpt = strip_tags($news['content']);
                                    echo (strlen($excerpt) > 120) ? substr($excerpt, 0, 120) . '...' : $excerpt; 
                                ?>
                            </p>

                            <div class="post-meta d-flex align-items-center mt-3 border-top pt-3">
                                <div class="post-author d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($news['author_img']); ?>" 
                                         alt="<?php echo htmlspecialchars($news['author']); ?>" 
                                         class="img-fluid rounded-circle me-2" 
                                         style="width: 30px; height: 30px; object-fit: cover;">
                                    <span class="author-name small fw-bold"><?php echo htmlspecialchars($news['author']); ?></span>
                                </div>
                                <span class="post-date small text-muted ms-auto">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </article>
                </div><?php 
                $delay += 100; // Increment delay for next card
                endwhile; 
            else: 
            ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No news articles found at the moment.</p>
                </div>
            <?php endif; ?>

        </div>

    </div>

</section>
  </main>

<?php require_once 'includes/footer.php'; ?>