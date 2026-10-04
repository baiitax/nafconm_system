<?php
require_once 'includes/header.php';
require_once 'config/db.php'; // Ensure your DB connection is included

// 1. Fetch Faculty Members
$faculty_query = $conn->query("SELECT * FROM faculty ORDER BY sort_order ASC, name ASC");
$faculty_members = $faculty_query->fetch_all(MYSQLI_ASSOC);

// 2. Fetch Programs (assuming you already have this logic as per your snippet)
$programs_query = $conn->query("SELECT p.*, d.name as department_name FROM programs p JOIN departments d ON p.department_id = d.id");
$programs = $programs_query->fetch_all(MYSQLI_ASSOC);
?>

<main class="main">
  <div class="page-title light-background">
    <div class="container d-lg-flex justify-content-between align-items-center">
      <h1 class="mb-2 mb-lg-0">Academics</h1>
      <nav class="breadcrumbs">
        <ol>
          <li><a href="index.php">Home</a></li>
          <li class="current">Academics</li>
        </ol>
      </nav>
    </div>
  </div>

  <section id="academics" class="academics section">
    <div class="container" data-aos="fade-up">
      <div class="programs-section mb-5">
          </div>

      <div class="faculty-section mt-5">
        <div class="section-header text-center mb-5" data-aos="fade-up">
          <h3>Meet Our Faculty</h3>
          <p>Learn from experienced educators, clinicians, and mentors</p>
        </div>

        <div class="row g-4">
          <?php if (empty($faculty_members)): ?>
            <div class="col-12 text-center">
              <p class="text-muted">Faculty information is being updated. Please check back soon.</p>
            </div>
          <?php else: ?>
            <?php foreach ($faculty_members as $index => $member): ?>
              <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                <div class="faculty-card">
                  <div class="faculty-img">
                    <img src="<?php echo htmlspecialchars($member['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($member['name']); ?>" 
                         class="img-fluid" 
                         style="width: 100%; height: 350px; object-fit: cover;">
                  </div>
                  <div class="faculty-content">
                    <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                    <p class="faculty-position"><?php echo htmlspecialchars($member['position']); ?></p>
                    <div class="faculty-social">
                      <?php if($member['linkedin_url'] !== '#'): ?>
                        <a href="<?php echo $member['linkedin_url']; ?>"><i class="bi bi-linkedin"></i></a>
                      <?php endif; ?>
                      
                      <?php if($member['twitter_url'] !== '#'): ?>
                        <a href="<?php echo $member['twitter_url']; ?>"><i class="bi bi-twitter"></i></a>
                      <?php endif; ?>
                      
                      <?php if($member['email'] !== '#'): ?>
                        <a href="mailto:<?php echo $member['email']; ?>"><i class="bi bi-envelope"></i></a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>