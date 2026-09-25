<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$faculty_id = $_SESSION['faculty_id'] ?? null;
$today_day = date('l');
$current_time = date('H:i:s');

// Fetch complete weekly schedule
$stmt = $pdo->prepare("SELECT * FROM timetable WHERE faculty_id = ? ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), start_time ASC");
$stmt->execute([$faculty_id]);
$all_slots = $stmt->fetchAll();

// Find today's slots
$today_slots = array_filter($all_slots, function($s) use ($today_day) {
    return $s['day_of_week'] === $today_day;
});

// Check missed classes today (slots that started earlier today but attendance not marked)
$missed_slots = [];
foreach ($today_slots as $slot) {
    if ($current_time > $slot['start_time']) {
        $chk_att = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE faculty_id = ? AND subject_code = ? AND date = ?");
        $chk_att->execute([$faculty_id, $slot['subject_code'], date('Y-m-d')]);
        $has_marked = (int)$chk_att->fetchColumn();
        if ($has_marked === 0) {
            $missed_slots[] = $slot;
        }
    }
}

// Find upcoming class today
$upcoming_slot = null;
foreach ($today_slots as $slot) {
    if ($slot['start_time'] >= $current_time) {
        $upcoming_slot = $slot;
        break;
    }
}

$page_title = 'Faculty Schedule & Class Reminders';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Teaching Timetable & Reminders</h3>
        <p class="text-muted small mb-0">Module 2: Real-time lecture schedules, upcoming session alerts, and unconducted class notifications</p>
      </div>
    </div>

    <!-- MISSED CLASS / ACTION REQUIRED ALERT -->
    <?php if (!empty($missed_slots)): ?>
      <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 shadow-sm">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill fs-3 text-warning"></i>
          <div>
            <strong class="d-block text-dark">Action Required: Pending Class Attendance!</strong>
            <span class="small text-muted">
              You had <?= count($missed_slots) ?> lecture(s) scheduled earlier today where attendance has not been recorded yet.
            </span>
          </div>
        </div>
        <a href="attendance.php?tab=students" class="btn btn-sm btn-warning text-dark fw-bold">
          <i class="bi bi-pencil-square me-1"></i> Log Pending Attendance Now
        </a>
      </div>
    <?php endif; ?>

    <!-- Bento Top Alert & Upcoming Class Grid -->
    <div class="bento-grid mb-4">
      <!-- Upcoming Class Widget -->
      <div class="bento-card col-span-7">
        <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
          <i class="bi bi-bell-fill text-primary me-2"></i>Upcoming Class Reminder
        </h5>
        <?php if ($upcoming_slot): ?>
          <div class="p-3 bg-primary-subtle rounded-3 border border-primary d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <span class="badge bg-primary text-white mb-1"><?= e($upcoming_slot['subject_code']) ?></span>
              <h5 class="fw-bold mb-0 text-dark"><?= e($upcoming_slot['subject_name']) ?></h5>
              <div class="small text-muted mt-1">
                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Hall: <strong><?= e($upcoming_slot['room_no']) ?></strong> &bull; <?= e($upcoming_slot['department']) ?> (<?= e($upcoming_slot['semester']) ?>)
              </div>
            </div>
            <div class="text-end">
              <div class="h4 fw-bold text-primary mb-0"><?= date('h:i A', strtotime($upcoming_slot['start_time'])) ?></div>
              <small class="text-muted">Starts today</small>
            </div>
          </div>
        <?php else: ?>
          <div class="p-4 bg-light rounded-3 text-center text-muted">
            <i class="bi bi-check-circle text-success fs-3 d-block mb-1"></i>
            No more upcoming lectures scheduled for the rest of today (<?= $today_day ?>).
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Summary -->
      <div class="bento-card col-span-5">
        <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
          <i class="bi bi-calendar2-day text-success me-2"></i>Today's Teaching Load
        </h5>
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-2">
          <span>Lectures Scheduled Today:</span>
          <strong class="h5 mb-0 text-primary"><?= count($today_slots) ?></strong>
        </div>
        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
          <span>Total Weekly Class Slots:</span>
          <strong class="h5 mb-0 text-dark"><?= count($all_slots) ?></strong>
        </div>
      </div>
    </div>

    <!-- Master Weekly Grid -->
    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-calendar3 text-primary"></i> Complete Weekly Teaching Schedule</h5>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Day</th>
              <th>Time Slot</th>
              <th>Subject</th>
              <th>Department / Cohort</th>
              <th>Lecture Hall</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($all_slots)): ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No timetable slots assigned to your faculty profile yet.</td></tr>
            <?php else: ?>
              <?php foreach ($all_slots as $s): ?>
                <?php $is_today = ($s['day_of_week'] === $today_day); ?>
                <tr class="<?= $is_today ? 'table-primary-subtle' : '' ?>">
                  <td>
                    <span class="badge <?= $is_today ? 'bg-primary' : 'bg-light text-dark border' ?> fw-bold">
                      <?= $s['day_of_week'] ?>
                    </span>
                  </td>
                  <td><code><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></code></td>
                  <td>
                    <strong><?= e($s['subject_code']) ?></strong> &bull; <?= e($s['subject_name']) ?>
                  </td>
                  <td><span class="small text-muted"><?= e($s['department']) ?> (<?= e($s['semester']) ?>)</span></td>
                  <td><span class="badge bg-secondary-subtle text-dark border"><?= e($s['room_no']) ?></span></td>
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
