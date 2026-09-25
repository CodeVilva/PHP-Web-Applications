<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$faculty_id = $_SESSION['faculty_id'] ?? null;
$user_id = $_SESSION['user_id'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? 'Faculty Welfare');
    $priority = trim($_POST['priority'] ?? 'medium');
    $description = trim($_POST['description'] ?? '');

    if (empty($subject) || empty($description)) {
        $err = 'Please provide both subject and detailed description of your grievance.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO complaints (complainant_type, faculty_id, category, subject, description, priority, status) VALUES ('faculty', ?, ?, ?, ?, ?, 'open')");
            $stmt->execute([$faculty_id, $category, $subject, $description, strtolower($priority)]);
            set_flash('success', 'Faculty grievance submitted successfully to the administration desk.');
            redirect('faculty/complaints.php');
        } catch (Exception $e) {
            $err = 'Database Error: ' . $e->getMessage();
        }
    }
}

// Fetch faculty complaints
$my_complaints = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE faculty_id = ? AND complainant_type = 'faculty' ORDER BY created_at DESC");
    $stmt->execute([$faculty_id]);
    $my_complaints = $stmt->fetchAll();
} catch (Exception $e) {
    $my_complaints = [];
}

$page_title = "Staff Grievance & Helpdesk";
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
                        <h3 class="fw-bold mb-1 text-white"><i class="bi bi-shield-exclamation me-2"></i>Staff Grievance & Redressal Cell</h3>
                        <p class="mb-0 text-white-50">Confidential submission of workplace, infrastructure, IT, or administrative concerns directly to the Dean & Campus Administration.</p>
                    </div>
                    <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#newGrievanceModal">
                        <i class="bi bi-plus-circle me-1"></i> Submit New Grievance
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Submitted Grievances Table -->
    <div class="bento-card p-4">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text text-primary me-2"></i>My Lodged Grievances & Status</h5>
        <?php if (empty($my_complaints)): ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle text-muted display-4"></i>
                <p class="text-muted mt-2">No grievances registered. You have a clean record!</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Ticket ID</th>
                            <th>Category</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Date Filed</th>
                            <th>Status</th>
                            <th>Action / Resolution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_complaints as $c): ?>
                        <tr>
                            <td class="fw-bold text-primary">#GRV-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['category']) ?></span></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($c['subject']) ?></div>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 300px;"><?= htmlspecialchars($c['description']) ?></small>
                            </td>
                            <td>
                                <?php 
                                $prio_color = match(strtolower($c['priority'])) {
                                    'high' => 'danger',
                                    'medium' => 'warning text-dark',
                                    default => 'info text-dark'
                                };
                                ?>
                                <span class="badge bg-<?= $prio_color ?> text-capitalize"><?= htmlspecialchars($c['priority']) ?></span>
                            </td>
                            <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <?php 
                                $stat_badge = match(strtolower($c['status'])) {
                                    'resolved' => 'success',
                                    'in_progress' => 'primary',
                                    'closed' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $stat_badge ?> text-capitalize"><?= htmlspecialchars($c['status']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $c['id'] ?>">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </td>
                        </tr>

                        <!-- View Modal -->
                        <div class="modal fade" id="viewModal<?= $c['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Ticket #GRV-<?= str_pad($c['id'], 4, '0', STR_PAD_LEFT) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <h6 class="fw-bold text-dark"><?= htmlspecialchars($c['subject']) ?></h6>
                                        <div class="d-flex gap-2 mb-3">
                                            <span class="badge bg-secondary"><?= htmlspecialchars($c['category']) ?></span>
                                            <span class="badge bg-<?= $prio_color ?> text-capitalize"><?= htmlspecialchars($c['priority']) ?> Priority</span>
                                            <span class="badge bg-<?= $stat_badge ?> text-capitalize"><?= htmlspecialchars($c['status']) ?></span>
                                        </div>
                                        <p class="text-secondary bg-light p-3 rounded"><?= nl2br(htmlspecialchars($c['description'])) ?></p>
                                        
                                        <?php if (!empty($c['admin_response'])): ?>
                                            <div class="alert alert-success">
                                                <h6 class="fw-bold mb-1"><i class="bi bi-patch-check-fill me-1"></i> Admin Resolution:</h6>
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($c['admin_response'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Grievance Modal -->
    <div class="modal fade" id="newGrievanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Submit Staff Grievance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Grievance Category *</label>
                                <select name="category" class="form-select" required>
                                    <option value="Infrastructure">Infrastructure & Lab Facilities</option>
                                    <option value="Faculty Welfare">Faculty Welfare & Support</option>
                                    <option value="Academic">Academic & Timetable</option>
                                    <option value="Administrative">Administrative & Allowance</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Urgency / Priority *</label>
                                <select name="priority" class="form-select" required>
                                    <option value="low">Low (Routine maintenance)</option>
                                    <option value="medium" selected>Medium (Standard attention)</option>
                                    <option value="high">High (Impacting teaching/safety)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Subject / Title *</label>
                                <input type="text" name="subject" class="form-control" placeholder="Brief summary of the issue" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Detailed Description *</label>
                                <textarea name="description" rows="5" class="form-control" placeholder="Provide complete facts, location/room number, and required corrective action..." required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Submit Grievance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
