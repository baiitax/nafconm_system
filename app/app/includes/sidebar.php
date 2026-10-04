<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/n-portal/'); 
}

// Safely initialize RBAC variables in case they aren't globally available
$user_type = strtolower($_SESSION['user_type'] ?? '');
$is_super = ($user_type === 'superadmin' || $user_type === 'admin');
$privileges = $_SESSION['privileges'] ?? [];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="left-sidebar shadow-sm">
  <div>
    <div class="brand-logo d-flex align-items-center justify-content-between">
      <a href="<?= $is_super ? 'index.php' : '../teacher/index.php' ?>" class="text-nowrap logo-img">
        <img src="../assets/images/logos/favicon.png" width="150" class="img-fluid mx-4 p-1 my-1 mb-0" alt="NAFCONM Logo" />
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-8"></i>
      </div>
    </div>
    
    <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
      <ul id="sidebarnav">
        
        <!-- COMMAND CENTER (Superadmin Only) -->
        <?php if ($is_super): ?>
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">COMMAND CENTER</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php" aria-expanded="false">
            <span><i class="ti ti-layout-dashboard"></i></span>
            <span class="hide-menu">Master Dashboard</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'announcements_list.php' ? 'active' : '' ?>" href="announcements_list.php" aria-expanded="false">
            <span><i class="ti ti-broadcast"></i></span>
            <span class="hide-menu">Campus Broadcasts</span>
          </a>
        </li>
        <?php endif; ?>

        <!-- ADMISSIONS & STUDENTS -->
        <?php if ($is_super || in_array('Admission', $privileges)): ?>
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">ADMISSIONS & STUDENTS</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'admissions_overview.php' ? 'active' : '' ?>" href="admissions_overview.php" aria-expanded="false">
            <span><i class="ti ti-user-check"></i></span>
            <span class="hide-menu">Admissions Desk</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'manage_students.php' ? 'active' : '' ?>" href="manage_students.php" aria-expanded="false">
            <span><i class="ti ti-users"></i></span>
            <span class="hide-menu">Student Directory</span>
          </a>
        </li>
        <?php endif; ?>
        
        <!-- ACADEMIC MANAGEMENT -->
        <?php if ($is_super || in_array('Academic', $privileges)): ?>
        <li class="nav-small-cap"><i class="ti ti-dots nav-small-cap-icon fs-4"></i><span class="hide-menu">ACADEMICS & FACULTY</span></li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'manage_academics.php' ? 'active' : '' ?>" href="manage_academics.php" aria-expanded="false">
                <span><i class="ti ti-building-bank"></i></span><span class="hide-menu">Academic Setup</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'manage_courses.php' ? 'active' : '' ?>" href="manage_courses.php" aria-expanded="false">
            <span><i class="ti ti-book"></i></span>
            <span class="hide-menu">Curriculum Catalog</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'assign_teachers.php' ? 'active' : '' ?>" href="assign_teachers.php" aria-expanded="false">
            <span><i class="ti ti-chalkboard"></i></span>
            <span class="hide-menu">Instructor Allocation</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'manage_teachers.php' ? 'active' : '' ?>" href="manage_teachers.php" aria-expanded="false">
            <span><i class="ti ti-users-group"></i></span>
            <span class="hide-menu">Staff Directory</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'student_academic_record.php' ? 'active' : '' ?>" href="student_academic_record.php" aria-expanded="false">
                <span><i class="ti ti-file-analytics"></i></span><span class="hide-menu">Academic Dossiers</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'manage_sessions.php' ? 'active' : '' ?>" href="manage_sessions.php" aria-expanded="false">
            <span><i class="ti ti-calendar-event"></i></span>
            <span class="hide-menu">Session Control</span>
            </a>
        </li>
        <?php endif; ?>
                
        <!-- FINANCE & BURSARY -->
        <?php if ($is_super || in_array('Payment', $privileges)): ?>
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">FINANCE & BURSARY</span>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'manage_payments.php' ? 'active' : '' ?>" href="manage_payments.php" aria-expanded="false">
                <span><i class="ti ti-cash-banknote"></i></span><span class="hide-menu">Central Bursary</span>
            </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'student_payments.php' ? 'active' : '' ?>" href="student_payments.php" aria-expanded="false">
            <span><i class="ti ti-receipt"></i></span>
            <span class="hide-menu">Payment Ledger</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'manage_fees.php' ? 'active' : '' ?>" href="manage_fees.php" aria-expanded="false">
            <span><i class="ti ti-currency-naira"></i></span>
            <span class="hide-menu">Fee Matrix</span>
          </a>
        </li>
        <?php endif; ?>

        <!-- CAMPUS WELFARE (HOSTEL) -->
        <?php if ($is_super || in_array('Hostel', $privileges)): ?>
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">CAMPUS WELFARE</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'manage_hostel.php' ? 'active' : '' ?>" href="manage_hostel.php" aria-expanded="false">
            <span><i class="ti ti-home-check"></i></span>
            <span class="hide-menu">Hostel Management</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'hostel_warden.php' ? 'active' : '' ?>" href="hostel_warden.php" aria-expanded="false">
            <span><i class="ti ti-bed"></i></span>
            <span class="hide-menu">Hostel Warden</span>
          </a>
        </li>
        <li class="sidebar-item">
            <a class="sidebar-link <?= $current_page == 'hostel_report.php' ? 'active' : '' ?>" href="hostel_report.php" aria-expanded="false">
                <span><i class="ti ti-report"></i></span><span class="hide-menu">Hostel Manifest</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- SYSTEM & SECURITY (Superadmin Only) -->
        <?php if ($is_super): ?>
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">SYSTEM & SECURITY</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'manage_settings.php' ? 'active' : '' ?>" href="manage_settings.php" aria-expanded="false">
            <span><i class="ti ti-settings"></i></span>
            <span class="hide-menu">Global Settings</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'activity_logs.php' ? 'active' : '' ?>" href="activity_logs.php" aria-expanded="false">
            <span><i class="ti ti-history"></i></span>
            <span class="hide-menu">Audit Trail</span>
          </a>
        </li>
        <?php endif; ?>

        <!-- ACCOUNT -->
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">ACCOUNT</span>
        </li>
        
        <!-- Return to Hub button for delegated teachers -->
        <?php if (!$is_super): ?>
        <li class="sidebar-item">
          <a class="sidebar-link text-primary bg-primary-subtle rounded-3 mb-2" href="../teacher/index.php" aria-expanded="false">
            <span><i class="ti ti-arrow-back-up text-primary"></i></span>
            <span class="hide-menu fw-bold text-primary">Return to Faculty Hub</span>
          </a>
        </li>
        <?php endif; ?>

        <li class="sidebar-item">
          <a class="sidebar-link text-danger" href="../logout.php" aria-expanded="false">
            <span><i class="ti ti-logout text-danger"></i></span>
            <span class="hide-menu fw-bold text-danger">Secure Logout</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>