<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

// Verify external award
if (isset($_GET['verify_ext_id'])) {
    $ext_id = (int)$_GET['verify_ext_id'];
    $pdo->prepare("UPDATE external_events SET verification_status = 'verified' WHERE id = ?")->execute([$ext_id]);
    set_flash('success', 'Student external achievement verified and added to college Roll of Honor!');
    redirect('faculty/events.php');
}

$external_submissions = $pdo->query("
    SELECT ee.*, st.full_name as student_name, st.roll_no, st.department
    FROM external_events ee
    JOIN students st ON ee.student_id = st.id
    ORDER BY ee.created_at DESC
")->fetchAll();

$events = $pdo->query("SELECT * FROM events ORDER BY start_datetime DESC")->fetchAll();

$page_title = 'Events & Student Accolades Desk';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Events & Accolades Verification</h3>
        <p class="text-muted small mb-0">Module 6: Verify student external competition wins and view campus conclaves</p>
      </div>
    </div>

    <!-- External Student Accolades Verification Table -->
    <div class="bento-card mb-4">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-trophy-fill text-warning"></i> External Competition Achievements Pending Verification</h5>
        <span class="text-muted small"><?= count($external_submissions) ?> Total Accolades</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Student</th>
              <th>External Event</th>
              <th>Host Institution</th>
              <th>Award Won</th>
              <th>Certificate Proof</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($external_submissions)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No external achievements submitted for verification.</td></tr>
            <?php else: ?>
              <?php foreach ($external_submissions as $sub): ?>
                <tr>
                  <td>
                    <strong><?= e($sub['student_name']) ?></strong>
                    <small class="text-muted d-block"><?= e($sub['roll_no']) ?> &bull; <?= e($sub['department']) ?></small>
                  </td>
                  <td>
                    <div class="fw-bold"><?= e($sub['event_name']) ?></div>
                    <span class="badge bg-light text-dark border"><?= e($sub['event_type']) ?></span>
                  </td>
                  <td><?= e($sub['organizing_institution']) ?></td>
                  <td><strong class="text-success"><?= e($sub['award_received']) ?></strong></td>
                  <td>
                    <?php if ($sub['certificate_file'] && file_exists(UPLOAD_PATH . '/certificates/' . $sub['certificate_file'])): ?>
                      <a href="<?= BASE_URL ?>/uploads/certificates/<?= e($sub['certificate_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2">
                        <i class="bi bi-file-earmark-image me-1"></i> View Proof
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">No File</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= $sub['verification_status'] === 'verified' ? 'bg-success' : 'bg-warning text-dark' ?>">
                      <?= ucfirst($sub['verification_status']) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <?php if ($sub['verification_status'] !== 'verified'): ?>
                      <a href="events.php?verify_ext_id=<?= $sub['id'] ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-check2-circle me-1"></i> Verify & Endorse
                      </a>
                    <?php endif; ?>
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
