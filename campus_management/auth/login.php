<?php
require_once __DIR__ . '/../config/config.php';

$error = '';
$selected_role = $_GET['role'] ?? 'student';
if (!in_array($selected_role, ['student', 'faculty', 'admin'])) {
    $selected_role = 'student';
}

$prefilled_username = trim($_GET['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $form_role = $_POST['role'] ?? $selected_role;
    
    if (empty($username_or_email) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
        $stmt->execute([$username_or_email, $username_or_email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user'] = $user;
            
            if ($user['role'] === 'student') {
                $st = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
                $st->execute([$user['id']]);
                $profile = $st->fetch();
                $_SESSION['student_id'] = $profile['id'] ?? null;
                $_SESSION['full_name'] = $profile['full_name'] ?? $user['username'];
                $_SESSION['roll_no'] = $profile['roll_no'] ?? '';
                $_SESSION['department'] = $profile['department'] ?? '';
                redirect('student/index.php');
            } elseif ($user['role'] === 'faculty') {
                $st = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
                $st->execute([$user['id']]);
                $profile = $st->fetch();
                $_SESSION['faculty_id'] = $profile['id'] ?? null;
                $_SESSION['full_name'] = $profile['full_name'] ?? $user['username'];
                $_SESSION['designation'] = $profile['designation'] ?? '';
                $_SESSION['department'] = $profile['department'] ?? '';
                redirect('faculty/index.php');
            } else {
                $_SESSION['full_name'] = 'Administrator';
                redirect('admin/index.php');
            }
        } else {
            $error = 'Invalid credentials or inactive account.';
            $prefilled_username = $username_or_email;
        }
    }
}
$flash = get_flash();

$role_meta = [
    'student' => [
        'title' => 'Student Portal Login',
        'subtitle' => 'Access your academic records, attendance, timetable, & materials',
        'badge' => 'Student Access',
        'icon' => 'bi-mortarboard-fill',
        'color' => '#00897b',
        'user_placeholder' => 'Student Roll No / Username / Email',
        'reg_link' => 'register.php?role=student',
        'reg_text' => 'New student? Register student account'
    ],
    'faculty' => [
        'title' => 'Staff / Faculty Portal Login',
        'subtitle' => 'Manage classes, log student attendance, & publish course materials',
        'badge' => 'Staff & Faculty',
        'icon' => 'bi-person-workspace',
        'color' => '#7b1fa2',
        'user_placeholder' => 'Faculty Employee ID / Email / Username',
        'reg_link' => 'register.php?role=faculty',
        'reg_text' => 'New faculty member? Register staff account'
    ],
    'admin' => [
        'title' => 'Administrator Central Command',
        'subtitle' => 'Institutional governance, master timetable, and accounts management',
        'badge' => 'Executive Admin',
        'icon' => 'bi-shield-lock-fill',
        'color' => '#0A66C2',
        'user_placeholder' => 'Admin Username / Email',
        'reg_link' => null,
        'reg_text' => null
    ]
];
$curr = $role_meta[$selected_role];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($curr['title']) ?> | ISCMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bento.css">
  <style>
    .role-nav-pills {
      background: #e9ecef;
      border-radius: 50rem;
      padding: 4px;
      display: flex;
      margin-bottom: 24px;
    }
    .role-nav-link {
      flex: 1;
      text-align: center;
      padding: 8px 12px;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--text-slate);
      border-radius: 50rem;
      text-decoration: none;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .role-nav-link.active.student {
      background: #00897b;
      color: #fff;
      box-shadow: 0 2px 6px rgba(0,137,123,0.3);
    }
    .role-nav-link.active.faculty {
      background: #7b1fa2;
      color: #fff;
      box-shadow: 0 2px 6px rgba(123,31,162,0.3);
    }
    .role-nav-link.active.admin {
      background: #0A66C2;
      color: #fff;
      box-shadow: 0 2px 6px rgba(10,102,194,0.3);
    }
  </style>
</head>
<body style="background: var(--bg-smoke); min-height: 100vh;" class="d-flex align-items-center justify-content-center p-3">

<div class="container" style="max-width: 980px;">
  <div class="row g-4 align-items-stretch">
    
    <!-- Left Hero Banner -->
    <div class="col-lg-5 d-none d-lg-block">
      <div class="bento-card bento-card-primary h-100 d-flex flex-column justify-content-between p-4 p-md-5" style="background: linear-gradient(145deg, #0A66C2 0%, #004b8d 100%);">
        <div>
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-white rounded-3 p-2 d-inline-flex" style="color: var(--brand-primary);">
              <i class="bi bi-mortarboard-fill fs-3"></i>
            </div>
            <span class="fs-4 fw-bold text-white tracking-tight">ISCMS Cloud</span>
          </div>
          <h3 class="display-6 fw-bold text-white mb-3" style="font-size: 1.85rem;">Integrated Campus System</h3>
          <p class="text-white-50 lead fs-6">
            Unified access for Students, Staff & Faculty, and Institutional Administrators.
          </p>
        </div>

        <div class="mt-4 pt-4 border-top border-white border-opacity-25">
          <div class="d-flex flex-column gap-2 text-white-50 small">
            <div><i class="bi bi-mortarboard text-white me-2"></i> Student Learning & Attendance</div>
            <div><i class="bi bi-person-workspace text-white me-2"></i> Staff & Faculty Workspace</div>
            <div><i class="bi bi-shield-lock text-white me-2"></i> Administrative Central Governance</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Login Card -->
    <div class="col-lg-7">
      <div class="bento-card h-100 p-4 p-md-5 d-flex flex-column justify-content-center">
        
        <!-- Role Switching Tabs -->
        <div class="role-nav-pills">
          <a href="login.php?role=student" class="role-nav-link student <?= $selected_role === 'student' ? 'active' : '' ?>">
            <i class="bi bi-mortarboard"></i> Student
          </a>
          <a href="login.php?role=faculty" class="role-nav-link faculty <?= $selected_role === 'faculty' ? 'active' : '' ?>">
            <i class="bi bi-person-workspace"></i> Staff / Faculty
          </a>
          <a href="login.php?role=admin" class="role-nav-link admin <?= $selected_role === 'admin' ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> Admin
          </a>
        </div>

        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge rounded-pill px-3 py-1 text-white" style="background: <?= $curr['color'] ?>;">
              <i class="bi <?= $curr['icon'] ?> me-1"></i> <?= $curr['badge'] ?>
            </span>
          </div>
          <h3 class="fw-bold mb-1" style="color: var(--text-slate);"><?= e($curr['title']) ?></h3>
          <p class="text-muted small mb-0"><?= e($curr['subtitle']) ?></p>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> py-2 small mb-3">
            <i class="bi bi-check-circle-fill me-1"></i> <?= e($flash['message']) ?>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small mb-3">
            <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="login.php?role=<?= $selected_role ?>">
          <input type="hidden" name="role" value="<?= $selected_role ?>">
          
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted"><?= $curr['user_placeholder'] ?></label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
              <input type="text" name="username" class="form-control border-start-0" placeholder="Enter username, ID, or email" value="<?= e($prefilled_username) ?>" required autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
              <input type="password" name="password" class="form-control border-start-0" placeholder="Enter password" required>
            </div>
          </div>

          <button type="submit" class="bento-btn w-100 py-2 mt-2 text-white" style="background: <?= $curr['color'] ?>; border-color: <?= $curr['color'] ?>;">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to <?= ucfirst($selected_role) ?> Portal
          </button>
        </form>

        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
          <?php if ($curr['reg_link']): ?>
            <a href="<?= $curr['reg_link'] ?>" class="small fw-bold" style="color: <?= $curr['color'] ?>;">
              <?= e($curr['reg_text']) ?> &rarr;
            </a>
          <?php else: ?>
            <span class="text-muted small">System Administrator Access</span>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/" class="small text-muted">&larr; Back to Home</a>
        </div>
      </div>
    </div>

  </div>
</div>

</body>
</html>
