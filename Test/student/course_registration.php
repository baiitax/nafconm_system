<?php
$page_title = 'Course Registration';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

$student_id = $_SESSION['student_id'];
$message = $error = '';
$can_register = false;
$available_courses = [];
$active_session_id = null;
$max_credit_units = 21; // Example max credit units

// 1. Check if student is eligible for course registration
$sql_check = "SELECT can_register_courses FROM students WHERE id = ?";
if ($stmt_check = $conn->prepare($sql_check)) {
    $stmt_check->bind_param("i", $student_id);
    $stmt_check->execute();
    $stmt_check->bind_result($can_register_flag);
    if ($stmt_check->fetch()) {
        $can_register = (bool)$can_register_flag;
    }
    $stmt_check->close();
}

// 2. Get active session
$sql_session = "SELECT id FROM sessions WHERE is_active = 1 LIMIT 1";
$result_session = $conn->query($sql_session);
if ($result_session->num_rows > 0) {
    $active_session_id = $result_session->fetch_assoc()['id'];
} else {
    $error = "No active session found for registration.";
    $can_register = false;
}

// 3. Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $can_register && $active_session_id) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Request validation failed. Please try again.";
    } else {
        $selected_courses = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
        if (empty($selected_courses)) {
            $error = "Please select at least one course to register.";
        } else {
            // Server-side validation for credit units
            $total_selected_units = 0;
            $ids_placeholder = implode(',', array_fill(0, count($selected_courses), '?'));
            $types = str_repeat('i', count($selected_courses));
            $sql_units = "SELECT SUM(credit_units) FROM courses WHERE id IN ($ids_placeholder)";
            if ($stmt_units = $conn->prepare($sql_units)) {
                $stmt_units->bind_param($types, ...$selected_courses);
                $stmt_units->execute();
                $stmt_units->bind_result($total_selected_units);
                $stmt_units->fetch();
                $stmt_units->close();
            }

            if ($total_selected_units > $max_credit_units) {
                $error = "Total credit units ($total_selected_units) exceed the maximum allowed ($max_credit_units).";
            } else {
                $conn->begin_transaction();
                $sql_insert = "INSERT INTO enrollments (student_id, course_id, session_id) VALUES (?, ?, ?)";
                if ($stmt_insert = $conn->prepare($sql_insert)) {
                    $all_inserted = true;
                    foreach ($selected_courses as $course_id) {
                        $stmt_insert->bind_param("iii", $student_id, $course_id, $active_session_id);
                        if (!$stmt_insert->execute()) {
                            $error = "Error registering course. You might already be registered for one of the selected courses.";
                            $all_inserted = false;
                            break;
                        }
                    }

                    if ($all_inserted) {
                        $conn->commit();
                        $message = "You have successfully registered for the selected courses!";
                    } else {
                        $conn->rollback();
                    }
                    $stmt_insert->close();
                } else {
                    $error = "Database error. Could not prepare statement.";
                }
            }
        }
    }
}

// 4. Fetch available courses if eligible and session is active
if ($can_register && $active_session_id) {
    $sql_courses = "SELECT c.id, c.course_code, c.course_name, c.credit_units 
                    FROM courses c 
                    WHERE c.is_open_for_registration = 1 
                    AND c.id NOT IN (
                        SELECT e.course_id 
                        FROM enrollments e 
                        WHERE e.student_id = ? AND e.session_id = ?
                    )";
    if ($stmt_courses = $conn->prepare($sql_courses)) {
        $stmt_courses->bind_param("ii", $student_id, $active_session_id);
        $stmt_courses->execute();
        $result = $stmt_courses->get_result();
        while ($row = $result->fetch_assoc()) {
            $available_courses[] = $row;
        }
        $stmt_courses->close();
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title fw-semibold mb-4">Course Registration</h5>

                <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

                <?php if (!$can_register): ?>
                    <div class="alert alert-warning">
                        Course registration is currently not enabled for you. Please ensure you have completed all necessary payments and try again.
                    </div>
                <?php elseif (empty($available_courses) && empty($error)): ?>
                    <div class="alert alert-info">
                        There are no new courses available for registration at this time, or you have already registered for all available courses.
                    </div>
                <?php else: ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="course-registration-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <!-- Available Courses Table -->
                            <div class="col-lg-7">
                                <h6 class="mb-3">Available Courses</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th></th>
                                                <th>Course Code</th>
                                                <th>Course Name</th>
                                                <th>Units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($available_courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <input class="form-check-input course-checkbox" type="checkbox" name="course_ids[]" 
                                                           value="<?php echo $course['id']; ?>"
                                                           data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                                           data-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                           data-units="<?php echo htmlspecialchars($course['credit_units']); ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td><?php echo htmlspecialchars($course['credit_units']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Preview Section -->
                            <div class="col-lg-5">
                                <h6 class="mb-3">Selected Courses Preview</h6>
                                <div class="card shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="table-responsive">
                                            <table class="table" id="preview-table">
                                                <thead>
                                                    <tr>
                                                        <th>Course Code</th>
                                                        <th>Course Name</th>
                                                        <th>Units</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Selected courses will be populated here by JS -->
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="2" class="text-end">Total Credit Units:</th>
                                                        <th id="total-units">0</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                        <div id="credit-unit-alert" class="alert alert-danger mt-2 d-none">
                                            Credit units cannot exceed <?php echo $max_credit_units; ?>.
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" id="submit-btn" class="btn btn-primary w-100 mt-3">Register Selected Courses</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($can_register && !empty($available_courses)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.course-checkbox');
    const previewTableBody = document.querySelector('#preview-table tbody');
    const totalUnitsEl = document.getElementById('total-units');
    const submitBtn = document.getElementById('submit-btn');
    const creditAlert = document.getElementById('credit-unit-alert');
    const maxUnits = <?php echo $max_credit_units; ?>;
    let selectedCourses = new Map();

    function updatePreview() {
        previewTableBody.innerHTML = '';
        let currentTotalUnits = 0;

        selectedCourses.forEach(course => {
            const row = document.createElement('tr');
            row.id = `preview-row-${course.id}`;
            row.innerHTML = `
                <td>${course.code}</td>
                <td>${course.name}</td>
                <td>${course.units}</td>
            `;
            previewTableBody.appendChild(row);
            currentTotalUnits += parseInt(course.units, 10);
        });

        totalUnitsEl.textContent = currentTotalUnits;

        if (currentTotalUnits > maxUnits || currentTotalUnits === 0) {
            submitBtn.disabled = true;
            if (currentTotalUnits > maxUnits) {
                totalUnitsEl.classList.add('text-danger', 'fw-bold');
                creditAlert.classList.remove('d-none');
            }
        } else {
            submitBtn.disabled = false;
            totalUnitsEl.classList.remove('text-danger', 'fw-bold');
            creditAlert.classList.add('d-none');
        }
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const courseId = this.value;
            if (this.checked) {
                selectedCourses.set(courseId, {
                    id: courseId,
                    code: this.dataset.code,
                    name: this.dataset.name,
                    units: this.dataset.units
                });
            } else {
                selectedCourses.delete(courseId);
            }
            updatePreview();
        });
    });
    updatePreview(); // Initial call to set button state
});
</script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>