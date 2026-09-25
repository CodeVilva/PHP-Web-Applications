<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;

$subj_stmt = $pdo->prepare("
    SELECT 
        subject_code,
        subject_name,
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_sessions,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_sessions,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_sessions
    FROM attendance 
    WHERE student_id = ?
    GROUP BY subject_code, subject_name
");
$subj_stmt->execute([$student_id]);
$subject_stats = $subj_stmt->fetchAll();

$log_stmt = $pdo->prepare("
    SELECT a.*, f.full_name as faculty_name
    FROM attendance a
    LEFT JOIN faculty f ON a.faculty_id = f.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC, a.id DESC
");
$log_stmt->execute([$student_id]);
$attendance_logs = $log_stmt->fetchAll();

$page_title = 'My Attendance Records';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Attendance Analytics</h3>
        <p class="text-muted small mb-0">Module 1: Real-time subject-wise & daily attendance tracking</p>
      </div>
      <button class="bento-btn bento-btn-outline" onclick="window.print()"><i class="bi bi-printer"></i> Print Summary</button>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ($subject_stats as $sb): 
        $tot = $sb['total_sessions'] ?: 1;
        $eff = $sb['present_sessions'] + ($sb['late_sessions'] * 0.5);
        $pct = round(($eff / $tot) * 100);
      ?>
      <div class="col-md-4">
        <div class="bento-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <span class="badge bg-light text-primary border fw-bold"><?= e($sb['subject_code']) ?></span>
              <h6 class="fw-bold mt-2 mb-1 text-dark"><?= e($sb['subject_name']) ?></h6>
            </div>
            <span class="status-pill <?= $pct >= 75 ? 'success' : 'danger' ?>"><?= $pct ?>%</span>
          </div>
          
          <div class="progress my-2" style="height: 6px;">
            <div class="progress-bar <?= $pct >= 75 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $pct ?>%;"></div>
          </div>

          <div class="d-flex justify-content-between small text-muted mt-2">
            <span><strong class="text-success"><?= $sb['present_sessions'] ?></strong> Present</span>
            <span><strong class="text-warning"><?= $sb['late_sessions'] ?></strong> Late</span>
            <span><strong class="text-danger"><?= $sb['absent_sessions'] ?></strong> Absent</span>
            <span><strong><?= $sb['total_sessions'] ?></strong> Total</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-list-check text-primary"></i> Daily Session Log</h5>
        <span class="text-muted small">Total Records: <?= count($attendance_logs) ?></span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Subject</th>
              <th>Faculty</th>
              <th>Status</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($attendance_logs)): ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No attendance records found.</td></tr>
            <?php else: ?>
              <?php foreach ($attendance_logs as $log): ?>
                <tr>
                  <td><strong><?= date('M d, Y', strtotime($log['date'])) ?></strong></td>
                  <td>
                    <div class="fw-bold"><?= e($log['subject_name']) ?></div>
                    <small class="text-muted"><?= e($log['subject_code']) ?></small>
                  </td>
                  <td><?= e($log['faculty_name'] ?? 'Faculty') ?></td>
                  <td>
                    <span class="status-pill <?= e($log['status']) ?>">
                      <?= ucfirst(e($log['status'])) ?>
                    </span>
                  </td>
                  <td class="text-muted small"><?= e($log['remarks'] ?: '—') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
