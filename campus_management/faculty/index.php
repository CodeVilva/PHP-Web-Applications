<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$faculty_id = $_SESSION['faculty_id'] ?? null;
$user_id = $_SESSION['user_id'];

$f_stmt = $pdo->prepare("SELECT * FROM faculty WHERE id = ?");
$f_stmt->execute([$faculty_id]);
$faculty = $f_stmt->fetch();

$courses_count = $pdo->prepare("SELECT COUNT(DISTINCT subject_code) FROM timetable WHERE faculty_id = ?");
$courses_count->execute([$faculty_id]);
$total_courses = (int)$courses_count->fetchColumn();

$today_day = date('l');
$today_lectures_stmt = $pdo->prepare("SELECT * FROM timetable WHERE faculty_id = ? AND day_of_week = ? ORDER BY start_time ASC");
$today_lectures_stmt->execute([$faculty_id, $today_day]);
$today_lectures = $today_lectures_stmt->fetchAll();

$materials_stmt = $pdo->prepare("SELECT COUNT(*) FROM learning_materials WHERE faculty_id = ?");
$materials_stmt->execute([$faculty_id]);
$materials_count = (int)$materials_stmt->fetchColumn();

$pending_sch_stmt = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE faculty_verified = 'pending'");
$pending_sch_count = (int)$pending_sch_stmt->fetchColumn();

$open_comp_stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'open' OR faculty_response IS NULL");
$open_comp_count = (int)$open_comp_stmt->fetchColumn();

$rec_complaints = $pdo->query("
    SELECT c.*, s.full_name as student_name, s.roll_no 
    FROM complaints c 
    JOIN students s ON c.student_id = s.id 
    ORDER BY c.created_at DESC LIMIT 3
")->fetchAll();

$page_title = 'Faculty Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    
    <div class="bento-card bento-card-primary mb-4" style="background: linear-gradient(135deg, #0077B5 0%, #004b8d 100%);">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-white text-dark fw-bold text-uppercase px-3 py-1">Faculty Workspace</span>
            <span class="text-white-50 small"><?= date('l, F j, Y') ?></span>
          </div>
          <h2 class="text-white fw-bold mb-1">Welcome, <?= e($faculty['full_name'] ?? $_SESSION['username']) ?></h2>
          <p class="text-white-50 mb-0">
            <?= e($faculty['designation'] ?? 'Professor') ?> &bull; <?= e($faculty['department'] ?? 'CS') ?> &bull; Office: <?= e($faculty['office_room'] ?? 'Main Block') ?>
          </p>
        </div>
        <div class="d-flex gap-2">
          <a href="attendance.php" class="bento-btn btn-light bg-white text-primary"><i class="bi bi-calendar-check-fill"></i> Mark Attendance</a>
          <a href="materials.php" class="bento-btn bento-btn-action"><i class="bi bi-upload"></i> Upload Notes</a>
        </div>
      </div>
    </div>

    <div class="bento-grid mb-4">
      
      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon">
              <i class="bi bi-book-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $total_courses ?></div>
              <div class="metric-label">Assigned Subjects</div>
            </div>
          </div>
          <small class="text-muted mt-2 d-block">Teaching curriculum</small>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon success">
              <i class="bi bi-clock-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= count($today_lectures) ?></div>
              <div class="metric-label">Today's Lectures</div>
            </div>
          </div>
          <small class="text-muted mt-2 d-block">Scheduled for <?= $today_day ?></small>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon warning">
              <i class="bi bi-clipboard-check-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $pending_sch_count ?></div>
              <div class="metric-label">Scholarship Reviews</div>
            </div>
          </div>
          <small class="text-muted mt-2 d-block">Pending faculty verification</small>
        </div>
      </div>

      <div class="col-span-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon action">
              <i class="bi bi-journal-arrow-up"></i>
            </div>
            <div>
              <div class="metric-value"><?= $materials_count ?></div>
              <div class="metric-label">Materials Uploaded</div>
            </div>
          </div>
          <small class="text-muted mt-2 d-block">Lecture notes & slides</small>
        </div>
      </div>

    </div>

    <div class="bento-grid">
      
      <div class="col-span-7">
        <div class="bento-card h-100">
          <div class="bento-header">
            <div>
              <h5 class="bento-title"><i class="bi bi-calendar-event text-primary"></i> Teaching Schedule (<?= $today_day ?>)</h5>
              <div class="bento-subtitle">Today's classroom sessions and labs</div>
            </div>
            <a href="timetable.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Weekly Schedule &rarr;</a>
          </div>

          <?php if (empty($today_lectures)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-calendar2-check fs-1 text-muted d-block mb-2"></i>
              No lectures scheduled for today.
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($today_lectures as $lec): ?>
                <div class="timetable-slot d-flex align-items-center justify-content-between p-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="bg-light rounded p-2 text-center" style="min-width: 90px;">
                      <strong class="d-block text-primary" style="font-size: 0.85rem;"><?= date('h:i A', strtotime($lec['start_time'])) ?></strong>
                      <small class="text-muted" style="font-size: 0.75rem;"><?= date('h:i A', strtotime($lec['end_time'])) ?></small>
                    </div>
                    <div>
                      <div class="fw-bold text-dark"><?= e($lec['subject_name']) ?> <span class="badge bg-light text-secondary border ms-1"><?= e($lec['subject_code']) ?></span></div>
                      <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($lec['room_no']) ?> &bull; <?= e($lec['semester']) ?></small>
                    </div>
                  </div>
                  <a href="attendance.php?subject_code=<?= urlencode($lec['subject_code']) ?>" class="bento-btn bento-btn-primary py-1 px-3 small">
                    <i class="bi bi-check-lg"></i> Mark Class
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-span-5">
        <div class="bento-card h-100">
          <div class="bento-header">
            <div>
              <h5 class="bento-title"><i class="bi bi-chat-dots-fill text-warning"></i> Student Grievances</h5>
              <div class="bento-subtitle">Departmental feedback requiring response</div>
            </div>
            <a href="complaints.php" class="btn btn-sm btn-link text-decoration-none">Manage All</a>
          </div>

          <div class="d-flex flex-column gap-3">
            <?php foreach ($rec_complaints as $c): ?>
              <div class="p-3 rounded-3 border bg-light bg-opacity-50">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <strong class="text-dark small"><?= e($c['subject']) ?></strong>
                  <span class="status-pill <?= e($c['status']) ?>"><?= ucfirst(e($c['status'])) ?></span>
                </div>
                <small class="text-muted d-block mb-2">By: <?= e($c['student_name']) ?> (<?= e($c['roll_no']) ?>) &bull; <?= e($c['category']) ?></small>
                <div class="d-flex justify-content-between align-items-center">
                  <small class="text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></small>
                  <a href="complaints.php" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">Respond &rarr;</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div>

    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
