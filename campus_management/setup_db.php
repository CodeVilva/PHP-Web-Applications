<?php
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';

if (isset($_POST['install_db']) || isset($_GET['auto'])) {
    try {
        $sql_file = __DIR__ . '/database/schema.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("schema.sql file not found at " . $sql_file);
        }
        
        $sql_content = file_get_contents($sql_file);
        $pdo->exec($sql_content);
        $message = "Database `campus_db` and all 14 tables initialized with a clean state and default administrator account!";
    } catch (Exception $e) {
        $error = "Error setting up database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Database Setup | ISCMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bento.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-4" style="background: var(--bg-smoke);">
  <div class="bento-card" style="max-width: 580px; width: 100%;">
    <div class="d-flex align-items-center gap-3 mb-4">
      <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
        <i class="bi bi-database-fill-gear"></i>
      </div>
      <div>
        <h4 class="mb-0 fw-bold" style="color: var(--brand-primary);">Database Setup & Installer</h4>
        <small class="text-muted">Integrated Student Campus Management System</small>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div><?= htmlspecialchars($message) ?></div>
      </div>
      <div class="p-3 bg-light rounded mb-3 small">
        <strong>Default Admin Account:</strong><br>
        Username: <code>admin</code><br>
        Password: <code>password123</code>
      </div>
      <div class="mt-3">
        <a href="auth/login.php" class="bento-btn bento-btn-primary w-100 py-2">
          <i class="bi bi-box-arrow-in-right"></i> Proceed to Login Portal
        </a>
      </div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-triangle-fill fs-5"></i>
          <div><?= htmlspecialchars($error) ?></div>
        </div>
      <?php endif; ?>

      <p class="text-secondary">
        Click below to create and initialize the MySQL database <code>campus_db</code> with all 14 role-based tables and an initial administrator login.
      </p>

      <form method="POST">
        <button type="submit" name="install_db" class="bento-btn bento-btn-primary w-100 py-3 mb-3">
          <i class="bi bi-play-circle-fill fs-5"></i> Initialize Clean Database
        </button>
      </form>

      <div class="text-center">
        <a href="auth/login.php" class="text-muted small">Go to Login &rarr;</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
