<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

$total_students = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_faculty  = (int)$pdo->query("SELECT COUNT(*) FROM faculty")->fetchColumn();
$pending_complaints = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'open' OR status = 'in_progress'")->fetchColumn();
$pending_scholarships = (int)$pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE admin_status = 'pending'")->fetchColumn();
$upcoming_events = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE start_datetime >= NOW()")->fetchColumn();
$active_initiatives = (int)$pdo->query("SELECT COUNT(*) FROM community_service WHERE status = 'upcoming'")->fetchColumn();

// Recent Complaints
$recent_complaints = $pdo->query("
    SELECT c.*, s.full_name as student_name, s.roll_no, u.email as student_email
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY c.created_at DESC LIMIT 5
")->fetchAll();

// Recent Scholarship Applications
$recent_scholarships = $pdo->query("
    SELECT sa.*, s.title as scholarship_title, s.amount, st.full_name as student_name, st.roll_no
    FROM scholarship_applications sa
    JOIN scholarships s ON sa.scholarship_id = s.id
    JOIN students st ON sa.student_id = st.id
    ORDER BY sa.applied_at DESC LIMIT 5
")->fetchAll();

// Recent Users
$recent_users = $pdo->query("
    SELECT * FROM users ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$page_title = "Admin Central Command";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Campus Executive Dashboard</h2>
        <p class="text-muted mb-0">System-wide real-time operations, governance, analytics, and service requests.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i> Reports</a>
        <a href="<?= BASE_URL ?>/admin/notifications.php" class="btn btn-primary"><i class="bi bi-broadcast me-1"></i> Broadcast</a>
      </div>
    </div>

    <!-- Bento Top Metrics Grid -->
    <div class="bento-grid mb-4">
      <div class="bento-card col-span-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Enrolled Students</span>
            <h2 class="fw-bold mt-2 mb-0" style="color: var(--brand-primary);"><?= $total_students ?></h2>
          </div>
          <div class="bento-icon" style="background: rgba(10,102,194,0.1); color: var(--brand-primary);">
            <i class="bi bi-mortarboard-fill"></i>
          </div>
        </div>
        <div class="mt-3 small text-muted">
          <a href="<?= BASE_URL ?>/admin/users.php?role=student" class="text-decoration-none fw-semibold">Manage Students &rarr;</a>
        </div>
      </div>

      <div class="bento-card col-span-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Teaching Faculty</span>
            <h2 class="fw-bold mt-2 mb-0" style="color: var(--brand-legacy);"><?= $total_faculty ?></h2>
          </div>
          <div class="bento-icon" style="background: rgba(0,119,181,0.1); color: var(--brand-legacy);">
            <i class="bi bi-person-video3"></i>
          </div>
        </div>
        <div class="mt-3 small text-muted">
          <a href="<?= BASE_URL ?>/admin/faculty.php" class="text-decoration-none fw-semibold">Faculty Directory &rarr;</a>
        </div>
      </div>

      <div class="bento-card col-span-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Active Grievances</span>
            <h2 class="fw-bold mt-2 mb-0 <?= $pending_complaints > 0 ? 'text-warning' : 'text-success' ?>"><?= $pending_complaints ?></h2>
          </div>
          <div class="bento-icon" style="background: rgba(245,158,11,0.1); color: #d97706;">
            <i class="bi bi-exclamation-triangle-fill"></i>
          </div>
        </div>
        <div class="mt-3 small text-muted">
          <a href="<?= BASE_URL ?>/admin/complaints.php" class="text-decoration-none fw-semibold">Resolution Desk &rarr;</a>
        </div>
      </div>

      <div class="bento-card col-span-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold text-uppercase">Pending Grants</span>
            <h2 class="fw-bold mt-2 mb-0 text-info"><?= $pending_scholarships ?></h2>
          </div>
          <div class="bento-icon" style="background: rgba(0,160,220,0.1); color: var(--brand-action);">
            <i class="bi bi-award-fill"></i>
          </div>
        </div>
        <div class="mt-3 small text-muted">
          <a href="<?= BASE_URL ?>/admin/scholarships.php" class="text-decoration-none fw-semibold">Review Applications &rarr;</a>
        </div>
      </div>
    </div>

    <!-- Main Bento Grid -->
    <div class="bento-grid mb-4">
      <div class="bento-card col-span-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0" style="color: var(--text-slate);">
            <i class="bi bi-inbox-fill text-primary me-2"></i>Institutional Grievance Queue
          </h5>
          <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        <?php if (empty($recent_complaints)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-check-circle text-success fs-2"></i>
            <p class="mt-2 mb-0">No complaints currently in queue.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Ticket</th>
                  <th>Student</th>
                  <th>Subject</th>
                  <th>Category</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_complaints as $c): ?>
                  <tr>
                    <td><code>#CMP-<?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></code></td>
                    <td>
                      <span class="small fw-semibold"><?= e($c['student_name']) ?></span>
                      <small class="text-muted d-block"><?= e($c['roll_no']) ?></small>
                    </td>
                    <td><span class="fw-semibold text-truncate d-block" style="max-width: 180px;"><?= e($c['subject']) ?></span></td>
                    <td><span class="badge bg-light text-dark border"><?= e($c['category']) ?></span></td>
                    <td><span class="badge <?= $c['priority'] === 'high' ? 'bg-danger' : 'bg-secondary' ?>"><?= ucfirst($c['priority']) ?></span></td>
                    <td><span class="badge bg-info text-dark"><?= ucfirst($c['status']) ?></span></td>
                    <td>
                      <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="bento-card col-span-4">
        <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
          <i class="bi bi-grid-fill text-primary me-2"></i>Quick Administration
        </h5>
        <div class="d-grid gap-2">
          <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-light text-start border p-3 d-flex align-items-center">
            <i class="bi bi-person-plus-fill fs-4 text-primary me-3"></i>
            <div>
              <div class="fw-bold">Create User Account</div>
              <small class="text-muted">Register student or faculty</small>
            </div>
          </a>
          <a href="<?= BASE_URL ?>/admin/timetable.php" class="btn btn-light text-start border p-3 d-flex align-items-center">
            <i class="bi bi-calendar-plus-fill fs-4 text-success me-3"></i>
            <div>
              <div class="fw-bold">Schedule Class Slot</div>
              <small class="text-muted">Allocate room and time block</small>
            </div>
          </a>
          <a href="<?= BASE_URL ?>/admin/events.php" class="btn btn-light text-start border p-3 d-flex align-items-center">
            <i class="bi bi-calendar2-event-fill fs-4 text-info me-3"></i>
            <div>
              <div class="fw-bold">Publish Campus Event</div>
              <small class="text-muted">Seminars, workshops, festivals</small>
            </div>
          </a>
          <a href="<?= BASE_URL ?>/admin/community.php" class="btn btn-light text-start border p-3 d-flex align-items-center">
            <i class="bi bi-heart-pulse-fill fs-4 text-danger me-3"></i>
            <div>
              <div class="fw-bold">New Community Project</div>
              <small class="text-muted">Service & volunteer initiatives</small>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
