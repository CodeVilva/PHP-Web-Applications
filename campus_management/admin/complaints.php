<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Handle update status / remarks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_complaint') {
    $comp_id  = (int)$_POST['complaint_id'];
    $status   = $_POST['status'];
    $priority = $_POST['priority'];
    $response = trim($_POST['admin_response'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE complaints
        SET status = ?, priority = ?, admin_response = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $priority, $response, $comp_id]);
    set_flash('success', "Complaint #$comp_id updated successfully.");
    redirect('admin/complaints.php');
}

$status_filter = $_GET['status'] ?? 'all';
$sql = "
    SELECT c.*, s.full_name as student_name, s.roll_no, u.email as student_email
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== 'all') {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY CASE WHEN c.status = 'open' THEN 1 WHEN c.status = 'in_progress' THEN 2 ELSE 3 END, c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

$page_title = "Institutional Grievance Desk";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Grievance & Issue Redressal Desk</h2>
        <p class="text-muted mb-0">Investigate student concerns, assign triage priorities, and record official resolutions.</p>
      </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bento-card mb-4 p-3">
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/complaints.php" class="btn btn-sm <?= $status_filter === 'all' ? 'btn-primary' : 'btn-light border' ?>">All Tickets</a>
        <a href="<?= BASE_URL ?>/admin/complaints.php?status=open" class="btn btn-sm <?= $status_filter === 'open' ? 'btn-primary' : 'btn-light border' ?>">Open</a>
        <a href="<?= BASE_URL ?>/admin/complaints.php?status=in_progress" class="btn btn-sm <?= $status_filter === 'in_progress' ? 'btn-primary' : 'btn-light border' ?>">In Progress</a>
        <a href="<?= BASE_URL ?>/admin/complaints.php?status=resolved" class="btn btn-sm <?= $status_filter === 'resolved' ? 'btn-primary' : 'btn-light border' ?>">Resolved</a>
      </div>
    </div>

    <!-- Complaints Table -->
    <div class="bento-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Ticket</th>
              <th>Student</th>
              <th>Subject & Details</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($complaints)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No grievance tickets found.</td></tr>
            <?php else: ?>
              <?php foreach ($complaints as $c): ?>
                <tr>
                  <td><code>#CMP-<?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></code></td>
                  <td>
                    <strong><?= e($c['student_name']) ?></strong>
                    <small class="text-muted d-block"><?= e($c['roll_no']) ?></small>
                  </td>
                  <td>
                    <strong><?= e($c['subject']) ?></strong>
                    <small class="text-muted d-block"><?= e(substr($c['description'], 0, 70)) ?>...</small>
                    <small class="text-muted"><?= date('M d, Y', strtotime($c['created_at'])) ?></small>
                  </td>
                  <td><span class="badge bg-light text-dark border"><?= e($c['category']) ?></span></td>
                  <td>
                    <span class="badge <?= $c['priority'] === 'high' ? 'bg-danger' : 'bg-secondary' ?>"><?= ucfirst($c['priority']) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= $c['status'] === 'resolved' ? 'bg-success' : ($c['status'] === 'in_progress' ? 'bg-warning text-dark' : 'bg-info text-dark') ?>">
                      <?= ucfirst($c['status']) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['id'] ?>">
                      <i class="bi bi-pencil-square me-1"></i> Triage
                    </button>

                    <!-- Triage Modal -->
                    <div class="modal fade text-start" id="editModal<?= $c['id'] ?>" tabindex="-1">
                      <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                          <form method="POST">
                            <input type="hidden" name="action" value="update_complaint">
                            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                            <div class="modal-header">
                              <h5 class="modal-title fw-bold">Grievance Resolution</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <p class="mb-1"><strong>Ticket:</strong> #CMP-<?= str_pad($c['id'], 3, '0', STR_PAD_LEFT) ?></p>
                              <p class="mb-1"><strong>Student:</strong> <?= e($c['student_name']) ?> (<?= e($c['roll_no']) ?>)</p>
                              <p class="mb-3"><strong>Subject:</strong> <?= e($c['subject']) ?></p>
                              <div class="p-3 bg-light rounded mb-3 small">
                                <?= nl2br(e($c['description'])) ?>
                              </div>

                              <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                  <label class="form-label fw-semibold">Status</label>
                                  <select name="status" class="form-select">
                                    <option value="open" <?= $c['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="in_progress" <?= $c['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $c['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="closed" <?= $c['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                  </select>
                                </div>
                                <div class="col-md-6">
                                  <label class="form-label fw-semibold">Priority</label>
                                  <select name="priority" class="form-select">
                                    <option value="low" <?= $c['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="medium" <?= $c['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= $c['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                  </select>
                                </div>
                              </div>

                              <div class="mb-3">
                                <label class="form-label fw-semibold">Administrative Resolution Note</label>
                                <textarea name="admin_response" class="form-control" rows="3" placeholder="Action taken..."><?= e($c['admin_response'] ?? '') ?></textarea>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary">Update Ticket</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
