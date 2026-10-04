<?php
session_start();
require_once '../config/db.php';


$message = "";

// --- 1. HANDLE DELETE ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optional: Fetch image path and delete file from server here
    $conn->query("DELETE FROM faculty WHERE id = $id");
    $message = "<div class='alert alert-success'>Faculty member removed.</div>";
}

// --- 2. HANDLE ADD / UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_faculty'])) {
    $id = (int)$_POST['faculty_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $linkedin = mysqli_real_escape_string($conn, $_POST['linkedin_url']);
    $twitter = mysqli_real_escape_string($conn, $_POST['twitter_url']);
    $sort_order = (int)$_POST['sort_order'];

    // Image Upload Logic
    $image_path = $_POST['existing_image']; // Default to old image
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../assets/img/person/";
        $file_ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_file_name = "faculty_" . time() . "." . $file_ext;
        $target_file = $target_dir . $new_file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = "assets/img/person/" . $new_file_name;
        }
    }

    if ($id > 0) {
        $sql = "UPDATE faculty SET name='$name', position='$position', email='$email', 
                image_path='$image_path', linkedin_url='$linkedin', twitter_url='$twitter', 
                sort_order=$sort_order WHERE id=$id";
    } else {
        $sql = "INSERT INTO faculty (name, position, email, image_path, linkedin_url, twitter_url, sort_order) 
                VALUES ('$name', '$position', '$email', '$image_path', '$linkedin', '$twitter', $sort_order)";
    }

    if ($conn->query($sql)) {
        $message = "<div class='alert alert-success'>Faculty data saved successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}


$faculty = $conn->query("SELECT * FROM faculty ORDER BY sort_order ASC");
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="body-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-semibold">Faculty Directory</h4>
            <button class="btn btn-primary" onclick="openFacultyModal()">
                <i class="ti ti-plus"></i> Add Faculty Member
            </button>
        </div>

        <?php echo $message; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Order</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $faculty->fetch_assoc()): ?>
                            <tr>
                                <td><img src="../<?= $row['image_path'] ?>" width="50" height="50" class="rounded-circle" style="object-fit: cover;"></td>
                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['position']) ?></td>
                                <td><?= $row['sort_order'] ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editFaculty(<?= json_encode($row) ?>)'>
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this member?')">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="facultyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Faculty Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="faculty_id" id="faculty_id" value="0">
                <input type="hidden" name="existing_image" id="existing_image" value="assets/img/person/default.webp">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Position / Title</label>
                        <input type="text" name="position" id="position" class="form-control" placeholder="e.g. Senior Tutor" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Faculty Photo</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">LinkedIn URL</label>
                        <input type="text" name="linkedin_url" id="linkedin_url" class="form-control" value="#">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Twitter/X URL</label>
                        <input type="text" name="twitter_url" id="twitter_url" class="form-control" value="#">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_faculty" class="btn btn-primary w-100">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFacultyModal() {
    document.getElementById('faculty_id').value = 0;
    document.querySelector('form').reset();
    document.getElementById('modalTitle').innerText = "Add Faculty Member";
    new bootstrap.Modal(document.getElementById('facultyModal')).show();
}

function editFaculty(data) {
    document.getElementById('faculty_id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('position').value = data.position;
    document.getElementById('email').value = data.email;
    document.getElementById('linkedin_url').value = data.linkedin_url;
    document.getElementById('twitter_url').value = data.twitter_url;
    document.getElementById('sort_order').value = data.sort_order;
    document.getElementById('existing_image').value = data.image_path;
    document.getElementById('modalTitle').innerText = "Edit Faculty Member";
    new bootstrap.Modal(document.getElementById('facultyModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>