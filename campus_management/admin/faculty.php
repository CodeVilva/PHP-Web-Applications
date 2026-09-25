<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Handle Appoint HOD Action (Strictly 1 HOD per Department)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'appoint_hod') {
    $fac_id = (int)$_POST['faculty_id'];
    
    // Fetch faculty info
    $stmt = $pdo->prepare("SELECT id, full_name, department FROM faculty WHERE id = ?");
    $stmt->execute([$fac_id]);
    $fac = $stmt->fetch();

    if ($fac) {
        $dept = $fac['department'];
        // 1. Reset any existing HOD for this department to Associate Professor
        $reset = $pdo->prepare("
            UPDATE faculty 
            SET is_hod = 0, 
                designation = CASE WHEN designation LIKE '%HOD%' OR designation LIKE '%Head%' THEN 'Associate Professor' ELSE designation END 
            WHERE department = ? AND is_hod = 1
        ");
        $reset->execute([$dept]);

        // 2. Appoint this faculty as the unique HOD for this department
        $set = $pdo->prepare("UPDATE faculty SET is_hod = 1, designation = 'Head of Department (HOD)' WHERE id = ?");
        $set->execute([$fac_id]);

        set_flash('success', "{$fac['full_name']} has been successfully appointed as the sole Head of Department (HOD) for '$dept'!");
        redirect('admin/faculty.php');
    }
}

$faculty = $pdo->query("
    SELECT f.*, u.email, u.status as user_status,
           (SELECT COUNT(*) FROM timetable t WHERE t.faculty_id = f.id) as slot_count,
           (SELECT COUNT(*) FROM learning_materials lm WHERE lm.faculty_id = f.id) as material_count
    FROM faculty f
    JOIN users u ON f.user_id = u.id
    ORDER BY f.department ASC, f.is_hod DESC, f.full_name ASC
")->fetchAll();

$page_title = "Faculty Directory & Workload";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Faculty & Department Head (HOD) Governance</h2>
        <p class="text-muted mb-0">Academic designations, HOD appointments (1 HOD per department), and workload distributions.</p>
      </div>
      <a href="<?= BASE_URL ?>/admin/users.php?role=faculty" class="bento-btn bento-btn-primary">
        <i class="bi bi-person-plus-fill me-1"></i> Provision Faculty
      </a>
    </div>

    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-person-badge-fill text-primary"></i> Academic Faculty & HOD Roster</h5>
        <span class="text-muted small"><?= count($faculty) ?> Staff Members</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Faculty Member</th>
              <th>Employee ID & Rank</th>
              <th>Department</th>
              <th>HOD Status</th>
              <th>Teaching Workload</th>
              <th>Course Notes</th>
              <th>Status</th>
              <th class="text-end">HOD Governance</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($faculty)): ?>
              <tr><td colspan="8" class="text-center py-4 text-muted">No faculty members registered yet.</td></tr>
            <?php else: ?>
              <?php foreach ($faculty as $f): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="rounded-circle <?= !empty($f['is_hod']) ? 'bg-warning-subtle text-dark border border-warning' : 'bg-primary-subtle text-primary' ?> fw-bold p-2 text-center me-2" style="width: 38px; height: 38px;">
                        <?= strtoupper(substr($f['full_name'], 0, 1)) ?>
                      </div>
                      <div>
                        <div class="fw-bold"><?= e($f['full_name']) ?></div>
                        <small class="text-muted"><?= e($f['email']) ?></small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <code><?= e($f['employee_id']) ?></code>
                    <div class="small text-muted"><?= e($f['designation']) ?></div>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= e($f['department']) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($f['is_hod'])): ?>
                      <span class="badge bg-warning text-dark border border-warning fw-bold">
                        <i class="bi bi-star-fill text-dark me-1"></i> Head of Dept
                      </span>
                    <?php else: ?>
                      <span class="text-muted small">Faculty Member</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="fw-bold text-primary"><?= $f['slot_count'] ?></span>
                    <small class="text-muted">weekly slots</small>
                  </td>
                  <td>
                    <span class="fw-bold"><?= $f['material_count'] ?></span>
                    <small class="text-muted">published</small>
                  </td>
                  <td>
                    <?php if ($f['user_status'] === 'active'): ?>
                      <span class="badge bg-success-subtle text-success border border-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if (empty($f['is_hod'])): ?>
                      <form method="POST" class="d-inline" onsubmit="return confirm('Designate <?= addslashes($f['full_name']) ?> as the sole HOD for <?= addslashes($f['department']) ?>? (Any existing HOD will be updated to standard faculty)');">
                        <input type="hidden" name="action" value="appoint_hod">
                        <input type="hidden" name="faculty_id" value="<?= $f['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark fw-semibold" title="Appoint as Department HOD">
                          <i class="bi bi-award-fill text-warning me-1"></i> Make HOD
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success border border-success">
                        <i class="bi bi-check2-circle me-1"></i> Active HOD
                      </span>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
