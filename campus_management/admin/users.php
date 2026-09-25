<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

$role_filter = $_GET['role'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? 'password123';
    $role      = $_POST['role'] ?? 'student';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if ($username && $email && $password && $full_name) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            set_flash('danger', 'A user with this username or email already exists.');
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $hashed, $email, $role]);
            $new_user_id = $pdo->lastInsertId();

            if ($role === 'student') {
                $roll_no  = trim($_POST['roll_no'] ?? ('STU' . date('Y') . str_pad($new_user_id, 3, '0', STR_PAD_LEFT)));
                $dept     = trim($_POST['department'] ?? 'Computer Science & Engineering');
                $sem      = trim($_POST['semester'] ?? 'Semester 1');
                $batch    = trim($_POST['batch_year'] ?? date('Y') . '-' . (date('Y')+4));
                $gender   = trim($_POST['gender'] ?? 'Male');
                $soc_cat  = trim($_POST['social_category'] ?? 'BC');
                $pdo->prepare("INSERT INTO students (user_id, roll_no, full_name, department, semester, batch_year, gender, social_category, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$new_user_id, $roll_no, $full_name, $dept, $sem, $batch, $gender, $soc_cat, $phone]);
            } elseif ($role === 'faculty') {
                $emp_id  = trim($_POST['employee_id'] ?? ('FAC' . str_pad($new_user_id, 3, '0', STR_PAD_LEFT)));
                $dept    = trim($_POST['department'] ?? 'Computer Science & Engineering');
                $designation = trim($_POST['designation'] ?? 'Assistant Professor');
                $room    = trim($_POST['office_room'] ?? 'Main Block');
                $is_hod  = (!empty($_POST['is_hod']) || stripos($designation, 'HOD') !== false || stripos($designation, 'Head of Department') !== false) ? 1 : 0;
                
                if ($is_hod) {
                    $pdo->prepare("UPDATE faculty SET is_hod = 0, designation = 'Associate Professor' WHERE department = ? AND is_hod = 1")->execute([$dept]);
                    $designation = 'Head of Department (HOD)';
                }

                $pdo->prepare("INSERT INTO faculty (user_id, employee_id, full_name, department, designation, is_hod, office_room, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$new_user_id, $emp_id, $full_name, $dept, $designation, $is_hod, $room, $phone]);
            }
            set_flash('success', "User account for '$full_name' ($role) created successfully!");
            redirect('admin/users.php');
        }
    } else {
        set_flash('danger', 'Please fill in all mandatory fields.');
    }
}

// Handle Toggle Status
if (isset($_GET['toggle_id'])) {
    $uid = (int)$_GET['toggle_id'];
    $u = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $u->execute([$uid]);
    $user_row = $u->fetch();
    if ($user_row && $uid !== $_SESSION['user_id']) {
        $new_st = ($user_row['status'] === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_st, $uid]);
        set_flash('info', 'User status updated.');
    }
    redirect('admin/users.php');
}

$sql = "
    SELECT u.*, 
           s.roll_no, s.full_name as student_name, s.department as student_dept, s.phone as student_phone,
           f.employee_id, f.full_name as faculty_name, f.department as faculty_dept, f.designation, f.phone as faculty_phone
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    WHERE 1=1
";
$params = [];

if ($role_filter !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}
if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR s.full_name LIKE ? OR f.full_name LIKE ? OR s.roll_no LIKE ? OR f.employee_id LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = "User Management";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Campus Directory & User Accounts</h2>
        <p class="text-muted mb-0">Provision accounts, manage security credentials, and view role memberships.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus-fill me-1"></i> Add New User
      </button>
    </div>

    <!-- Filters Bar -->
    <div class="bento-card mb-4 p-3">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search by name, email, ID..." value="<?= e($search) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <select name="role" class="form-select" onchange="this.form.submit()">
            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Students</option>
            <option value="faculty" <?= $role_filter === 'faculty' ? 'selected' : '' ?>>Faculty</option>
            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrators</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        </div>
        <div class="col-md-3 text-end">
          <span class="text-muted small">Showing <strong><?= count($users) ?></strong> users</span>
        </div>
      </form>
    </div>

    <!-- Users Table -->
    <div class="bento-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Identifier / Designation</th>
              <th>Department</th>
              <th>Email</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No users matched your search criteria.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <?php 
                  $display_name = $u['student_name'] ?? $u['faculty_name'] ?? $u['username'];
                  $dept = $u['student_dept'] ?? $u['faculty_dept'] ?? 'Administration';
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="rounded-circle bg-light border p-2 text-primary fw-bold me-2 text-center" style="width: 38px; height: 38px; line-height: 20px;">
                        <?= strtoupper(substr($display_name, 0, 1)) ?>
                      </div>
                      <div>
                        <div class="fw-bold"><?= e($display_name) ?></div>
                        <small class="text-muted">@<?= e($u['username']) ?></small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : ($u['role'] === 'faculty' ? 'bg-primary' : 'bg-success') ?>">
                      <?= ucfirst($u['role']) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($u['role'] === 'student'): ?>
                      <code><?= e($u['roll_no'] ?? 'N/A') ?></code>
                    <?php elseif ($u['role'] === 'faculty'): ?>
                      <code><?= e($u['employee_id'] ?? 'N/A') ?></code>
                      <small class="text-muted d-block"><?= e($u['designation'] ?? '') ?></small>
                    <?php else: ?>
                      <span class="text-muted small">System Admin</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="small"><?= e($dept) ?></span></td>
                  <td><div class="small"><?= e($u['email']) ?></div></td>
                  <td>
                    <?php if ($u['status'] === 'active'): ?>
                      <span class="badge bg-success-subtle text-success border border-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                      <a href="<?= BASE_URL ?>/admin/users.php?toggle_id=<?= $u['id'] ?>" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="Toggle status">
                        <i class="bi <?= $u['status'] === 'active' ? 'bi-person-slash' : 'bi-person-check' ?>"></i>
                      </a>
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

<!-- Modal: Create User -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="create_user">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Provision New User Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">User Role</label>
            <select name="role" id="roleSelect" class="form-select" onchange="toggleRoleFields(this.value)">
              <option value="student">Student</option>
              <option value="faculty">Faculty</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Username</label>
              <input type="text" name="username" class="form-control" required placeholder="e.g. john.doe">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Initial Password</label>
              <input type="password" name="password" class="form-control" required placeholder="Password">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Full Legal Name</label>
            <input type="text" name="full_name" class="form-control" required placeholder="Full Name">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" name="email" class="form-control" required placeholder="user@campus.edu">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="+1-555-0199">
            </div>
          </div>

          <div id="deptField" class="mb-3">
            <label class="form-label fw-semibold">Department</label>
            <input type="text" name="department" class="form-control" value="Computer Science & Engineering" required>
          </div>

          <div id="studentFields" class="mb-3">
            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Roll Number</label>
                <input type="text" name="roll_no" class="form-control" placeholder="e.g. CS-2026-001">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Current Semester</label>
                <input type="text" name="semester" class="form-control" value="Semester 1">
              </div>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Gender</label>
                <select name="gender" class="form-select">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Social Category</label>
                <select name="social_category" class="form-select">
                  <option value="OC">OC</option>
                  <option value="BC" selected>BC</option>
                  <option value="MBC">MBC</option>
                  <option value="SC">SC</option>
                  <option value="ST">ST</option>
                </select>
              </div>
            </div>
          </div>

          <div id="facultyFields" class="mb-3" style="display:none;">
            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Employee ID</label>
                <input type="text" name="employee_id" class="form-control" placeholder="e.g. FAC-CS-01">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Designation</label>
                <input type="text" name="designation" class="form-control" placeholder="e.g. Assistant Professor">
              </div>
            </div>
            <div class="form-check mt-2 p-2 bg-light rounded border">
              <input class="form-check-input ms-1 me-2" type="checkbox" name="is_hod" value="1" id="isHodCheck">
              <label class="form-check-label fw-semibold text-dark small" for="isHodCheck">
                <i class="bi bi-star-fill text-warning me-1"></i> Designate as Department Head (HOD)
              </label>
              <div class="text-muted" style="font-size: 11px; padding-left: 2rem;">Appoints as the sole HOD for this department (replaces any previous HOD).</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleRoleFields(role) {
    const dept = document.getElementById('deptField');
    const stud = document.getElementById('studentFields');
    const fac = document.getElementById('facultyFields');
    if (role === 'admin') {
        dept.style.display = 'none';
        stud.style.display = 'none';
        fac.style.display = 'none';
    } else if (role === 'faculty') {
        dept.style.display = 'block';
        stud.style.display = 'none';
        fac.style.display = 'block';
    } else {
        dept.style.display = 'block';
        stud.style.display = 'block';
        fac.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
