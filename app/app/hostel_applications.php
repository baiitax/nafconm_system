<?php
session_start();
require_once '../config/db.php';

// Security check
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// --- 1. DYNAMIC COLUMN DETECTION (Hostels Table) ---
$res_h = $conn->query("SHOW COLUMNS FROM hostels");
$h_cols = []; while($r = $res_h->fetch_assoc()){ $h_cols[] = $r['Field']; }

$cap_col  = in_array('capacity', $h_cols) ? 'capacity' : (in_array('total_beds', $h_cols) ? 'total_beds' : 'id');
$name_col = in_array('name', $h_cols) ? 'name' : (in_array('hostel_name', $h_cols) ? 'hostel_name' : 'id');
$room_col = in_array('room_number', $h_cols) ? 'room_number' : (in_array('room_no', $h_cols) ? 'room_no' : 'id');

// --- 2. DYNAMIC COLUMN DETECTION (Applications Table) ---
$res_a = $conn->query("SHOW COLUMNS FROM hostel_applications");
$a_cols = []; while($r = $res_a->fetch_assoc()){ $a_cols[] = $r['Field']; }

// Determine safest sort column (Use created_at if exists, otherwise use id)
$sort_col = in_array('created_at', $a_cols) ? 'created_at' : 'id';

// --- 3. Handle Decision Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_decision'])) {
    $app_id = intval($_POST['app_id']);
    $status = $_POST['status'];
    $room_id = !empty($_POST['room_id']) ? intval($_POST['room_id']) : null;

    $stmt = $conn->prepare("UPDATE hostel_applications SET status = ?, hostel_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $status, $room_id, $app_id);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Application status updated successfully.";
    }
    header("Location: hostel_applications.php");
    exit;
}

// --- 4. Fetch Applications (Safe Query) ---
$sql = "SELECT ha.*, s.full_name, s.student_id_number, h.$name_col as hostel_name, h.$room_col as room_number
        FROM hostel_applications ha
        JOIN students s ON ha.student_id = s.id
        LEFT JOIN hostels h ON ha.hostel_id = h.id
        ORDER BY ha.$sort_col DESC";
$applications = $conn->query($sql);

// --- 5. Fetch Available Rooms (Safe Query) ---
$rooms_sql = "SELECT id, $name_col as h_name, $room_col as h_room, $cap_col as h_cap, 
              (SELECT COUNT(*) FROM hostel_applications WHERE hostel_id = hostels.id AND status = 'approved') as occupied 
              FROM hostels HAVING occupied < h_cap";
$available_rooms = $conn->query($rooms_sql);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-md-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0 text-dark">Process Accommodations</h4>
            <p class="text-muted small mb-0">Sorting by: <b><?= $sort_col ?></b> | Capacity column: <b><?= $cap_col ?></b></p>
        </div>
    </div>

    

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
    <?php endif; ?>

    <div class="card shadow-none border">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle text-uppercase fs-2 fw-bolder">
                        <tr>
                            <th class="ps-4">Student</th>
                            <th>Current Room</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="fs-3">
                        <?php while($row = $applications->fetch_assoc()): 
                            $badge = ($row['status'] == 'approved') ? 'bg-success' : (($row['status'] == 'pending') ? 'bg-warning text-dark' : 'bg-danger');
                        ?>
                        <tr>
                            <td class="ps-4">
                                <h6 class="fw-bold mb-0"><?= strtoupper($row['full_name']) ?></h6>
                                <small class="text-muted"><?= $row['student_id_number'] ?></small>
                            </td>
                            <td><?= $row['hostel_name'] ? $row['hostel_name']." (".$row['room_number'].")" : '<span class="text-muted">Not Assigned</span>' ?></td>
                            <td><span class="badge <?= $badge ?> px-3 rounded-pill"><?= ucfirst($row['status']) ?></span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-dark px-3 fw-bold" onclick='openModal(<?= $row['id'] ?>)' data-bs-toggle="modal" data-bs-target="#actionModal">Handle</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Update Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="app_id" id="modal_app_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Assign Bed space</label>
                    <select name="room_id" class="form-select border-2">
                        <option value="">-- Select Room --</option>
                        <?php $available_rooms->data_seek(0); while($rm = $available_rooms->fetch_assoc()): ?>
                            <option value="<?= $rm['id'] ?>"><?= $rm['h_name'] ?> - Room <?= $rm['h_room'] ?> (Avail: <?= $rm['h_cap']-$rm['occupied'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-bold">Action</label>
                    <select name="status" class="form-select border-2" required>
                        <option value="pending">Keep Pending</option>
                        <option value="approved">Approve Application</option>
                        <option value="rejected">Reject Application</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" name="submit_decision" class="btn btn-primary w-100 py-2 fw-bold shadow">Submit Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('modal_app_id').value = id;
}
</script>

<?php include 'includes/footer.php'; ?>