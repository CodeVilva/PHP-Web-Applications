<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM learning_materials WHERE id = ?");
    $stmt->execute([$del_id]);
    $mat = $stmt->fetch();
    if ($mat) {
        $real_path = UPLOAD_PATH . '/materials/' . $mat['file_path'];
        if (file_exists($real_path)) {
            @unlink($real_path);
        }
        $pdo->prepare("DELETE FROM learning_materials WHERE id = ?")->execute([$del_id]);
        set_flash('info', 'Study material removed.');
    }
    redirect('admin/materials.php');
}

$materials = $pdo->query("
    SELECT lm.*, f.full_name as faculty_name, f.department
    FROM learning_materials lm
    LEFT JOIN faculty f ON lm.faculty_id = f.id
    ORDER BY lm.created_at DESC
")->fetchAll();

$page_title = "Course Materials Repository";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">E-Learning Content & Material Moderation</h2>
        <p class="text-muted mb-0">System-wide curriculum notes, lecture slides, assignments, and digital assets.</p>
      </div>
    </div>

    <div class="bento-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Title & Subject</th>
              <th>Instructor</th>
              <th>Format</th>
              <th>Size</th>
              <th>Downloads</th>
              <th>Uploaded Date</th>
              <th class="text-end text-nowrap" style="width: 140px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($materials)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No materials found in the repository.</td></tr>
            <?php else: ?>
              <?php foreach ($materials as $m): ?>
                <?php 
                  $real_file = UPLOAD_PATH . '/materials/' . $m['file_path'];
                  $has_file = file_exists($real_file);
                  $web_link = BASE_URL . '/uploads/materials/' . e($m['file_path']);
                ?>
                <tr>
                  <td>
                    <div class="fw-bold"><?= e($m['title']) ?></div>
                    <small class="text-muted"><?= e($m['subject_code']) ?> &bull; <?= e($m['subject_name']) ?></small>
                  </td>
                  <td><?= e($m['faculty_name'] ?? 'Faculty') ?></td>
                  <td><span class="badge bg-light text-dark border text-uppercase"><?= e($m['file_type']) ?></span></td>
                  <td><small class="text-muted"><?= e($m['file_size']) ?></small></td>
                  <td><strong class="text-primary"><?= $m['download_count'] ?></strong></td>
                  <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                  <td class="text-end text-nowrap">
                    <div class="d-inline-flex align-items-center gap-1">
                      <?php if ($has_file): ?>
                        <a href="<?= $web_link ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" title="Open File">
                          <i class="bi bi-box-arrow-up-right me-1"></i> Open
                        </a>
                      <?php endif; ?>
                      <a href="<?= BASE_URL ?>/admin/materials.php?delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirm('Delete this study material permanently?')" title="Delete">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
