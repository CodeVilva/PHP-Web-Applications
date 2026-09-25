<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;
$user_id = $_SESSION['user_id'];

$st = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$st->execute([$student_id]);
$student = $st->fetch();

$att_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance WHERE student_id = ?
");
$att_stmt->execute([$student_id]);
$att_stats = $att_stmt->fetch();
$total_classes = $att_stats['total_classes'] ?: 1;
$attended = $att_stats['present_count'] + ($att_stats['late_count'] * 0.5);
$attendance_pct = round(($attended / $total_classes) * 100);

$comm_stmt = $pdo->prepare("SELECT SUM(hours_awarded) as total_hours FROM community_service_participants WHERE student_id = ? AND status = 'verified'");
$comm_stmt->execute([$student_id]);
$comm_hours = (int)($comm_stmt->fetch()['total_hours'] ?? 0);

$ev_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE student_id = ? AND status = 'confirmed'");
$ev_stmt->execute([$student_id]);
$registered_events_count = (int)$ev_stmt->fetchColumn();

$today_day = date('l');
$tt_stmt = $pdo->prepare("
    SELECT t.*, f.full_name as faculty_name 
    FROM timetable t 
    LEFT JOIN faculty f ON t.faculty_id = f.id 
    WHERE t.day_of_week = ? 
    ORDER BY t.start_time ASC
");
$tt_stmt->execute([$today_day]);
$today_classes = $tt_stmt->fetchAll();

$notif_stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE (user_id = ? OR role_target = 'student' OR role_target = 'all') 
    ORDER BY created_at DESC LIMIT 4
");
$notif_stmt->execute([$user_id]);
$recent_notifs = $notif_stmt->fetchAll();

$page_title = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    
    <div class="bento-card bento-card-primary mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-white text-primary fw-bold text-uppercase px-3 py-1">Student Workspace</span>
            <span class="text-white-50 small"><?= date('l, F j, Y') ?></span>
          </div>
          <h2 class="text-white fw-bold mb-1">Welcome back, <?= e($student['full_name'] ?? $_SESSION['username']) ?>!</h2>
          <p class="text-white-50 mb-0">
            Roll No: <strong><?= e($student['roll_no'] ?? 'N/A') ?></strong> &bull; <?= e($student['department'] ?? 'CS') ?> &bull; <?= e($student['semester'] ?? 'Sem 5') ?>
          </p>
        </div>
        <div class="d-flex gap-2">
          <a href="attendance.php" class="bento-btn btn-light bg-white text-primary"><i class="bi bi-clock-history"></i> Full Attendance</a>
          <a href="complaints.php" class="bento-btn bento-btn-action"><i class="bi bi-plus-circle"></i> New Complaint</a>
        </div>
      </div>
    </div>

    <div class="bento-grid mb-4">
      
      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon <?= $attendance_pct >= 75 ? 'success' : 'danger' ?>">
              <i class="bi bi-pie-chart-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $attendance_pct ?>%</div>
              <div class="metric-label">Overall Attendance</div>
            </div>
          </div>
          <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar <?= $attendance_pct >= 75 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $attendance_pct ?>%;"></div>
          </div>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon">
              <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= number_format($student['gpa'] ?? 3.85, 2) ?></div>
              <div class="metric-label">Cumulative GPA</div>
            </div>
          </div>
          <div class="text-muted small mt-2">Scale 4.00 &bull; Top 5% standing</div>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon warning">
              <i class="bi bi-heart-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $comm_hours ?> hrs</div>
              <div class="metric-label">Volunteer Credits</div>
            </div>
          </div>
          <div class="text-muted small mt-2">Verified Community Hours</div>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon action">
              <i class="bi bi-calendar-event-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $registered_events_count ?></div>
              <div class="metric-label">Events Registered</div>
            </div>
          </div>
          <div class="text-muted small mt-2">Active campus activities</div>
        </div>
      </div>

    </div>

    <div class="bento-grid">
      
      <div class="col-span-7">
        <div class="bento-card h-100">
          <div class="bento-header">
            <div>
              <h5 class="bento-title"><i class="bi bi-calendar-week text-primary"></i> Today's Schedule (<?= $today_day ?>)</h5>
              <div class="bento-subtitle">Upcoming lectures and labs for your batch</div>
            </div>
            <a href="timetable.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Weekly View &rarr;</a>
          </div>

          <?php if (!empty($today_classes)): ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($today_classes as $cls): ?>
                <div class="timetable-slot d-flex align-items-center justify-content-between p-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="bg-light rounded p-2 text-center" style="min-width: 90px;">
                      <strong class="d-block text-primary" style="font-size: 0.85rem;"><?= date('h:i A', strtotime($cls['start_time'])) ?></strong>
                      <small class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($cls['end_time'])) ?></small>
                    </div>
                    <div>
                      <div class="fw-bold text-dark"><?= e($cls['subject_name']) ?> <span class="badge bg-light text-secondary border ms-1"><?= e($cls['subject_code']) ?></span></div>
                      <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($cls['faculty_name'] ?? 'Faculty') ?> &bull; <i class="bi bi-geo-alt me-1"></i><?= e($cls['room_no']) ?></small>
                    </div>
                  </div>
                  <span class="status-pill info">Scheduled</span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar-check fs-1 text-muted d-block mb-2"></i>
              No classes scheduled for today. Check your full weekly timetable!
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-span-5">
        <div class="bento-card h-100">
          <div class="bento-header">
            <div>
              <h5 class="bento-title"><i class="bi bi-bell-fill text-primary"></i> Campus Announcements</h5>
              <div class="bento-subtitle">Latest updates from Administration & Faculty</div>
            </div>
            <a href="notifications.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
          </div>

          <div class="d-flex flex-column gap-3">
            <?php foreach ($recent_notifs as $n): ?>
              <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <strong class="text-dark small"><i class="bi bi-info-circle text-primary me-1"></i> <?= e($n['title']) ?></strong>
                  <span class="text-muted" style="font-size: 11px;"><?= time_elapsed_string($n['created_at']) ?></span>
                </div>
                <p class="text-secondary small mb-0"><?= e($n['message']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-4 pt-3 border-top">
            <h6 class="fw-bold text-muted text-uppercase mb-2" style="font-size: 11px;">Quick Access Hub</h6>
            <div class="row g-2">
              <div class="col-6">
                <a href="materials.php" class="bento-btn bento-btn-outline w-100 text-start py-2">
                  <i class="bi bi-journal-bookmark text-primary"></i> Course Notes
                </a>
              </div>
              <div class="col-6">
                <a href="scholarships.php" class="bento-btn bento-btn-outline w-100 text-start py-2">
                  <i class="bi bi-award text-success"></i> Scholarships
                </a>
              </div>
              <div class="col-6">
                <a href="community.php" class="bento-btn bento-btn-outline w-100 text-start py-2">
                  <i class="bi bi-heart text-danger"></i> Community Work
                </a>
              </div>
              <div class="col-6">
                <a href="faculty.php" class="bento-btn bento-btn-outline w-100 text-start py-2">
                  <i class="bi bi-people text-info"></i> Faculty Info
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
