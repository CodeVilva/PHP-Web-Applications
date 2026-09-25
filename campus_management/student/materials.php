<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

// Handle Download Request
if (isset($_GET['download_id'])) {
    $mat_id = (int)$_GET['download_id'];
    $stmt = $pdo->prepare("SELECT * FROM learning_materials WHERE id = ?");
    $stmt->execute([$mat_id]);
    $mat = $stmt->fetch();

    if ($mat) {
        $pdo->prepare("UPDATE learning_materials SET download_count = download_count + 1 WHERE id = ?")->execute([$mat_id]);
        $real_file = UPLOAD_PATH . '/materials/' . $mat['file_path'];
        if (file_exists($real_file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($mat['file_path']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($real_file));
            readfile($real_file);
            exit;
        } else {
            set_flash('info', 'Viewing material resource: ' . htmlspecialchars($mat['title']));
            redirect('student/materials.php');
        }
    }
}

$materials = $pdo->query("
    SELECT lm.*, f.full_name as faculty_name, f.department
    FROM learning_materials lm
    LEFT JOIN faculty f ON lm.faculty_id = f.id
    ORDER BY lm.created_at DESC
")->fetchAll();

$page_title = 'Online Learning Materials';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="mb-4">
      <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Online Learning Materials</h3>
      <p class="text-muted small mb-0">Module 4: Access lecture slides, syllabus, and study notes uploaded by faculty</p>
    </div>

    <div class="row g-4">
      <?php if (empty($materials)): ?>
        <div class="col-12">
          <div class="bento-card text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
            <h5>No learning materials published yet.</h5>
            <p class="small text-muted mb-0">When your instructors upload syllabus notes or lecture files, they will appear here.</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($materials as $m): ?>
          <?php 
            $real_file = UPLOAD_PATH . '/materials/' . $m['file_path'];
            $has_file = file_exists($real_file);
            $web_link = BASE_URL . '/uploads/materials/' . e($m['file_path']);
          ?>
          <div class="col-md-6 col-lg-6">
            <div class="bento-card h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <span class="badge bg-light text-primary border fw-bold"><?= e($m['subject_code']) ?> &bull; <?= e($m['file_type']) ?></span>
                  <small class="text-muted"><i class="bi bi-download me-1"></i><?= $m['download_count'] ?> downloads</small>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= e($m['title']) ?></h5>
                <small class="text-primary fw-semibold d-block mb-2"><?= e($m['subject_name']) ?></small>
                <p class="text-secondary small mb-3"><?= e($m['description']) ?></p>
              </div>

              <div class="pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="small text-muted">
                  <i class="bi bi-person me-1"></i><?= e($m['faculty_name'] ?? 'Faculty') ?> &bull; <?= e($m['file_size']) ?>
                </div>
                <div>
                  <a href="materials.php?download_id=<?= $m['id'] ?>" class="bento-btn bento-btn-primary py-1 px-3 small">
                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
