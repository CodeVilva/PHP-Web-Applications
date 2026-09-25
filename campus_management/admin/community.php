<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// 1. Create Initiative
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_initiative'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organizer   = trim($_POST['organizer'] ?? 'NSS & Community Welfare Cell');
    $hours       = (int)($_POST['hours_credited'] ?? 4);
    $location    = trim($_POST['location'] ?? 'Campus Quad');
    $event_date  = $_POST['event_date'] ?? date('Y-m-d');
    $max_p       = (int)($_POST['max_participants'] ?? 50);

    if ($title) {
        $stmt = $pdo->prepare("
            INSERT INTO community_service (title, description, organizer, location, event_date, hours_credited, max_participants, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'upcoming')
        ");
        $stmt->execute([$title, $description, $organizer, $location, $event_date, $hours, $max_p]);
        set_flash('success', "Community initiative '$title' launched successfully!");
        redirect('admin/community.php');
    }
}

// 2. Verify Student Hours & Issue Certificate
if (isset($_GET['verify_id'])) {
    $pid = (int)$_GET['verify_id'];
    $stmt = $pdo->prepare("
        UPDATE community_service_participants csp
        JOIN community_service cs ON csp.activity_id = cs.id
        SET csp.status = 'verified', csp.hours_awarded = cs.hours_credited
        WHERE csp.id = ?
    ");
    $stmt->execute([$pid]);
    set_flash('success', 'Student community service hours verified!');
    redirect('admin/community.php');
}

// Overall Community Statistics
$total_volunteers = (int)$pdo->query("SELECT COUNT(*) FROM community_service_participants")->fetchColumn();
$total_hours = (int)$pdo->query("SELECT COALESCE(SUM(hours_awarded), 0) FROM community_service_participants WHERE status = 'verified'")->fetchColumn();
$total_projects = (int)$pdo->query("SELECT COUNT(*) FROM community_service")->fetchColumn();
$verified_count = (int)$pdo->query("SELECT COUNT(*) FROM community_service_participants WHERE status = 'verified'")->fetchColumn();
$verification_rate = $total_volunteers > 0 ? round(($verified_count / $total_volunteers) * 100) : 100;

// Monthly Participation & Hours Frequency for Chart (Real Data for Past 6 Months)
$chart_months = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chart_months[$ym] = [
        'label' => date('M Y', strtotime("-$i months")),
        'volunteers' => 0,
        'hours' => 0
    ];
}

$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(registered_at, '%Y-%m') as ym,
        COUNT(id) as volunteer_count,
        COALESCE(SUM(hours_awarded), 0) as hours_count
    FROM community_service_participants
    WHERE registered_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym
");
while ($row = $stmt->fetch()) {
    if (isset($chart_months[$row['ym']])) {
        $chart_months[$row['ym']]['volunteers'] = (int)$row['volunteer_count'];
        $chart_months[$row['ym']]['hours'] = (int)$row['hours_count'];
    }
}

$chart_labels = array_column($chart_months, 'label');
$chart_volunteers = array_column($chart_months, 'volunteers');
$chart_hours = array_column($chart_months, 'hours');

$initiatives = $pdo->query("
    SELECT cs.*, 
           (SELECT COUNT(*) FROM community_service_participants csp WHERE csp.activity_id = cs.id) as volunteers_count
    FROM community_service cs
    ORDER BY cs.created_at DESC
")->fetchAll();

$participations = $pdo->query("
    SELECT csp.*, cs.title as initiative_title, cs.hours_credited, st.full_name as student_name, st.roll_no, st.department
    FROM community_service_participants csp
    JOIN community_service cs ON csp.activity_id = cs.id
    JOIN students st ON csp.student_id = st.id
    ORDER BY csp.registered_at DESC
")->fetchAll();

$page_title = "Community Services";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Community Services</h3>
        <p class="text-muted small mb-0">Module 5: Outreach programs, volunteer participation tracking, and certified service hours</p>
      </div>
      <button class="bento-btn bento-btn-primary" data-bs-toggle="modal" data-bs-target="#createInitModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Launch Project
      </button>
    </div>

    <!-- Overview Metric Cards -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon">
              <i class="bi bi-people-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $total_volunteers ?></div>
              <div class="metric-label">Enrolled Volunteers</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon success">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div class="metric-value text-success"><?= $total_hours ?></div>
              <div class="metric-label">Certified Hours</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon action">
              <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $total_projects ?></div>
              <div class="metric-label">Total Initiatives</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon warning">
              <i class="bi bi-patch-check-fill"></i>
            </div>
            <div>
              <div class="metric-value text-primary"><?= $verification_rate ?>%</div>
              <div class="metric-label">Verification Rate</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Frequency Graph Bento Card -->
    <div class="bento-card mb-4">
      <div class="bento-header">
        <div>
          <h5 class="bento-title"><i class="bi bi-bar-chart-fill text-primary"></i> Volunteer Participation & Hours Frequency Graph</h5>
          <div class="bento-subtitle">Monthly frequency trends for student enrollments and certified service hours</div>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="badge bg-light text-primary border"><i class="bi bi-circle-fill me-1" style="font-size: 8px; color: #0A66C2;"></i> Volunteers</span>
          <span class="badge bg-light text-success border"><i class="bi bi-circle-fill me-1" style="font-size: 8px; color: #16A34A;"></i> Hours Credited</span>
        </div>
      </div>
      <div style="height: 280px; width: 100%;">
        <canvas id="participationFrequencyChart"></canvas>
      </div>
    </div>

    <!-- Active Projects & Student Logs -->
    <div class="bento-card mb-4">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-heart-pulse text-danger"></i> Active Outreach Initiatives</h5>
        <span class="text-muted small"><?= count($initiatives) ?> Projects</span>
      </div>
      <div class="row g-3">
        <?php if (empty($initiatives)): ?>
          <div class="col-12 text-center py-4 text-muted">No community initiatives launched yet.</div>
        <?php else: ?>
          <?php foreach ($initiatives as $init): ?>
            <div class="col-md-6 col-lg-4">
              <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary"><?= e($init['organizer']) ?></span>
                    <span class="badge bg-success"><?= $init['hours_credited'] ?> hrs credit</span>
                  </div>
                  <h6 class="fw-bold text-dark mb-1"><?= e($init['title']) ?></h6>
                  <p class="text-secondary small mb-2"><?= e(substr($init['description'], 0, 90)) ?>...</p>
                  <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i> <?= e($init['location']) ?></div>
                  <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i> <?= date('M d, Y', strtotime($init['event_date'])) ?></div>
                </div>
                <div class="pt-2 border-top mt-2 small text-primary fw-bold">
                  <?= $init['volunteers_count'] ?> / <?= $init['max_participants'] ?> Enrolled Volunteers
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Student Volunteers Table -->
    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-person-check-fill text-success"></i> Student Participation & Hours Verification</h5>
        <span class="text-muted small"><?= count($participations) ?> Records</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Initiative</th>
              <th>Hours Awarded</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($participations)): ?>
              <tr><td colspan="5" class="text-center py-4 text-muted">No student participation entries yet.</td></tr>
            <?php else: ?>
              <?php foreach ($participations as $p): ?>
                <tr>
                  <td>
                    <strong><?= e($p['student_name']) ?></strong>
                    <small class="text-muted d-block"><?= e($p['roll_no']) ?> &bull; <?= e($p['department'] ?? '') ?></small>
                  </td>
                  <td><?= e($p['initiative_title']) ?></td>
                  <td><strong class="text-success"><?= $p['hours_awarded'] ?: $p['hours_credited'] ?> hrs</strong></td>
                  <td>
                    <span class="status-pill <?= e($p['status']) ?>">
                      <?= ucfirst(e($p['status'])) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <?php if ($p['status'] !== 'verified'): ?>
                      <a href="community.php?verify_id=<?= $p['id'] ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-check-circle me-1"></i> Verify Hours
                      </a>
                    <?php else: ?>
                      <span class="text-success small fw-semibold"><i class="bi bi-patch-check-fill me-1"></i> Verified</span>
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

<!-- Modal: Create Initiative -->
<div class="modal fade" id="createInitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="create_initiative" value="1">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Launch Community Project</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Initiative Title *</label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Tree Plantation & Environmental Awareness">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Organizer / Cell</label>
              <input type="text" name="organizer" class="form-control" required value="NSS Unit 1">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Hours Credited</label>
              <input type="number" name="hours_credited" class="form-control" required value="4">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Location</label>
              <input type="text" name="location" class="form-control" required placeholder="North Campus Quad">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Event Date</label>
              <input type="date" name="event_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Max Volunteers</label>
            <input type="number" name="max_participants" class="form-control" required value="50">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Scope and volunteer tasks..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="bento-btn bento-btn-primary">Publish Project</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('participationFrequencyChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
          {
            label: 'Volunteers Enrolled',
            data: <?= json_encode($chart_volunteers) ?>,
            backgroundColor: 'rgba(10, 102, 194, 0.75)',
            borderColor: '#0A66C2',
            borderWidth: 1.5,
            borderRadius: 6,
            yAxisID: 'y'
          },
          {
            label: 'Hours Credited',
            data: <?= json_encode($chart_hours) ?>,
            type: 'line',
            borderColor: '#16A34A',
            backgroundColor: 'rgba(22, 163, 74, 0.1)',
            borderWidth: 2.5,
            pointBackgroundColor: '#16A34A',
            pointRadius: 4,
            fill: true,
            tension: 0.35,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            padding: 10,
            cornerRadius: 8
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            }
          },
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Volunteers',
              color: '#0A66C2',
              font: { weight: 'bold', size: 11 }
            },
            grid: {
              color: '#F0F2F5'
            },
            beginAtZero: true
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Hours Awarded',
              color: '#16A34A',
              font: { weight: 'bold', size: 11 }
            },
            grid: {
              drawOnChartArea: false
            },
            beginAtZero: true
          }
        }
      }
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
