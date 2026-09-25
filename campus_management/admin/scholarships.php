<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Handle Application Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'review_scholarship') {
        $app_id = intval($_POST['app_id'] ?? 0);
        $status = $_POST['status'] ?? 'approved';
        $sanctioned_amount = floatval($_POST['sanctioned_amount'] ?? 0);
        $admin_remarks = trim($_POST['admin_remarks'] ?? '');

        try {
            $stmt = $pdo->prepare("UPDATE scholarship_applications SET admin_status = ?, disbursed_amount = ?, admin_remarks = ? WHERE id = ?");
            $stmt->execute([$status, $sanctioned_amount, $admin_remarks, $app_id]);
            set_flash('success', "Scholarship application #$app_id updated to status '$status'.");
            redirect('admin/scholarships.php');
        } catch (Exception $e) {
            set_flash('danger', 'Database Error: ' . $e->getMessage());
        }
    }
}

// Fetch all applications with HOD details
$applications = [];
try {
    $stmt = $pdo->query("
        SELECT sa.*, s.title as scholarship_name, s.amount as max_amount,
               st.full_name as student_name, st.roll_no, st.department, st.social_category,
               f.full_name as hod_name, f.designation as hod_designation
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        JOIN students st ON sa.student_id = st.id
        LEFT JOIN faculty f ON sa.faculty_id = f.id
        ORDER BY sa.applied_at DESC
    ");
    $applications = $stmt->fetchAll();
} catch (Exception $e) {
    $applications = [];
}

// Fetch stats
$total_approved = 0;
$total_sanctioned = 0;
$total_pending = 0;
$total_hod_endorsed = 0;

foreach ($applications as $a) {
    if ($a['admin_status'] === 'approved') {
        $total_approved++;
        $total_sanctioned += floatval($a['disbursed_amount'] ?? 0);
    } elseif ($a['admin_status'] === 'pending') {
        $total_pending++;
    }
    if ($a['faculty_verified'] === 'verified') {
        $total_hod_endorsed++;
    }
}

$page_title = "Scholarships & Welfare Administration";
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
              <h3 class="fw-bold mb-1 text-white"><i class="bi bi-mortarboard-fill me-2"></i>Institutional & Government Scholarships Central</h3>
              <p class="mb-0 text-white-50">Two-tier governance workflow: Department HOD Verification followed by Administrative Sanction & Fund Disbursement.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon">
              <i class="bi bi-inbox-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= count($applications) ?></div>
              <div class="metric-label">Total Applications</div>
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
              <div class="metric-value text-warning"><?= $total_hod_endorsed ?></div>
              <div class="metric-label">HOD Endorsed</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon action">
              <i class="bi bi-hourglass-split"></i>
            </div>
            <div>
              <div class="metric-value text-primary"><?= $total_pending ?></div>
              <div class="metric-label">Pending Admin Sanction</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon success">
              <i class="bi bi-cash-stack"></i>
            </div>
            <div>
              <div class="metric-value text-success">₹<?= number_format($total_sanctioned, 2) ?></div>
              <div class="metric-label">Disbursed Funds</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Applications Table -->
    <div class="bento-card">
      <div class="bento-header">
        <div>
          <h5 class="bento-title"><i class="bi bi-card-checklist text-primary"></i> Scholarship Applications & Sanction Queue</h5>
          <div class="bento-subtitle">Department HOD recommendations & central scholarship disbursement</div>
        </div>
        <span class="text-muted small"><?= count($applications) ?> Records</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Applicant</th>
              <th>Scheme Name</th>
              <th>Income & GPA</th>
              <th>Document</th>
              <th>HOD Endorsement</th>
              <th>Sanction Amount</th>
              <th>Admin Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($applications)): ?>
              <tr><td colspan="8" class="text-center py-5 text-muted">No scholarship applications submitted yet.</td></tr>
            <?php else: ?>
              <?php foreach ($applications as $app): ?>
                <tr>
                  <td>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($app['student_name']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($app['roll_no']) ?> &bull; <?= htmlspecialchars($app['department']) ?></small>
                  </td>
                  <td>
                    <div class="fw-bold text-primary"><?= htmlspecialchars($app['scholarship_name']) ?></div>
                    <small class="text-muted">Applied: <?= date('M d, Y', strtotime($app['applied_at'])) ?></small>
                  </td>
                  <td>
                    <div>GPA: <strong><?= number_format($app['gpa'], 2) ?></strong></div>
                    <small class="text-muted">₹<?= number_format(floatval($app['annual_family_income'] ?? 0), 0) ?>/yr</small>
                  </td>
                  <td>
                    <?php 
                      $doc_file = $app['document_path'] ?? '';
                      $doc_path = UPLOAD_PATH . '/scholarships/' . $doc_file;
                    ?>
                    <?php if (!empty($doc_file) && file_exists($doc_path)): ?>
                      <a href="<?= BASE_URL ?>/uploads/scholarships/<?= htmlspecialchars($doc_file) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Proof
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">No File</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($app['faculty_verified'] === 'verified'): ?>
                      <span class="status-pill verified"><i class="bi bi-check-circle-fill me-1"></i> Endorsed</span>
                      <?php if (!empty($app['hod_name'])): ?>
                        <div class="small text-muted" style="font-size:11px;">By <?= htmlspecialchars($app['hod_name']) ?></div>
                      <?php endif; ?>
                    <?php elseif ($app['faculty_verified'] === 'rejected'): ?>
                      <span class="status-pill rejected"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-pill pending"><i class="bi bi-clock-history me-1"></i> Pending HOD</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($app['admin_status'] === 'approved' && floatval($app['disbursed_amount'] ?? 0) > 0): ?>
                      <strong class="text-success">₹<?= number_format(floatval($app['disbursed_amount']), 2) ?></strong>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($app['admin_status'] === 'approved'): ?>
                      <span class="status-pill verified">Sanctioned</span>
                    <?php elseif ($app['admin_status'] === 'rejected'): ?>
                      <span class="status-pill rejected">Rejected</span>
                    <?php else: ?>
                      <span class="status-pill info">Under Review</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button class="bento-btn bento-btn-primary py-1 px-3 small" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $app['id'] ?>">
                      <i class="bi bi-pencil-square me-1"></i> Sanction
                    </button>
                  </td>
                </tr>

                <!-- Review Modal -->
                <div class="modal fade text-start" id="reviewModal<?= $app['id'] ?>" tabindex="-1">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                      <form method="POST">
                        <input type="hidden" name="action" value="review_scholarship">
                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                        <div class="modal-header border-bottom bg-light">
                          <h5 class="modal-title fw-bold">Scholarship Sanction: <?= htmlspecialchars($app['student_name']) ?></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                              <strong>Applicant:</strong>
                              <span><?= htmlspecialchars($app['student_name']) ?> (<?= htmlspecialchars($app['roll_no']) ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                              <strong>Department:</strong>
                              <span><?= htmlspecialchars($app['department']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                              <strong>Scheme:</strong>
                              <span class="text-primary fw-bold"><?= htmlspecialchars($app['scholarship_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                              <strong>Max Grant Eligible:</strong>
                              <span class="text-success fw-bold">₹<?= number_format(floatval($app['max_amount'] ?? 0), 2) ?></span>
                            </div>
                          </div>

                          <!-- HOD Endorsement Status Box -->
                          <div class="p-3 mb-3 rounded-3 border <?= $app['faculty_verified'] === 'verified' ? 'bg-success-subtle border-success' : ($app['faculty_verified'] === 'rejected' ? 'bg-danger-subtle border-danger' : 'bg-warning-subtle border-warning') ?>">
                            <div class="fw-bold d-flex justify-content-between align-items-center mb-1">
                              <span><i class="bi bi-patch-check-fill me-1"></i> Department HOD Endorsement:</span>
                              <span class="badge <?= $app['faculty_verified'] === 'verified' ? 'bg-success' : ($app['faculty_verified'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                                <?= ucfirst($app['faculty_verified']) ?>
                              </span>
                            </div>
                            <div class="small">
                              <strong>Reviewing HOD:</strong> <?= htmlspecialchars($app['hod_name'] ?? 'Pending Department HOD') ?><br>
                              <strong>HOD Remarks:</strong> <?= htmlspecialchars($app['faculty_remarks'] ?: 'No HOD remarks recorded yet.') ?>
                            </div>
                          </div>

                          <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Central Administrative Decision *</label>
                            <select name="status" class="form-select" required>
                              <option value="approved" <?= $app['admin_status'] === 'approved' ? 'selected' : '' ?>>Approve & Sanction Disbursement</option>
                              <option value="rejected" <?= $app['admin_status'] === 'rejected' ? 'selected' : '' ?>>Reject Application</option>
                              <option value="pending" <?= $app['admin_status'] === 'pending' ? 'selected' : '' ?>>Hold as Pending</option>
                            </select>
                          </div>

                          <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Disbursed Amount (₹) *</label>
                            <input type="number" step="0.01" name="sanctioned_amount" class="form-control" required value="<?= htmlspecialchars((floatval($app['disbursed_amount'] ?? 0) > 0) ? $app['disbursed_amount'] : ($app['max_amount'] ?? 0)) ?>">
                          </div>

                          <div class="mb-2">
                            <label class="form-label fw-semibold small text-dark">Administrative Remarks & Sanction Order No.</label>
                            <textarea name="admin_remarks" class="form-control" rows="2" placeholder="e.g. Sanction Order #TN-SCH-2026/89 approved..."><?= htmlspecialchars($app['admin_remarks'] ?? '') ?></textarea>
                          </div>
                        </div>

                        <div class="modal-footer border-top bg-light">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          <button type="submit" class="bento-btn bento-btn-primary">Commit Sanction Order</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
