<?php
session_start();

// Check if application data is in the session, otherwise redirect
if (!isset($_SESSION['application_status_data'])) {
    $_SESSION['status_error_message'] = "Please enter your details to check your status.";
    header('Location: apply.php');
    exit();
}

$app = $_SESSION['application_status_data'];
$olevel_results = $app['olevel_results'] ?? [];

// Clear the session data to force re-check next time
unset($_SESSION['application_status_data']);

// Helper function to format status
function format_status($status) {
    $status_map = [
        'pending' => ['text' => 'Pending Review', 'class' => 'warning'],
        'under_review' => ['text' => 'Under Review', 'class' => 'info'],
        'accepted' => ['text' => 'Accepted', 'class' => 'success'],
        'rejected' => ['text' => 'Not Accepted', 'class' => 'danger'],
        'waitlisted' => ['text' => 'Waitlisted', 'class' => 'secondary'],
    ];
    return $status_map[$status] ?? ['text' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'light'];
}

$status_info = format_status($app['status']);

$pageTitle = "Admission Status";
include 'includes/header.php';
?>

<main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
        <div class="container">
            <h1 class="mb-2 mb-lg-0">Your Application Status</h1>
            <nav class="breadcrumbs">
                <ol>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="admissions.php">Admissions</a></li>
                    <li class="current">Status</li>
                </ol>
            </nav>
        </div>
    </div><!-- End Page Title -->

    <!-- Status Section -->
    <section id="admission-status" class="admission-status section">
        <div class="container" data-aos="fade-up">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            Application Details
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Hello, <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h5>
                            <p>Here is the current status of your application submitted on <?php echo date('F j, Y', strtotime($app['application_date'])); ?>.</p>
                            
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Program of Interest
                                    <span><?php echo htmlspecialchars($app['program_of_interest']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Application Status
                                    <span class="badge bg-<?php echo $status_info['class']; ?> rounded-pill px-3 py-2"><?php echo $status_info['text']; ?></span>
                                </li>
                            </ul>

                            <?php if (!empty($olevel_results)): ?>
                            <div class="mt-4">
                                <h6>Submitted O'Level Results</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Subject</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($olevel_results as $result): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($result['subject']); ?></td>
                                                    <td><?php echo htmlspecialchars($result['grade']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($app['review_remarks'])): ?>
                            <div class="alert alert-info mt-4">
                                <strong>Reviewer's Remarks:</strong>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($app['review_remarks'])); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="text-center mt-4">
                                <a href="apply.php" class="btn btn-secondary">Check Another Application</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /Status Section -->

</main>

<?php
include 'includes/footer.php';
?>