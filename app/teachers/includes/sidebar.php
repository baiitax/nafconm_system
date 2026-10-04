<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}
$current_page = basename($_SERVER['PHP_SELF']);

// --- REAL-TIME RBAC SYNC ---
// Always fetch the latest privileges to ensure the UI updates instantly when an admin changes them.
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../config/db.php';
    
    $_SESSION['privileges'] = []; // Clear old cache
    
    $stmt_p = $conn->prepare("SELECT privilege_name FROM teacher_privileges WHERE user_id = ?");
    if ($stmt_p) {
        $stmt_p->bind_param("i", $_SESSION['user_id']);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        while ($row = $res_p->fetch_assoc()) {
            $_SESSION['privileges'][] = $row['privilege_name'];
        }
        $stmt_p->close();
    }
}

// Ensure the helper function exists safely
if (!function_exists('has_privilege')) {
    function has_privilege($role) {
        return (isset($_SESSION['privileges']) && is_array($_SESSION['privileges']) && in_array($role, $_SESSION['privileges']));
    }
}

// Check if teacher has ANY delegated admin rights to render the section header
$has_admin_rights = (has_privilege('Admission') || has_privilege('Payment') || has_privilege('Hostel') || has_privilege('Academic'));
?>
<aside class="left-sidebar shadow-sm">
  <div>
    <div class="brand-logo d-flex align-items-center justify-content-between p-4">
      <a href="<?php echo BASE_URL; ?>teachers/index.php" class="text-nowrap logo-img d-flex align-items-center text-decoration-none">
        <img src="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" width="40" alt="NAFCONM Logo" class="me-2" />
        <span class="fw-black text-dark fs-5">NAFCONM</span>
      </a>
      <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
        <i class="ti ti-x fs-8"></i>
      </div>
    </div>
    
    <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
      <ul id="sidebarnav" class="pt-2">
        
        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">HOME</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/index.php" aria-expanded="false">
            <span><i class="ti ti-layout-dashboard"></i></span>
            <span class="hide-menu">Dashboard</span>
          </a>
        </li>

        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">LEARNING MANAGEMENT</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'my_student.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/my_student.php" aria-expanded="false">
            <span><i class="ti ti-users"></i></span>
            <span class="hide-menu">Class Rosters</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'materials.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/materials.php" aria-expanded="false">
            <span><i class="ti ti-folder"></i></span>
            <span class="hide-menu">Course Materials</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'assignments.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/assignments.php" aria-expanded="false">
            <span><i class="ti ti-file-text"></i></span>
            <span class="hide-menu">Assignments & Tasks</span>
          </a>
        </li>

        <li class="nav-small-cap">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">COMMUNICATION & DATA</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'announcements.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/announcements.php" aria-expanded="false">
            <span><i class="ti ti-bell"></i></span>
            <span class="hide-menu">Announcements</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/reports.php" aria-expanded="false">
            <span><i class="ti ti-report-analytics"></i></span>
            <span class="hide-menu">Academic Reports</span>
          </a>
        </li>

        <!-- DYNAMIC PRIVILEGE INJECTIONS (RBAC) -->
        <?php if($has_admin_rights): ?>
            <li class="nav-small-cap mt-4">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu text-primary fw-black">DELEGATED ADMIN</span>
            </li>
            
            <?php if(has_privilege('Admission')): ?>
            <li class="sidebar-item mb-1">
              <a class="sidebar-link bg-primary-subtle text-primary <?= $current_page == 'admissions_overview.php' ? 'active border border-primary' : '' ?>" href="<?php echo BASE_URL; ?>app/admissions_overview.php" aria-expanded="false">
                <span><i class="ti ti-user-check"></i></span>
                <span class="hide-menu fw-bold">Admissions Desk</span>
              </a>
            </li>
            <?php endif; ?>

            <?php if(has_privilege('Payment')): ?>
            <li class="sidebar-item mb-1">
              <a class="sidebar-link bg-success-subtle text-success <?= $current_page == 'manage_payments.php' ? 'active border border-success' : '' ?>" href="<?php echo BASE_URL; ?>app/manage_payments.php" aria-expanded="false">
                <span><i class="ti ti-cash-banknote"></i></span>
                <span class="hide-menu fw-bold">Bursary Control</span>
              </a>
            </li>
            <?php endif; ?>

            <?php if(has_privilege('Hostel')): ?>
            <li class="sidebar-item mb-1">
              <a class="sidebar-link bg-warning-subtle text-warning <?= ($current_page == 'hostel_warden.php' || $current_page == 'manage_hostels.php') ? 'active border border-warning' : '' ?>" href="<?php echo BASE_URL; ?>app/hostel_warden.php" aria-expanded="false">
                <span><i class="ti ti-home-check"></i></span>
                <span class="hide-menu fw-bold">Hostel Warden</span>
              </a>
            </li>
            <?php endif; ?>

            <?php if(has_privilege('Academic')): ?>
            <li class="sidebar-item mb-1">
              <a class="sidebar-link bg-info-subtle text-info <?= $current_page == 'manage_academics.php' ? 'active border border-info' : '' ?>" href="<?php echo BASE_URL; ?>app/manage_academics.php" aria-expanded="false">
                <span><i class="ti ti-building-bank"></i></span>
                <span class="hide-menu fw-bold">Academic Setup</span>
              </a>
            </li>
            <?php endif; ?>
        <?php endif; ?>

        <li class="nav-small-cap mt-4">
          <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
          <span class="hide-menu">ACCOUNT</span>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link <?= $current_page == 'profile.php' ? 'active' : '' ?>" href="<?php echo BASE_URL; ?>teachers/profile.php" aria-expanded="false">
            <span><i class="ti ti-user"></i></span>
            <span class="hide-menu">My Profile</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a class="sidebar-link text-danger" href="<?php echo BASE_URL; ?>logout.php" aria-expanded="false">
            <span><i class="ti ti-logout text-danger"></i></span>
            <span class="hide-menu text-danger fw-bold">Secure Logout</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>