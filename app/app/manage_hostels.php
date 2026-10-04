<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

// --- Handle Delete ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM hostels WHERE id = $id");
    header("Location: manage_hostels.php");
    exit;
}

// --- Fetch Inventory ---
// We use COALESCE and IFNULL to provide fallbacks for missing columns
$sql = "SELECT h.*, 
        (SELECT COUNT(*) FROM hostel_applications WHERE hostel_id = h.id AND status = 'approved') as occupied 
        FROM hostels h ORDER BY h.id DESC";
$inventory = $conn->query($sql);

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bolder mb-0">Hostel Inventory</h4>
            <p class="text-muted small mb-0">Manage rooms and bed capacities across campus.</p>
        </div>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#roomModal">
            <i class="ti ti-plus me-1"></i> Add Room
        </button>
    </div>

    <div class="card shadow-none border">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light-subtle text-uppercase fs-2">
                        <tr>
                            <th class="ps-4">Building</th>
                            <th>Room No.</th>
                            <th>Gender</th>
                            <th>Capacity</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="fs-3">
                        <?php if($inventory && $inventory->num_rows > 0): ?>
                            <?php while($row = $inventory->fetch_assoc()): 
                                // Defensive programming: Check if keys exist before using them
                                $h_name = $row['name'] ?? $row['hostel_name'] ?? 'Unknown';
                                $h_room = $row['room_number'] ?? $row['room_no'] ?? 'N/A';
                                $h_gender = $row['gender'] ?? 'Not Set';
                                $h_cap = $row['capacity'] ?? 0;
                                $h_occ = $row['occupied'] ?? 0;
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($h_name) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($h_room) ?></span></td>
                                <td>
                                    <span class="badge <?= $h_gender == 'Male' ? 'bg-light-info text-info' : 'bg-light-danger text-danger' ?>">
                                        <?= $h_gender ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="me-2"><?= $h_occ ?>/<?= $h_cap ?></span>
                                        <div class="progress flex-grow-1" style="height: 5px; min-width: 50px;">
                                            <div class="progress-bar" style="width: <?= ($h_cap > 0) ? ($h_occ/$h_cap)*100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-light-danger text-danger" onclick="return confirm('Delete this room?')">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-5">No hostel records found. Please add a room.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="roomModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_room.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Hostel Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Building Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Mandela Block">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Room Number</label>
                        <input type="text" name="room_number" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Total Bed Capacity</label>
                    <input type="number" name="capacity" class="form-control" required value="4">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_room" class="btn btn-primary w-100">Save Room</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>