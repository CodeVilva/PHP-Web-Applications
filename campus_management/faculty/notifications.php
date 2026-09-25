<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$user_id = $_SESSION['user_id'];

$notifs = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE (user_id = ? OR role_target = 'faculty' OR role_target = 'all') 
    ORDER BY created_at DESC
");
$notifs->execute([$user_id]);
$all_notifs = $notifs->fetchAll();

$page_title = 'Faculty Notifications';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="mb-4">
      <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Faculty Notice Board</h3>
      <p class="text-muted small mb-0">Departmental circulars, deadline reminders, and campus updates</p>
    </div>

    <div class="bento-card">
      <div class="d-flex flex-column gap-3">
        <?php foreach ($all_notifs as $n): ?>
          <div class="p-3 rounded-3 border bg-light d-flex align-items-start gap-3">
            <div class="metric-icon <?= e($n['type']) ?> flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
              <i class="bi bi-bell-fill"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-bold text-dark mb-0"><?= e($n['title']) ?></h6>
                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></small>
              </div>
              <p class="text-secondary small mb-0"><?= e($n['message']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
