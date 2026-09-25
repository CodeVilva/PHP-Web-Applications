<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    $title      = trim($_POST['title'] ?? '');
    $message    = trim($_POST['message'] ?? '');
    $type       = $_POST['type'] ?? 'info';
    $target_aud = $_POST['role_target'] ?? 'all';

    if ($title && $message) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, role_target)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$title, $message, $type, $target_aud]);
        set_flash('success', "Campus broadcast '$title' dispatched successfully!");
        redirect('admin/notifications.php');
    }
}

// Delete notification
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$del_id]);
    set_flash('info', 'Broadcast removed.');
    redirect('admin/notifications.php');
}

$broadcasts = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC")->fetchAll();

$page_title = "Campus Broadcast Console";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Campus Broadcast & Dispatch Console</h2>
        <p class="text-muted mb-0">Push urgent alerts, circulars, and system notices across user cohorts.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBroadcastModal">
        <i class="bi bi-broadcast me-1"></i> Send Campus Broadcast
      </button>
    </div>

    <div class="bento-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Broadcast Title</th>
              <th>Audience</th>
              <th>Type</th>
              <th>Message Snippet</th>
              <th>Date / Time</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($broadcasts)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No broadcasts dispatched yet.</td></tr>
            <?php else: ?>
              <?php foreach ($broadcasts as $b): ?>
                <tr>
                  <td><strong><?= e($b['title']) ?></strong></td>
                  <td>
                    <span class="badge <?= $b['role_target'] === 'all' ? 'bg-primary' : ($b['role_target'] === 'student' ? 'bg-success' : 'bg-info text-dark') ?>">
                      <?= ucfirst($b['role_target']) ?>
                    </span>
                  </td>
                  <td><span class="badge bg-light text-dark border text-uppercase"><?= e($b['type']) ?></span></td>
                  <td><span class="small text-muted"><?= e(substr($b['message'], 0, 70)) ?>...</span></td>
                  <td><?= date('M d, Y h:i A', strtotime($b['created_at'])) ?></td>
                  <td class="text-end">
                    <a href="<?= BASE_URL ?>/admin/notifications.php?delete_id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this notification?')" title="Delete">
                      <i class="bi bi-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Modal: New Broadcast -->
<div class="modal fade" id="newBroadcastModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="send_broadcast">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Dispatch Campus Broadcast</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Notification Title</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Campus Circular">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Target Audience</label>
              <select name="role_target" class="form-select">
                <option value="all">Entire Campus (All Users)</option>
                <option value="student">Students Only</option>
                <option value="faculty">Faculty Members Only</option>
                <option value="admin">Administrators Only</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Alert Type</label>
              <select name="type" class="form-select">
                <option value="info">Information</option>
                <option value="success">Success / Event</option>
                <option value="warning">Warning</option>
                <option value="danger">Urgent / Alert</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Message Body</label>
            <textarea name="message" class="form-control" rows="4" required placeholder="Complete message content..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Dispatch Now</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
