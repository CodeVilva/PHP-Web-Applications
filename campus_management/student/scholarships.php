<?php
require_once __DIR__ . '/../config/config.php';
require_auth('student');

$student_id = $_SESSION['student_id'] ?? null;
$user_id = $_SESSION['user_id'];
$msg = '';
$err = '';

// Get Student profile
$st = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$st->execute([$student_id]);
$student = $st->fetch();

// Handle Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scholarship_id = intval($_POST['scholarship_id'] ?? 0);
    $annual_income = floatval($_POST['annual_family_income'] ?? 0);
    $gpa = floatval($_POST['gpa'] ?? 3.5);
    $statement = trim($_POST['statement'] ?? '');

    $document_path = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['document']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $newname = 'sch_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_dir = UPLOAD_PATH . '/scholarships/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (move_uploaded_file($_FILES['document']['tmp_name'], $upload_dir . $newname)) {
                $document_path = 'uploads/scholarships/' . $newname;
            } else {
                $err = 'Failed to upload scholarship supporting document.';
            }
        } else {
            $err = 'Invalid document format. Only PDF, JPG, PNG allowed.';
        }
    }

    if (empty($err)) {
        if ($scholarship_id <= 0) {
            $err = 'Please select a valid scholarship scheme.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO scholarship_applications (scholarship_id, student_id, gpa, annual_family_income, statement, document_path, faculty_verified, admin_status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')");
                $stmt->execute([$scholarship_id, $student_id, $gpa, $annual_income, $statement, $document_path]);
                set_flash('success', 'Scholarship application submitted successfully! It is now under verification by the scholarship cell.');
                redirect('student/scholarships.php');
            } catch (Exception $e) {
                $err = 'Database Error: ' . $e->getMessage();
            }
        }
    }
}

// Available schemes
$schemes = $pdo->query("SELECT * FROM scholarships WHERE status = 'active' ORDER BY amount DESC")->fetchAll();

// Fetch My Applications
$my_apps = [];
try {
    $stmt = $pdo->prepare("
        SELECT sa.*, s.title as scholarship_name, s.provider, s.amount as max_amount,
               f.full_name as hod_name
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        LEFT JOIN faculty f ON sa.faculty_id = f.id
        WHERE sa.student_id = ?
        ORDER BY sa.applied_at DESC
    ");
    $stmt->execute([$student_id]);
    $my_apps = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch Beneficiaries / Recipients Directory (Public roll)
$beneficiaries = [];
try {
    $stmt = $pdo->query("
        SELECT sa.disbursed_amount, sa.applied_at, s.title as scholarship_name, st.full_name as student_name, st.department, st.social_category
        FROM scholarship_applications sa
        JOIN scholarships s ON sa.scholarship_id = s.id
        JOIN students st ON sa.student_id = st.id
        WHERE sa.admin_status = 'approved'
        ORDER BY sa.applied_at DESC LIMIT 20
    ");
    $beneficiaries = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = "Scholarships & Financial Aid";
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
                        <h3 class="fw-bold mb-1 text-white"><i class="bi bi-mortarboard-fill me-2"></i>Scholarships & Govt Welfare Schemes</h3>
                        <p class="mb-0 text-white-50">Apply for Post-Matric SC/ST Scholarships, Pudhumai Penn Scheme, 7.5% Govt Quota Aid, and Merit Grants.</p>
                    </div>
                    <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#applyScholarshipModal">
                        <i class="bi bi-file-earmark-plus me-1"></i> Apply for Scholarship
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

    <!-- Scheme Information Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="bento-card p-4 h-100 border-start border-4 border-primary">
                <span class="badge bg-primary mb-2">Govt of Tamil Nadu</span>
                <h5 class="fw-bold text-dark">SC / ST Post-Matric</h5>
                <p class="small text-muted mb-2">100% Tuition fee waiver & maintenance allowance for SC/ST students with family income under ₹2.5 Lakhs.</p>
                <span class="badge bg-light text-dark border">SC / ST Category</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bento-card p-4 h-100 border-start border-4 border-danger">
                <span class="badge bg-danger mb-2">Moovalur Ramamirtham</span>
                <h5 class="fw-bold text-dark">Pudhumai Penn Scheme</h5>
                <p class="small text-muted mb-2">₹1,000/month financial grant for female students who studied Classes 6 to 12 in Tamil Nadu Govt Schools.</p>
                <span class="badge bg-light text-dark border">All Female Students</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bento-card p-4 h-100 border-start border-4 border-success">
                <span class="badge bg-success mb-2">State Welfare</span>
                <h5 class="fw-bold text-dark">7.5% Govt School Quota</h5>
                <p class="small text-muted mb-2">Full scholarship covering complete tuition, hostel, and examination fees under preferential admissions.</p>
                <span class="badge bg-light text-dark border">Govt School Students</span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bento-card p-4 h-100 border-start border-4 border-warning">
                <span class="badge bg-warning text-dark mb-2">Backward Classes Dept</span>
                <h5 class="fw-bold text-dark">BC / MBC / DNC Aid</h5>
                <p class="small text-muted mb-2">Free education and fee concessions for Backward and Most Backward classes with eligible annual income limits.</p>
                <span class="badge bg-light text-dark border">BC / MBC Category</span>
            </div>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-pills mb-4" id="schTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="myapps-tab" data-bs-toggle="tab" data-bs-target="#myapps-pane">
                <i class="bi bi-person-lines-fill me-1"></i> My Applications (<?= count($my_apps) ?>)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="recipients-tab" data-bs-toggle="tab" data-bs-target="#recipients-pane">
                <i class="bi bi-trophy me-1"></i> Beneficiaries Roll / Sanctions Directory
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- My Applications -->
        <div class="tab-pane fade show active" id="myapps-pane">
            <div class="bento-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-check text-primary me-2"></i>My Submitted Scholarship Applications</h5>
                <?php if (empty($my_apps)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-mortarboard text-muted display-4"></i>
                        <p class="text-muted mt-2">You have not applied for any scholarship yet.</p>
                        <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#applyScholarshipModal">Apply Now</button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Scheme Name</th>
                                    <th>GPA & Income</th>
                                    <th>Applied Date</th>
                                    <th>Department HOD Endorsement</th>
                                    <th>Admin Sanction & Grant</th>
                                    <th>Remarks / Sanction Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_apps as $ma): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-primary"><?= htmlspecialchars($ma['scholarship_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ma['provider']) ?></small>
                                    </td>
                                    <td>
                                        <div>GPA: <strong><?= number_format($ma['gpa'], 2) ?></strong></div>
                                        <small class="text-muted">Income: ₹<?= number_format(floatval($ma['annual_family_income']), 0) ?></small>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($ma['applied_at'])) ?></td>
                                    <td>
                                        <?php if ($ma['faculty_verified'] === 'verified'): ?>
                                            <span class="status-pill verified"><i class="bi bi-check-circle-fill me-1"></i> Endorsed</span>
                                            <?php if (!empty($ma['hod_name'])): ?>
                                                <div class="small text-muted" style="font-size:11px;">By <?= htmlspecialchars($ma['hod_name']) ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($ma['faculty_verified'] === 'rejected'): ?>
                                            <span class="status-pill rejected"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="status-pill pending"><i class="bi bi-clock-history me-1"></i> Awaiting HOD</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ma['admin_status'] === 'approved'): ?>
                                            <span class="status-pill verified">
                                                <i class="bi bi-award-fill me-1"></i> Sanctioned (₹<?= number_format(floatval($ma['disbursed_amount'] ?? 0), 2) ?>)
                                            </span>
                                        <?php elseif ($ma['admin_status'] === 'rejected'): ?>
                                            <span class="status-pill rejected"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                                        <?php else: ?>
                                            <span class="status-pill info"><i class="bi bi-hourglass-split me-1"></i> Under Review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $remarks_arr = [];
                                            if (!empty($ma['faculty_remarks'])) $remarks_arr[] = 'HOD: ' . $ma['faculty_remarks'];
                                            if (!empty($ma['admin_remarks'])) $remarks_arr[] = 'Admin: ' . $ma['admin_remarks'];
                                            $remarks_text = !empty($remarks_arr) ? implode(' | ', $remarks_arr) : 'Application under active evaluation';
                                        ?>
                                        <small class="text-secondary"><?= htmlspecialchars($remarks_text) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Beneficiaries Directory -->
        <div class="tab-pane fade" id="recipients-pane">
            <div class="bento-card p-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-award-fill text-warning me-2"></i>Campus Scholarship Beneficiaries Directory</h5>
                <?php if (empty($beneficiaries)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people text-muted display-4"></i>
                        <p class="text-muted mt-2">No disbursed scholarship records yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Department</th>
                                    <th>Category</th>
                                    <th>Scheme Conferred</th>
                                    <th>Sanction Grant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($beneficiaries as $ben): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($ben['student_name']) ?></td>
                                    <td><?= htmlspecialchars($ben['department']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ben['social_category'] ?? 'OC') ?></span></td>
                                    <td class="text-primary fw-semibold"><?= htmlspecialchars($ben['scholarship_name']) ?></td>
                                    <td class="fw-bold text-success">₹<?= number_format(floatval($ben['disbursed_amount'] ?? 0), 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Apply Modal -->
    <div class="modal fade" id="applyScholarshipModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-mortarboard-fill text-primary me-2"></i>Scholarship Application Form</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Select Scholarship Scheme *</label>
                                <select name="scholarship_id" class="form-select" required>
                                    <?php foreach ($schemes as $sc): ?>
                                        <option value="<?= $sc['id'] ?>"><?= htmlspecialchars($sc['title']) ?> (Max ₹<?= number_format($sc['amount']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Your Social Category</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['social_category'] ?? 'OC') ?>" disabled>
                                <div class="form-text">Auto-verified from your student enrollment profile.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Annual Family Income (₹) *</label>
                                <input type="number" step="0.01" name="annual_family_income" class="form-control" placeholder="e.g. 150000" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Current CGPA *</label>
                                <input type="number" step="0.01" name="gpa" class="form-control" value="<?= htmlspecialchars($student['cgpa'] ?? '3.50') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supporting Document (Income/Community Cert) *</label>
                                <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="form-text">PDF or Image (Max 5MB).</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Statement of Purpose / Need</label>
                                <textarea name="statement" rows="3" class="form-control" placeholder="Briefly describe your academic background and financial need..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
