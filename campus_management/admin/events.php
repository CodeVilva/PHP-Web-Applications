<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Handle Event Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_campus_event') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $number_of_guests = intval($_POST['number_of_guests'] ?? 0);

        if (empty($title) || empty($event_date) || empty($venue)) {
            set_flash('danger', 'Please fill in Event Title, Date, and Venue.');
        } else {
            try {
                $start_dt = $event_date . ' 09:30:00';
                $end_dt   = $event_date . ' 17:00:00';
                $stmt = $pdo->prepare("INSERT INTO events (title, description, category, event_type, venue, start_datetime, end_datetime, number_of_guests, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published')");
                $stmt->execute([$title, $description, $category, $category, $venue, $start_dt, $end_dt, $number_of_guests]);
                set_flash('success', 'Campus Event successfully published!');

                redirect('admin/events.php');
            } catch (Exception $e) {
                set_flash('danger', 'Database Error: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'verify_external') {
        $ext_id = intval($_POST['ext_id'] ?? 0);
        $status = $_POST['status'] ?? 'verified';

        try {
            $stmt = $pdo->prepare("UPDATE external_events SET verification_status = ? WHERE id = ?");
            $stmt->execute([strtolower($status), $ext_id]);
            set_flash('success', "External symposium achievement status updated to '$status'.");
            redirect('admin/events.php');
        } catch (Exception $e) {
            set_flash('danger', 'Database Error: ' . $e->getMessage());
        }
    }
}

// Fetch Campus Events with Registration counts
$events = [];
try {
    $stmt = $pdo->query("SELECT e.*, COUNT(er.id) as registered_students FROM events e LEFT JOIN event_registrations er ON e.id = er.event_id GROUP BY e.id ORDER BY e.start_datetime DESC");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    $events = [];
}

// Fetch External Achievements for Roll of Honor
$external_achievements = [];
try {
    $stmt = $pdo->query("SELECT ee.*, s.roll_no, s.full_name as student_name, s.department FROM external_events ee JOIN students s ON ee.student_id = s.id ORDER BY ee.created_at DESC");
    $external_achievements = $stmt->fetchAll();
} catch (Exception $e) {
    $external_achievements = [];
}

$page_title = "Campus Events & External Accolades";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="bento-card p-4 text-white" style="background: linear-gradient(135deg, var(--brand-primary) 0%, #004182 100%);">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1 text-white"><i class="bi bi-calendar-event me-2"></i>Campus Events & Inter-College Awards Desk</h3>
                        <p class="mb-0 text-white-50">Schedule internal symposiums, manage external conference accolades, and honor student winners.</p>
                    </div>
                    <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#newEventModal">
                        <i class="bi bi-plus-circle me-1"></i> Create Campus Event
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-pills mb-4" id="eventTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internal-pane">
                <i class="bi bi-building me-1"></i> Campus Internal Events (<?= count($events) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="external-tab" data-bs-toggle="tab" data-bs-target="#external-pane">
                <i class="bi bi-trophy me-1"></i> External Symposiums & Awards (<?= count($external_achievements) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Internal Campus Events -->
        <div class="tab-pane fade show active" id="internal-pane">
            <div class="bento-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Scheduled Campus Events & Guest Limits</h5>
                <?php if (empty($events)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x text-muted display-4"></i>
                        <p class="text-muted mt-2">No campus events currently scheduled.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date & Time</th>
                                    <th>Venue</th>
                                    <th>Max Guest Capacity</th>
                                    <th>Registered Students</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ev['title']) ?></div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;"><?= htmlspecialchars($ev['description']) ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ev['event_type'] ?? 'General') ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= date('M d, Y', strtotime($ev['start_datetime'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($ev['start_datetime'])) ?></small>
                                    </td>
                                    <td><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($ev['venue']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $ev['number_of_guests'] > 0 ? $ev['number_of_guests'] . ' Guests' : 'Unlimited' ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary fs-6"><?= $ev['registered_students'] ?></span>
                                    </td>
                                    <td>
                                        <?php if (strtotime($ev['start_datetime']) >= strtotime('today')): ?>
                                            <span class="badge bg-success">Upcoming</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Concluded</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- External Symposium & Awards Tab -->
        <div class="tab-pane fade" id="external-pane">
            <div class="bento-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Inter-College Symposiums & State/National Awards</h5>
                    <span class="badge bg-primary fs-6"><?= count($external_achievements) ?> Entries Recorded</span>
                </div>

                <?php if (empty($external_achievements)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-award text-muted display-4"></i>
                        <p class="text-muted mt-2">No external symposium participations logged yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Event & Host Institution</th>
                                    <th>Type & Date</th>
                                    <th>Award / Prize Secured</th>
                                    <th>Certificate</th>
                                    <th>Status</th>
                                    <th>Admin Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($external_achievements as $ext): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ext['student_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ext['roll_no']) ?> (<?= htmlspecialchars($ext['department']) ?>)</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($ext['event_name']) ?></div>
                                        <small class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars($ext['organizing_institution']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($ext['event_type']) ?></span>
                                        <div class="small text-muted mt-1"><?= date('M d, Y', strtotime($ext['event_date'])) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark fw-bold"><i class="bi bi-star-fill me-1"></i><?= htmlspecialchars($ext['award_received'] ?? 'Participant') ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($ext['certificate_file'])): ?>
                                            <a href="<?= BASE_URL ?>/uploads/certificates/<?= htmlspecialchars($ext['certificate_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-file-earmark-check"></i> View Proof
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">No File</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $st_color = match(strtolower($ext['verification_status'])) {
                                            'verified' => 'success',
                                            'rejected' => 'danger',
                                            default => 'warning text-dark'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $st_color ?> text-capitalize"><?= htmlspecialchars($ext['verification_status']) ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $ext['id'] ?>">
                                            <i class="bi bi-check2-circle"></i> Verify
                                        </button>
                                    </td>
                                </tr>

                                <!-- Verify Modal -->
                                <div class="modal fade" id="verifyModal<?= $ext['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="verify_external">
                                                <input type="hidden" name="ext_id" value="<?= $ext['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Verify Achievement</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Student:</strong> <?= htmlspecialchars($ext['student_name']) ?> (<?= htmlspecialchars($ext['roll_no']) ?>)</p>
                                                    <p><strong>Event:</strong> <?= htmlspecialchars($ext['event_name']) ?> at <?= htmlspecialchars($ext['organizing_institution']) ?></p>
                                                    <p><strong>Award Won:</strong> <?= htmlspecialchars($ext['award_received'] ?? 'Participation') ?></p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Verification Decision *</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="verified" <?= strtolower($ext['verification_status']) === 'verified' ? 'selected' : '' ?>>Verified & Approved (Roll of Honor)</option>
                                                            <option value="rejected" <?= strtolower($ext['verification_status']) === 'rejected' ? 'selected' : '' ?>>Rejected (Invalid certificate/information)</option>
                                                            <option value="pending" <?= strtolower($ext['verification_status']) === 'pending' ? 'selected' : '' ?>>Under Review</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary fw-bold">Update Verification</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div class="modal fade" id="newEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_campus_event">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus text-primary me-2"></i>Schedule Campus Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Event Title *</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g., Annual Tech Symposium 'IGNITE 2026'" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category *</label>
                                <select name="category" class="form-select" required>
                                    <option value="Technical Symposium">Technical Symposium</option>
                                    <option value="Cultural Fest">Cultural Fest</option>
                                    <option value="Sports Meet">Sports Meet</option>
                                    <option value="Workshop & Seminar">Workshop & Seminar</option>
                                    <option value="General">General Campus Event</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Max Guests / External Seats (0 for Unlimited)</label>
                                <input type="number" name="number_of_guests" class="form-control" value="0" min="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Event Date *</label>
                                <input type="date" name="event_date" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Venue / Auditorium *</label>
                                <input type="text" name="venue" class="form-control" placeholder="e.g. Main Auditorium / Seminar Hall A" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Event Details & Guidelines</label>
                                <textarea name="description" rows="4" class="form-control" placeholder="Describe the event rules, registration details, cash prizes, and guest speakers..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Publish Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
