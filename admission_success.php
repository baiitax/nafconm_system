<?php
session_start();

// Check if success data is available, otherwise redirect
if (!isset($_SESSION['admission_success_data'])) {
    header('Location: admissions.php');
    exit();
}

$success_data = $_SESSION['admission_success_data'];

// Clear the session data to prevent re-displaying on refresh
unset($_SESSION['admission_success_data']);

$pageTitle = "Application Submitted";
include 'includes/header.php';
?>

<main class="main">

    <!-- Page Title -->
    <div class="page-title light-background">
        <div class="container">
            <h1 class="mb-2 mb-lg-0">Application Submitted Successfully</h1>
            <nav class="breadcrumbs">
                <ol>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="admissions.php">Admissions</a></li>
                    <li class="current">Success</li>
                </ol>
            </nav>
        </div>
    </div><!-- End Page Title -->

    <!-- Success Section -->
    <section id="admission-success" class="admission-success section">
        <div class="container" data-aos="fade-up">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="icon mb-4" data-aos="zoom-in" data-aos-delay="100">
                        <i class="bi bi-check-circle-fill" style="font-size: 4rem; color: var(--accent-color);"></i>
                    </div>
                    <h2>Thank You, <?php echo htmlspecialchars($success_data['name']); ?>!</h2>
                    <p class="lead">Your application has been received.</p>
                    <p>A confirmation has been sent to your email address: <strong><?php echo htmlspecialchars($success_data['email']); ?></strong>.</p>
                    <div class="alert alert-warning mt-4">
                        <h4>Your Application Token</h4>
                        <p>Please save the following token. You will need it along with your email to check your admission status later.</p>
                        <p class="h3 user-select-all" style="background-color: #fff3cd; padding: 10px; border-radius: 5px;"><strong><?php echo htmlspecialchars($success_data['token']); ?></strong></p>
                    </div>
                    <div class="mt-4">
                        <a href="apply.php" class="btn btn-primary">Check Status or Apply Again</a>
                        <a href="index.php" class="btn btn-outline-secondary">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /Success Section -->

</main>

<?php
include 'includes/footer.php';
?>