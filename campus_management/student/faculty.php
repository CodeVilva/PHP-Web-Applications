<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$faculty = $pdo->query("
    SELECT f.*, u.email, u.status as user_status
    FROM faculty f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.full_name ASC
")->fetchAll();

$page_title = 'Faculty Directory & Subjects';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Faculty & Department Directory</h3>
        <p class="text-muted small mb-0">Module 3: Academic staff profiles, subjects handled, office cabins, and contact details</p>
      </div>
    </div>

    <div class="row g-4">
      <?php if (empty($faculty)): ?>
        <div class="col-12"><div class="bento-card text-center py-5 text-muted">No faculty profiles available.</div></div>
      <?php else: ?>
        <?php foreach ($faculty as $f): ?>
          <div class="col-md-6 col-lg-4">
            <div class="bento-card h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="d-flex align-items-center gap-3 mb-3">
                  <div class="rounded-circle bg-primary-subtle text-primary fw-bold fs-4 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <?= strtoupper(substr($f['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <h5 class="fw-bold mb-0 text-dark"><?= e($f['full_name']) ?></h5>
                    <small class="text-primary fw-semibold"><?= e($f['designation']) ?></small>
                  </div>
                </div>

                <div class="small mb-2">
                  <span class="badge bg-light text-dark border me-1"><?= e($f['department']) ?></span>
                  <code><?= e($f['employee_id']) ?></code>
                </div>

                <div class="p-2 bg-light rounded-3 small mb-3">
                  <div class="mb-1"><i class="bi bi-book-half text-primary me-1"></i> <strong>Subjects Handled:</strong> <?= e($f['subjects_handled'] ?: 'Computer Science Core') ?></div>
                  <div><i class="bi bi-mortarboard text-secondary me-1"></i> <strong>Qualification:</strong> <?= e($f['qualification'] ?: 'Ph.D. / M.Tech') ?></div>
                </div>

                <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i> Office: <strong><?= e($f['office_room'] ?: 'Academic Block A') ?></strong></div>
                <div class="small text-muted"><i class="bi bi-envelope me-1"></i> <?= e($f['email']) ?></div>
              </div>

              <div class="pt-3 border-top mt-3">
                <a href="mailto:<?= e($f['email']) ?>" class="btn btn-sm btn-outline-primary w-100">
                  <i class="bi bi-envelope-fill me-1"></i> Contact Instructor
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
