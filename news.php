<?php 
  $pageTitle = "News";
  include 'includes/header.php';
  include 'config/db.php'; 

  // 1. Fetch Featured Post (Hero)
  $featured_query = $conn->query("SELECT * FROM news WHERE is_featured = 1 ORDER BY created_at DESC LIMIT 1");
  $featured = $featured_query->fetch_assoc();

  // 2. Fetch Secondary Posts (Below Hero)
  $secondary_query = $conn->query("SELECT * FROM news WHERE is_featured = 0 ORDER BY created_at DESC LIMIT 2");

  // 3. Fetch Tab Content
  $top_stories = $conn->query("SELECT * FROM news WHERE is_top_story = 1 ORDER BY created_at DESC LIMIT 5");
  $trending = $conn->query("SELECT * FROM news WHERE is_trending = 1 ORDER BY created_at DESC LIMIT 5");
  $latest_sidebar = $conn->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 5");

  // 4. Main News Grid (With Pagination Logic - Simplified)
  $limit = 6;
  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $offset = ($page - 1) * $limit;
  $main_grid = $conn->query("SELECT * FROM news ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
?>

<main class="main">
  <section id="news-hero" class="news-hero section">
    <div class="container" data-aos="fade-up">
      <div class="row g-4">
        <div class="col-lg-8">
          
          <?php if($featured): ?>
          <article class="featured-post position-relative mb-4" data-aos="fade-up">
            <img src="<?= $featured['featured_image'] ?>" alt="<?= $featured['title'] ?>" class="img-fluid">
            <div class="post-overlay">
              <div class="post-content">
                <div class="post-meta">
                  <span class="category"><?= $featured['category'] ?></span>
                  <span class="date"><?= date('m/d/Y', strtotime($featured['created_at'])) ?></span>
                </div>
                <h2 class="post-title">
                  <a href="news-details.php?slug=<?= $featured['slug'] ?>"><?= $featured['title'] ?></a>
                </h2>
                <p class="post-excerpt"><?= $featured['excerpt'] ?></p>
                <div class="post-author"><span>by</span> <a href="#"><?= $featured['author'] ?></a></div>
              </div>
            </div>
          </article>
          <?php endif; ?>

          <div class="row g-4">
            <?php while($row = $secondary_query->fetch_assoc()): ?>
            <div class="col-md-6">
              <article class="secondary-post" data-aos="fade-up">
                <div class="post-image">
                  <img src="<?= $row['featured_image'] ?>" alt="" class="img-fluid">
                </div>
                <div class="post-content">
                  <div class="post-meta">
                    <span class="category"><?= $row['category'] ?></span>
                    <span class="date"><?= date('m/d/Y', strtotime($row['created_at'])) ?></span>
                  </div>
                  <h3 class="post-title">
                    <a href="news-details.php?slug=<?= $row['slug'] ?>"><?= $row['title'] ?></a>
                  </h3>
                  <div class="post-author">by <a href="#"><?= $row['author'] ?></a></div>
                </div>
              </article>
            </div>
            <?php endwhile; ?>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="news-tabs" data-aos="fade-up">
            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#top-stories" type="button">Top stories</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#trending" type="button">Trending</button></li>
              <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#latest" type="button">Latest</button></li>
            </ul>

            <div class="tab-content">
              <div class="tab-pane fade show active" id="top-stories">
                <?php while($ts = $top_stories->fetch_assoc()): ?>
                <article class="tab-post">
                  <div class="row g-0 align-items-center">
                    <div class="col-4"><img src="<?= $ts['featured_image'] ?>" class="img-fluid"></div>
                    <div class="col-8">
                      <div class="post-content">
                        <span class="category"><?= $ts['category'] ?></span>
                        <h4 class="post-title"><a href="news-details.php?slug=<?= $ts['slug'] ?>"><?= $ts['title'] ?></a></h4>
                      </div>
                    </div>
                  </div>
                </article>
                <?php endwhile; ?>
              </div>

              <div class="tab-pane fade" id="trending">
                <?php while($tr = $trending->fetch_assoc()): ?>
                  <article class="tab-post">
                      <div class="row g-0 align-items-center">
                          <div class="col-4"><img src="<?= $tr['featured_image'] ?>" class="img-fluid"></div>
                          <div class="col-8"><div class="post-content">
                              <h4 class="post-title"><a href="news-details.php?slug=<?= $tr['slug'] ?>"><?= $tr['title'] ?></a></h4>
                          </div></div>
                      </div>
                  </article>
                <?php endwhile; ?>
              </div>

              <div class="tab-pane fade" id="latest">
                 <?php while($ls = $latest_sidebar->fetch_assoc()): ?>
                  <article class="tab-post">
                      <div class="row g-0 align-items-center">
                          <div class="col-4"><img src="<?= $ls['featured_image'] ?>" class="img-fluid"></div>
                          <div class="col-8"><div class="post-content">
                              <h4 class="post-title"><a href="news-details.php?slug=<?= $ls['slug'] ?>"><?= $ls['title'] ?></a></h4>
                          </div></div>
                      </div>
                  </article>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="news-posts" class="news-posts section">
    <div class="container">
      <div class="row gy-4">
        <?php while($item = $main_grid->fetch_assoc()): ?>
        <div class="col-xl-4 col-md-6" data-aos="fade-up">
          <article>
            <div class="post-img"><img src="<?= $item['featured_image'] ?>" class="img-fluid"></div>
            <p class="post-category"><?= $item['category'] ?></p>
            <h2 class="title"><a href="news-details.php?slug=<?= $item['slug'] ?>"><?= $item['title'] ?></a></h2>
            <div class="d-flex align-items-center">
              <img src="<?= $item['author_img'] ?>" class="img-fluid post-author-img flex-shrink-0">
              <div class="post-meta">
                <p class="post-author"><?= $item['author'] ?></p>
                <p class="post-date"><?= date('M j, Y', strtotime($item['created_at'])) ?></p>
              </div>
            </div>
          </article>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </section>

  <section class="pagination-2 section">
    <div class="container">
      <nav class="d-flex justify-content-center">
        <ul class="pagination">
          </ul>
      </nav>
    </div>
  </section>
</main>

<?php include 'includes/footer.php'; ?>