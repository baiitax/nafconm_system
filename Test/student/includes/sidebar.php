<?php
// This check is for standalone testing of the sidebar. In a real app, header.php defines this.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/n-portal/');
}
?>
<!-- Sidebar Start -->
<aside class="left-sidebar">
  <!-- Sidebar scroll-->
  <div>
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <a href="<?php echo BASE_URL; ?>student/index.php" class="text-nowrap logo-img">
        <img src="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" width="120" class="p-2 mx-5 my-3" alt="N-Portal Logo" />
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-8"></i>
      </div>
    </div>
    <!-- Sidebar navigation-->
    <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
      <ul id="sidebarnav">
        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">Home</span></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/index.php" aria-expanded="false"><span><i class="ti ti-layout-dashboard"></i></span><span class="hide-menu">Dashboard</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/announcements.php" aria-expanded="false"><span><i class="ti ti-bell"></i></span><span class="hide-menu">Announcements</span></a></li>
        
        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">ACADEMICS</span></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/course_registration.php" aria-expanded="false"><span><i class="ti ti-book-2"></i></span><span class="hide-menu">Course Registration</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/my_courses.php" aria-expanded="false"><span><i class="ti ti-books"></i></span><span class="hide-menu">My Courses</span></a></li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/course_materials.php" aria-expanded="false">
            <span><i class="ti ti-folder"></i></span>
            <span class="hide-menu">Course Materials</span>
          </a>
        </li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/assignments.php" aria-expanded="false"><span><i class="ti ti-upload"></i></span><span class="hide-menu">Assignments</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/assessment_sheet.php" aria-expanded="false"><span><i class="ti ti-file-invoice"></i></span><span class="hide-menu">Assessment Sheet</span></a>
        </li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/my_grades.php" aria-expanded="false"><span><i class="ti ti-award"></i></span><span class="hide-menu">My Grades</span></a></li>
        <li class="sidebar-item">
          <a class="sidebar-link" href="<?php echo BASE_URL; ?>student/reports.php" aria-expanded="false">
            <span><i class="ti ti-report-analytics"></i></span><span class="hide-menu">My Reports</span></a>
        </li>
        
        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">FINANCE</span></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/payments_overview.php" aria-expanded="false"><span><i class="ti ti-cash"></i></span><span class="hide-menu">Payment Overview</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/payments.php" aria-expanded="false"><span><i class="ti ti-cash"></i></span><span class="hide-menu">Fee Payment</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/transactions.php" aria-expanded="false"><span><i class="ti ti-credit-card"></i></span><span class="hide-menu">My Transactions</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/payment_history.php" aria-expanded="false"><span><i class="ti ti-history"></i></span><span class="hide-menu">Payment History</span></a></li>

        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">ACCOMMODATION</span></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/hostel_booking.php" aria-expanded="false"><span><i class="ti ti-home"></i></span><span class="hide-menu">Hostel Booking</span></a></li>
        
        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">ACCOUNT</span></li>
        <li class="sidebar-item"><a class="sidebar-link" href="<?php echo BASE_URL; ?>student/my_profile.php" aria-expanded="false"><span><i class="ti ti-user-circle"></i></span><span class="hide-menu">My Profile</span></a></li>
        <li class="sidebar-item"><a class="sidebar-link mb-5 py-3" href="<?php echo BASE_URL; ?>logout.php" aria-expanded="false"><span><i class="ti ti-logout"></i></span><span class="hide-menu">Logout</span></a></li>
      </ul>
    </nav>
    <!-- End Sidebar navigation -->
  </div>
  <!-- End Sidebar scroll-->
</aside>
<!--  Sidebar End -->