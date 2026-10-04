<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/n-portal/');
}
?>
<!-- Sidebar Start -->
    <aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="<?php echo BASE_URL; ?>teachers/index.php" class="text-nowrap logo-img">
            <img src="<?php echo BASE_URL; ?>assets/images/logos/favicon.png" width="150" class="img-fluid mx-4 p-1 my-1 mb-0" alt="N-Portal Logo" />
          </a>
          <div class="close-btn d-xl-none d-block sidebartoggler cursor-pointer" id="sidebarCollapse">
            <i class="ti ti-x fs-8"></i>
          </div>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Home</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/index.php" aria-expanded="false">
                <span>
                  <i class="ti ti-layout-dashboard"></i>
                </span>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">ACADEMICS</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/my_courses.php" aria-expanded="false">
                <span>
                  <i class="ti ti-book"></i>
                </span>
                <span class="hide-menu">My Courses</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/my_students.php" aria-expanded="false">
                <span>
                  <i class="ti ti-users"></i>
                </span>
                <span class="hide-menu">My Students</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/assignments.php" aria-expanded="false">
                <span>
                  <i class="ti ti-file-text"></i>
                </span>
                <span class="hide-menu">Assignments</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/course_materials.php" aria-expanded="false">
                <span>
                  <i class="ti ti-folder"></i>
                </span>
                <span class="hide-menu">Course Materials</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/grade_submissions.php" aria-expanded="false">
                <span>
                  <i class="ti ti-award"></i>
                </span>
                <span class="hide-menu">Grade Submissions</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/record_assessments.php" aria-expanded="false">
                <span><i class="ti ti-edit"></i></span>
                <span class="hide-menu">Record Assessments</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/assessment_overview.php" aria-expanded="false">
                <span>
                  <i class="ti ti-chart-pie"></i>
                </span>
                <span class="hide-menu">Assessment Overview</span>
              </a>
            </li>
             <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/reports.php" aria-expanded="false">
                <span>
                  <i class="ti ti-report-analytics"></i>
                </span>
                <span class="hide-menu">Reports</span>
              </a>
            </li>
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">ACCOUNT</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>teachers/profile.php" aria-expanded="false">
                <span>
                  <i class="ti ti-user"></i>
                </span>
                <span class="hide-menu">My Profile</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?php echo BASE_URL; ?>logout.php" aria-expanded="false">
                <span>
                  <i class="ti ti-logout"></i>
                </span>
                <span class="hide-menu">Logout</span>
              </a>
            </li>
          </ul>
          
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
    <!--  Sidebar End -->