<?php
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$action_msg = "";

// --- Handle Update/Create ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grade'])) {
    $min = $_POST['min_score'];
    $max = $_POST['max_score'];
    $letter = strtoupper($_POST['letter_grade']);
    $gp = $_POST['grade_point'];
    $id = $_POST['grade_id'] ?? null;

    if (!empty($id)) {
        $stmt = $conn->prepare("UPDATE grading_policy SET min_score=?, max_score=?, letter_grade=?, grade_point=? WHERE id=?");
        $stmt->bind_param("ddsdi", $min, $max, $letter, $gp, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO grading_policy (min_score, max_score, letter_grade, grade_point) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ddsd", $min, $max, $letter, $gp);
    }

    if ($stmt->execute()) {
        $action_msg = "Grading rule synchronized successfully.";
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM grading_policy WHERE id = $id");
    header("Location: manage_grading.php");
    exit;
}

// Fetch current policy
$policy = $conn->query("SELECT * FROM grading_policy ORDER BY min_score DESC");

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0">Grading System Configuration</h4>
            <p class="text-muted small mb-0">Define performance benchmarks and grade point weightage.</p>
        </div>
        <button class="btn btn-primary d-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#gradeModal" onclick="prepareCreate()">
            <i class="ti ti-plus fs-4"></i> Add Grade Level
        </button>
    </div>

    <?php if ($action_msg): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><?= $action_msg ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-none border bg-light-primary-subtle h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Policy Summary</h5>
                    <p class="small text-muted mb-4">These thresholds are used globally by the Result Processing Engine to compute student transcripts.</p>
                    
                    <div class="list-group list-group-flush rounded-3 border">
                        <?php 
                        $policy->data_seek(0);
                        while($row = $policy->fetch_assoc()): 
                            $color = ($row['letter_grade'] == 'F') ? 'text-danger' : 'text-primary';
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="fw-bolder mb-0 <?= $color ?>"><?= $row['letter_grade'] ?> (<?= number_format($row['grade_point'], 1) ?>)</h6>
                                <small class="text-muted"><?= $row['min_score'] ?>% - <?= $row['max_score'] ?>%</small>
                            </div>
                            <i class="ti ti-chevron-right text-muted"></i>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow-none border">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light-subtle">
                                <tr class="text-muted fs-2 text-uppercase ls-1">
                                    <th class="ps-4">Letter Grade</th>
                                    <th>Score Range (%)</th>
                                    <th>Grade Point</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $policy->data_seek(0); while($row = $policy->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge rounded-pill bg-dark px-3 py-2 fw-bolder"><?= $row['letter_grade'] ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= number_format($row['min_score'], 0) ?>%</span> 
                                        <span class="text-muted mx-2">to</span> 
                                        <span class="fw-bold text-dark"><?= number_format($row['max_score'], 0) ?>%</span>
                                    </td>
                                    <td><h5 class="mb-0 fw-bolder text-primary"><?= number_format($row['grade_point'], 2) ?></h5></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light-warning text-warning me-2" onclick='prepareEdit(<?= json_encode($row) ?>)' data-bs-toggle="modal" data-bs-target="#gradeModal"><i class="ti ti-edit"></i></button>
                                        <a href="?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-light-danger text-danger" onclick="return confirm('Delete this grade level?')"><i class="ti ti-trash"></i></a>
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
</div>

<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark py-3">
                <h5 class="modal-title text-white fw-bold" id="modalLabel">Define Grade Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="grade_id" id="grade_id">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Letter Grade</label>
                            <input type="text" name="letter_grade" id="letter" class="form-control form-control-lg text-center fw-bold" placeholder="A" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Grade Point</label>
                            <input type="number" step="0.01" name="grade_point" id="gp" class="form-control form-control-lg text-center text-primary fw-bold" placeholder="4.00" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Min Score (%)</label>
                            <input type="number" step="0.01" name="min_score" id="min" class="form-control" placeholder="70" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Max Score (%)</label>
                            <input type="number" step="0.01" name="max_score" id="max" class="form-control" placeholder="100" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="save_grade" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow">
                        Save System Threshold
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function prepareCreate() {
    document.getElementById('modalLabel').innerText = 'Define Grade Rule';
    document.getElementById('grade_id').value = '';
    document.querySelector('form').reset();
}

function prepareEdit(data) {
    document.getElementById('modalLabel').innerText = 'Edit Rule: Grade ' + data.letter_grade;
    document.getElementById('grade_id').value = data.id;
    document.getElementById('letter').value = data.letter_grade;
    document.getElementById('gp').value = data.grade_point;
    document.getElementById('min').value = data.min_score;
    document.getElementById('max').value = data.max_score;
}
</script>

<?php include 'includes/footer.php'; ?>