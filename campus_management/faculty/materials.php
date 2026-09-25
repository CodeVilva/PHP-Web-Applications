<?php
require_once __DIR__ . '/../config/config.php';
require_auth('faculty');

$faculty_id = $_SESSION['faculty_id'] ?? null;

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($subject_code) || empty($subject_name) || empty($title)) {
        set_flash('danger', 'Please enter subject code, subject name, and document title.');
    } else {
        $file_name_db = '';
        $file_type    = 'PDF';
        $file_size    = '0 KB';

        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['material_file']['tmp_name'];
            $orig_name = $_FILES['material_file']['name'];
            $size_num  = $_FILES['material_file']['size'];
            $ext       = strtoupper(pathinfo($orig_name, PATHINFO_EXTENSION));

            $file_type = $ext ?: 'PDF';
            $file_size = format_bytes($size_num);

            $safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $subject_code) . '_' . time() . '.' . strtolower($ext);
            $upload_dir    = UPLOAD_PATH . '/materials/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $target_file = $upload_dir . $safe_filename;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $file_name_db = $safe_filename;
            } else {
                set_flash('danger', 'Failed to save uploaded file on server. Check folder permissions.');
            }
        } else {
            // Optional fallback if file is not selected but text notes entered
            $file_type = $_POST['file_type'] ?? 'PDF';
            $file_name_db = 'notes_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $subject_code)) . '_' . time() . '.' . strtolower($file_type);
            $file_size = 'Text Resource';
        }

        if ($file_name_db) {
            $ins = $pdo->prepare("
                INSERT INTO learning_materials (faculty_id, subject_code, subject_name, title, description, file_path, file_type, file_size, download_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $ins->execute([$faculty_id, $subject_code, $subject_name, $title, $description, $file_name_db, $file_type, $file_size]);
            set_flash('success', "Learning material '{$title}' published successfully for students!");
            redirect('faculty/materials.php');
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM learning_materials WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$del_id, $faculty_id]);
    $mat = $stmt->fetch();
    if ($mat) {
        $real_path = UPLOAD_PATH . '/materials/' . $mat['file_path'];
        if (file_exists($real_path)) {
            @unlink($real_path);
        }
        $pdo->prepare("DELETE FROM learning_materials WHERE id = ? AND faculty_id = ?")->execute([$del_id, $faculty_id]);
        set_flash('info', 'Study material removed.');
    }
    redirect('faculty/materials.php');
}

$materials = $pdo->prepare("SELECT * FROM learning_materials WHERE faculty_id = ? ORDER BY created_at DESC");
$materials->execute([$faculty_id]);
$my_materials = $materials->fetchAll();

$page_title = 'Upload Learning Materials';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Online Learning Materials</h3>
        <p class="text-muted small mb-0">Module 4: Upload syllabus notes, lecture slides, question banks, and monographs</p>
      </div>
      <button type="button" class="bento-btn bento-btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload New Resource
      </button>
    </div>

    <div class="bento-card">
      <div class="bento-header">
        <h5 class="bento-title"><i class="bi bi-files text-primary"></i> My Uploaded Course Materials</h5>
        <span class="text-muted small"><?= count($my_materials) ?> Files</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Title & Description</th>
              <th>Subject</th>
              <th>File Type</th>
              <th>File Size</th>
              <th>Downloads</th>
              <th>Upload Date</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($my_materials)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No materials uploaded yet. Click <strong>Upload New Resource</strong> above.</td></tr>
            <?php else: ?>
              <?php foreach ($my_materials as $m): ?>
                <?php 
                  $file_link = BASE_URL . '/uploads/materials/' . e($m['file_path']);
                  $file_exists = file_exists(UPLOAD_PATH . '/materials/' . $m['file_path']);
                ?>
                <tr>
                  <td>
                    <div class="fw-bold text-dark"><?= e($m['title']) ?></div>
                    <small class="text-muted"><?= e($m['description']) ?></small>
                  </td>
                  <td>
                    <span class="badge bg-light text-primary border"><?= e($m['subject_code']) ?></span>
                    <div class="small text-muted"><?= e($m['subject_name']) ?></div>
                  </td>
                  <td><span class="badge bg-secondary"><?= e($m['file_type']) ?></span></td>
                  <td><small class="text-muted"><?= e($m['file_size']) ?></small></td>
                  <td><strong class="text-primary"><?= $m['download_count'] ?></strong></td>
                  <td><?= date('M d, Y', strtotime($m['created_at'])) ?></td>
                  <td class="text-end">
                    <?php if ($file_exists): ?>
                      <a href="<?= $file_link ?>" download class="btn btn-sm btn-outline-primary me-1" title="Download Resource">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download
                      </a>
                    <?php endif; ?>
                    <a href="materials.php?delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this material permanently?')" title="Delete">
                      <i class="bi bi-trash"></i>
                    </a>
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

<!-- Modal: Upload Resource -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Upload Course Material</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="materials.php" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Subject Code *</label>
              <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS501" required>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold">Subject Name *</label>
              <input type="text" name="subject_name" class="form-control" placeholder="e.g. Database Management Systems" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Document Title *</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Unit 2: Relational Calculus & Normalization" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Select File from Device *</label>
              <input type="file" name="material_file" id="material_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.png,.jpg,.jpeg" required>
              <small class="text-muted" style="font-size:11px;">Supported formats: PDF, DOCX, PPTX, XLSX, TXT, ZIP, Images</small>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Brief Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Summary of contents, key topics covered..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="bento-btn bento-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="upload_material" class="bento-btn bento-btn-primary">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload & Publish
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
