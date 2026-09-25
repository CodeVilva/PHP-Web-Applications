<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    $role = current_role();
    if ($role === 'admin') redirect('admin/index.php');
    if ($role === 'faculty') redirect('faculty/index.php');
    if ($role === 'student') redirect('student/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Integrated Student Campus Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bento.css">
</head>
<body style="background: var(--bg-smoke);">

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-3" style="border-color: var(--border-light) !important;">
  <div class="container">
    <div class="d-flex align-items-center gap-2">
      <div class="brand-icon">
        <i class="bi bi-mortarboard-fill"></i>
      </div>
      <div>
        <span class="fs-5 fw-bold" style="color: var(--brand-primary);">ISCMS Portal</span>
        <span class="badge bg-light text-secondary ms-2 border">Campus Management</span>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="setup_db.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-database-gear me-1"></i> Setup DB</a>
      
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="bi bi-person-plus me-1"></i> Register Account
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="auth/register.php?role=student"><i class="bi bi-mortarboard text-success"></i> Student Registration</a></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="auth/register.php?role=faculty"><i class="bi bi-person-workspace text-purple" style="color:#7b1fa2;"></i> Staff & Faculty Registration</a></li>
        </ul>
      </div>

      <div class="dropdown">
        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="bi bi-box-arrow-in-right me-1"></i> Portal Sign In
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="auth/login.php?role=student"><i class="bi bi-mortarboard text-success"></i> Student Portal</a></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="auth/login.php?role=faculty"><i class="bi bi-person-workspace" style="color:#7b1fa2;"></i> Staff / Faculty Portal</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item d-flex align-items-center gap-2" href="auth/login.php?role=admin"><i class="bi bi-shield-lock text-primary"></i> Administrator Central</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<div class="container py-5">
  <div class="text-center max-w-700 mx-auto mb-5">
    <span class="role-badge admin mb-2">Integrated Academic Governance</span>
    <h1 class="display-5 fw-bold mb-3" style="color: var(--text-slate);">
      Integrated Student Campus Management System
    </h1>
    <p class="lead text-muted fs-6">
      Dedicated workspaces and role-based data flow across Students, Staff & Faculty, and Institutional Administrators.
    </p>
  </div>

  <!-- Bento 3-Role Grid -->
  <div class="bento-grid mb-5">
    
    <!-- 1. Student Card -->
    <div class="col-span-4">
      <div class="bento-card h-100 border-top border-4 d-flex flex-column justify-content-between" style="border-top-color: #00897b !important;">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="role-badge student">Role 1: Student</span>
            <i class="bi bi-mortarboard fs-4 text-teal"></i>
          </div>
          <h4 class="fw-bold mb-2">Student Workspace</h4>
          <p class="text-muted small mb-3">Personal academic dashboard for class attendance, timetable schedules, course materials, scholarship applications, and grievance filing.</p>
          <ul class="list-unstyled small text-secondary mb-4 space-y-1">
            <li><i class="bi bi-check2 text-success me-2"></i> Attendance percentages & warning triggers</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Weekly timetable & room allocation</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Download lecture slides & study notes</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Apply for scholarships & track reviews</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Submit & track student grievances</li>
          </ul>
        </div>
        <div class="d-flex flex-column gap-2 mt-auto">
          <a href="auth/login.php?role=student" class="bento-btn bento-btn-primary w-100" style="background: #00897b; border-color: #00897b;">
            <i class="bi bi-box-arrow-in-right me-1"></i> Student Login
          </a>
          <a href="auth/register.php?role=student" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-person-plus me-1"></i> Register as Student
          </a>
        </div>
      </div>
    </div>

    <!-- 2. Faculty / Staff Card -->
    <div class="col-span-4">
      <div class="bento-card h-100 border-top border-4 d-flex flex-column justify-content-between" style="border-top-color: #7b1fa2 !important;">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="role-badge faculty">Role 2: Staff & Faculty</span>
            <i class="bi bi-person-workspace fs-4 text-purple"></i>
          </div>
          <h4 class="fw-bold mb-2">Staff & Faculty Workspace</h4>
          <p class="text-muted small mb-3">Faculty portal for real-time class attendance logging, syllabus notes distribution, scholarship endorsements, and grievance replies.</p>
          <ul class="list-unstyled small text-secondary mb-4 space-y-1">
            <li><i class="bi bi-check2 text-success me-2"></i> Interactive student attendance marker</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Upload study files & lecture notes</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Review & endorse scholarship candidates</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Respond directly to student complaints</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Host campus workshops & events</li>
          </ul>
        </div>
        <div class="d-flex flex-column gap-2 mt-auto">
          <a href="auth/login.php?role=faculty" class="bento-btn bento-btn-primary w-100" style="background: #7b1fa2; border-color: #7b1fa2;">
            <i class="bi bi-box-arrow-in-right me-1"></i> Staff / Faculty Login
          </a>
          <a href="auth/register.php?role=faculty" class="btn btn-sm btn-outline-secondary w-100">
            <i class="bi bi-person-workspace me-1"></i> Register as Staff / Faculty
          </a>
        </div>
      </div>
    </div>

    <!-- 3. Administrator Card -->
    <div class="col-span-4">
      <div class="bento-card h-100 border-top border-4 d-flex flex-column justify-content-between" style="border-top-color: #0A66C2 !important;">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="role-badge admin">Role 3: Administrator</span>
            <i class="bi bi-shield-lock fs-4 text-primary"></i>
          </div>
          <h4 class="fw-bold mb-2">Administrator Command</h4>
          <p class="text-muted small mb-3">Executive administration console for user accounts provisioning, master timetable scheduling, scholarship grants, and audit reports.</p>
          <ul class="list-unstyled small text-secondary mb-4 space-y-1">
            <li><i class="bi bi-check2 text-success me-2"></i> Provision students & faculty accounts</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Master schedule & room clash detection</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Final scholarship grant disbursements</li>
            <li><i class="bi bi-check2 text-success me-2"></i> Institutional grievance resolution desk</li>
            <li><i class="bi bi-check2 text-success me-2"></i> System reports & campus broadcast</li>
          </ul>
        </div>
        <div class="d-flex flex-column gap-2 mt-auto">
          <a href="auth/login.php?role=admin" class="bento-btn bento-btn-primary w-100" style="background: #0A66C2; border-color: #0A66C2;">
            <i class="bi bi-shield-lock me-1"></i> Administrator Sign In
          </a>
          <span class="text-center text-muted small py-1">Admin accounts managed internally</span>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
