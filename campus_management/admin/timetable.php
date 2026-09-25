<?php
require_once __DIR__ . '/../config/config.php';
require_auth('admin');

// Handle add slot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot') {
    $dept       = trim($_POST['department']);
    $sem        = trim($_POST['semester']);
    $day        = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time   = $_POST['end_time'];
    $code       = trim($_POST['subject_code']);
    $name       = trim($_POST['subject_name']);
    $faculty_id = (int)$_POST['faculty_id'];
    $room       = trim($_POST['room_no']);

    $stmt = $pdo->prepare("
        INSERT INTO timetable (department, semester, day_of_week, start_time, end_time, subject_code, subject_name, faculty_id, room_no)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$dept, $sem, $day, $start_time, $end_time, $code, $name, $faculty_id, $room]);
    set_flash('success', "Timetable slot for $day ($room) created successfully!");
    redirect('admin/timetable.php');
}

// Handle delete slot
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM timetable WHERE id = ?")->execute([$del_id]);
    set_flash('info', 'Timetable slot removed.');
    redirect('admin/timetable.php');
}

$faculty_list = $pdo->query("SELECT id, full_name, employee_id, department, is_hod FROM faculty ORDER BY full_name ASC")->fetchAll();

$default_departments = [
    'Computer Science & Engineering',
    'Information Technology',
    'Electronics & Communication Engineering',
    'Electrical & Electronics Engineering',
    'Mechanical Engineering',
    'Civil Engineering'
];
$db_depts = $pdo->query("
    SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' 
    UNION 
    SELECT DISTINCT department FROM students WHERE department IS NOT NULL AND department != ''
    UNION
    SELECT DISTINCT department FROM timetable WHERE department IS NOT NULL AND department != ''
")->fetchAll(PDO::FETCH_COLUMN);

$departments = array_values(array_unique(array_merge($default_departments, $db_depts)));

$day_filter = $_GET['day'] ?? 'all';
$sql = "
    SELECT t.*, f.full_name as faculty_name, f.employee_id
    FROM timetable t
    LEFT JOIN faculty f ON t.faculty_id = f.id
    WHERE 1=1
";
$params = [];
if ($day_filter !== 'all') {
    $sql .= " AND t.day_of_week = ?";
    $params[] = $day_filter;
}
$sql .= " ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$slots = $stmt->fetchAll();

$page_title = "Master Timetable Manager";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="h3 fw-bold mb-1" style="color: var(--text-slate);">Campus Schedule & Room Allocation</h2>
        <p class="text-muted mb-0">Master institutional scheduling, lecture halls, and instructor allocations.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSlotModal">
        <i class="bi bi-calendar-plus-fill me-1"></i> Add Schedule Slot
      </button>
    </div>

    <!-- Day Filter -->
    <div class="bento-card mb-4 p-3">
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/admin/timetable.php" class="btn btn-sm <?= $day_filter === 'all' ? 'btn-primary' : 'btn-light border' ?>">All Days</a>
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d): ?>
          <a href="<?= BASE_URL ?>/admin/timetable.php?day=<?= $d ?>" class="btn btn-sm <?= $day_filter === $d ? 'btn-primary' : 'btn-light border' ?>"><?= $d ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Master Schedule Grid -->
    <div class="bento-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small">
            <tr>
              <th>Day</th>
              <th>Time</th>
              <th>Subject</th>
              <th>Department / Sem</th>
              <th>Faculty Instructor</th>
              <th>Hall / Room</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($slots)): ?>
              <tr><td colspan="7" class="text-center py-4 text-muted">No scheduled timetable slots found.</td></tr>
            <?php else: ?>
              <?php foreach ($slots as $s): ?>
                <tr>
                  <td><span class="badge bg-light text-dark border fw-bold"><?= $s['day_of_week'] ?></span></td>
                  <td><code class="fw-bold"><?= date('h:i A', strtotime($s['start_time'])) ?> - <?= date('h:i A', strtotime($s['end_time'])) ?></code></td>
                  <td>
                    <strong><?= e($s['subject_code']) ?></strong> &bull; <?= e($s['subject_name']) ?>
                  </td>
                  <td><span class="small text-muted"><?= e($s['department']) ?> (<?= e($s['semester']) ?>)</span></td>
                  <td><?= e($s['faculty_name'] ?? 'Unassigned') ?></td>
                  <td><span class="badge bg-dark-subtle text-dark border"><?= e($s['room_no']) ?></span></td>
                  <td class="text-end">
                    <a href="<?= BASE_URL ?>/admin/timetable.php?delete_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this slot?')" title="Delete Slot">
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

<!-- Modal: Add Slot -->
<div class="modal fade" id="addSlotModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_slot">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Add Timetable Slot</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Subject Code *</label>
              <input type="text" name="subject_code" class="form-control" required placeholder="e.g. CS101">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Subject Name *</label>
              <input type="text" name="subject_name" class="form-control" required placeholder="e.g. Data Structures">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Department *</label>
              <select name="department" id="departmentSelect" class="form-select" required>
                <option value="">-- Select Department --</option>
                <?php foreach ($departments as $dept): ?>
                  <option value="<?= e($dept) ?>"><?= e($dept) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Semester *</label>
              <select name="semester" class="form-select" required>
                <option value="Semester 1">Semester 1</option>
                <option value="Semester 2">Semester 2</option>
                <option value="Semester 3">Semester 3</option>
                <option value="Semester 4">Semester 4</option>
                <option value="Semester 5">Semester 5</option>
                <option value="Semester 6">Semester 6</option>
                <option value="Semester 7">Semester 7</option>
                <option value="Semester 8">Semester 8</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Instructor Faculty *</label>
            <select name="faculty_id" id="facultySelect" class="form-select" required>
              <option value="">-- Select Department First --</option>
            </select>
            <div class="form-text small text-muted" id="facultyHint">Instructors are automatically filtered by selected department.</div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Day of Week *</label>
              <select name="day_of_week" class="form-select" required>
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
                <option value="Saturday">Saturday</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Room / Hall *</label>
              <input type="text" name="room_no" class="form-control" required placeholder="e.g. Hall 101">
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Start Time *</label>
              <input type="time" name="start_time" class="form-control" required value="09:00">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">End Time *</label>
              <input type="time" name="end_time" class="form-control" required value="10:30">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Allocate & Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const allFaculty = <?= json_encode($faculty_list) ?>;

function matchDept(facultyDept, selectedDept) {
  if (!facultyDept || !selectedDept) return false;
  const f = facultyDept.toLowerCase().replace(/[^a-z0-9]/g, '');
  const s = selectedDept.toLowerCase().replace(/[^a-z0-9]/g, '');
  if (f === s) return true;
  if (f.includes(s) || s.includes(f)) return true;
  
  const keywords = ['computer', 'information', 'electronics', 'electrical', 'mechanical', 'civil'];
  for (const k of keywords) {
    if (f.includes(k) && s.includes(k)) return true;
  }
  return false;
}

function updateFacultyDropdown() {
  const deptSelect = document.getElementById('departmentSelect');
  const facultySelect = document.getElementById('facultySelect');
  const hint = document.getElementById('facultyHint');
  if (!deptSelect || !facultySelect) return;

  const selectedDept = deptSelect.value.trim();
  facultySelect.innerHTML = '';

  if (!selectedDept) {
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Select Department First --';
    facultySelect.appendChild(defaultOpt);
    if (hint) hint.textContent = 'Instructors are automatically filtered by selected department.';
    return;
  }

  const matchingFaculty = allFaculty.filter(f => matchDept(f.department, selectedDept));
  const otherFaculty = allFaculty.filter(f => !matchDept(f.department, selectedDept));

  if (matchingFaculty.length === 0 && otherFaculty.length === 0) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'No faculty registered in system';
    opt.disabled = true;
    facultySelect.appendChild(opt);
    if (hint) hint.textContent = 'No faculty available. Register faculty first in User Management.';
  } else if (matchingFaculty.length === 0) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = '-- No faculty specifically in this department --';
    opt.disabled = true;
    facultySelect.appendChild(opt);

    const group = document.createElement('optgroup');
    group.label = 'Faculty from Other Departments';
    otherFaculty.forEach((f, idx) => {
      const o = document.createElement('option');
      o.value = f.id;
      o.textContent = `${f.full_name} (${f.employee_id}) - ${f.department || 'General'}` + (f.is_hod == 1 ? ' [HOD]' : '');
      if (idx === 0) o.selected = true;
      group.appendChild(o);
    });
    facultySelect.appendChild(group);
    if (hint) hint.textContent = 'No direct department staff found; displaying available faculty from other departments.';
  } else {
    const group = document.createElement('optgroup');
    group.label = `${selectedDept} Faculty`;
    matchingFaculty.forEach((f, idx) => {
      const opt = document.createElement('option');
      opt.value = f.id;
      opt.textContent = `${f.full_name} (${f.employee_id})` + (f.is_hod == 1 ? ' [HOD]' : '');
      if (idx === 0) opt.selected = true;
      group.appendChild(opt);
    });
    facultySelect.appendChild(group);

    if (otherFaculty.length > 0) {
      const otherGroup = document.createElement('optgroup');
      otherGroup.label = 'Other Department Faculty';
      otherFaculty.forEach(f => {
        const o = document.createElement('option');
        o.value = f.id;
        o.textContent = `${f.full_name} (${f.employee_id}) - ${f.department || 'General'}` + (f.is_hod == 1 ? ' [HOD]' : '');
        otherGroup.appendChild(o);
      });
      facultySelect.appendChild(otherGroup);
    }
    if (hint) hint.textContent = `Showing ${matchingFaculty.length} instructor(s) from ${selectedDept}.`;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const deptSelect = document.getElementById('departmentSelect');
  if (deptSelect) {
    deptSelect.addEventListener('change', updateFacultyDropdown);
  }

  const addModal = document.getElementById('addSlotModal');
  if (addModal) {
    addModal.addEventListener('shown.bs.modal', function () {
      if (deptSelect && !deptSelect.value && deptSelect.options.length > 1) {
        deptSelect.selectedIndex = 1;
        updateFacultyDropdown();
      } else {
        updateFacultyDropdown();
      }
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
