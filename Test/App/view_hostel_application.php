<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if the user is logged in and is a superadmin
if (!isset($_SESSION["loggedin"]) || $_SESSION["user_type"] !== 'superadmin') {
    header("location: ../index.php");
    exit;
}

$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$application_id) {
    header("location: hostel_applications.php");
    exit;
}

// Handle application review and booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['review_application'])) {
    $status = $_POST['status'];
    $remarks = trim($_POST['remarks']);
    $bed_id = !empty($_POST['bed_id']) ? (int)$_POST['bed_id'] : null;
    $student_id = (int)$_POST['student_id'];
    $current_booking_id = !empty($_POST['current_booking_id']) ? (int)$_POST['current_booking_id'] : null;
    $current_bed_id = !empty($_POST['current_bed_id']) ? (int)$_POST['current_bed_id'] : null;
    $reviewed_by_user_id = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        // Update application status and remarks
        $sql_update_app = "UPDATE hostel_applications SET status = ?, remarks = ?, approved_by_user_id = ?, approval_date = NOW() WHERE id = ?";
        $stmt_update_app = $conn->prepare($sql_update_app);
        $stmt_update_app->bind_param("ssii", $status, $remarks, $reviewed_by_user_id, $application_id);
        $stmt_update_app->execute();
        $stmt_update_app->close();

        // If a bed was previously assigned, make it available again
        if ($current_bed_id) {
            $sql_free_bed = "UPDATE beds SET is_available = 1 WHERE id = ?";
            $stmt_free_bed = $conn->prepare($sql_free_bed);
            $stmt_free_bed->bind_param("i", $current_bed_id);
            $stmt_free_bed->execute();
            $stmt_free_bed->close();
        }
        // If there was a booking, cancel it
        if ($current_booking_id) {
            $sql_cancel_booking = "UPDATE hostel_bookings SET status = 'cancelled' WHERE id = ?";
            $stmt_cancel_booking = $conn->prepare($sql_cancel_booking);
            $stmt_cancel_booking->bind_param("i", $current_booking_id);
            $stmt_cancel_booking->execute();
            $stmt_cancel_booking->close();
        }

        // If approved and a new bed is selected, create a new booking
        if ($status === 'Approved' && $bed_id) {
            // Fetch active session
            $active_session_res = $conn->query("SELECT start_date, end_date FROM sessions WHERE is_active = 1 LIMIT 1");
            if ($active_session_res->num_rows > 0) {
                $active_session = $active_session_res->fetch_assoc();
                $start_date = $active_session['start_date'];
                $end_date = $active_session['end_date'];

                // Create new booking
                $sql_book = "INSERT INTO hostel_bookings (student_id, bed_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'booked')";
                $stmt_book = $conn->prepare($sql_book);
                $stmt_book->bind_param("iiss", $student_id, $bed_id, $start_date, $end_date);
                $stmt_book->execute();
                $stmt_book->close();

                // Mark the new bed as unavailable
                $sql_occupy_bed = "UPDATE beds SET is_available = 0 WHERE id = ?";
                $stmt_occupy_bed = $conn->prepare($sql_occupy_bed);
                $stmt_occupy_bed->bind_param("i", $bed_id);
                $stmt_occupy_bed->execute();
                $stmt_occupy_bed->close();
            } else {
                throw new Exception("No active session found to create a booking.");
            }
        }

        $conn->commit();
        $_SESSION['action_success'] = "Application reviewed successfully.";
        log_activity($conn, $reviewed_by_user_id, "Reviewed hostel application ID {$application_id}, status set to {$status}.");

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['action_error'] = "Error: " . $e->getMessage();
    }

    header("location: hostel_applications.php");
    exit;
}

// Fetch application details
$application = null;
$sql_app = "SELECT ha.*, s.full_name, s.student_id_number, s.email, s.phone, u.username 
            FROM hostel_applications ha
            JOIN students s ON ha.student_id = s.id
            JOIN users u ON s.user_id = u.id
            WHERE ha.id = ?";
$stmt_app = $conn->prepare($sql_app);
$stmt_app->bind_param("i", $application_id);
$stmt_app->execute();
$result_app = $stmt_app->get_result();
if ($result_app->num_rows === 1) {
    $application = $result_app->fetch_assoc();
} else {
    die("Application not found.");
}
$stmt_app->close();

// Fetch student's payment status for hostel fees in the active session
$payment_status = "Not Paid";
$sql_payment = "SELECT p.status FROM payments p 
                JOIN fees f ON p.session_id = f.session_id
                JOIN sessions s ON p.session_id = s.id
                WHERE p.student_id = ? AND s.is_active = 1 AND f.fee_name LIKE '%Hostel%' AND p.status = 'approved' LIMIT 1";
$stmt_payment = $conn->prepare($sql_payment);
$stmt_payment->bind_param("i", $application['student_id']);
$stmt_payment->execute();
$result_payment = $stmt_payment->get_result();
if ($result_payment->num_rows > 0) {
    $payment_status = "Paid";
}
$stmt_payment->close();

// Fetch current booking if exists
$current_booking = null;
$sql_booking = "SELECT hb.id, hb.bed_id, r.room_number, h.name as hostel_name 
                FROM hostel_bookings hb
                JOIN beds b ON hb.bed_id = b.id
                JOIN rooms r ON b.room_id = r.id
                JOIN hostels h ON r.hostel_id = h.id
                WHERE hb.student_id = ? AND hb.status = 'booked' LIMIT 1";
$stmt_booking = $conn->prepare($sql_booking);
$stmt_booking->bind_param("i", $application['student_id']);
$stmt_booking->execute();
$current_booking = $stmt_booking->get_result()->fetch_assoc();
$stmt_booking->close();

// Fetch available beds
$available_beds = [];
$sql_beds = "SELECT b.id, b.bed_number, r.room_number, h.name as hostel_name 
             FROM beds b
             JOIN rooms r ON b.room_id = r.id
             JOIN hostels h ON r.hostel_id = h.id
             WHERE b.is_available = 1
             ORDER BY h.name, r.room_number, b.bed_number";
$result_beds = $conn->query($sql_beds);
while ($row = $result_beds->fetch_assoc()) {
    $available_beds[] = $row;
}

$conn->close();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold m-0">Review Hostel Application</h5>
        <a href="hostel_applications.php" class="btn btn-outline-primary">Back to List</a>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-3">Applicant Details</h5>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($application['full_name']); ?></p>
                    <p><strong>Student ID:</strong> <?php echo htmlspecialchars($application['student_id_number']); ?></p>
                    <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><?php echo htmlspecialchars($application['email']); ?></a></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['phone'] ?? 'N/A'); ?></p>
                    <hr>
                    <h5 class="card-title fw-semibold mb-3">Application Info</h5>
                    <p><strong>Application Date:</strong> <?php echo date("F j, Y, g:i a", strtotime($application['application_date'])); ?></p>
                    <p><strong>Hostel Fee Status:</strong> <span class="badge bg-<?php echo $payment_status === 'Paid' ? 'success' : 'danger'; ?>"><?php echo $payment_status; ?></span></p>
                    <p><strong>Current Booking:</strong> <?php if ($current_booking): ?><span class="fw-semibold"><?php echo htmlspecialchars($current_booking['hostel_name'] . ' - Room ' . $current_booking['room_number'] . ', Bed ' . $current_booking['bed_id']); ?></span><?php else: ?><span class="text-muted">No active booking</span><?php endif; ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title fw-semibold mb-3">Review & Action</h5>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $application_id; ?>" method="post">
                        <input type="hidden" name="student_id" value="<?php echo $application['student_id']; ?>">
                        <input type="hidden" name="current_booking_id" value="<?php echo $current_booking['id'] ?? ''; ?>">
                        <input type="hidden" name="current_bed_id" value="<?php echo $current_booking['bed_id'] ?? ''; ?>">
                        <div class="mb-3"><label for="status" class="form-label">Application Status</label><select class="form-select" id="status" name="status" required><option value="Pending" <?php if ($application['status'] === 'Pending') echo 'selected'; ?>>Pending</option><option value="Approved" <?php if ($application['status'] === 'Approved') echo 'selected'; ?>>Approve</option><option value="Rejected" <?php if ($application['status'] === 'Rejected') echo 'selected'; ?>>Reject</option></select></div>
                        <div class="mb-3"><label for="bed_id" class="form-label">Assign Bed (if approving)</label><select class="form-select" id="bed_id" name="bed_id"><option value="">-- Select a Bed --</option><?php if ($current_booking): ?><option value="<?php echo $current_booking['bed_id']; ?>" selected>(Current) <?php echo htmlspecialchars($current_booking['hostel_name'] . ' - Room ' . $current_booking['room_number'] . ', Bed ' . $current_booking['bed_id']); ?></option><?php endif; ?><?php foreach ($available_beds as $bed): ?><option value="<?php echo $bed['id']; ?>"><?php echo htmlspecialchars($bed['hostel_name'] . ' - Room ' . $bed['room_number'] . ', Bed ' . $bed['bed_number']); ?></option><?php endforeach; ?></select><div class="form-text">Selecting a new bed will override any current booking.</div></div>
                        <div class="mb-3"><label for="remarks" class="form-label">Remarks (Optional)</label><textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo htmlspecialchars($application['remarks'] ?? ''); ?></textarea></div>
                        <button type="submit" name="review_application" class="btn btn-primary">Save Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>