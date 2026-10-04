<?php 
  $pageTitle = "Public Notices & Announcements";
  include 'includes/header.php';
  include 'config/db.php'; 

  // Fetch only PUBLIC announcements
  $sql = "SELECT * FROM announcements WHERE audience = 'public' ORDER BY is_pinned DESC, created_at DESC";
  $notices = $conn->query($sql);
?>

<main class="main">
    <div class="page-title light-background">
      <div class="container d-lg-flex justify-content-between align-items-center">
        <h1 class="mb-2 mb-lg-0">Public Announcements</h1>
        <nav class="breadcrumbs">
          <ol>
            <li><a href="index.php">Home</a></li>
            <li class="current">Notice Board</li>
          </ol>
        </nav>
      </div>
    </div>

    <section class="section">
        <div class="container" data-aos="fade-up">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    
                    <?php if($notices->num_rows > 0): ?>
                        <?php while($row = $notices->fetch_assoc()): ?>
                            <div class="card mb-4 border-0 shadow-sm overflow-hidden <?= $row['is_pinned'] ? 'border-start border-4 border-warning' : '' ?>">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <?php if($row['is_pinned']): ?>
                                                <span class="badge bg-warning text-dark mb-2"><i class="bi bi-pin-angle-fill"></i> IMPORTANT</span>
                                            <?php endif; ?>
                                            <h3 class="h4 fw-bold text-primary mb-1"><?= htmlspecialchars($row['title']) ?></h3>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-muted small d-block"><?= date('D, M jS, Y', strtotime($row['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="announcement-text text-secondary" style="line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($row['content'])) ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-light border-0 py-2 px-4">
                                    <small class="text-muted italic">Official Communication - NAFCONM Registry</small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-megaphone display-1 text-muted"></i>
                            <p class="mt-3 fs-5 text-muted">No public announcements at this time.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>