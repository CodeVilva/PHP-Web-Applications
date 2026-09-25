<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

if (isset($_POST['verify_student'])) {
    $part_id = (int)$_POST['participant_id'];
    $hours = (int)$_POST['hours_awarded'];
    $status = $_POST['status'] ?? 'verified';

    $pdo->prepare("UPDATE community_service_participants SET status = ?, hours_awarded = ? WHERE id = ?")
        ->execute([$status, $hours, $part_id]);
    set_flash('success', 'Student community service participation updated!');
    redirect('faculty/community.php');
}

$records = $pdo->query("
    SELECT p.*, s.full_name as student_name, s.roll_no, s.department, a.title as activity_title, a.hours_credited as max_hours, a.event_date
    FROM community_service_participants p
    JOIN students s ON p.student_id = s.id
    JOIN community_service a ON p.activity_id = a.id
    ORDER BY p.registered_at DESC
")->fetchAll();

$page_title = 'Community Service Records';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="mb-4">
      <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Community Service Oversight</h3>
      <p class="text-muted small mb-0">Module 5: Review volunteer attendance and award certified community service hours</p>
    </div>

    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-heart-pulse-fill text-danger"></i> Student Participation Logs</h5>
        <span class="text-muted small"><?= count($records) ?> Registrations</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Student</th>
              <th>Activity / Drive</th>
              <th>Date</th>
              <th>Status</th>
              <th>Hours Awarded</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): ?>
              <tr>
                <td>
                  <strong class="text-dark"><?= e($r['student_name']) ?></strong>
                  <div class="small text-muted"><?= e($r['roll_no']) ?> &bull; <?= e($r['department']) ?></div>
                </td>
                <td class="fw-semibold"><?= e($r['activity_title']) ?></td>
                <td><?= date('M d, Y', strtotime($r['event_date'])) ?></td>
                <td>
                  <span class="status-pill <?= e($r['status']) ?>">
                    <?= ucfirst(e($r['status'])) ?>
                  </span>
                </td>
                <td><strong><?= $r['hours_awarded'] ?></strong> / <?= $r['max_hours'] ?> hrs</td>
                <td>
                  <form method="POST" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="participant_id" value="<?= $r['id'] ?>">
                    <input type="number" name="hours_awarded" class="form-control form-control-sm" style="width: 70px;" value="<?= $r['hours_awarded'] ?: $r['max_hours'] ?>" min="0" max="<?= $r['max_hours'] ?>">
                    <select name="status" class="form-select form-select-sm" style="width: 110px;">
                      <option value="verified" <?= $r['status'] === 'verified' ? 'selected' : '' ?>>Verify</option>
                      <option value="attended" <?= $r['status'] === 'attended' ? 'selected' : '' ?>>Attended</option>
                      <option value="registered" <?= $r['status'] === 'registered' ? 'selected' : '' ?>>Registered</option>
                    </select>
                    <button type="submit" name="verify_student" class="btn btn-sm btn-primary">
                      <i class="bi bi-check-lg"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
