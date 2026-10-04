<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action_err = $action_success = "";

// --- HOSTEL CRUD ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Create/Edit Hostel
    if (isset($_POST['save_hostel'])) {
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $description = trim($_POST['description']);
        $hostel_id = $_POST['hostel_id'] ?? null;

        if (empty($name)) {
            $action_err = "Hostel name is required.";
        } else {
            if (empty($hostel_id)) { // Create
                $sql = "INSERT INTO hostels (name, location, description) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $name, $location, $description);
                if ($stmt->execute()) {
                    $action_success = "Hostel created successfully.";
                    log_activity($conn, $user_id, "Created hostel '{$name}'.");
                } else { $action_err = "Error creating hostel."; }
            } else { // Update
                $sql = "UPDATE hostels SET name = ?, location = ?, description = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $name, $location, $description, $hostel_id);
                if ($stmt->execute()) {
                    $action_success = "Hostel updated successfully.";
                    log_activity($conn, $user_id, "Updated hostel ID: {$hostel_id}.");
                } else { $action_err = "Error updating hostel."; }
            }
            $stmt->close();
        }
    }

    // Delete Hostel
    if (isset($_POST['delete_hostel'])) {
        $hostel_id = $_POST['hostel_id'];
        $sql = "DELETE FROM hostels WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $hostel_id);
        if ($stmt->execute()) {
            $action_success = "Hostel deleted successfully.";
            log_activity($conn, $user_id, "Deleted hostel ID: {$hostel_id}.");
        } else {
            if ($conn->errno == 1451) { $action_err = "Cannot delete hostel with associated rooms."; } 
            else { $action_err = "Error deleting hostel."; }
        }
        $stmt->close();
    }

    // --- ROOM CRUD ---
    if (isset($_POST['save_room'])) {
        $hostel_id = $_POST['hostel_id'];
        $room_number = trim($_POST['room_number']);
        $capacity = (int)$_POST['capacity'];
        $room_type = trim($_POST['room_type']);
        $room_id = $_POST['room_id'] ?? null;

        if (empty($hostel_id) || empty($room_number) || empty($capacity)) {
            $action_err = "Hostel, room number, and capacity are required.";
        } else {
            if (empty($room_id)) { // Create
                $sql = "INSERT INTO rooms (hostel_id, room_number, capacity, room_type) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isis", $hostel_id, $room_number, $capacity, $room_type);
                if ($stmt->execute()) {
                    $action_success = "Room created successfully.";
                    log_activity($conn, $user_id, "Created room '{$room_number}'.");
                } else { $action_err = "Error creating room."; }
            } else { // Update
                $sql = "UPDATE rooms SET hostel_id = ?, room_number = ?, capacity = ?, room_type = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isisi", $hostel_id, $room_number, $capacity, $room_type, $room_id);
                if ($stmt->execute()) {
                    $action_success = "Room updated successfully.";
                    log_activity($conn, $user_id, "Updated room ID: {$room_id}.");
                } else { $action_err = "Error updating room."; }
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_room'])) {
        $room_id = $_POST['room_id'];
        $sql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        if ($stmt->execute()) {
            $action_success = "Room deleted successfully.";
            log_activity($conn, $user_id, "Deleted room ID: {$room_id}.");
        } else {
            if ($conn->errno == 1451) { $action_err = "Cannot delete room with associated beds or bookings."; } 
            else { $action_err = "Error deleting room."; }
        }
        $stmt->close();
    }

    // --- BED CRUD ---
    if (isset($_POST['save_bed'])) {
        $room_id = $_POST['room_id'];
        $bed_number = trim($_POST['bed_number']);
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $bed_id = $_POST['bed_id'] ?? null;

        if (empty($room_id) || empty($bed_number)) {
            $action_err = "Room and bed number are required.";
        } else {
            if (empty($bed_id)) { // Create
                $sql = "INSERT INTO beds (room_id, bed_number, is_available) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $room_id, $bed_number, $is_available);
                if ($stmt->execute()) {
                    $action_success = "Bed created successfully.";
                    log_activity($conn, $user_id, "Created bed '{$bed_number}'.");
                } else { $action_err = "Error creating bed."; }
            } else { // Update
                $sql = "UPDATE beds SET room_id = ?, bed_number = ?, is_available = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isii", $room_id, $bed_number, $is_available, $bed_id);
                if ($stmt->execute()) {
                    $action_success = "Bed updated successfully.";
                    log_activity($conn, $user_id, "Updated bed ID: {$bed_id}.");
                } else { $action_err = "Error updating bed."; }
            }
            $stmt->close();
        }
    }

    if (isset($_POST['delete_bed'])) {
        $bed_id = $_POST['bed_id'];
        $sql = "DELETE FROM beds WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bed_id);
        if ($stmt->execute()) {
            $action_success = "Bed deleted successfully.";
            log_activity($conn, $user_id, "Deleted bed ID: {$bed_id}.");
        } else {
            if ($conn->errno == 1451) { $action_err = "Cannot delete a bed that has active bookings."; } 
            else { $action_err = "Error deleting bed."; }
        }
        $stmt->close();
    }
}

// --- Fetch Data for Display ---
$hostels = $conn->query("SELECT * FROM hostels ORDER BY name")->fetch_all(MYSQLI_ASSOC); // Keep this for dropdowns

// Fetch rooms with occupied count
$rooms_query = "
    SELECT
        r.*,
        h.name as hostel_name,
        COUNT(hb.id) AS occupied_beds_count
    FROM
        rooms r
    JOIN
        hostels h ON r.hostel_id = h.id
    LEFT JOIN
        beds b ON r.id = b.room_id
    LEFT JOIN
        hostel_bookings hb ON b.id = hb.bed_id AND hb.status = 'booked'
    GROUP BY
        r.id
    ORDER BY
        h.name, r.room_number";
$rooms = $conn->query($rooms_query)->fetch_all(MYSQLI_ASSOC);

// Fetch beds with student info
$beds_query = "
    SELECT
        b.*,
        r.room_number,
        h.name as hostel_name,
        s.full_name AS student_occupant_name,
        s.id AS student_occupant_id
    FROM
        beds b
    JOIN
        rooms r ON b.room_id = r.id
    JOIN
        hostels h ON r.hostel_id = h.id
    LEFT JOIN
        hostel_bookings hb ON b.id = hb.bed_id AND hb.status = 'booked'
    LEFT JOIN
        students s ON hb.student_id = s.id
    ORDER BY
        h.name, r.room_number, b.bed_number";
$beds = $conn->query($beds_query)->fetch_all(MYSQLI_ASSOC);

// Fetch students for room accordions
$students_in_rooms = [];
$students_result = $conn->query("SELECT s.id AS student_id, s.full_name AS student_name, s.student_id_number, r.id AS room_id, r.room_number, h.name AS hostel_name, b.bed_number FROM students s JOIN hostel_bookings hb ON s.id = hb.student_id AND hb.status = 'booked' JOIN beds b ON hb.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id ORDER BY h.name, r.room_number, b.bed_number");
while ($row = $students_result->fetch_assoc()) {
    $students_in_rooms[$row['room_id']][] = $row;
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Manage Hostels</h5>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <div class="card">
      <div class="card-body">
        <ul class="nav nav-tabs" id="hostelTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" id="hostels-tab" data-bs-toggle="tab" data-bs-target="#hostels-pane" type="button" role="tab">Hostels</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="rooms-tab" data-bs-toggle="tab" data-bs-target="#rooms-pane" type="button" role="tab">Rooms</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" id="beds-tab" data-bs-toggle="tab" data-bs-target="#beds-pane" type="button" role="tab">Beds</button></li>
        </ul>
        <div class="tab-content" id="hostelTabsContent">
          <!-- Hostels Tab -->
          <div class="tab-pane fade show active" id="hostels-pane" role="tabpanel">
            <div class="d-flex justify-content-end my-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hostelModal" onclick="prepareHostelCreate()">Add New Hostel</button></div>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Location</th><th>Description</th><th>Actions</th></tr></thead><tbody>
              <?php foreach ($hostels as $hostel): ?><tr><td><?php echo htmlspecialchars($hostel['name']); ?></td><td><?php echo htmlspecialchars($hostel['location']); ?></td><td><?php echo htmlspecialchars($hostel['description']); ?></td><td><button class="btn btn-sm btn-info" onclick='prepareHostelEdit(<?php echo json_encode($hostel); ?>)'>Edit</button> <button class="btn btn-sm btn-danger" onclick='prepareDelete("hostel", <?php echo $hostel["id"]; ?>)'>Delete</button></td></tr><?php endforeach; ?>
            </tbody></table></div>
          </div>
          <!-- Rooms Tab -->
          <div class="tab-pane fade" id="rooms-pane" role="tabpanel">
            <div class="d-flex justify-content-end my-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="prepareRoomCreate()">Add New Room</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room Number</th>
                            <th>Hostel</th>
                            <th>Type</th>
                            <th>Availability</th> <!-- Changed from Capacity -->
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room):
                            $available_beds = $room['capacity'] - $room['occupied_beds_count'];
                            $badge_class = $available_beds > 0 ? 'bg-success' : 'bg-danger';
                            $badge_text = $available_beds > 0 ? "Available ({$available_beds})" : "Occupied";
                            $apply_link_attr = $available_beds > 0 ? 'href="#" onclick="showApplyModal(' . $room['id'] . ')"' : ''; // Placeholder for apply modal
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($room['hostel_name']); ?></td>
                                <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>" <?php echo $apply_link_attr; ?>><?php echo $badge_text; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick='prepareRoomEdit(<?php echo json_encode($room); ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick='prepareDelete("room", <?php echo $room["id"]; ?>)'>Delete</button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5">
                                    <div class="accordion accordion-flush" id="accordionRoom<?php echo $room['id']; ?>">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingRoom<?php echo $room['id']; ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRoom<?php echo $room['id']; ?>" aria-expanded="false" aria-controls="collapseRoom<?php echo $room['id']; ?>">
                                                    View Occupants (<?php echo $room['occupied_beds_count']; ?>/<?php echo $room['capacity']; ?>)
                                                </button>
                                            </h2>
                                            <div id="collapseRoom<?php echo $room['id']; ?>" class="accordion-collapse collapse" aria-labelledby="headingRoom<?php echo $room['id']; ?>" data-bs-parent="#accordionRoom<?php echo $room['id']; ?>">
                                                <div class="accordion-body">
                                                    <?php if (!empty($students_in_rooms[$room['id']])): ?>
                                                        <ul class="list-group">
                                                            <?php foreach ($students_in_rooms[$room['id']] as $student): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <?php echo htmlspecialchars($student['student_name']); ?> (ID: <?php echo htmlspecialchars($student['student_id_number']); ?>)
                                                                    <span class="badge bg-primary rounded-pill">Bed: <?php echo htmlspecialchars($student['bed_number']); ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p>No students currently assigned to this room.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
          </div>
          <!-- Beds Tab -->
          <div class="tab-pane fade" id="beds-pane" role="tabpanel">
            <div class="d-flex justify-content-end my-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bedModal" onclick="prepareBedCreate()">Add New Bed</button></div>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Bed Number</th>
                            <th>Room</th>
                            <th>Hostel</th>
                            <th>Status</th> <!-- Changed from Available -->
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beds as $bed):
                            $bed_status_class = $bed['is_available'] ? 'bg-success' : 'bg-danger';
                            $bed_status_text = $bed['is_available'] ? 'Available' : 'Occupied';
                            $occupant_info = !$bed['is_available'] && $bed['student_occupant_name'] ? ' (' . htmlspecialchars($bed['student_occupant_name']) . ')' : '';
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bed['bed_number']); ?></td>
                                <td><?php echo htmlspecialchars($bed['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($bed['hostel_name']); ?></td>
                                <td><span class="badge <?php echo $bed_status_class; ?>"><?php echo $bed_status_text . $occupant_info; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick='prepareBedEdit(<?php echo json_encode($bed); ?>)'>Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick='prepareDelete("bed", <?php echo $bed["id"]; ?>)'>Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hostel Modal -->
<div class="modal fade" id="hostelModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="hostelModalLabel">Add Hostel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="hostel_id" id="hostelId"><div class="mb-3"><label for="hostelName" class="form-label">Name</label><input type="text" class="form-control" id="hostelName" name="name" required></div><div class="mb-3"><label for="hostelLocation" class="form-label">Location</label><input type="text" class="form-control" id="hostelLocation" name="location"></div><div class="mb-3"><label for="hostelDescription" class="form-label">Description</label><textarea class="form-control" id="hostelDescription" name="description" rows="3"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_hostel" class="btn btn-primary">Save</button></div></form></div></div></div>

<!-- Room Modal -->
<div class="modal fade" id="roomModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="roomModalLabel">Add Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="room_id" id="roomId"><div class="mb-3"><label for="roomHostelId" class="form-label">Hostel</label><select class="form-select" id="roomHostelId" name="hostel_id" required><option value="">Select Hostel...</option><?php foreach ($hostels as $hostel): ?><option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label for="roomNumber" class="form-label">Room Number</label><input type="text" class="form-control" id="roomNumber" name="room_number" required></div><div class="mb-3"><label for="roomCapacity" class="form-label">Capacity</label><input type="number" class="form-control" id="roomCapacity" name="capacity" required min="1"></div><div class="mb-3"><label for="roomType" class="form-label">Room Type</label><input type="text" class="form-control" id="roomType" name="room_type"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_room" class="btn btn-primary">Save</button></div></form></div></div></div>

<!-- Bed Modal -->
<div class="modal fade" id="bedModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="bedModalLabel">Add Bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="bed_id" id="bedId"><div class="mb-3"><label for="bedRoomId" class="form-label">Room</label><select class="form-select" id="bedRoomId" name="room_id" required><option value="">Select Room...</option><?php foreach ($rooms as $room): ?><option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['hostel_name'] . ' - ' . $room['room_number']); ?></option><?php endforeach; ?></select></div><div class="mb-3"><label for="bedNumber" class="form-label">Bed Number/Name</label><input type="text" class="form-control" id="bedNumber" name="bed_number" required></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="isAvailable" name="is_available" value="1" checked><label class="form-check-label" for="isAvailable">Is Available</label></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="save_bed" class="btn btn-primary">Save</button></div></form></div></div></div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Are you sure you want to delete this item? This action cannot be undone.</p><input type="hidden" id="deleteId" name=""><input type="hidden" id="deleteType" name=""></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" id="deleteButton" class="btn btn-danger">Delete</button></div></form></div></div></div>

<script>
const hostelModal = new bootstrap.Modal(document.getElementById('hostelModal'));
const roomModal = new bootstrap.Modal(document.getElementById('roomModal'));
const bedModal = new bootstrap.Modal(document.getElementById('bedModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

function prepareHostelCreate() {
    document.getElementById('hostelModalLabel').innerText = 'Add Hostel';
    document.getElementById('hostelModal').querySelector('form').reset();
    document.getElementById('hostelId').value = '';
    hostelModal.show();
}

function prepareHostelEdit(hostel) {
    document.getElementById('hostelModalLabel').innerText = 'Edit Hostel';
    document.getElementById('hostelId').value = hostel.id;
    document.getElementById('hostelName').value = hostel.name;
    document.getElementById('hostelLocation').value = hostel.location;
    document.getElementById('hostelDescription').value = hostel.description;
    hostelModal.show();
}

function prepareRoomCreate() {
    document.getElementById('roomModalLabel').innerText = 'Add Room';
    document.getElementById('roomModal').querySelector('form').reset();
    document.getElementById('roomId').value = '';
    roomModal.show();
}

function prepareRoomEdit(room) {
    document.getElementById('roomModalLabel').innerText = 'Edit Room';
    document.getElementById('roomId').value = room.id;
    document.getElementById('roomHostelId').value = room.hostel_id;
    document.getElementById('roomNumber').value = room.room_number;
    document.getElementById('roomCapacity').value = room.capacity;
    document.getElementById('roomType').value = room.room_type;
    roomModal.show();
}

function prepareBedCreate() {
    document.getElementById('bedModalLabel').innerText = 'Add Bed';
    document.getElementById('bedModal').querySelector('form').reset();
    document.getElementById('bedId').value = '';
    document.getElementById('isAvailable').checked = true;
    bedModal.show();
}

function prepareBedEdit(bed) {
    document.getElementById('bedModalLabel').innerText = 'Edit Bed';
    document.getElementById('bedId').value = bed.id;
    document.getElementById('bedRoomId').value = bed.room_id;
    document.getElementById('bedNumber').value = bed.bed_number;
    document.getElementById('isAvailable').checked = bed.is_available == 1;
    bedModal.show();
}

function prepareDelete(type, id) {
    document.getElementById('deleteId').name = type + '_id';
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteButton').name = 'delete_' + type;
    deleteModal.show();
}

// Placeholder function for applying to a room
function showApplyModal(roomId) {
    alert('You clicked to apply for Room ID: ' + roomId + '. This would open a hostel application form.');
}
</script>

<?php
include 'includes/footer.php';
?>