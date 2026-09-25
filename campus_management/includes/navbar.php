<?php
$role = current_role();
$user_display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
?>
<header class="app-navbar">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-light d-lg-none" id="sidebarToggle">
      <i class="bi bi-list fs-5"></i>
    </button>
    <div class="d-none d-md-flex align-items-center text-muted small">
      <i class="bi bi-shield-check text-primary me-2"></i> Role: <span class="badge bg-light text-dark ms-1 text-uppercase fw-bold"><?= e($role) ?></span>
    </div>
  </div>

  <div class="d-flex align-items-center gap-3">
    <?php
      $notif_url = BASE_URL . '/' . $role . '/notifications.php';
    ?>
    <a href="<?= $notif_url ?>" class="position-relative btn btn-light rounded-circle p-2 text-secondary" title="Notifications">
      <i class="bi bi-bell fs-5"></i>
      <?php if ($unread_count > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
          <?= $unread_count ?>
        </span>
      <?php endif; ?>
    </a>

    <div class="dropdown">
      <div class="d-flex align-items-center gap-2" role="button" data-bs-toggle="dropdown">
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white shadow-sm" style="width: 38px; height: 38px; background: var(--brand-primary);">
          <?= strtoupper(substr($user_display_name, 0, 1)) ?>
        </div>
        <div class="d-none d-sm-block text-start">
          <div class="fw-bold text-dark lh-1" style="font-size: 0.88rem;"><?= e($user_display_name) ?></div>
          <small class="text-muted" style="font-size: 0.75rem;"><?= ucfirst(e($role)) ?></small>
        </div>
        <i class="bi bi-chevron-down text-muted small ms-1"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2 border-light">
        <li class="px-3 py-2 border-bottom">
          <div class="fw-bold"><?= e($user_display_name) ?></div>
          <div class="text-muted small"><?= e($_SESSION['email'] ?? '') ?></div>
        </li>
        <?php if ($role === 'faculty'): ?>
          <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/faculty/profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
        <?php endif; ?>
        <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</header>
