<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Attendance stats
$attendance_stats = $pdo->query("
    SELECT subject_code, subject_name, 
           COUNT(id) as total_records,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM attendance
    GROUP BY subject_code, subject_name
    ORDER BY subject_code ASC
")->fetchAll();

// Scholarship stats
$scholarship_stats = $pdo->query("
    SELECT s.title, s.provider, s.amount,
           COUNT(sa.id) as total_applicants,
           SUM(CASE WHEN sa.admin_status = 'approved' THEN 1 ELSE 0 END) as awarded_count,
           SUM(CASE WHEN sa.admin_status = 'approved' THEN s.amount ELSE 0 END) as total_disbursed
    FROM scholarships s
    LEFT JOIN scholarship_applications sa ON s.id = sa.scholarship_id
    GROUP BY s.id
")->fetchAll();

$page_title = "Campus Analytics & Reports";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Campus Analytics & Audit Reports</h2>
        <p class="text-muted mb-0">Aggregate attendance benchmarks and endowment disbursement statistics.</p>
      </div>
      <button class="btn btn-outline-primary" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i> Print / Export Report
      </button>
    </div>

    <!-- Attendance Summary -->
    <div class="bento-card mb-4">
      <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
        <i class="bi bi-calendar-check-fill text-primary me-2"></i>Subject-wise Attendance Audit
      </h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Subject</th>
              <th>Total Logs</th>
              <th>Present</th>
              <th>Absent</th>
              <th>Late</th>
              <th>Attendance %</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($attendance_stats)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No attendance data logged yet.</td></tr>
            <?php else: ?>
              <?php foreach ($attendance_stats as $as): ?>
                <?php 
                  $pct = $as['total_records'] > 0 ? round(($as['present_count'] / $as['total_records']) * 100, 1) : 0;
                ?>
                <tr>
                  <td><strong><?= e($as['subject_code']) ?></strong> - <?= e($as['subject_name']) ?></td>
                  <td><?= $as['total_records'] ?></td>
                  <td><span class="badge bg-success-subtle text-success"><?= $as['present_count'] ?></span></td>
                  <td><span class="badge bg-danger-subtle text-danger"><?= $as['absent_count'] ?></span></td>
                  <td><span class="badge bg-warning-subtle text-warning text-dark"><?= $as['late_count'] ?></span></td>
                  <td><strong><?= $pct ?>%</strong></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Scholarship Summary -->
    <div class="bento-card">
      <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
        <i class="bi bi-cash-stack text-success me-2"></i>Scholarship Fund Disbursement Audit
      </h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Scheme Title</th>
              <th>Provider</th>
              <th>Award (₹)</th>
              <th>Applicants</th>
              <th>Awards Granted</th>
              <th>Total Disbursed</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($scholarship_stats)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No scholarship schemes available yet.</td></tr>
            <?php else: ?>
              <?php $grand_total = 0; ?>
              <?php foreach ($scholarship_stats as $ss): ?>
                <?php $grand_total += $ss['total_disbursed']; ?>
                <tr>
                  <td><strong><?= e($ss['title']) ?></strong></td>
                  <td><span class="badge bg-light text-dark border"><?= e($ss['provider']) ?></span></td>
                  <td>₹<?= number_format($ss['amount'], 2) ?></td>
                  <td><?= $ss['total_applicants'] ?></td>
                  <td><span class="badge bg-success"><?= $ss['awarded_count'] ?> Granted</span></td>
                  <td><strong class="text-success">₹<?= number_format($ss['total_disbursed'], 2) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <tr class="table-light fw-bold">
                <td colspan="5" class="text-end">Total Aid Disbursed:</td>
                <td class="text-success h5 fw-bold mb-0">₹<?= number_format($grand_total, 2) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
