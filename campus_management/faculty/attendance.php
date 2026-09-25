<?php
require_once __DIR__ . '/../config/config.php';
require_faculty();

$user_id = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'students';

// Fetch Faculty Profile
$stmt = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
$stmt->execute([$user_id]);
$faculty = $stmt->fetch();
$faculty_id = $faculty['id'] ?? 0;

// Fetch Existing Face Embedding Profile
$stmt = $pdo->prepare("SELECT * FROM faculty_face_profiles WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$face_profile = $stmt->fetch();

// Helper: Python Face Recognition Service Caller
function call_face_service($endpoint, $payload) {
    $url = "http://127.0.0.1:5000" . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($response === false || empty($response)) {
        return [
            'success' => false, 
            'error' => 'Biometric AI Service is currently offline on port 5000. Please start `python python_face_service/app.py` in the terminal.' . ($curl_err ? " ($curl_err)" : '')
        ];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false, 
            'error' => 'Biometric engine returned an unparseable response (HTTP ' . $http_code . '). Please ensure face is clearly visible in the camera frame.'
        ];
    }
    return $decoded;
}

// -------------------------------------------------------------
// POST: Save Student Batch Attendance
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_attendance'])) {
    $subject_code = sanitize($_POST['subject_code'] ?? '');
    $subject_name = sanitize($_POST['subject_name'] ?? '');
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $attendance_data = $_POST['attendance'] ?? [];
    $remarks_data = $_POST['remarks'] ?? [];

    if (!empty($attendance_data)) {
        $count = 0;
        foreach ($attendance_data as $student_id => $status) {
            $student_id = (int)$student_id;
            $status = in_array($status, ['present', 'absent', 'late']) ? $status : 'present';
            $remarks = sanitize($remarks_data[$student_id] ?? '');

            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ? AND subject_code = ?");
            $stmt->execute([$student_id, $date, $subject_code]);
            $existing = $stmt->fetch();

            if ($existing) {
                $upd = $pdo->prepare("UPDATE attendance SET status = ?, remarks = ?, faculty_id = ? WHERE id = ?");
                $upd->execute([$status, $remarks, $faculty_id, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO attendance (student_id, faculty_id, subject_code, subject_name, date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$student_id, $faculty_id, $subject_code, $subject_name, $date, $status, $remarks]);
            }
            $count++;
        }
        set_flash('success', "Class attendance successfully recorded for {$count} students.");
    }
    redirect('faculty/attendance.php?tab=students');
}

// -------------------------------------------------------------
// POST: Register Facial Biometrics Profile (OpenCV SFace)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_face_biometrics'])) {
    $base64_image = $_POST['reg_face_image'] ?? '';

    if (empty($base64_image)) {
        set_flash('danger', 'No facial image was captured. Please look at the camera and capture photo.');
        redirect('faculty/attendance.php?tab=face');
    }

    $api_res = call_face_service('/register-face', [
        'faculty_id' => $faculty_id,
        'image' => $base64_image
    ]);

    if (!empty($api_res['success'])) {
        $embedding = $api_res['embedding'] ?? $api_res['face_embedding'] ?? null;
        $embedding_str = is_array($embedding) ? json_encode($embedding) : $embedding;

        $upsert = $pdo->prepare("
            INSERT INTO faculty_face_profiles (faculty_id, embedding, model_name) 
            VALUES (?, ?, 'opencv_sface')
            ON DUPLICATE KEY UPDATE embedding = VALUES(embedding), updated_at = NOW()
        ");
        $upsert->execute([$faculty_id, $embedding_str]);

        set_flash('success', 'Biometric Enrollment Complete! 128-dimensional OpenCV SFace profile registered.');
    } else {
        $err = $api_res['message'] ?? $api_res['error'] ?? 'Biometric enrollment failed. Please ensure your face is well-lit and unobstructed.';
        set_flash('danger', 'Enrollment Error: ' . htmlspecialchars($err));
    }
    redirect('faculty/attendance.php?tab=face');
}

// -------------------------------------------------------------
// POST: Verify Face Attendance (Multi-Frame Liveness + Match)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_face_attendance'])) {
    $action_type = sanitize($_POST['action_type'] ?? 'check_in');
    $probe_frames_raw = $_POST['probe_frames_json'] ?? '';
    $probe_frames = json_decode($probe_frames_raw, true) ?: [];

    if (empty($probe_frames)) {
        set_flash('danger', 'No live camera frames detected. Please activate the webcam.');
        redirect('faculty/attendance.php?tab=face');
    }

    if (!$face_profile) {
        set_flash('warning', 'Please enroll your facial biometrics profile first.');
        redirect('faculty/attendance.php?tab=face');
    }

    $stored_embedding = json_decode($face_profile['embedding'] ?? $face_profile['face_embedding'] ?? '[]', true);

    $api_res = call_face_service('/verify-face', [
        'faculty_id' => $faculty_id,
        'frames' => $probe_frames,
        'probe_frames' => $probe_frames,
        'stored_embedding' => $stored_embedding,
        'registered_embedding' => $stored_embedding
    ]);

    if (!empty($api_res['success'])) {
        if (!empty($api_res['verified'])) {
            $confidence = min(99.99, max(10.0, (float)($api_res['confidence'] ?? 95.0)));
            $today = date('Y-m-d');
            $now = date('H:i:s');

            $check = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM faculty_attendance WHERE faculty_id = ? AND date = ?");
            $check->execute([$faculty_id, $today]);
            $existing = $check->fetch();

            if ($action_type === 'check_in') {
                if ($existing) {
                    $upd = $pdo->prepare("UPDATE faculty_attendance SET check_in_time = ?, confidence_score = ?, status = 'present', verification_method = 'face_recognition' WHERE id = ?");
                    $upd->execute([$now, $confidence, $existing['id']]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO faculty_attendance (faculty_id, date, check_in_time, verification_method, confidence_score, status) VALUES (?, ?, ?, 'face_recognition', ?, 'present')");
                    $ins->execute([$faculty_id, $today, $now, $confidence]);
                }
                set_flash('success', 'Biometric Match Confirmed (' . number_format($confidence, 1) . '% Match)! Arrival recorded at ' . date('h:i A'));
            } else {
                if ($existing) {
                    $upd = $pdo->prepare("UPDATE faculty_attendance SET check_out_time = ?, confidence_score = ? WHERE id = ?");
                    $upd->execute([$now, $confidence, $existing['id']]);
                    set_flash('success', 'Biometric Match Confirmed (' . number_format($confidence, 1) . '% Match)! Departure recorded at ' . date('h:i A'));
                } else {
                    set_flash('danger', 'You must check in for arrival before recording departure.');
                }
            }
        } else {

            $msg = $api_res['message'] ?? 'Facial biometrics does not match the registered faculty profile.';
            set_flash('danger', 'Biometric Verification Rejected: ' . htmlspecialchars($msg));
        }
    } else {
        $err = $api_res['error'] ?? 'Local Python Face Recognition microservice is unavailable. Ensure python python_face_service/app.py is running on port 5000.';
        set_flash('danger', $err);
    }
    redirect('faculty/attendance.php?tab=face');
}

// Fetch Students for Class Attendance
$dept = $faculty['department'] ?? '';
$students = [];
if (!empty($dept)) {
    $stmt = $pdo->prepare("SELECT id, roll_no, full_name, department, semester FROM students WHERE department = ? ORDER BY roll_no ASC");
    $stmt->execute([$dept]);
    $students = $stmt->fetchAll();
}
if (empty($students)) {
    $students = $pdo->query("SELECT id, roll_no, full_name, department, semester FROM students ORDER BY department ASC, roll_no ASC LIMIT 30")->fetchAll();
}

// Fetch Attendance History
$face_records = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM faculty_attendance WHERE faculty_id = ? ORDER BY date DESC, check_in_time DESC LIMIT 15");
    $stmt->execute([$faculty_id]);
    $face_records = $stmt->fetchAll();
} catch (Exception $e) {}

$today_log = $pdo->prepare("SELECT * FROM faculty_attendance WHERE faculty_id = ? AND date = ?");
$today_log->execute([$faculty_id, date('Y-m-d')]);
$faculty_today = $today_log->fetch();

// Fetch Recent Class Sessions Marked by Faculty
$recent_sessions = [];
try {
    $sess_stmt = $pdo->prepare("
        SELECT date, subject_code, subject_name, 
               COUNT(*) as total_students,
               SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
               SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
               SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
        FROM attendance
        WHERE faculty_id = ?
        GROUP BY date, subject_code, subject_name
        ORDER BY date DESC
        LIMIT 15
    ");
    $sess_stmt->execute([$faculty_id]);
    $recent_sessions = $sess_stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Faculty Attendance & OpenCV Face Recognition';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Attendance Terminal & Biometrics</h3>
        <p class="text-muted small mb-0">Genuine Computer Vision Face Detection & SFace Recognition &bull; Local Python Engine</p>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'students' ? 'active' : '' ?>" href="attendance.php?tab=students">
          <i class="bi bi-people-fill me-1"></i> Student Class Attendance
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'face' ? 'active' : '' ?>" href="attendance.php?tab=face">
          <i class="bi bi-person-bounding-box me-1"></i> Faculty AI Face Biometrics
        </a>
      </li>
    </ul>

    <?php if ($tab === 'students'): ?>
      <!-- TAB 1: STUDENT BATCH ATTENDANCE -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-card-checklist text-primary"></i> Mark Class Attendance</h5>
          <span class="text-muted small"><?= count($students) ?> Students in Cohort</span>
        </div>

        <?php if (empty($students)): ?>
          <div class="text-center py-4 text-muted">
            <i class="bi bi-person-x fs-1 d-block mb-2"></i>
            No students registered in the system yet.
          </div>
        <?php else: ?>
          <form method="POST" action="attendance.php?tab=students">
            <input type="hidden" name="save_student_attendance" value="1">
            <div class="row g-3 p-3 bg-light rounded-3 mb-4">
              <div class="col-md-4">
                <label class="form-label small fw-bold">Subject Code *</label>
                <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS501" value="CS501" required>
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-bold">Subject Name *</label>
                <input type="text" name="subject_name" class="form-control" placeholder="e.g. Database Management Systems" value="Database Management Systems" required>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-bold">Session Date *</label>
                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                  <tr>
                    <th>Roll No</th>
                    <th>Student Name</th>
                    <th>Department</th>
                    <th class="text-center" style="width: 260px;">Attendance Status</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($students as $stu): ?>
                    <tr>
                      <td><code><?= e($stu['roll_no']) ?></code></td>
                      <td><strong><?= e($stu['full_name']) ?></strong></td>
                      <td><span class="small text-muted"><?= e($stu['department']) ?></span></td>
                      <td class="text-center">
                        <div class="btn-group w-100" role="group">
                          <input type="radio" class="btn-check" name="attendance[<?= $stu['id'] ?>]" id="pres_<?= $stu['id'] ?>" value="present" checked>
                          <label class="btn btn-outline-success btn-sm" for="pres_<?= $stu['id'] ?>"><i class="bi bi-check-lg"></i> Present</label>

                          <input type="radio" class="btn-check" name="attendance[<?= $stu['id'] ?>]" id="late_<?= $stu['id'] ?>" value="late">
                          <label class="btn btn-outline-warning btn-sm" for="late_<?= $stu['id'] ?>"><i class="bi bi-clock"></i> Late</label>

                          <input type="radio" class="btn-check" name="attendance[<?= $stu['id'] ?>]" id="abs_<?= $stu['id'] ?>" value="absent">
                          <label class="btn btn-outline-danger btn-sm" for="abs_<?= $stu['id'] ?>"><i class="bi bi-x-lg"></i> Absent</label>
                        </div>
                      </td>
                      <td>
                        <input type="text" name="remarks[<?= $stu['id'] ?>]" class="form-control form-control-sm" placeholder="Optional remark...">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end mt-4">
              <button type="submit" class="bento-btn bento-btn-primary">
                <i class="bi bi-check2-circle"></i> Save Class Attendance
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Daily Session Log Table -->
      <div class="bento-card">
        <div class="bento-header">
          <div>
            <h5 class="bento-title"><i class="bi bi-list-check text-primary"></i> Daily Session Log</h5>
            <div class="bento-subtitle">History of class attendance sessions recorded by you</div>
          </div>
          <span class="text-muted small"><?= count($recent_sessions) ?> Recorded Sessions</span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Session Date</th>
                <th>Subject</th>
                <th>Enrolled Strength</th>
                <th>Attendance Breakdown</th>
                <th>Attendance Rate</th>
                <th class="text-end">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recent_sessions)): ?>
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">
                    No classroom sessions recorded yet. Use the form above to record today's class attendance.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($recent_sessions as $sess): 
                  $tot = (int)$sess['total_students'];
                  $pres = (int)$sess['present_count'];
                  $late = (int)$sess['late_count'];
                  $abs = (int)$sess['absent_count'];
                  $rate = $tot > 0 ? round((($pres + ($late * 0.5)) / $tot) * 100) : 0;
                ?>
                  <tr>
                    <td><strong><?= date('M d, Y', strtotime($sess['date'])) ?></strong></td>
                    <td>
                      <div class="fw-bold text-dark"><?= e($sess['subject_name']) ?></div>
                      <span class="badge bg-light text-primary border"><?= e($sess['subject_code']) ?></span>
                    </td>
                    <td><strong><?= $tot ?></strong> Students</td>
                    <td>
                      <div class="d-flex gap-2 small">
                        <span class="badge bg-success-subtle text-success border border-success"><?= $pres ?> Present</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning"><?= $late ?> Late</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger"><?= $abs ?> Absent</span>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                          <div class="progress-bar <?= $rate >= 75 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= $rate ?>%;"></div>
                        </div>
                        <span class="fw-bold small <?= $rate >= 75 ? 'text-success' : 'text-danger' ?>"><?= $rate ?>%</span>
                      </div>
                    </td>
                    <td class="text-end">
                      <span class="status-pill verified"><i class="bi bi-check-circle-fill me-1"></i> Recorded</span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <!-- TAB 2: FACULTY FACE RECOGNITION BIOMETRIC SCANNER -->
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="bento-card">
            <div class="bento-header">
              <h5 class="bento-title"><i class="bi bi-person-bounding-box text-primary"></i> OpenCV Biometric Terminal</h5>
              <?php if ($face_profile): ?>
                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Biometric Profile Enrolled</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle-fill me-1"></i> Biometric Enrollment Required</span>
              <?php endif; ?>
            </div>

            <?php if (!$face_profile): ?>
              <!-- Enrollment Warning & Modal Trigger -->
              <div class="alert alert-warning p-3 mb-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-person-badge me-2"></i>First-Time Biometric Registration Required</h6>
                <p class="small mb-2">You have not registered your facial biometrics yet. Capture your face to generate a 128-dimensional OpenCV embedding vector.</p>
                <button type="button" class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#faceRegistrationModal">
                  <i class="bi bi-camera-fill me-1"></i> Register Face Biometrics Now
                </button>
              </div>
            <?php else: ?>
              <div class="d-flex justify-content-between align-items-center p-2 mb-3 bg-light rounded small">
                <span><i class="bi bi-shield-check text-success me-1"></i> SFace 128-dim Profile Active</span>
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#faceRegistrationModal">
                  <i class="bi bi-arrow-repeat me-1"></i> Re-enroll Profile
                </button>
              </div>
            <?php endif; ?>

            <!-- Video & Canvas Scanner -->
            <div class="text-center p-2 bg-dark rounded-3 position-relative overflow-hidden mb-3" style="min-height: 340px; display: flex; align-items: center; justify-content: center; background: #0F172A !important;">
              <!-- Live Video Stream -->
              <video id="faceWebcam" autoplay playsinline muted style="width: 100%; max-height: 320px; border-radius: 8px; object-fit: cover; display: none; transform: scaleX(-1);"></video>
              <canvas id="faceCaptureCanvas" width="320" height="240" style="display: none;"></canvas>

              <!-- Placeholder -->
              <div id="camPlaceholder" class="py-5 text-white-50">
                <div class="p-3 rounded-circle bg-dark d-inline-block border border-secondary mb-3">
                  <i class="bi bi-camera-video fs-1 text-primary"></i>
                </div>
                <h6 class="text-white fw-bold mb-1">Biometric Camera Standby</h6>
                <p class="small text-muted mb-3">Click below to activate camera and perform genuine OpenCV facial match</p>
                <button type="button" id="btnActivateCam" class="btn btn-primary btn-sm px-4 fw-bold" onclick="startBiometricWebcam()">
                  <i class="bi bi-camera-video-fill me-1"></i> Activate Live Camera
                </button>
              </div>

              <!-- Animated Biometric Bounding Box & HUD -->
              <div id="faceOverlayBox" style="display: none; position: absolute; border: 2px solid #00A0DC; width: 200px; height: 240px; border-radius: 24px; pointer-events: none; box-shadow: 0 0 25px rgba(0,160,220,0.6); transition: all 0.3s ease;">
                <div id="scanLaserLine" style="position: absolute; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, #00A0DC, #ffffff, #00A0DC, transparent); box-shadow: 0 0 8px #00A0DC; top: 0; animation: scanLaserAnim 2s infinite ease-in-out;"></div>
                <div id="faceHudStatus" style="position: absolute; top: 12px; left: 12px; font-size: 11px; color: #00A0DC; background: rgba(0,0,0,0.85); padding: 3px 8px; border-radius: 6px; font-weight: 700; border: 1px solid rgba(0,160,220,0.4);">
                  <i class="bi bi-arrow-repeat spin me-1"></i> LIVE CAMERA SCAN
                </div>
              </div>
            </div>

            <!-- Status Indicator -->
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span id="faceTerminalStatus" class="small text-muted"><i class="bi bi-info-circle me-1"></i> Click 'Activate Live Camera' to begin.</span>
                <span id="burstCountBadge" class="small text-muted">0/3 Frames</span>
              </div>
              <div class="progress" style="height: 6px;">
                <div id="burstProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
              </div>
            </div>

            <!-- Attendance Submission Form -->
            <form method="POST" action="attendance.php?tab=face" id="biometricAttendanceForm">
              <input type="hidden" name="verify_face_attendance" value="1">
              <input type="hidden" name="probe_frames_json" id="probeFramesInput" value="">

              <div class="d-flex gap-2">
                <button type="submit" name="action_type" value="check_in" id="btnCheckIn" class="bento-btn bento-btn-primary flex-fill py-2" disabled>
                  <i class="bi bi-box-arrow-in-right me-1"></i> Verify Biometrics & Check In
                </button>
                <button type="submit" name="action_type" value="check_out" id="btnCheckOut" class="bento-btn bento-btn-outline flex-fill py-2" disabled>
                  <i class="bi bi-box-arrow-right me-1"></i> Verify & Check Out
                </button>
              </div>
            </form>

            <div id="camErrNotice" class="alert alert-danger mt-3 small d-none" role="alert"></div>
          </div>
        </div>

        <div class="col-lg-5">
          <!-- Today's Status Card -->
          <div class="bento-card mb-4">
            <h6 class="fw-bold mb-3" style="color: var(--text-slate);"><i class="bi bi-clock-history text-primary me-2"></i>Today's Biometric Log</h6>
            <?php if ($faculty_today): ?>
              <div class="p-3 bg-success-subtle border border-success rounded-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="badge bg-success">Status: <?= ucfirst($faculty_today['status']) ?></span>
                  <small class="text-muted"><?= date('M d, Y') ?></small>
                </div>
                <div class="small mb-1"><strong>Check-In Time:</strong> <?= date('h:i:s A', strtotime($faculty_today['check_in_time'])) ?></div>
                <div class="small mb-1"><strong>Check-Out Time:</strong> <?= $faculty_today['check_out_time'] ? date('h:i:s A', strtotime($faculty_today['check_out_time'])) : 'Active on Campus' ?></div>
                <div class="small text-muted"><strong>Biometric Score:</strong> <?= $faculty_today['confidence_score'] ?>% Confidence Match</div>
              </div>
            <?php else: ?>
              <div class="p-3 bg-warning-subtle border border-warning rounded-3 text-dark small">
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                You have not checked in for today yet. Use the camera scanner on the left to verify your arrival.
              </div>
            <?php endif; ?>
          </div>

          <!-- History Logs -->
          <div class="bento-card">
            <div class="bento-header">
              <h6 class="bento-title"><i class="bi bi-calendar3 text-primary"></i> Recent Attendance History</h6>
              <span class="text-muted small"><?= count($face_records) ?> Logs</span>
            </div>
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Arrival</th>
                    <th>Departure</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($face_records)): ?>
                    <tr><td colspan="4" class="text-center py-3 text-muted">No attendance logs yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($face_records as $rec): ?>
                      <tr>
                        <td><strong><?= date('M d, Y', strtotime($rec['date'])) ?></strong></td>
                        <td><code><?= date('h:i A', strtotime($rec['check_in_time'])) ?></code></td>
                        <td><?= $rec['check_out_time'] ? '<code>' . date('h:i A', strtotime($rec['check_out_time'])) . '</code>' : '<span class="text-muted small">In Session</span>' ?></td>
                        <td><span class="status-pill <?= $rec['status'] === 'present' ? 'present' : 'late' ?>"><?= ucfirst($rec['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Modal: Face Registration -->
<div class="modal fade" id="faceRegistrationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="attendance.php?tab=face" id="faceRegForm">
        <input type="hidden" name="register_face_biometrics" value="1">
        <input type="hidden" name="reg_face_image" id="regFaceImageInput" value="">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-camera-fill text-primary me-2"></i>Enroll Facial Biometrics</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <p class="small text-muted mb-3">Look directly into your camera in a well-lit area. This snapshot will be processed by OpenCV SFace to create your 128-dim biometric embedding.</p>

          <div class="bg-dark rounded-3 overflow-hidden position-relative d-flex align-items-center justify-content-center mb-3" style="height: 260px; background: #0F172A !important;">
            <video id="regWebcam" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: none;"></video>
            <canvas id="regCanvas" width="320" height="240" style="display: none;"></canvas>
            
            <div id="regPlaceholder" class="text-white-50 p-4">
              <div class="spinner-border text-primary mb-2" id="regSpinner" role="status"></div>
              <p class="small text-white mb-2" id="regStatusMsg">Starting camera...</p>
              <button type="button" id="btnManualStartRegCam" class="btn btn-outline-light btn-sm px-3" onclick="startRegWebcam()">
                <i class="bi bi-camera-fill me-1"></i> Initialize Camera
              </button>
            </div>
          </div>

          <div id="regPreviewBox" class="mb-3 d-none">
            <span class="badge bg-success mb-2"><i class="bi bi-check2-circle me-1"></i> Photo Captured &bull; Ready to Enroll</span>
            <div>
              <img id="regCapturedImg" src="" style="max-height: 120px; border-radius: 8px; border: 2px solid #22C55E;" alt="Preview">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="btnSnapReg" class="btn btn-primary" onclick="captureRegPhoto()" disabled>
            <i class="bi bi-camera-fill me-1"></i> Capture Photo
          </button>
          <button type="submit" id="btnSubmitReg" class="btn btn-success fw-bold" disabled>
            <i class="bi bi-check2-circle me-1"></i> Save Biometric Profile
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
@keyframes scanLaserAnim {
  0% { top: 5%; }
  50% { top: 92%; }
  100% { top: 5%; }
}
.spin {
  display: inline-block;
  animation: rotation 1.5s infinite linear;
}
@keyframes rotation {
  from { transform: rotate(0deg); }
  to { transform: rotate(359deg); }
}
</style>

<script>
let activeWebcamStream = null;
let regWebcamStream = null;

// Safe stream attachment helper
function attachCameraStream(videoEl, stream) {
  if (!videoEl || !stream) return;
  videoEl.srcObject = stream;
  videoEl.muted = true;
  videoEl.playsInline = true;
  videoEl.autoplay = true;
  videoEl.onloadedmetadata = () => {
    videoEl.play().catch(e => console.warn("Video metadata play notice:", e));
  };
  videoEl.play().catch(e => console.warn("Video direct play notice:", e));
}

// 1. Face Verification Camera & Multi-Frame Burst Capture
async function startBiometricWebcam() {
  const video = document.getElementById('faceWebcam');
  const canvas = document.getElementById('faceCaptureCanvas');
  const placeholder = document.getElementById('camPlaceholder');
  const overlay = document.getElementById('faceOverlayBox');
  const status = document.getElementById('faceTerminalStatus');
  const errNotice = document.getElementById('camErrNotice');
  const pBar = document.getElementById('burstProgressBar');
  const countBadge = document.getElementById('burstCountBadge');
  const framesInput = document.getElementById('probeFramesInput');
  const btnIn = document.getElementById('btnCheckIn');
  const btnOut = document.getElementById('btnCheckOut');

  errNotice.classList.add('d-none');
  status.innerHTML = '<span class="text-primary"><i class="bi bi-hourglass-split me-1"></i> Requesting webcam access...</span>';

  try {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error("WebRTC camera access requires a secure context (http://localhost or HTTPS).");
    }

    try {
      activeWebcamStream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" }
      });
    } catch (e1) {
      activeWebcamStream = await navigator.mediaDevices.getUserMedia({ video: true });
    }

    attachCameraStream(video, activeWebcamStream);
    video.style.display = 'block';
    placeholder.style.display = 'none';
    overlay.style.display = 'block';

    status.innerHTML = '<span class="text-info fw-bold"><i class="bi bi-record-circle-fill text-danger me-1"></i> Camera Active. Waiting for frame stabilization...</span>';

    // Wait until video has active dimensions
    let checkReadyCount = 0;
    const readyCheck = setInterval(() => {
      checkReadyCount++;
      if ((video.videoWidth > 0 && video.readyState >= 2) || checkReadyCount > 20) {
        clearInterval(readyCheck);
        startFrameBurstCapture();
      }
    }, 100);

    function startFrameBurstCapture() {
      status.innerHTML = '<span class="text-info fw-bold"><i class="bi bi-record-circle-fill text-danger me-1"></i> Capturing liveness frames...</span>';
      let frames = [];
      let frameCount = 0;
      const ctx = canvas.getContext('2d');

      const captureInterval = setInterval(() => {
        if (video.videoWidth > 0) {
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
        }
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const b64 = canvas.toDataURL('image/jpeg', 0.90);
        frames.push(b64);
        frameCount++;

        pBar.style.width = `${(frameCount / 3) * 100}%`;
        countBadge.innerText = `${frameCount}/3 Frames`;

        if (frameCount >= 3) {
          clearInterval(captureInterval);
          framesInput.value = JSON.stringify(frames);

          status.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-patch-check-fill me-1"></i> Liveness frames captured. Ready for OpenCV verification!</span>';
          btnIn.disabled = false;
          btnOut.disabled = false;
        }
      }, 250);
    }

  } catch (err) {
    console.error("Biometric camera error:", err);
    errNotice.classList.remove('d-none');
    errNotice.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${err.message || "Camera access denied or unavailable."}`;
    status.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Camera Error</span>';
  }
}

// 2. Face Registration Camera
async function startRegWebcam() {
  const video = document.getElementById('regWebcam');
  const placeholder = document.getElementById('regPlaceholder');
  const snapBtn = document.getElementById('btnSnapReg');
  const spinner = document.getElementById('regSpinner');
  const statusMsg = document.getElementById('regStatusMsg');

  if (spinner) spinner.style.display = 'inline-block';
  if (statusMsg) statusMsg.innerText = 'Requesting camera access...';

  try {
    if (regWebcamStream) {
      regWebcamStream.getTracks().forEach(t => t.stop());
    }

    try {
      regWebcamStream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: "user" }
      });
    } catch (e1) {
      regWebcamStream = await navigator.mediaDevices.getUserMedia({ video: true });
    }

    attachCameraStream(video, regWebcamStream);
    video.style.display = 'block';
    placeholder.style.display = 'none';
    snapBtn.disabled = false;
  } catch (err) {
    console.error("Reg webcam error:", err);
    if (spinner) spinner.style.display = 'none';
    if (statusMsg) statusMsg.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> Camera access denied or not found.</span>';
  }
}

function stopRegWebcam() {
  if (regWebcamStream) {
    regWebcamStream.getTracks().forEach(t => t.stop());
    regWebcamStream = null;
  }
  const video = document.getElementById('regWebcam');
  const placeholder = document.getElementById('regPlaceholder');
  if (video) video.style.display = 'none';
  if (placeholder) placeholder.style.display = 'block';
}

function captureRegPhoto() {
  const video = document.getElementById('regWebcam');
  const canvas = document.getElementById('regCanvas');
  const input = document.getElementById('regFaceImageInput');
  const previewBox = document.getElementById('regPreviewBox');
  const previewImg = document.getElementById('regCapturedImg');
  const submitBtn = document.getElementById('btnSubmitReg');

  if (video.videoWidth > 0) {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
  }

  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  const dataUri = canvas.toDataURL('image/jpeg', 0.92);

  input.value = dataUri;
  if (previewImg) previewImg.src = dataUri;
  previewBox.classList.remove('d-none');
  submitBtn.disabled = false;
}

// Modal automatic camera hooks
document.addEventListener('DOMContentLoaded', () => {
  const regModal = document.getElementById('faceRegistrationModal');
  if (regModal) {
    regModal.addEventListener('shown.bs.modal', async () => {
      await startRegWebcam();
    });
    regModal.addEventListener('hidden.bs.modal', () => {
      stopRegWebcam();
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
