<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id && isset($_SESSION['user_id'])) {
    $st_find = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $st_find->execute([$_SESSION['user_id']]);
    $student_id = $st_find->fetchColumn();
    $_SESSION['student_id'] = $student_id;
}

$st_data = null;
if ($student_id) {
    $student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $student->execute([$student_id]);
    $st_data = $student->fetch();
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today_day = date('l');
$current_time = date('H:i:s');

$st_dept = trim($st_data['department'] ?? '');
$st_sem  = trim($st_data['semester'] ?? '');

$selected_dept = $_GET['dept'] ?? ($st_dept ?: 'all');
$selected_sem  = $_GET['sem'] ?? 'all';

$params = [];
$where_clauses = [];

if ($selected_dept !== 'all' && !empty($selected_dept)) {
    $keywords = ['Computer', 'Civil', 'Mechanical', 'Electrical', 'Electronics', 'Information Technology'];
    $matched_kw = '';
    foreach ($keywords as $kw) {
        if (stripos($selected_dept, $kw) !== false) {
            $matched_kw = $kw;
            break;
        }
    }
    
    if ($matched_kw) {
        $where_clauses[] = "(t.department = ? OR t.department LIKE ?)";
        $params[] = $selected_dept;
        $params[] = "%$matched_kw%";
    } else {
        $where_clauses[] = "(t.department = ? OR t.department LIKE ?)";
        $params[] = $selected_dept;
        $params[] = "%$selected_dept%";
    }
}

if ($selected_sem !== 'all' && !empty($selected_sem)) {
    $where_clauses[] = "(t.semester = ? OR t.semester LIKE ?)";
    $params[] = $selected_sem;
    $params[] = "%$selected_sem%";
}

$sql = "
    SELECT t.*, f.full_name as faculty_name, f.employee_id
    FROM timetable t 
    LEFT JOIN faculty f ON t.faculty_id = f.id 
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time ASC";

$tt_stmt = $pdo->prepare($sql);
$tt_stmt->execute($params);
$slots = $tt_stmt->fetchAll();

// If specific filter returned nothing and no manual filter was passed, fall back to showing all campus slots
$showing_fallback = false;
if (empty($slots) && empty($_GET['dept']) && empty($_GET['sem'])) {
    $slots = $pdo->query("
        SELECT t.*, f.full_name as faculty_name, f.employee_id
        FROM timetable t 
        LEFT JOIN faculty f ON t.faculty_id = f.id 
        ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time ASC
    ")->fetchAll();
    if (!empty($slots)) {
        $showing_fallback = true;
    }
}

$available_depts = $pdo->query("SELECT DISTINCT department FROM timetable WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_COLUMN);

$schedule_by_day = [];
foreach ($days as $day) {
    $schedule_by_day[$day] = [];
}
foreach ($slots as $s) {
    if (isset($schedule_by_day[$s['day_of_week']])) {
        $schedule_by_day[$s['day_of_week']][] = $s;
    }
}

// Find upcoming class for today
$today_classes = $schedule_by_day[$today_day] ?? [];
$next_class = null;
foreach ($today_classes as $tc) {
    if ($tc['start_time'] >= $current_time) {
        $next_class = $tc;
        break;
    }
}

$page_title = 'Class Timetable';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Academic Timetable</h3>
        <p class="text-muted small mb-0">
          Module 2: Weekly schedule for 
          <strong class="text-dark"><?= e($st_dept ?: 'General') ?></strong> 
          &bull; 
          <span class="badge bg-light text-primary border"><?= e($st_sem ?: 'Semester 1') ?></span>
        </p>
      </div>
      <div class="d-flex gap-2">
        <button class="bento-btn bento-btn-primary" onclick="window.print()">
          <i class="bi bi-printer me-1"></i> Print Timetable
        </button>
      </div>
    </div>

    <?php if ($showing_fallback): ?>
      <div class="alert alert-info border-info py-2 px-3 mb-4 small">
        <i class="bi bi-info-circle-fill me-1"></i> Displaying all campus master timetable slots.
      </div>
    <?php endif; ?>

    <!-- Schedule by Day Grid -->
    <div class="row g-4">
      <?php foreach ($days as $day): ?>
        <div class="col-lg-4 col-md-6">
          <div class="bento-card h-100 <?= $day === $today_day ? 'border-primary shadow-sm' : '' ?>">
            <div class="bento-header pb-2 d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0 <?= $day === $today_day ? 'text-primary' : 'text-dark' ?>">
                <i class="bi bi-calendar-event me-1"></i> <?= $day ?>
                <?php if ($day === $today_day): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary ms-1" style="font-size: 10px;">Today</span>
                <?php endif; ?>
              </h6>
              <span class="badge bg-light text-secondary border"><?= count($schedule_by_day[$day]) ?> Classes</span>
            </div>

            <?php if (empty($schedule_by_day[$day])): ?>
              <div class="text-center py-4 text-muted small">
                <i class="bi bi-cup-hot fs-3 text-muted d-block mb-1"></i>
                No classes scheduled
              </div>
            <?php else: ?>
              <div class="d-flex flex-column gap-2 mt-2">
                <?php foreach ($schedule_by_day[$day] as $slot): ?>
                  <div class="p-2 rounded-3 border bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                      <strong class="text-primary" style="font-size: 0.8rem;">
                        <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                      </strong>
                      <span class="badge bg-white text-dark border small" style="font-size: 10px;"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($slot['room_no']) ?></span>
                    </div>
                    <div class="fw-bold text-dark small"><?= e($slot['subject_code']) ?> - <?= e($slot['subject_name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                      <small class="text-muted" style="font-size: 11px;"><i class="bi bi-person me-1"></i><?= e($slot['faculty_name'] ?? 'Instructor') ?></small>
                      <small class="text-secondary" style="font-size: 10px;"><?= e($slot['department']) ?></small>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
