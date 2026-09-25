<?php
$role = current_role();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-header">
    <div class="brand-icon">
      <i class="bi bi-mortarboard-fill"></i>
    </div>
    <div class="brand-text">
      <span class="brand-name">ISCMS Cloud</span>
      <span class="brand-badge text-uppercase"><?= e($role) ?></span>
    </div>
  </div>

  <div class="sidebar-menu">
    <?php if ($role === 'student'): ?>
      <div class="menu-section">Main Menu</div>
      <a href="<?= BASE_URL ?>/student/index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i> Dashboard
      </a>
      <a href="<?= BASE_URL ?>/student/classroom.php" class="nav-item <?= $current_page == 'classroom.php' ? 'active' : '' ?>">
        <i class="bi bi-mortarboard"></i> Online Classroom (Q&A)
      </a>
      <a href="<?= BASE_URL ?>/student/attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
        <i class="bi bi-calendar-check"></i> Attendance Tracker
      </a>
      <a href="<?= BASE_URL ?>/student/timetable.php" class="nav-item <?= $current_page == 'timetable.php' ? 'active' : '' ?>">
        <i class="bi bi-clock-history"></i> Class Timetable
      </a>
      <a href="<?= BASE_URL ?>/student/materials.php" class="nav-item <?= $current_page == 'materials.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-bookmark-fill"></i> Study Materials
      </a>
      <a href="<?= BASE_URL ?>/student/faculty.php" class="nav-item <?= $current_page == 'faculty.php' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Faculty Directory
      </a>
      
      <div class="menu-section">Campus Life & Aid</div>
      <a href="<?= BASE_URL ?>/student/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>">
        <i class="bi bi-heart-pulse-fill"></i> Community Service
      </a>
      <a href="<?= BASE_URL ?>/student/events.php" class="nav-item <?= $current_page == 'events.php' ? 'active' : '' ?>">
        <i class="bi bi-trophy-fill"></i> Events & Accolades
      </a>
      <a href="<?= BASE_URL ?>/student/scholarships.php" class="nav-item <?= $current_page == 'scholarships.php' ? 'active' : '' ?>">
        <i class="bi bi-award-fill"></i> Scholarships & Grants
      </a>
      <a href="<?= BASE_URL ?>/student/complaints.php" class="nav-item <?= $current_page == 'complaints.php' ? 'active' : '' ?>">
        <i class="bi bi-chat-left-dots-fill"></i> Grievance Desk
      </a>

    <?php elseif ($role === 'faculty'): ?>
      <div class="menu-section">Teaching Hub</div>
      <a href="<?= BASE_URL ?>/faculty/index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-grid-fill"></i> Faculty Workspace
      </a>
      <a href="<?= BASE_URL ?>/faculty/attendance.php" class="nav-item <?= $current_page == 'attendance.php' ? 'active' : '' ?>">
        <i class="bi bi-camera-video-fill"></i> Attendance & Face AI
      </a>
      <a href="<?= BASE_URL ?>/faculty/timetable.php" class="nav-item <?= $current_page == 'timetable.php' ? 'active' : '' ?>">
        <i class="bi bi-calendar-week"></i> Schedule & Reminders
      </a>
      <a href="<?= BASE_URL ?>/faculty/classroom.php" class="nav-item <?= $current_page == 'classroom.php' ? 'active' : '' ?>">
        <i class="bi bi-mortarboard-fill"></i> Classroom & Grading
      </a>
      <a href="<?= BASE_URL ?>/faculty/materials.php" class="nav-item <?= $current_page == 'materials.php' ? 'active' : '' ?>">
        <i class="bi bi-cloud-arrow-up-fill"></i> Upload Study Notes
      </a>
      <a href="<?= BASE_URL ?>/faculty/profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
        <i class="bi bi-person-lines-fill"></i> Profile & Subjects
      </a>

      <div class="menu-section">Oversight & Service</div>
      <a href="<?= BASE_URL ?>/faculty/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>">
        <i class="bi bi-heart-pulse-fill"></i> Community Services
      </a>
      <a href="<?= BASE_URL ?>/faculty/events.php" class="nav-item <?= $current_page == 'events.php' ? 'active' : '' ?>">
        <i class="bi bi-award-fill"></i> Events & Achievements
      </a>
      <?php 
        $is_fac_hod = false;
        if (is_logged_in() && !empty($_SESSION['user_id'])) {
            $h_chk = $pdo->prepare("SELECT is_hod FROM faculty WHERE user_id = ?");
            $h_chk->execute([$_SESSION['user_id']]);
            $is_fac_hod = (bool)$h_chk->fetchColumn();
        }
      ?>
      <a href="<?= BASE_URL ?>/faculty/scholarships.php" class="nav-item <?= $current_page == 'scholarships.php' ? 'active' : '' ?>">
        <i class="bi bi-patch-check-fill <?= $is_fac_hod ? 'text-warning' : '' ?>"></i> <?= $is_fac_hod ? 'HOD Endorsements' : 'Scholarships Desk' ?>
        <?php if ($is_fac_hod): ?>
          <span class="badge bg-warning text-dark ms-auto fw-bold" style="font-size:9px;">HOD</span>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>/faculty/complaints.php" class="nav-item <?= $current_page == 'complaints.php' ? 'active' : '' ?>">
        <i class="bi bi-chat-quote-fill"></i> Staff Grievance Desk
      </a>

    <?php elseif ($role === 'admin'): ?>
      <div class="menu-section">Administration</div>
      <a href="<?= BASE_URL ?>/admin/index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Executive Command
      </a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> User Management
      </a>
      <a href="<?= BASE_URL ?>/admin/timetable.php" class="nav-item <?= $current_page == 'timetable.php' ? 'active' : '' ?>">
        <i class="bi bi-calendar3"></i> Master Timetable
      </a>
      <a href="<?= BASE_URL ?>/admin/faculty.php" class="nav-item <?= $current_page == 'faculty.php' ? 'active' : '' ?>">
        <i class="bi bi-person-video3"></i> Faculty Biometrics
      </a>
      <a href="<?= BASE_URL ?>/admin/materials.php" class="nav-item <?= $current_page == 'materials.php' ? 'active' : '' ?>">
        <i class="bi bi-collection-fill"></i> Academic Content
      </a>

      <div class="menu-section">Governance & Reports</div>
      <a href="<?= BASE_URL ?>/admin/community.php" class="nav-item <?= $current_page == 'community.php' ? 'active' : '' ?>">
        <i class="bi bi-heart-pulse-fill"></i> Community Services
      </a>
      <a href="<?= BASE_URL ?>/admin/events.php" class="nav-item <?= $current_page == 'events.php' ? 'active' : '' ?>">
        <i class="bi bi-trophy-fill"></i> Events & External Roll
      </a>
      <a href="<?= BASE_URL ?>/admin/scholarships.php" class="nav-item <?= $current_page == 'scholarships.php' ? 'active' : '' ?>">
        <i class="bi bi-cash-stack"></i> Govt Grants & Schemes
      </a>
      <a href="<?= BASE_URL ?>/admin/complaints.php" class="nav-item <?= $current_page == 'complaints.php' ? 'active' : '' ?>">
        <i class="bi bi-shield-exclamation"></i> Grievance Redressal
      </a>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-line-fill"></i> Analytics & Audit
      </a>
      <a href="<?= BASE_URL ?>/admin/notifications.php" class="nav-item <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
        <i class="bi bi-broadcast"></i> Campus Dispatch
      </a>
    <?php endif; ?>
  </div>
</aside>
