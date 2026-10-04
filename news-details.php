<?php 
  include 'config/db.php'; 

  // 1. Get the slug from URL and sanitize
  $slug = isset($_GET['slug']) ? mysqli_real_escape_string($conn, $_GET['slug']) : '';

  if (empty($slug)) {
      header("Location: news.php");
      exit();
  }

  // 2. Fetch the specific news article
  $query = $conn->prepare("SELECT * FROM news WHERE slug = ? LIMIT 1");
  $query->bind_param("s", $slug);
  $query->execute();
  $result = $query->get_result();
  $article = $result->fetch_assoc();

  // Redirect if news doesn't exist
  if (!$article) {
      header("Location: news.php");
      exit();
  }

  // 3. Update view count (optional but good for 'trending' logic)
  $conn->query("UPDATE news SET views = views + 1 WHERE id = " . $article['id']);

  // 4. Fetch Recent Posts for Sidebar
  $recent_posts = $conn->query("SELECT title, slug, featured_image, created_at FROM news WHERE id != {$article['id']} ORDER BY created_at DESC LIMIT 5");

  $pageTitle = $article['title'];
  include 'includes/header.php';
?>

<main class="main">

  <div class="page-title light-background">
    <div class="container d-lg-flex justify-content-between align-items-center">
      <h1 class="mb-2 mb-lg-0">News Details</h1>
      <nav class="breadcrumbs">
        <ol>
          <li><a href="index.php">Home</a></li>
          <li><a href="news.php">News</a></li>
          <li class="current">Details</li>
        </ol>
      </nav>
    </div>
  </div>

  <section id="news-details" class="news-details section">
    <div class="container">
      <div class="row g-5">

        <div class="col-lg-8" data-aos="fade-up">
          <article class="article">
            <div class="post-img">
              <img src="<?= $article['featured_image'] ?>" alt="" class="img-fluid rounded">
            </div>

            <h2 class="title mt-4"><?= $article['title'] ?></h2>

            <div class="meta-top d-flex align-items-center mb-4">
              <ul class="d-flex list-unstyled m-0">
                <li class="d-flex align-items-center me-3">
                    <i class="bi bi-person"></i> <a href="#" class="ms-1"><?= $article['author'] ?></a>
                </li>
                <li class="d-flex align-items-center me-3">
                    <i class="bi bi-clock"></i> <a href="#" class="ms-1"><time><?= date('M j, Y', strtotime($article['created_at'])) ?></time></a>
                </li>
                <li class="d-flex align-items-center">
                    <i class="bi bi-folder2"></i> <a href="#" class="ms-1"><?= $article['category'] ?></a>
                </li>
              </ul>
            </div>

            <div class="content">
              <?= nl2br($article['content']) ?>
            </div>

            <div class="meta-bottom mt-5 border-top pt-3">
              <i class="bi bi-tags"></i>
              <ul class="tags d-inline list-unstyled">
                <li><a href="#"><?= $article['category'] ?></a></li>
                <li><a href="#">NAFCONM</a></li>
              </ul>
            </div>
          </article>
        </div>

        <div class="col-lg-4">
          <div class="sidebar shadow-sm p-4 rounded bg-white">
            
            <div class="sidebar-item recent-posts">
              <h3 class="sidebar-title">Recent News</h3>
              <div class="mt-3">
                <?php while($post = $recent_posts->fetch_assoc()): ?>
                <div class="post-item mt-3 d-flex border-bottom pb-2">
                  <img src="<?= $post['featured_image'] ?>" alt="" class="flex-shrink-0 rounded me-3" style="width: 80px; height: 60px; object-fit: cover;">
                  <div>
                    <h4 class="h6 mb-1"><a href="news-details.php?slug=<?= $post['slug'] ?>" class="text-dark fw-bold"><?= $post['title'] ?></a></h4>
                    <time class="small text-muted"><?php echo date('M j, Y', strtotime($post['created_at'])); ?></time>
                  </div>
                </div>
                <?php endwhile; ?>
              </div>
            </div>

            <div class="sidebar-item categories mt-5">
              <h3 class="sidebar-title">Categories</h3>
              <ul class="mt-3 list-unstyled">
                <li><a href="#">Academics <span>(12)</span></a></li>
                <li><a href="#">Community <span>(5)</span></a></li>
                <li><a href="#">Research <span>(8)</span></a></li>
              </ul>
            </div>

          </div>
        </div>

      </div>
    </div>
  </section>

</main>

<?php include 'includes/footer.php'; ?>