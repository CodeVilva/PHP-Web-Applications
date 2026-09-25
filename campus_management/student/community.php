<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;

if (isset($_POST['register_activity'])) {
    $act_id = (int)$_POST['activity_id'];
    $chk = $pdo->prepare("SELECT id FROM community_service_participants WHERE activity_id = ? AND student_id = ?");
    $chk->execute([$act_id, $student_id]);
    if ($chk->fetch()) {
        set_flash('warning', 'You are already registered for this community initiative.');
    } else {
        $ins = $pdo->prepare("INSERT INTO community_service_participants (activity_id, student_id, status, hours_awarded) VALUES (?, ?, 'registered', 0)");
        $ins->execute([$act_id, $student_id]);
        set_flash('success', 'Successfully registered for community service activity!');
    }
    redirect('student/community.php');
}

$activities = $pdo->query("SELECT * FROM community_service ORDER BY event_date DESC")->fetchAll();

$my_parts = $pdo->prepare("
    SELECT p.*, a.title, a.event_date, a.location, a.hours_credited 
    FROM community_service_participants p
    JOIN community_service a ON p.activity_id = a.id
    WHERE p.student_id = ?
");
$my_parts->execute([$student_id]);
$my_activities = $my_parts->fetchAll();
$registered_act_ids = array_column($my_activities, 'activity_id');

$page_title = 'Community Service Activities';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Community Service Hub</h3>
        <p class="text-muted small mb-0">Module 5: Participate in social impact drives & earn volunteer credits</p>
      </div>
    </div>

    <div class="bento-card mb-4">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-person-check text-primary"></i> My Volunteering Record</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Initiative</th>
              <th>Date</th>
              <th>Location</th>
              <th>Status</th>
              <th>Hours Earned</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($my_activities)): ?>
              <tr><td colspan="5" class="text-center py-3 text-muted">You have not registered for any community service activities yet.</td></tr>
            <?php else: ?>
              <?php foreach ($my_activities as $ma): ?>
                <tr>
                  <td class="fw-bold text-dark"><?= e($ma['title']) ?></td>
                  <td><?= date('M d, Y', strtotime($ma['event_date'])) ?></td>
                  <td><?= e($ma['location']) ?></td>
                  <td>
                    <span class="status-pill <?= e($ma['status']) ?>">
                      <?= ucfirst(e($ma['status'])) ?>
                    </span>
                  </td>
                  <td><strong class="text-success"><?= $ma['hours_awarded'] ?> hrs</strong> / <?= $ma['hours_credited'] ?> max</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <h5 class="fw-bold mb-3" style="color: var(--text-slate);">Available Campus Initiatives</h5>
    <div class="row g-4">
      <?php foreach ($activities as $act): 
        $is_reg = in_array($act['id'], $registered_act_ids);
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="bento-card h-100 d-flex flex-column justify-content-between">
            <div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-light text-primary border"><i class="bi bi-clock me-1"></i><?= $act['hours_credited'] ?> Credits</span>
                <span class="status-pill <?= e($act['status']) ?>"><?= ucfirst(e($act['status'])) ?></span>
              </div>
              <h5 class="fw-bold text-dark mb-1"><?= e($act['title']) ?></h5>
              <small class="text-muted d-block mb-2"><i class="bi bi-geo-alt text-danger me-1"></i><?= e($act['location']) ?> &bull; <?= date('M d, Y', strtotime($act['event_date'])) ?></small>
              <p class="text-secondary small mb-3"><?= e($act['description']) ?></p>
            </div>

            <div class="pt-3 border-top d-flex justify-content-between align-items-center">
              <small class="text-muted"><i class="bi bi-people me-1"></i>Max <?= $act['max_participants'] ?> volunteers</small>
              <?php if ($is_reg): ?>
                <span class="badge bg-success bg-opacity-25 text-success border border-success py-2 px-3">
                  <i class="bi bi-check2"></i> Registered
                </span>
              <?php elseif ($act['status'] === 'upcoming'): ?>
                <form method="POST">
                  <input type="hidden" name="activity_id" value="<?= $act['id'] ?>">
                  <button type="submit" name="register_activity" class="bento-btn bento-btn-primary py-1 px-3 small">
                    <i class="bi bi-plus-lg"></i> Join Drive
                  </button>
                </form>
              <?php else: ?>
                <span class="badge bg-light text-muted border">Closed</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
