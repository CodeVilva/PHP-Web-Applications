<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$user_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_faculty = $stmt->fetch();

$is_hod = !empty($my_faculty['is_hod']) || stripos($my_faculty['designation'] ?? '', 'HOD') !== false || stripos($my_faculty['designation'] ?? '', 'Head of Department') !== false;
$faculty_id = $my_faculty['id'] ?? 0;
$faculty_dept = $my_faculty['department'] ?? '';

// Handle HOD Application Review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_application'])) {
    if (!$is_hod) {
        set_flash('danger', 'Unauthorized: Only the designated Head of Department (HOD) is authorized to verify scholarship applications.');
        redirect('faculty/scholarships.php');
    }

    $app_id = (int)$_POST['application_id'];
    $verification = in_array($_POST['faculty_verified'], ['verified', 'rejected', 'pending']) ? $_POST['faculty_verified'] : 'pending';
    $remarks = trim($_POST['faculty_remarks'] ?? '');

    // Verify application belongs to student in this HOD's department
    $check_stmt = $pdo->prepare("
        SELECT sa.id 
        FROM scholarship_applications sa 
        JOIN students st ON sa.student_id = st.id 
        WHERE sa.id = ? AND st.department = ?
    ");
    $check_stmt->execute([$app_id, $faculty_dept]);
    if ($check_stmt->fetch()) {
        $upd = $pdo->prepare("
            UPDATE scholarship_applications 
            SET faculty_verified = ?, faculty_remarks = ?, faculty_id = ?, verified_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$verification, $remarks, $faculty_id, $app_id]);
        $status_label = ($verification === 'verified') ? 'verified & endorsed' : $verification;
        set_flash('success', "Scholarship application successfully {$status_label} by HOD and queued for Administrative Sanction!");
    } else {
        set_flash('danger', "Access denied: This student application does not belong to your department ($faculty_dept).");
    }
    redirect('faculty/scholarships.php');
}

// Fetch applications according to HOD status
$apps = [];
$dept_hod = null;

if ($is_hod) {
    // HOD only views students from their specific department
    $apps_stmt = $pdo->prepare("
        SELECT sa.*, s.title as scholarship_title, s.provider, s.amount, 
               st.full_name as student_name, st.roll_no, st.department, st.semester, st.social_category
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        JOIN students st ON sa.student_id = st.id
        WHERE st.department = ?
        ORDER BY sa.applied_at DESC
    ");
    $apps_stmt->execute([$faculty_dept]);
    $apps = $apps_stmt->fetchAll();
} else {
    // Non-HOD: fetch current HOD info for their department
    $hod_stmt = $pdo->prepare("
        SELECT f.full_name, f.designation, u.email, f.office_room
        FROM faculty f
        JOIN users u ON f.user_id = u.id
        WHERE f.department = ? AND f.is_hod = 1
        LIMIT 1
    ");
    $hod_stmt->execute([$faculty_dept]);
    $dept_hod = $hod_stmt->fetch();

    // Read-only view of department applications
    $apps_stmt = $pdo->prepare("
        SELECT sa.*, s.title as scholarship_title, s.provider, s.amount, 
               st.full_name as student_name, st.roll_no, st.department, st.semester, st.social_category
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        JOIN students st ON sa.student_id = st.id
        WHERE st.department = ?
        ORDER BY sa.applied_at DESC
    ");
    $apps_stmt->execute([$faculty_dept]);
    $apps = $apps_stmt->fetchAll();
}

// Stats
$total_dept_apps = count($apps);
$pending_endorsements = 0;
$endorsed_count = 0;
$disbursed_count = 0;

foreach ($apps as $a) {
    if ($a['faculty_verified'] === 'pending') $pending_endorsements++;
    if ($a['faculty_verified'] === 'verified') $endorsed_count++;
    if ($a['admin_status'] === 'approved') $disbursed_count++;
}

$page_title = $is_hod ? 'HOD Scholarship Verification' : 'Department Scholarships Desk';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">
          <?php if ($is_hod): ?>
            <i class="bi bi-patch-check-fill text-warning me-2"></i>HOD Scholarship Endorsement Terminal
          <?php else: ?>
            <i class="bi bi-award-fill text-primary me-2"></i>Department Scholarships Oversight
          <?php endif; ?>
        </h3>
        <p class="text-muted small mb-0">
          Department: <strong><?= e($faculty_dept) ?></strong> &bull; 
          <?php if ($is_hod): ?>
            <span class="badge bg-warning text-dark border"><i class="bi bi-star-fill me-1"></i> Authorized as Head of Department</span>
          <?php else: ?>
            <span class="badge bg-light text-dark border">Designation: <?= e($my_faculty['designation'] ?? 'Faculty') ?></span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if (!$is_hod): ?>
      <!-- Non-HOD Notice Card -->
      <div class="bento-card mb-4 p-4 border-warning bg-warning-subtle">
        <div class="d-flex align-items-start gap-3">
          <div class="fs-2 text-warning"><i class="bi bi-shield-lock-fill"></i></div>
          <div>
            <h5 class="fw-bold text-dark mb-1">HOD Verification Privilege Required</h5>
            <p class="small text-dark mb-2">
              Scholarship applications and academic credential verifications are restricted to the <strong>Head of Department (HOD)</strong> to maintain institutional integrity. 
              Only the appointed HOD for <strong><?= e($faculty_dept) ?></strong> can evaluate and endorse candidate applications to the Administration.
            </p>
            <div class="small bg-white p-2 rounded border d-inline-block">
              <i class="bi bi-person-badge-fill text-primary me-1"></i> 
              Current Department HOD: <strong><?= e($dept_hod['full_name'] ?? 'Pending Appointment by Admin') ?></strong> 
              <?php if (!empty($dept_hod['email'])): ?> &bull; <span class="text-muted"><?= e($dept_hod['email']) ?></span><?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Department Summary Metric Cards -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon">
              <i class="bi bi-people-fill"></i>
            </div>
            <div>
              <div class="metric-value"><?= $total_dept_apps ?></div>
              <div class="metric-label"><?= e($faculty_dept) ?> Applicants</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon warning">
              <i class="bi bi-clock-history"></i>
            </div>
            <div>
              <div class="metric-value text-warning"><?= $pending_endorsements ?></div>
              <div class="metric-label">Pending HOD Review</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon success">
              <i class="bi bi-patch-check-fill"></i>
            </div>
            <div>
              <div class="metric-value text-success"><?= $endorsed_count ?></div>
              <div class="metric-label">HOD Endorsed</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="bento-card">
          <div class="metric-box">
            <div class="metric-icon action">
              <i class="bi bi-cash-stack"></i>
            </div>
            <div>
              <div class="metric-value text-primary"><?= $disbursed_count ?></div>
              <div class="metric-label">Sanctioned by Admin</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Candidate Applications Table -->
    <div class="bento-card">
      <div class="bento-header">
        <div>
          <h5 class="bento-title">
            <i class="bi bi-award-fill text-warning"></i> 
            <?= $is_hod ? 'Department Applications Awaiting Endorsement' : 'Department Scholarship Applications (Read-Only)' ?>
          </h5>
          <div class="bento-subtitle">Applications restricted strictly to students enrolled in <strong><?= e($faculty_dept) ?></strong></div>
        </div>
        <span class="text-muted small"><?= count($apps) ?> Records</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Student Applicant</th>
              <th>Scholarship Scheme</th>
              <th>GPA & Family Income</th>
              <th>Proof Document</th>
              <th>HOD Endorsement</th>
              <th>Admin Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($apps)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                  No scholarship applications found from students in the <strong><?= e($faculty_dept) ?></strong> department.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($apps as $a): ?>
                <tr>
                  <td>
                    <strong class="text-dark"><?= e($a['student_name']) ?></strong>
                    <div class="small text-muted"><?= e($a['roll_no']) ?> &bull; <?= e($a['department']) ?> (<?= e($a['semester']) ?>)</div>
                  </td>
                  <td>
                    <div class="fw-bold text-primary"><?= e($a['scholarship_title']) ?></div>
                    <small class="text-success fw-semibold">Scheme: ₹<?= number_format($a['amount'], 2) ?></small>
                  </td>
                  <td>
                    <div>GPA: <strong><?= $a['gpa'] ?></strong></div>
                    <small class="text-muted">Income: ₹<?= number_format($a['annual_family_income'], 0) ?></small>
                  </td>
                  <td>
                    <?php if ($a['document_path'] && file_exists(UPLOAD_PATH . '/scholarships/' . $a['document_path'])): ?>
                      <a href="<?= BASE_URL ?>/uploads/scholarships/<?= e($a['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2">
                        <i class="bi bi-file-earmark-pdf me-1"></i> View Proof
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">No Document</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($a['faculty_verified'] === 'verified'): ?>
                      <span class="status-pill verified"><i class="bi bi-check-circle-fill me-1"></i> Endorsed</span>
                    <?php elseif ($a['faculty_verified'] === 'rejected'): ?>
                      <span class="status-pill rejected"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-pill pending"><i class="bi bi-clock-history me-1"></i> Pending HOD</span>
                    <?php endif; ?>
                    <?php if (!empty($a['faculty_remarks'])): ?>
                      <div class="small text-muted mt-1" style="font-size: 11px;">"<?= e(substr($a['faculty_remarks'], 0, 40)) ?><?= strlen($a['faculty_remarks']) > 40 ? '...' : '' ?>"</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($a['admin_status'] === 'approved'): ?>
                      <span class="status-pill verified"><i class="bi bi-award-fill me-1"></i> Sanctioned (₹<?= number_format($a['disbursed_amount'], 2) ?>)</span>
                    <?php elseif ($a['admin_status'] === 'rejected'): ?>
                      <span class="status-pill rejected"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-pill info"><i class="bi bi-hourglass-split me-1"></i> In Review</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if ($is_hod): ?>
                      <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $a['id'] ?>">
                        <i class="bi bi-clipboard-check me-1"></i> HOD Review
                      </button>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $a['id'] ?>">
                        <i class="bi bi-eye me-1"></i> View
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>

                <?php if ($is_hod): ?>
                  <!-- Modal: HOD Review Application -->
                  <div class="modal fade text-start" id="reviewModal<?= $a['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <div class="modal-header border-bottom bg-light">
                          <h5 class="modal-title fw-bold">
                            <i class="bi bi-star-fill text-warning me-1"></i> HOD Endorsement: <?= e($a['student_name']) ?>
                          </h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                          <input type="hidden" name="review_application" value="1">
                          <input type="hidden" name="application_id" value="<?= $a['id'] ?>">
                          <div class="modal-body">
                            <div class="p-3 bg-light rounded-3 mb-3">
                              <div class="d-flex justify-content-between mb-1">
                                <strong>Scholarship Scheme:</strong>
                                <span class="text-primary fw-bold"><?= e($a['scholarship_title']) ?></span>
                              </div>
                              <div class="d-flex justify-content-between mb-1">
                                <strong>Applicant Roll No:</strong>
                                <span><?= e($a['roll_no']) ?> (<?= e($a['department']) ?>)</span>
                              </div>
                              <div class="d-flex justify-content-between mb-1">
                                <strong>Academic GPA:</strong>
                                <span class="badge bg-primary"><?= $a['gpa'] ?> / 4.00</span>
                              </div>
                              <div class="d-flex justify-content-between">
                                <strong>Family Annual Income:</strong>
                                <span class="text-success fw-bold">₹<?= number_format($a['annual_family_income'], 2) ?></span>
                              </div>
                            </div>

                            <div class="p-3 bg-white rounded-3 mb-3 small border">
                              <strong class="text-dark d-block mb-1">Student Statement of Need:</strong>
                              <p class="text-secondary mb-0"><?= nl2br(e($a['statement'])) ?></p>
                            </div>

                            <?php if ($a['document_path'] && file_exists(UPLOAD_PATH . '/scholarships/' . $a['document_path'])): ?>
                              <div class="mb-3">
                                <label class="form-label small fw-bold text-dark">Attached Proof / Marksheet:</label><br>
                                <a href="<?= BASE_URL ?>/uploads/scholarships/<?= e($a['document_path']) ?>" target="_blank" class="bento-btn bento-btn-outline py-1 px-3 small">
                                  <i class="bi bi-file-earmark-pdf-fill me-1"></i> Open Attached Document
                                </a>
                              </div>
                            <?php endif; ?>

                            <div class="mb-3">
                              <label class="form-label small fw-bold text-dark">HOD Recommendation Decision *</label>
                              <select name="faculty_verified" class="form-select" required>
                                <option value="verified" <?= $a['faculty_verified'] === 'verified' ? 'selected' : '' ?>>Verify & Endorse to Central Administration</option>
                                <option value="rejected" <?= $a['faculty_verified'] === 'rejected' ? 'selected' : '' ?>>Reject Application (Does not meet criteria)</option>
                                <option value="pending" <?= $a['faculty_verified'] === 'pending' ? 'selected' : '' ?>>Hold as Pending / Awaiting Student Clarification</option>
                              </select>
                            </div>

                            <div class="mb-2">
                              <label class="form-label small fw-bold text-dark">HOD Endorsement Remarks & Notes</label>
                              <textarea name="faculty_remarks" class="form-control" rows="3" placeholder="Add comments regarding the candidate's academic standing, discipline, and attendance record..."><?= e($a['faculty_remarks'] ?? '') ?></textarea>
                            </div>
                          </div>
                          <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="bento-btn bento-btn-primary">Submit HOD Endorsement</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php else: ?>
                  <!-- Modal: Read-only view for non-HOD staff -->
                  <div class="modal fade text-start" id="viewModal<?= $a['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content border-0 shadow">
                        <div class="modal-header border-bottom">
                          <h5 class="modal-title fw-bold">Application Details: <?= e($a['student_name']) ?></h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <p><strong>Scholarship:</strong> <?= e($a['scholarship_title']) ?></p>
                          <p><strong>GPA:</strong> <?= $a['gpa'] ?> &bull; <strong>Income:</strong> ₹<?= number_format($a['annual_family_income'], 2) ?></p>
                          <div class="p-2 bg-light rounded small mb-3">
                            <strong>Statement:</strong> <?= nl2br(e($a['statement'])) ?>
                          </div>
                          <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle me-1"></i> Endorsements on scholarship applications are conducted exclusively by the Head of Department.
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
