<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/n-portal/');
}
?>
<!-- Sidebar Start -->
<aside class="left-sidebar">
  <div>
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="text-nowrap logo-img">
        <img src="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" width="120" class="p-2 mx-5 my-3" alt="NAFCONM Logo" />
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-8"></i>
      </div>
    </div>
    
    <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
      <ul id="sidebarnav">
        
        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">HOME</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/index.php" aria-expanded="false">
                <span><i class="ti ti-layout-dashboard"></i></span>
                <span class="hide-menu">Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/announcements.php" aria-expanded="false">
                <span><i class="ti ti-bell"></i></span>
                <span class="hide-menu">Announcements</span>
            </a>
        </li>
        
        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">FINANCE</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/pay_fees.php" aria-expanded="false">
                <span><i class="ti ti-cash"></i></span>
                <span class="hide-menu">Pay Session Fees</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/financials.php" aria-expanded="false">
                <span><i class="ti ti-receipt-2"></i></span>
                <span class="hide-menu">Financial Ledger</span>
            </a>
        </li>

        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">ACADEMICS</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/course_registration.php" aria-expanded="false">
                <span><i class="ti ti-book-2"></i></span>
                <span class="hide-menu">Course Registration</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/my_courses.php" aria-expanded="false">
                <span><i class="ti ti-books"></i></span>
                <span class="hide-menu">My Courses</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/results.php" aria-expanded="false">
                <span><i class="ti ti-school"></i></span>
                <span class="hide-menu">Check Results</span>
            </a>
        </li>

        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">LEARNING (LMS)</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/course_materials.php" aria-expanded="false">
                <span><i class="ti ti-folder"></i></span>
                <span class="hide-menu">Course Materials</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/assignments.php" aria-expanded="false">
                <span><i class="ti ti-upload"></i></span>
                <span class="hide-menu">Assignments</span>
            </a>
        </li>

        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">CAMPUS LIFE</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/hostel_booking.php" aria-expanded="false">
                <span><i class="ti ti-home"></i></span>
                <span class="hide-menu">Hostel Booking</span>
            </a>
        </li>
        
        <!-- NEW: OFFICIAL DOCUMENT CENTER -->
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">DOCUMENT CENTER</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="print_admission_letter.php" target="_blank" aria-expanded="false">
            <span><i class="ti ti-certificate"></i></span>
            <span class="hide-menu">Admission Letter</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="print_sif.php" target="_blank" aria-expanded="false">
            <span><i class="ti ti-id-badge-2"></i></span>
            <span class="hide-menu">Student Info Form (SIF)</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="print_crf.php" target="_blank" aria-expanded="false">
            <span><i class="ti ti-file-text"></i></span>
            <span class="hide-menu">Course Reg Form (CRF)</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="print_clearance.php" target="_blank" aria-expanded="false">
            <span><i class="ti ti-rosette-discount-check"></i></span>
            <span class="hide-menu">Financial Clearance</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="print_transcript.php" target="_blank" aria-expanded="false">
            <span><i class="ti ti-report"></i></span>
            <span class="hide-menu">Session Transcript</span>
          </a>
        </li>
        
        <li class="nav-small-cap">
            <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
            <span class="hide-menu">ACCOUNT</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/profile.php" aria-expanded="false">
                <span><i class="ti ti-user-circle"></i></span>
                <span class="hide-menu">My Profile & ID</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link mb-5 py-3" href="<?php echo BASE_URL; ?>logout.php" aria-expanded="false">
                <span><i class="ti ti-logout text-danger"></i></span>
                <span class="hide-menu fw-bold text-danger">Logout</span>
            </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>