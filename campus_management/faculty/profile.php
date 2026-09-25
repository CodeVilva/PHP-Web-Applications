<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$faculty_id = $_SESSION['faculty_id'] ?? null;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $designation = trim($_POST['designation']);
    $qualification = trim($_POST['qualification']);
    $phone = trim($_POST['phone']);
    $office_room = trim($_POST['office_room']);
    $bio = trim($_POST['bio']);

    $upd = $pdo->prepare("
        UPDATE faculty 
        SET full_name = ?, designation = ?, qualification = ?, phone = ?, office_room = ?, bio = ?
        WHERE id = ?
    ");
    $upd->execute([$full_name, $designation, $qualification, $phone, $office_room, $bio, $faculty_id]);
    $_SESSION['full_name'] = $full_name;
    set_flash('success', 'Faculty profile updated successfully.');
    redirect('faculty/profile.php');
}

$fac = $pdo->prepare("SELECT f.*, u.email, u.username FROM faculty f JOIN users u ON f.user_id = u.id WHERE f.id = ?");
$fac->execute([$faculty_id]);
$f = $fac->fetch();

$page_title = 'Faculty Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="mb-4">
      <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Faculty Profile Management</h3>
      <p class="text-muted small mb-0">Module 3: Update your academic background, research interests, office hours, and contact information</p>
    </div>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="bento-card text-center h-100">
          <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-bold fs-1 mb-3 mx-auto shadow" style="width: 100px; height: 100px; background: var(--brand-primary);">
            <?= strtoupper(substr($f['full_name'] ?? 'F', 0, 1)) ?>
          </div>
          <h4 class="fw-bold mb-1 text-dark"><?= e($f['full_name']) ?></h4>
          <span class="badge bg-light text-primary border mb-3"><?= e($f['designation']) ?></span>

          <div class="text-start bg-light p-3 rounded-3 small">
            <div class="mb-1"><strong>Employee ID:</strong> <?= e($f['employee_id']) ?></div>
            <div class="mb-1"><strong>Department:</strong> <?= e($f['department']) ?></div>
            <div class="mb-1"><strong>Email:</strong> <?= e($f['email']) ?></div>
            <div><strong>Office:</strong> <?= e($f['office_room']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="bento-card">
          <div class="bento-header">
            <h5 class="bento-title"><i class="bi bi-pencil-square text-primary"></i> Edit Profile Information</h5>
          </div>

          <form method="POST">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-bold">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($f['full_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Designation</label>
                <input type="text" name="designation" class="form-control" value="<?= e($f['designation']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Highest Qualification</label>
                <input type="text" name="qualification" class="form-control" value="<?= e($f['qualification']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= e($f['phone']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold">Office Room Location</label>
                <input type="text" name="office_room" class="form-control" value="<?= e($f['office_room']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label small fw-bold">Academic Bio & Research Focus</label>
                <textarea name="bio" class="form-control" rows="4"><?= e($f['bio']) ?></textarea>
              </div>
            </div>

            <div class="mt-4 text-end">
              <button type="submit" name="update_profile" class="bento-btn bento-btn-primary py-2 px-4">
                <i class="bi bi-check2-circle me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
