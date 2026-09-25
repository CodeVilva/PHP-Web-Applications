<?php
require_once __DIR__ . '/../config/config.php';

$page_title = "Register";
$error = '';

if (is_logged_in()) {
    $role = current_role();
    redirect($role . '/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $full_name = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? 'Computer Science');
    $phone = trim($_POST['phone'] ?? '');
    $social_category = trim($_POST['social_category'] ?? 'OC');
    $gender = trim($_POST['gender'] ?? 'Male');

    $roll_no = trim($_POST['roll_no'] ?? '');
    $year_of_study = intval($_POST['year_of_study'] ?? 1);
    $designation = trim($_POST['designation'] ?? 'Assistant Professor');
    $subjects_handled = trim($_POST['subjects_handled'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Please fill all required fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = "An account with this email or username already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$username, $email, $hashed, $role]);
                $new_user_id = $pdo->lastInsertId();

                if ($role === 'student') {
                    if (empty($roll_no)) {
                        $roll_no = 'STU' . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                    }
                    $stmt = $pdo->prepare("INSERT INTO students (user_id, roll_no, full_name, department, semester, batch_year, social_category, gender, phone, gpa) VALUES (?, ?, ?, ?, 'Semester 1', '2026-2030', ?, ?, ?, 3.50)");
                    $stmt->execute([$new_user_id, $roll_no, $full_name, $department, $social_category, $gender, $phone]);
                } elseif ($role === 'faculty') {
                    $employee_id = 'FAC' . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                    $stmt = $pdo->prepare("INSERT INTO faculty (user_id, employee_id, full_name, department, designation, phone, subjects_handled) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$new_user_id, $employee_id, $full_name, $department, $designation, $phone, $subjects_handled]);
                }

                $pdo->commit();
                set_flash('success', 'Registration successful! You can now log in.');
                redirect('auth/login.php?role=' . urlencode($role) . '&username=' . urlencode($username));
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | Integrated Campus Portal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bento.css">
</head>
<body style="background: var(--bg-smoke); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;">

<div class="container" style="max-width: 760px;">
    <div class="bento-card p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h3 class="fw-bold" style="color: var(--brand-primary);">Create Campus Account</h3>
            <p class="text-muted small">Integrated Student Campus Management System Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">Register As</label>
                    <div class="d-flex gap-4 p-2 border rounded bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role_student" value="student" checked onchange="toggleRoleFields()">
                            <label class="form-check-label fw-semibold" for="role_student">
                                <i class="bi bi-mortarboard me-1 text-primary"></i> Student
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="role_faculty" value="faculty" onchange="toggleRoleFields()">
                            <label class="form-check-label fw-semibold" for="role_faculty">
                                <i class="bi bi-person-badge me-1 text-primary"></i> Faculty / Staff
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="johndoe" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email Address *</label>
                    <input type="email" name="email" class="form-control" required placeholder="name@campus.edu" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+91 9876543210" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Re-enter password">
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department *</label>
                    <select name="department" class="form-select" required>
                        <option value="Computer Science & Engineering">Computer Science & Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electronics & Communication Engineering">Electronics & Communication</option>
                        <option value="Electrical & Electronics Engineering">Electrical & Electronics</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>

                <!-- Student Specific Fields -->
                <div id="student_fields" class="row g-3 m-0 p-0">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gender *</label>
                        <select name="gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female (Pudhumai Penn Eligible)</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Social Category (Welfare & Aid) *</label>
                        <select name="social_category" class="form-select" required>
                            <option value="OC">OC (Open Category)</option>
                            <option value="BC" selected>BC (Backward Class)</option>
                            <option value="MBC">MBC (Most Backward Class / DNC)</option>
                            <option value="SC">SC (Scheduled Caste)</option>
                            <option value="ST">ST (Scheduled Tribe)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Roll / Register Number</label>
                        <input type="text" name="roll_no" class="form-control" placeholder="e.g. 717822P101" value="<?= htmlspecialchars($_POST['roll_no'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Year of Study</label>
                        <select name="year_of_study" class="form-select">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>

                <!-- Faculty Specific Fields -->
                <div id="faculty_fields" class="row g-3 m-0 p-0 d-none">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Designation</label>
                        <select name="designation" class="form-select">
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                            <option value="Head of Department">Head of Department (HoD)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Subjects Handled</label>
                        <input type="text" name="subjects_handled" class="form-control" placeholder="e.g. Data Structures, AI, Networks">
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="bento-btn bento-btn-primary w-100 py-2 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Complete Registration
                    </button>
                </div>

                <div class="col-12 text-center mt-3">
                    <span class="text-muted">Already have an account?</span>
                    <a href="login.php" class="text-primary text-decoration-none fw-semibold ms-1">Log in here</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRoleFields() {
    const isFaculty = document.getElementById('role_faculty').checked;
    const studentFields = document.getElementById('student_fields');
    const facultyFields = document.getElementById('faculty_fields');

    if (isFaculty) {
        studentFields.classList.add('d-none');
        facultyFields.classList.remove('d-none');
    } else {
        studentFields.classList.remove('d-none');
        facultyFields.classList.add('d-none');
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
