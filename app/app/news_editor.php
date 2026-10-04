<?php
session_start();
require_once '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = ['title'=>'','slug'=>'','content'=>'','excerpt'=>'','category'=>'','author'=>'','is_featured'=>0,'is_trending'=>0,'is_top_story'=>0];

if ($id > 0) {
    $res = $conn->query("SELECT * FROM news WHERE id = $id");
    $article = $res->fetch_assoc();
}

// Save Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $slug = mysqli_real_escape_string($conn, strtolower(str_replace(' ', '-', $_POST['slug'])));
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $excerpt = mysqli_real_escape_string($conn, $_POST['excerpt']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $author = mysqli_real_escape_string($conn, $_POST['author']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_trending = isset($_POST['is_trending']) ? 1 : 0;
    $is_top_story = isset($_POST['is_top_story']) ? 1 : 0;

    // Handle Image Upload
    $img_path = $article['featured_image'] ?? 'assets/img/blog/default.webp';
    if (!empty($_FILES['image']['name'])) {
        $target = "../assets/img/blog/" . time() . "_" . $_FILES['image']['name'];
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $img_path = "assets/img/blog/" . time() . "_" . $_FILES['image']['name'];
        }
    }

    if ($id > 0) {
        $sql = "UPDATE news SET title='$title', slug='$slug', content='$content', excerpt='$excerpt', category='$category', author='$author', featured_image='$img_path', is_featured=$is_featured, is_trending=$is_trending, is_top_story=$is_top_story WHERE id=$id";
    } else {
        $sql = "INSERT INTO news (title, slug, content, excerpt, category, author, featured_image, is_featured, is_trending, is_top_story) 
                VALUES ('$title', '$slug', '$content', '$excerpt', '$category', '$author', '$img_path', $is_featured, $is_trending, $is_top_story)";
    }

    if ($conn->query($sql)) {
        header("Location: news_list.php?msg=Saved");
        exit;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <h4 class="fw-semibold mb-4"><?= ($id > 0) ? 'Edit Article' : 'New Article' ?></h4>
        <div class="card">
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Article Title</label>
                                <input type="text" name="title" class="form-control" value="<?= $article['title'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL Slug (e.g. graduation-2025)</label>
                                <input type="text" name="slug" class="form-control" value="<?= $article['slug'] ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Main Content</label>
                                <textarea name="content" class="form-control tinymice" rows="12" required><?= $article['content'] ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Featured Image</label>
                                <?php if($id > 0): ?><img src="../<?= $article['featured_image'] ?>" class="img-fluid mb-2 rounded"><?php endif; ?>
                                <input type="file" name="image" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="Academics" <?= $article['category']=='Academics'?'selected':'' ?>>Academics</option>
                                    <option value="Campus" <?= $article['category']=='Campus'?'selected':'' ?>>Campus Life</option>
                                    <option value="Community" <?= $article['category']=='Community'?'selected':'' ?>>Community</option>
                                    <option value="Research" <?= $article['category']=='Research'?'selected':'' ?>>Research</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Author Name</label>
                                <input type="text" name="author" class="form-control" value="<?= $article['author'] ?>" placeholder="NAFCONM Admin">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Summary / Excerpt</label>
                                <textarea name="excerpt" class="form-control" rows="3"><?= $article['excerpt'] ?></textarea>
                            </div>
                            <div class="card bg-light p-3 border-0">
                                <h6 class="fw-bold">Display Settings</h6>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" <?= $article['is_featured']?'checked':'' ?>> <label class="form-check-label">Featured (Hero)</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_trending" <?= $article['is_trending']?'checked':'' ?>> <label class="form-check-label">Trending List</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_top_story" <?= $article['is_top_story']?'checked':'' ?>> <label class="form-check-label">Top Story Tab</label></div>
                            </div>
                            <hr>
                            <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Save Article</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>