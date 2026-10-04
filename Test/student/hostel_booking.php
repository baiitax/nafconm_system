<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'student') {
    header("location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$student_id = null;
$action_err = $action_success = "";

// Get student ID from user ID
$sql_student = "SELECT id FROM students WHERE user_id = ?";
if ($stmt_student = $conn->prepare($sql_student)) {
    $stmt_student->bind_param("i", $user_id);
    if ($stmt_student->execute()) {
        $stmt_student->store_result();
        if ($stmt_student->num_rows == 1) {
            $stmt_student->bind_result($s_id);
            if ($stmt_student->fetch()) {
                $student_id = $s_id;
            }
        }
    }
    $stmt_student->close();
}

if ($student_id === null) {
    die("Error: Could not find student profile.");
}

// --- Handle POST Actions ---

// Handle Booking Cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_booking'])) {
    $booking_id_to_cancel = $_POST['booking_id'];
    $bed_id_to_free = $_POST['bed_id'];

    $conn->begin_transaction();
    try {
        $stmt_cancel = $conn->prepare("UPDATE hostel_bookings SET status = 'cancelled' WHERE id = ? AND student_id = ?");
        $stmt_cancel->bind_param("ii", $booking_id_to_cancel, $student_id);
        $stmt_cancel->execute();

        $stmt_free_bed = $conn->prepare("UPDATE beds SET is_available = 1 WHERE id = ?");
        $stmt_free_bed->bind_param("i", $bed_id_to_free);
        $stmt_free_bed->execute();

        $conn->commit();
        $action_success = "Your hostel booking has been cancelled.";
        log_activity($conn, $user_id, "Cancelled hostel booking ID: {$booking_id_to_cancel}");
    } catch (Exception $e) {
        $conn->rollback();
        $action_err = "An error occurred while cancelling your booking.";
    }
}

// Handle New Booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_bed'])) {
    $bed_id_to_book = $_POST['bed_id'];

    $sql_check_booking = "SELECT id FROM hostel_bookings WHERE student_id = ? AND status = 'booked'";
    $stmt_check_booking = $conn->prepare($sql_check_booking);
    $stmt_check_booking->bind_param("i", $student_id);
    $stmt_check_booking->execute();
    $stmt_check_booking->store_result();
    if ($stmt_check_booking->num_rows > 0) {
        $action_err = "You already have an active hostel booking. Please cancel it before making a new one.";
    }
    $stmt_check_booking->close();

    if (empty($action_err)) {
        $conn->begin_transaction();
        try {
            $stmt_check_bed = $conn->prepare("SELECT id FROM beds WHERE id = ? AND is_available = 1 FOR UPDATE");
            $stmt_check_bed->bind_param("i", $bed_id_to_book);
            $stmt_check_bed->execute();
            $stmt_check_bed->store_result();

            if ($stmt_check_bed->num_rows == 1) {
                $stmt_book = $conn->prepare("INSERT INTO hostel_bookings (student_id, bed_id, start_date) VALUES (?, ?, CURDATE())");
                $stmt_book->bind_param("ii", $student_id, $bed_id_to_book);
                $stmt_book->execute();
                $booking_id = $conn->insert_id;

                $stmt_update_bed = $conn->prepare("UPDATE beds SET is_available = 0 WHERE id = ?");
                $stmt_update_bed->bind_param("i", $bed_id_to_book);
                $stmt_update_bed->execute();

                $conn->commit();
                $action_success = "Hostel bed booked successfully!";
                log_activity($conn, $user_id, "Booked bed ID: {$bed_id_to_book}");
            } else {
                $conn->rollback();
                $action_err = "Sorry, this bed is no longer available. Please select another one.";
            }
            $stmt_check_bed->close();
        } catch (Exception $e) {
            $conn->rollback();
            $action_err = "An error occurred during booking. Please try again.";
        }
    }
}

// --- Fetch Data for Display ---

$current_booking = null;
$sql_booking = "SELECT hb.id, hb.bed_id, hb.booked_at, h.name as hostel_name, r.room_number, b.bed_number
                FROM hostel_bookings hb
                JOIN beds b ON hb.bed_id = b.id
                JOIN rooms r ON b.room_id = r.id
                JOIN hostels h ON r.hostel_id = h.id
                WHERE hb.student_id = ? AND hb.status = 'booked'";
if ($stmt_booking = $conn->prepare($sql_booking)) {
    $stmt_booking->bind_param("i", $student_id);
    if ($stmt_booking->execute()) {
        $result = $stmt_booking->get_result();
        if ($result->num_rows == 1) {
            $current_booking = $result->fetch_assoc();
        }
    }
    $stmt_booking->close();
}

$hostel_id = $_GET['hostel_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

$page_data = [];
$view_level = 'hostels';
$breadcrumbs = [['name' => 'Hostel Booking', 'url' => 'hostel_booking.php']];

if (!$current_booking) {
    if ($hostel_id && $room_id) {
        $view_level = 'beds';
        $sql_beds = "SELECT b.id, b.bed_number FROM beds b WHERE b.room_id = ? AND b.is_available = 1 ORDER BY b.bed_number";
        $stmt_beds = $conn->prepare($sql_beds);
        $stmt_beds->bind_param("i", $room_id);
        $stmt_beds->execute();
        $page_data['items'] = $stmt_beds->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_beds->close();

        $sql_info = "SELECT r.room_number, h.id as hostel_id, h.name as hostel_name FROM rooms r JOIN hostels h ON r.hostel_id = h.id WHERE r.id = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("i", $room_id);
        $stmt_info->execute();
        $page_data['info'] = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();
        $breadcrumbs[] = ['name' => $page_data['info']['hostel_name'], 'url' => "hostel_booking.php?hostel_id={$page_data['info']['hostel_id']}"];
        $breadcrumbs[] = ['name' => "Room {$page_data['info']['room_number']}", 'url' => ""];

    } elseif ($hostel_id) {
        $view_level = 'rooms';
        $sql_rooms = "SELECT r.id, r.room_number, r.room_type, r.capacity, (SELECT COUNT(*) FROM beds b WHERE b.room_id = r.id AND b.is_available = 1) as available_beds
                      FROM rooms r WHERE r.hostel_id = ? ORDER BY r.room_number";
        $stmt_rooms = $conn->prepare($sql_rooms);
        $stmt_rooms->bind_param("i", $hostel_id);
        $stmt_rooms->execute();
        $page_data['items'] = $stmt_rooms->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rooms->close();

        $sql_info = "SELECT name FROM hostels WHERE id = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("i", $hostel_id);
        $stmt_info->execute();
        $page_data['info'] = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();
        $breadcrumbs[] = ['name' => $page_data['info']['name'], 'url' => ""];

    } else {
        $view_level = 'hostels';
        $sql_hostels = "SELECT h.id, h.name, h.description, (SELECT COUNT(*) FROM rooms r JOIN beds b ON r.id = b.room_id WHERE r.hostel_id = h.id AND b.is_available = 1) as available_beds
                        FROM hostels h ORDER BY h.name";
        $page_data['items'] = $conn->query($sql_hostels)->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <h5 class="card-title fw-semibold mb-4">Hostel Booking</h5>

    <?php if ($action_success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $action_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($action_err): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $action_err; ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <?php if ($current_booking): ?>
        <div class="card"><div class="card-body"><h5 class="card-title">Your Current Booking</h5><p>You have successfully booked a bed. Here are your details:</p>
            <ul class="list-group list-group-flush mb-4">
                <li class="list-group-item"><strong>Hostel:</strong> <?php echo htmlspecialchars($current_booking['hostel_name']); ?></li>
                <li class="list-group-item"><strong>Room Number:</strong> <?php echo htmlspecialchars($current_booking['room_number']); ?></li>
                <li class="list-group-item"><strong>Bed Number:</strong> <?php echo htmlspecialchars($current_booking['bed_number']); ?></li>
                <li class="list-group-item"><strong>Booking Date:</strong> <?php echo htmlspecialchars(date("F j, Y", strtotime($current_booking['booked_at']))); ?></li>
            </ul>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to cancel your booking? This action cannot be undone.');">
                <input type="hidden" name="booking_id" value="<?php echo $current_booking['id']; ?>"><input type="hidden" name="bed_id" value="<?php echo $current_booking['bed_id']; ?>">
                <button type="submit" name="cancel_booking" class="btn btn-danger">Cancel Booking</button>
            </form>
        </div></div>
    <?php else: ?>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <?php foreach ($breadcrumbs as $crumb): ?>
                <?php if (!empty($crumb['url'])): ?><li class="breadcrumb-item"><a href="<?php echo $crumb['url']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a></li>
                <?php else: ?><li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($crumb['name']); ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ol></nav>
        <div class="card"><div class="card-body">
            <?php if ($view_level === 'hostels'): ?>
                <h5 class="card-title">Step 1: Select a Hostel</h5><div class="list-group">
                <?php foreach ($page_data['items'] as $item): ?><a href="?hostel_id=<?php echo $item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><div><h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6><p class="mb-1"><?php echo htmlspecialchars($item['description']); ?></p></div><span class="badge bg-success rounded-pill"><?php echo $item['available_beds']; ?> beds available</span></a><?php endforeach; ?>
                </div>
            <?php elseif ($view_level === 'rooms'): ?>
                <h5 class="card-title">Step 2: Select a Room in <?php echo htmlspecialchars($page_data['info']['name']); ?></h5><div class="list-group">
                <?php foreach ($page_data['items'] as $item): ?><a href="?hostel_id=<?php echo $hostel_id; ?>&room_id=<?php echo $item['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $item['available_beds'] == 0 ? 'disabled' : ''; ?>"><div><h6 class="mb-1">Room <?php echo htmlspecialchars($item['room_number']); ?></h6><p class="mb-1 text-muted">Type: <?php echo htmlspecialchars($item['room_type']); ?> | Capacity: <?php echo htmlspecialchars($item['capacity']); ?></p></div><span class="badge bg-<?php echo $item['available_beds'] > 0 ? 'success' : 'danger'; ?> rounded-pill"><?php echo $item['available_beds']; ?> beds available</span></a><?php endforeach; ?>
                </div>
            <?php elseif ($view_level === 'beds'): ?>
                <h5 class="card-title">Step 3: Select a Bed in Room <?php echo htmlspecialchars($page_data['info']['room_number']); ?></h5><div class="list-group">
                <?php if (!empty($page_data['items'])): foreach ($page_data['items'] as $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($item['bed_number']); ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post"><input type="hidden" name="bed_id" value="<?php echo $item['id']; ?>"><button type="submit" name="book_bed" class="btn btn-sm btn-primary">Book Now</button></form>
                    </div>
                <?php endforeach; else: ?><div class="alert alert-warning">No available beds in this room.</div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div></div>
    <?php endif; ?>
  </div>
</div>

<?php
include 'includes/footer.php';
?>