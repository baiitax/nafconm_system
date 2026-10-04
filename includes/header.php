<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - NAFCONM' : 'Nafisatu College of Nursing and Midwifery'; ?></title>
  <meta name="description" content="Nafisatu College of Nursing and Midwifery (NAFCONM) - Training compassionate, skilled, and professional healthcare practitioners.">
  <meta name="keywords" content="nursing school, midwifery, healthcare education, NAFCONM, admissions">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <!-- Custom Branding Overrides -->
  <style>
    :root {
      --default-color: #495057; /* Dark Gray for text */
      --primary-color: #A91D22; /* Professional Red */
      --accent-color: #A91D22;  /* Professional Red */
      --secondary-color: #6c757d; /* Medium Gray */
      --background-color: #ffffff; /* White */
      --surface-color: #f8f9fa; /* Light Gray */
    }

    .btn-primary, .btn-primary:hover, .btn-primary:active, .btn-primary:focus {
      background-color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
    }

    .btn-secondary {
      background-color: var(--secondary-color) !important;
      border-color: var(--secondary-color) !important;
      color: #fff !important;
    }

    a, .breadcrumbs .current, .section-title h2, .programs-filters .filter-active {
      color: var(--primary-color);
    }

    .feature-card.active, .timeline-dot, .programs-filters .filter-active {
      background-color: var(--primary-color);
    }
  </style>

</head>

<body class="<?php echo $body_class ?? 'default-page'; ?>">

  <header id="header" class="header d-flex align-items-center sticky-top">
    <div class="header-container container-fluid container-xl position-relative d-flex align-items-center justify-content-end">

      <a href="" class="d-flex align-items-center me-auto">
        <img src="assets/img/logo.png" alt="Education" width="120px">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About</a></li>
          <li><a href="academics.php">Academics</a></li>
          <li><a href="admissions-info.php">Admissions</a></li>
          <li><a href="contact.php">Contact</a></li>
          <li><a href="news.php">News</a></li>
          <li><a href="track_application.php">Track Application</a></li>
          <li>
              <a href="./app" class="btn">
            Login
            </a>
          </li>
          <li><a href="apply.php" class="btn-apply">Apply Now</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

    </div>
  </header>