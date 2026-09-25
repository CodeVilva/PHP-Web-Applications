<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;
$tab = $_GET['tab'] ?? 'internal';

// 1. RSVP to Internal College Event
if (isset($_GET['register_event_id'])) {
    $ev_id = (int)$_GET['register_event_id'];
    $chk = $pdo->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND student_id = ?");
    $chk->execute([$ev_id, $student_id]);
    if ($chk->fetch()) {
        set_flash('info', 'You are already registered for this event.');
    } else {
        $pdo->prepare("INSERT INTO event_registrations (event_id, student_id, status) VALUES (?, ?, 'confirmed')")
            ->execute([$ev_id, $student_id]);
        set_flash('success', 'RSVP confirmed! See you at the event.');
    }
    redirect('student/events.php?tab=internal');
}

// 2. Submit External Event Achievement / Award
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_external_event'])) {
    $event_name   = trim($_POST['event_name']);
    $institution  = trim($_POST['organizing_institution']);
    $event_type   = $_POST['event_type'] ?? 'Symposium';
    $event_date   = $_POST['event_date'];
    $award        = trim($_POST['award_received']);

    $cert_file = null;
    if (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['certificate_file']['name'], PATHINFO_EXTENSION));
        $safe_name = 'cert_' . $student_id . '_' . time() . '.' . $ext;
        $target = UPLOAD_PATH . '/certificates/' . $safe_name;
        if (move_uploaded_file($_FILES['certificate_file']['tmp_name'], $target)) {
            $cert_file = $safe_name;
        }
    }

    $pdo->prepare("
        INSERT INTO external_events (student_id, event_name, organizing_institution, event_type, event_date, award_received, certificate_file, verification_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ")->execute([$student_id, $event_name, $institution, $event_type, $event_date, $award, $cert_file]);

    set_flash('success', 'External event participation and award submitted for faculty verification!');
    redirect('student/events.php?tab=external');
}

// Fetch internal campus events
$events = $pdo->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as rsvp_count,
           (SELECT id FROM event_registrations er WHERE er.event_id = e.id AND er.student_id = " . intval($student_id) . ") as my_rsvp
    FROM events e
    ORDER BY e.start_datetime ASC
")->fetchAll();

// Fetch my external events
$my_external = $pdo->prepare("SELECT * FROM external_events WHERE student_id = ? ORDER BY event_date DESC");
$my_external->execute([$student_id]);
$external_records = $my_external->fetchAll();

$page_title = 'Campus Events & External Accolades';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Events & External Achievements</h3>
        <p class="text-muted small mb-0">Module 6: College symposiums, guest conclaves, and external competition awards tracker</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitExternalModal">
        <i class="bi bi-trophy-fill me-1"></i> Log External Award / Win
      </button>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'internal' ? 'active' : '' ?>" href="events.php?tab=internal">
          <i class="bi bi-calendar-event me-1"></i> College Events & Conclaves
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'external' ? 'active' : '' ?>" href="events.php?tab=external">
          <i class="bi bi-trophy-fill me-1"></i> My External Accolades (<?= count($external_records) ?>)
        </a>
      </li>
    </ul>

    <?php if ($tab === 'internal'): ?>
      <!-- 1. INTERNAL COLLEGE EVENTS -->
      <div class="row g-4">
        <?php if (empty($events)): ?>
          <div class="col-12"><div class="bento-card text-center py-5 text-muted">No upcoming college events scheduled.</div></div>
        <?php else: ?>
          <?php foreach ($events as $ev): ?>
            <div class="col-md-6 col-lg-4">
              <div class="bento-card h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary-subtle text-primary border border-primary"><?= e($ev['category']) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($ev['venue']) ?></span>
                  </div>
                  <h5 class="fw-bold text-dark mb-1"><?= e($ev['title']) ?></h5>
                  <div class="small text-muted mb-2">
                    <i class="bi bi-clock me-1"></i><?= date('M d, Y h:i A', strtotime($ev['start_datetime'])) ?>
                  </div>
                  <p class="text-secondary small mb-3"><?= e(substr($ev['description'], 0, 110)) ?>...</p>

                  <div class="p-2 bg-light rounded-3 small mb-3 d-flex justify-content-between">
                    <span><i class="bi bi-people text-primary me-1"></i> Dignitaries: <strong><?= $ev['number_of_guests'] ?? 5 ?></strong></span>
                    <span><i class="bi bi-award text-warning me-1"></i> Awards: <strong><?= $ev['awards_distributed'] ?? 3 ?></strong></span>
                  </div>
                </div>

                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                  <small class="text-muted fw-semibold"><?= $ev['rsvp_count'] ?> / <?= $ev['max_seats'] ?> Attending</small>
                  <?php if ($ev['my_rsvp']): ?>
                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2"><i class="bi bi-check2 me-1"></i> RSVP'd</span>
                  <?php else: ?>
                    <a href="events.php?register_event_id=<?= $ev['id'] ?>" class="bento-btn bento-btn-primary py-1 px-3 small">
                      <i class="bi bi-check-circle me-1"></i> RSVP Seat
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'external'): ?>
      <!-- 2. EXTERNAL ACCOLADES & AWARDS TAB -->
      <div class="bento-card">
        <h5 class="fw-bold mb-3" style="color: var(--text-slate);">
          <i class="bi bi-award-fill text-warning me-2"></i>My Outside College Competition Awards & Medals
        </h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th>Event Name & Type</th>
                <th>Host College / Institution</th>
                <th>Event Date</th>
                <th>Award / Rank Won</th>
                <th>Certificate Proof</th>
                <th>Verification</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($external_records)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No external achievements logged yet. Click <strong>Log External Award / Win</strong> above!</td></tr>
              <?php else: ?>
                <?php foreach ($external_records as $rec): ?>
                  <tr>
                    <td>
                      <strong><?= e($rec['event_name']) ?></strong>
                      <span class="badge bg-light text-dark border ms-1"><?= e($rec['event_type']) ?></span>
                    </td>
                    <td><?= e($rec['organizing_institution']) ?></td>
                    <td><?= date('M d, Y', strtotime($rec['event_date'])) ?></td>
                    <td><strong class="text-success"><i class="bi bi-trophy-fill text-warning me-1"></i><?= e($rec['award_received']) ?></strong></td>
                    <td>
                      <?php if ($rec['certificate_file'] && file_exists(UPLOAD_PATH . '/certificates/' . $rec['certificate_file'])): ?>
                        <a href="<?= BASE_URL ?>/uploads/certificates/<?= e($rec['certificate_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2">
                          <i class="bi bi-file-earmark-image me-1"></i> View Certificate
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">No File</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= $rec['verification_status'] === 'verified' ? 'bg-success' : ($rec['verification_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                        <?= ucfirst($rec['verification_status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Modal: Log External Award -->
<div class="modal fade" id="submitExternalModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="submit_external_event" value="1">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Log Outside College Competition / Award</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Event Name *</label>
            <input type="text" name="event_name" class="form-control" required placeholder="e.g. National Level Hackathon / Paper Presentation">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Organizing College / Institution *</label>
            <input type="text" name="organizing_institution" class="form-control" required placeholder="e.g. IIT Madras / Anna University">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Event Type</label>
              <select name="event_type" class="form-select">
                <option value="Symposium">Technical Symposium</option>
                <option value="Hackathon">Hackathon / Coding</option>
                <option value="Conference">Paper / Project Presentation</option>
                <option value="Sports">Sports / Athletics</option>
                <option value="Cultural">Cultural / Arts</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Event Date *</label>
              <input type="date" name="event_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Award / Achievement Won *</label>
            <input type="text" name="award_received" class="form-control" required placeholder="e.g. 1st Prize (₹500) / Gold Medal / Best Innovation">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Attach Certificate Proof / Trophy Photo</label>
            <input type="file" name="certificate_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit for Verification</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
