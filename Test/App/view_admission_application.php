<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';
$page_title = 'View Application';
require_once 'includes/header.php';

$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$application_id) {
    header("location: admission_applications.php");
    exit;
}

// Handle Create Student Profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_student_profile'])) {
    $app_id_to_create = (int)$_POST['application_id'];
    
    // Refetch application data to ensure it's 'accepted' and not yet processed
    $sql_app_check = "SELECT * FROM admission_applications WHERE id = ? AND status = 'accepted' AND student_id IS NULL";
    $stmt_check = $conn->prepare($sql_app_check);
    $stmt_check->bind_param("i", $app_id_to_create);
    $stmt_check->execute();
    $app_data = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($app_data) {
        $username = strtolower(str_replace(' ', '', $app_data['first_name']) . '.' . str_replace(' ', '', $app_data['last_name']));
        $password = bin2hex(random_bytes(4)); // Generate an 8-char temporary password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $student_id_number = 'ADM-' . str_pad($app_data['id'], 5, '0', STR_PAD_LEFT);

        $conn->begin_transaction();
        try {
            // 1. Create user
            $sql_user = "INSERT INTO users (username, password, user_type) VALUES (?, ?, 'student')";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("ss", $username, $hashed_password);
            $stmt_user->execute();
            $user_id = $stmt_user->insert_id;
            $stmt_user->close();

            // 2. Create student profile
            $sql_student = "INSERT INTO students (user_id, full_name, student_id_number, email, phone, date_of_birth, address) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_student = $conn->prepare($sql_student);
            $full_name = $app_data['first_name'] . ' ' . $app_data['last_name'];
            $stmt_student->bind_param("issssss", $user_id, $full_name, $student_id_number, $app_data['email'], $app_data['phone'], $app_data['date_of_birth'], $app_data['address']);
            $stmt_student->execute();
            $student_id = $stmt_student->insert_id;
            $stmt_student->close();

            // 3. Link student profile to application
            $sql_link = "UPDATE admission_applications SET student_id = ? WHERE id = ?";
            $stmt_link = $conn->prepare($sql_link);
            $stmt_link->bind_param("ii", $student_id, $app_id_to_create);
            $stmt_link->execute();
            $stmt_link->close();

            $conn->commit();
            $action_success = "Student profile created successfully! Username: <strong>$username</strong>, Temporary Password: <strong>$password</strong>. Please communicate these credentials to the student.";
            log_activity($conn, $_SESSION['user_id'], "Created student profile for application ID {$app_id_to_create}.");

        } catch (Exception $e) {
            $conn->rollback();
            // Check for duplicate entry specifically
            if ($conn->errno == 1062) {
                $action_err = "Error: A user with this email or a similar username already exists. Please manually create the student profile.";
            } else {
                $action_err = "Database error during student creation: " . $e->getMessage();
            }
        }
    } else {
        $action_err = "This application is not in 'Accepted' status or a student profile has already been created.";
    }
}

// Fetch application details
$application = null;
$sql = "SELECT app.*, u.username as reviewer_name 
        FROM admission_applications app
        LEFT JOIN users u ON app.reviewed_by_user_id = u.id
        WHERE app.id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $application = $result->fetch_assoc();
    } else {
        die("Application not found.");
    }
    $stmt->close();
}

// Fetch documents
$documents = [];
$sql_docs = "SELECT * FROM application_documents WHERE application_id = ?";
if($stmt_docs = $conn->prepare($sql_docs)) {
    $stmt_docs->bind_param("i", $application_id);
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result();
    while($row = $result_docs->fetch_assoc()) $documents[] = $row;
    $stmt_docs->close();
}

require_once 'includes/sidebar.php';
?>

<!--  Main wrapper -->
<div class="body-wrapper">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="card-title fw-semibold">Applications Details</h5>
    </div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title fw-semibold m-0">Application Details</h5>
                    <a href="admission_applications.php" class="btn btn-outline-primary">Back to List</a>
                </div>

                <?php if (isset($action_success)): ?><div class="alert alert-success"><?php echo $action_success; ?></div><?php endif; ?>
                <?php if (isset($action_err)): ?><div class="alert alert-danger"><?php echo $action_err; ?></div><?php endif; ?>

                <div class="row">
                    <div class="col-lg-7">
                        <h6 class="fw-semibold">Applicant Information</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['middle_name'] . ' ' . $application['last_name']); ?></p>
                        <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>"><?php echo htmlspecialchars($application['email']); ?></a></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['phone']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo date("F j, Y", strtotime($application['date_of_birth'])); ?></p>
                        <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($application['address'])); ?></p>
                        <hr>
                        <h6 class="fw-semibold">Academic Information</h6>
                        <p><strong>Program of Interest:</strong> <?php echo htmlspecialchars($application['program_of_interest']); ?></p>
                        <p><strong>Previous School:</strong> <?php echo htmlspecialchars($application['previous_school']); ?></p>
                        <hr>
                        <h6 class="fw-semibold">Uploaded Documents</h6>
                        <?php if (!empty($documents)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                <li class="list-group-item"><a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank"><i class="ti ti-download me-1"></i> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No documents were uploaded with this application.</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-5">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="card-title fw-semibold">Review & Actions</h6>
                                <p><strong>Status:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $application['status']))); ?></span></p>
                                <p><strong>Reviewed By:</strong> <?php echo htmlspecialchars($application['reviewer_name'] ?? 'N/A'); ?></p>
                                <p><strong>Review Date:</strong> <?php echo $application['review_date'] ? date("M d, Y", strtotime($application['review_date'])) : 'N/A'; ?></p>
                                
                                <form action="admission_applications.php" method="post" class="mt-3">
                                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                                    <div class="mb-3"><label for="status" class="form-label">Update Status</label><select class="form-select" id="status" name="status" required><option value="pending" <?php if($application['status']=='pending') echo 'selected';?>>Pending</option><option value="under_review" <?php if($application['status']=='under_review') echo 'selected';?>>Under Review</option><option value="accepted" <?php if($application['status']=='accepted') echo 'selected';?>>Accepted</option><option value="rejected" <?php if($application['status']=='rejected') echo 'selected';?>>Rejected</option><option value="waitlisted" <?php if($application['status']=='waitlisted') echo 'selected';?>>Waitlisted</option></select></div>
                                    <div class="mb-3"><label for="review_remarks" class="form-label">Review Remarks</label><textarea class="form-control" id="review_remarks" name="review_remarks" rows="3"><?php echo htmlspecialchars($application['review_remarks']); ?></textarea></div>
                                    <button type="submit" name="review_application" class="btn btn-primary">Update Status</button>
                                </form>

                                <?php if ($application['status'] === 'accepted' && is_null($application['student_id'])): ?>
                                <hr>
                                <h6 class="fw-semibold">Create Student Profile</h6>
                                <p>This application is accepted. You can now create a student profile and user account.</p>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $application_id; ?>" method="post" onsubmit="return confirm('This will create a new student and user account. Are you sure?');">
                                    <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                                    <button type="submit" name="create_student_profile" class="btn btn-success">Create Student Profile</button>
                                </form>
                                <?php elseif (!is_null($application['student_id'])): ?>
                                <hr>
                                <div class="alert alert-success">A student profile has already been created for this applicant. <a href="view_student_details.php?student_id=<?php echo $application['student_id']; ?>" class="alert-link">View Profile</a>.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>