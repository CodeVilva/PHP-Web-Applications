<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $category = $_POST['category'] ?? 'Other';
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if (!empty($subject) && !empty($description)) {
        $ins = $pdo->prepare("
            INSERT INTO complaints (student_id, category, subject, description, priority, status)
            VALUES (?, ?, ?, ?, ?, 'open')
        ");
        $ins->execute([$student_id, $category, $subject, $description, $priority]);
        set_flash('success', 'Grievance ticket created successfully. Our team will review shortly.');
        redirect('student/complaints.php');
    }
}

$cm_stmt = $pdo->prepare("SELECT * FROM complaints WHERE student_id = ? ORDER BY created_at DESC");
$cm_stmt->execute([$student_id]);
$complaints = $cm_stmt->fetchAll();

$page_title = 'Student Grievance & Complaints';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Student Grievance & Support</h3>
        <p class="text-muted small mb-0">Module 8: Submit complaints, track investigation progress, and read Faculty & Admin replies</p>
      </div>
      <button type="button" class="bento-btn bento-btn-primary" data-bs-toggle="modal" data-bs-target="#newComplaintModal">
        <i class="bi bi-plus-circle me-1"></i> New Complaint
      </button>
    </div>

    <div class="row g-4">
      <?php if (empty($complaints)): ?>
        <div class="col-12">
          <div class="bento-card text-center py-5 text-muted">
            <i class="bi bi-shield-check fs-1 d-block mb-2 text-success"></i>
            No active complaints or grievances filed.
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($complaints as $c): ?>
          <div class="col-12">
            <div class="bento-card">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                  <span class="badge bg-light text-primary border me-2"><?= e($c['category']) ?></span>
                  <span class="status-pill <?= e($c['status']) ?>"><?= ucfirst(str_replace('_', ' ', e($c['status']))) ?></span>
                  <h5 class="fw-bold text-dark mt-2 mb-0"><?= e($c['subject']) ?></h5>
                </div>
                <div class="text-end small text-muted">
                  <div>Priority: <strong class="text-uppercase text-<?= $c['priority'] === 'high' ? 'danger' : 'secondary' ?>"><?= e($c['priority']) ?></strong></div>
                  <div>Filed: <?= date('M d, Y h:i A', strtotime($c['created_at'])) ?></div>
                </div>
              </div>

              <div class="p-3 bg-light rounded-3 mb-3 small text-secondary">
                <?= nl2br(e($c['description'])) ?>
              </div>

              <div class="row g-3 pt-2 border-top">
                <div class="col-md-6">
                  <div class="p-3 rounded-3 border bg-white small h-100">
                    <strong class="d-block text-primary mb-1"><i class="bi bi-person-workspace me-1"></i> Faculty Response:</strong>
                    <div class="text-secondary"><?= e($c['faculty_response'] ?: 'Pending faculty assessment.') ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="p-3 rounded-3 border bg-white small h-100">
                    <strong class="d-block text-dark mb-1"><i class="bi bi-shield-shaded me-1"></i> Admin Resolution:</strong>
                    <div class="text-secondary"><?= e($c['admin_response'] ?: 'Pending administrative action.') ?></div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <div class="modal fade" id="newComplaintModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold">Submit Grievance / Complaint</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Category</label>
                <select name="category" class="form-select" required>
                  <option value="Academic">Academic</option>
                  <option value="Hostel">Hostel</option>
                  <option value="Library">Library</option>
                  <option value="Infrastructure">Infrastructure</option>
                  <option value="Harassment">Harassment</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Priority Level</label>
                <select name="priority" class="form-select" required>
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High (Urgent)</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-bold">Subject Summary</label>
                <input type="text" name="subject" class="form-control" placeholder="Brief summary of the issue" required>
              </div>
              <div class="col-12">
                <label class="form-label small fw-bold">Detailed Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Provide complete facts, dates, classroom, or personnel involved..." required></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top">
            <button type="button" class="bento-btn bento-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="submit_complaint" class="bento-btn bento-btn-primary">Submit Ticket</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
