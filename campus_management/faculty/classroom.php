<?php
require_once __DIR__ . '/../config/config.php';
require_faculty();

$user_id = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'live';
$action = $_GET['action'] ?? '';

// Fetch Faculty Profile
$stmt = $pdo->prepare("SELECT * FROM faculty WHERE user_id = ?");
$stmt->execute([$user_id]);
$faculty = $stmt->fetch();
$faculty_id = $faculty['id'] ?? 0;

// -------------------------------------------------------------
// POST: Schedule a Live Class
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_live_class'])) {
    $subject_code = sanitize($_POST['subject_code'] ?? '');
    $subject_name = sanitize($_POST['subject_name'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $scheduled_at = sanitize($_POST['scheduled_at'] ?? date('Y-m-d H:i:s'));
    $duration = (int)($_POST['duration_minutes'] ?? 60);
    $room_id = 'room_' . substr(md5(uniqid(rand(), true)), 0, 10);
    $passcode = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);

    $stmt = $pdo->prepare("
        INSERT INTO live_classes (faculty_id, subject_code, subject_name, title, description, scheduled_at, duration_minutes, room_id, status, meeting_passcode)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    $stmt->execute([$faculty_id, $subject_code, $subject_name, $title, $description, $scheduled_at, $duration, $room_id, $passcode]);

    set_flash('success', "Live lecture '{$title}' scheduled successfully! Room ID: {$room_id}");
    redirect('faculty/classroom.php?tab=live');
}

// -------------------------------------------------------------
// GET: Launch Live Video Class
// -------------------------------------------------------------
if ($action === 'start' && !empty($_GET['class_id'])) {
    $class_id = (int)$_GET['class_id'];
    $stmt = $pdo->prepare("UPDATE live_classes SET status = 'live' WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$class_id, $faculty_id]);
    set_flash('success', "Live broadcasting started! WebRTC Mesh connection active.");
    redirect('faculty/classroom.php?tab=live&room=' . $class_id);
}

// -------------------------------------------------------------
// GET: End Live Video Class
// -------------------------------------------------------------
if ($action === 'end' && !empty($_GET['class_id'])) {
    $class_id = (int)$_GET['class_id'];
    $stmt = $pdo->prepare("UPDATE live_classes SET status = 'ended' WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$class_id, $faculty_id]);

    $sig = $pdo->prepare("INSERT INTO webrtc_signals (live_class_id, sender_id, sender_role, signal_type, signal_data) VALUES (?, ?, 'faculty', 'end_call', '{}')");
    $sig->execute([$class_id, $user_id]);

    $pdo->prepare("DELETE FROM webrtc_participants WHERE live_class_id = ?")->execute([$class_id]);

    set_flash('info', "Live classroom session has been concluded.");
    redirect('faculty/classroom.php?tab=live');
}

// -------------------------------------------------------------
// POST: Create Assignment
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $subject_code = sanitize($_POST['subject_code'] ?? '');
    $subject_name = sanitize($_POST['subject_name'] ?? '');
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d H:i:s'));
    $max_marks = (int)($_POST['max_marks'] ?? 100);

    $file_path = null;
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/assignments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = 'assignment_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $file_name)) {
            $file_path = 'uploads/assignments/' . $file_name;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO assignments (faculty_id, subject_code, subject_name, title, description, attachment_path, due_date, max_marks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$faculty_id, $subject_code, $subject_name, $title, $description, $file_path, $due_date, $max_marks]);


    set_flash('success', "Assignment '{$title}' published successfully.");
    redirect('faculty/classroom.php?tab=assignments');
}

// -------------------------------------------------------------
// POST: Grade Assignment Submission
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $marks = (int)$_POST['marks_obtained'];
    $feedback = sanitize($_POST['feedback'] ?? '');

    $stmt = $pdo->prepare("UPDATE assignment_submissions SET marks_obtained = ?, feedback = ?, status = 'graded' WHERE id = ?");
    $stmt->execute([$marks, $feedback, $submission_id]);

    set_flash('success', "Submission graded successfully.");
    redirect('faculty/classroom.php?tab=submissions');
}

// -------------------------------------------------------------
// POST: Answer Student Query
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_query'])) {
    $query_id = (int)$_POST['query_id'];
    $answer = sanitize($_POST['answer'] ?? '');

    $stmt = $pdo->prepare("UPDATE classroom_queries SET answer = ?, answered_by_faculty_id = ?, answered_at = NOW() WHERE id = ?");
    $stmt->execute([$answer, $faculty_id, $query_id]);

    set_flash('success', "Answer posted to student.");
    redirect('faculty/classroom.php?tab=queries');
}

// Fetch Active Live Class
$active_room = null;
$room_param = (int)($_GET['room'] ?? 0);
if ($room_param > 0) {
    $stmt = $pdo->prepare("SELECT * FROM live_classes WHERE id = ? AND faculty_id = ?");
    $stmt->execute([$room_param, $faculty_id]);
    $active_room = $stmt->fetch();
}

// Fetch Faculty's Live Classes Catalog
$stmt = $pdo->prepare("SELECT * FROM live_classes WHERE faculty_id = ? ORDER BY scheduled_at DESC");
$stmt->execute([$faculty_id]);
$all_live_classes = $stmt->fetchAll();

// Fetch Assignments
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE faculty_id = ? ORDER BY created_at DESC");
$stmt->execute([$faculty_id]);
$assignments = $stmt->fetchAll();

// Fetch Submissions
$stmt = $pdo->prepare("
    SELECT s.*, a.title as assignment_title, a.max_marks, a.subject_code, stu.full_name as student_name, stu.roll_no
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN students stu ON s.student_id = stu.id
    WHERE a.faculty_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$faculty_id]);
$all_submissions = $stmt->fetchAll();

// Fetch Student Queries
$stmt = $pdo->prepare("
    SELECT q.*, s.full_name as student_name, s.roll_no
    FROM classroom_queries q
    JOIN students s ON q.student_id = s.id
    ORDER BY q.created_at DESC LIMIT 50
");
$stmt->execute();
$queries = $stmt->fetchAll();

$page_title = 'Online Classroom & WebRTC Live Studio';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Online Learning Studio</h3>
        <p class="text-muted small mb-0">Native WebRTC Peer-to-Peer Live Classes & Google Classroom Style Workspace</p>
      </div>
      <div>
        <button class="bento-btn bento-btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleLiveModal">
          <i class="bi bi-camera-video me-1"></i> Schedule Live Video Class
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'live' ? 'active' : '' ?>" href="classroom.php?tab=live">
          <i class="bi bi-broadcast text-danger me-1"></i> Live WebRTC Sessions
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>" href="classroom.php?tab=assignments">
          <i class="bi bi-journal-check me-1"></i> Assignments Hub
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'submissions' ? 'active' : '' ?>" href="classroom.php?tab=submissions">
          <i class="bi bi-patch-check me-1"></i> Evaluate Submissions (<?= count($all_submissions) ?>)
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'queries' ? 'active' : '' ?>" href="classroom.php?tab=queries">
          <i class="bi bi-chat-dots me-1"></i> Student Q&A Stream
        </a>
      </li>
    </ul>

    <?php if ($tab === 'live'): ?>
      <!-- TAB 0: LIVE WEBRTC BROADCAST STUDIO -->
      <?php if ($active_room): ?>
        <div class="bento-card mb-4 p-3 bg-dark text-white rounded-3 position-relative" style="background: #0F172A !important;">
          <!-- Header Bar -->
          <div class="d-flex justify-content-between align-items-center mb-3 px-2 flex-wrap gap-2">
            <div>
              <span class="badge bg-danger animate-pulse me-2"><i class="bi bi-record-circle me-1"></i> LIVE BROADCASTING</span>
              <strong class="text-white fs-5"><?= e($active_room['title']) ?></strong> (<code><?= e($active_room['subject_code']) ?></code>)
            </div>
            <div class="d-flex align-items-center gap-3">
              <span id="connectionBadge" class="badge bg-secondary p-2">Initializing WebRTC...</span>
              <a href="classroom.php?tab=live&action=end&class_id=<?= $active_room['id'] ?>" class="btn btn-danger btn-sm fw-bold" onclick="return confirm('Are you sure you want to end this live classroom session for all students?')">
                <i class="bi bi-telephone-x-fill me-1"></i> End Live Class
              </a>
            </div>
          </div>

          <!-- Alert Container for Errors -->
          <div id="webrtcAlertBox" class="alert alert-danger d-none mb-3" role="alert"></div>

          <!-- Video Grid Stage -->
          <div class="row g-3">
            <!-- Faculty Main Video Stream -->
            <div class="col-lg-8">
              <div class="position-relative bg-black rounded-3 overflow-hidden d-flex align-items-center justify-content-center" style="height: 460px; border: 1px solid #334155;">
                <video id="localFacultyVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                <div class="position-absolute bottom-0 start-0 m-3 px-3 py-1 rounded bg-dark bg-opacity-75 text-white fw-bold small">
                  <i class="bi bi-person-video3 me-1 text-primary"></i> Prof. <?= e($_SESSION['full_name'] ?? 'Instructor') ?> (Host)
                </div>
              </div>

              <!-- Media Control Toolbar -->
              <div class="d-flex justify-content-between align-items-center mt-3 p-2 bg-black rounded border border-secondary">
                <div class="d-flex gap-2">
                  <button type="button" id="toggleMicBtn" class="btn btn-outline-light btn-sm fw-semibold">
                    <i class="bi bi-mic-fill me-1"></i> Mic Mute
                  </button>
                  <button type="button" id="toggleCamBtn" class="btn btn-outline-light btn-sm fw-semibold">
                    <i class="bi bi-camera-video-fill me-1"></i> Camera Off
                  </button>
                  <button type="button" id="shareScreenBtn" class="btn btn-outline-info btn-sm fw-semibold">
                    <i class="bi bi-display me-1"></i> Share Screen
                  </button>
                </div>
                <div class="small text-white-50">
                  <i class="bi bi-shield-lock-fill text-success me-1"></i> Local WebRTC Mesh
                </div>
              </div>
            </div>

            <!-- Connected Students Video Strip & Participants -->
            <div class="col-lg-4">
              <div class="bento-card bg-black text-white p-3 h-100 d-flex flex-column" style="background: #020617 !important; border: 1px solid #1E293B;">
                <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom border-secondary">
                  <h6 class="mb-0 text-white fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Connected Students (<span id="participantCount">1</span>)</h6>
                  <span class="badge bg-primary">Live Mesh</span>
                </div>

                <!-- Remote Peers Video Container -->
                <div id="remoteStudentsGrid" class="flex-grow-1 overflow-y-auto d-flex flex-column gap-2" style="max-height: 420px;">
                  <div id="noStudentsNotice" class="text-center py-5 text-muted small">
                    <i class="bi bi-person-x fs-2 d-block mb-1"></i>
                    Awaiting students to join classroom...
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- WebRTC Client Script -->
        <script src="<?= BASE_URL ?>/assets/js/webrtc_classroom.js"></script>
        <script>
        document.addEventListener("DOMContentLoaded", async function() {
            const statusBadge = document.getElementById('connectionBadge');
            const alertBox = document.getElementById('webrtcAlertBox');
            const localVideo = document.getElementById('localFacultyVideo');
            const studentsGrid = document.getElementById('remoteStudentsGrid');
            const noStudentsNotice = document.getElementById('noStudentsNotice');
            const participantCountEl = document.getElementById('participantCount');

            const webrtc = new WebRTCClassroom({
                classId: <?= $active_room['id'] ?>,
                userId: <?= $user_id ?>,
                userRole: 'faculty',
                userName: 'Prof. <?= e($_SESSION['full_name'] ?? 'Instructor') ?>',
                baseUrl: '<?= BASE_URL ?>',
                onStatusChange: (statusText, statusType) => {
                    statusBadge.innerText = statusText;
                    statusBadge.className = `badge bg-${statusType} p-2`;
                },
                onParticipantUpdate: (participants) => {
                    participantCountEl.innerText = participants.length;
                    if (participants.length > 1) {
                        if (noStudentsNotice) noStudentsNotice.style.display = 'none';
                    } else {
                        if (noStudentsNotice) noStudentsNotice.style.display = 'block';
                    }
                },
                onRemoteTrack: (peerId, peerName, peerRole, stream) => {
                    if (noStudentsNotice) noStudentsNotice.style.display = 'none';

                    let peerVideo = document.getElementById(`peer_video_${peerId}`);
                    if (!peerVideo) {
                        const tile = document.createElement('div');
                        tile.id = `peer_tile_${peerId}`;
                        tile.className = 'position-relative bg-dark rounded overflow-hidden mb-2';
                        tile.style.height = '140px';
                        tile.innerHTML = `
                            <video id="peer_video_${peerId}" autoplay playsinline style="width:100%; height:100%; object-fit:cover;"></video>
                            <div class="position-absolute bottom-0 start-0 px-2 py-1 bg-dark bg-opacity-75 text-white" style="font-size:11px;">
                                <i class="bi bi-person-fill me-1 text-info"></i> ${peerName}
                            </div>
                        `;
                        studentsGrid.appendChild(tile);
                        peerVideo = tile.querySelector('video');
                    }
                    attachMediaStream(peerVideo, stream, false);
                },
                onRemoteLeave: (peerId) => {
                    const tile = document.getElementById(`peer_tile_${peerId}`);
                    if (tile) tile.remove();
                },
                onError: (errorMsg) => {
                    alertBox.classList.remove('d-none');
                    alertBox.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${errorMsg}`;
                    statusBadge.innerText = "Error";
                    statusBadge.className = "badge bg-danger p-2";
                }
            });

            // Initialize camera and start signaling
            const ok = await webrtc.init();
            if (ok && webrtc.localStream) {
                attachMediaStream(localVideo, webrtc.localStream, true);
            }

            // Media control buttons
            const micBtn = document.getElementById('toggleMicBtn');
            const camBtn = document.getElementById('toggleCamBtn');
            const screenBtn = document.getElementById('shareScreenBtn');

            micBtn.addEventListener('click', () => {
                const enabled = webrtc.toggleMic();
                micBtn.innerHTML = enabled ? '<i class="bi bi-mic-fill me-1"></i> Mic Mute' : '<i class="bi bi-mic-mute-fill text-danger me-1"></i> Unmute Mic';
            });

            camBtn.addEventListener('click', () => {
                const enabled = webrtc.toggleCam();
                camBtn.innerHTML = enabled ? '<i class="bi bi-camera-video-fill me-1"></i> Camera Off' : '<i class="bi bi-camera-video-off-fill text-danger me-1"></i> Turn Cam On';
            });

            let isSharing = false;
            screenBtn.addEventListener('click', async () => {
                if (!isSharing) {
                    const s = await webrtc.startScreenShare();
                    if (s) {
                        isSharing = true;
                        screenBtn.innerHTML = '<i class="bi bi-stop-circle text-danger me-1"></i> Stop Sharing';
                        attachMediaStream(localVideo, s, true);
                        localVideo.style.transform = 'none';
                    }
                } else {
                    webrtc.stopScreenShare();
                    isSharing = false;
                    screenBtn.innerHTML = '<i class="bi bi-display me-1"></i> Share Screen';
                    attachMediaStream(localVideo, webrtc.localStream, true);
                    localVideo.style.transform = 'scaleX(-1)';
                }
            });

            window.addEventListener('beforeunload', () => {
                webrtc.leaveRoom();
            });
        });
        </script>
      <?php endif; ?>

      <!-- Live Classes Catalog -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-broadcast text-danger"></i> Live Scheduled Lectures</h5>
        </div>

        <?php if (empty($all_live_classes)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
            No live video sessions scheduled yet. Click 'Schedule Live Video Class' to host a session.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Topic / Title</th>
                  <th>Subject</th>
                  <th>Scheduled Time</th>
                  <th>Duration</th>
                  <th>Passcode</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_live_classes as $cls): ?>
                  <tr>
                    <td>
                      <strong class="text-dark"><?= e($cls['title']) ?></strong>
                      <?php if (!empty($cls['description'])): ?>
                        <div class="small text-muted text-truncate" style="max-width: 280px;"><?= e($cls['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e($cls['subject_code']) ?></span> <span class="small"><?= e($cls['subject_name']) ?></span></td>
                    <td><?= date('M d, Y h:i A', strtotime($cls['scheduled_at'])) ?></td>
                    <td><?= (int)$cls['duration_minutes'] ?> Mins</td>
                    <td><code><?= e($cls['meeting_passcode'] ?? 'OPEN') ?></code></td>
                    <td>
                      <?php if ($cls['status'] === 'live'): ?>
                        <span class="badge bg-danger animate-pulse"><i class="bi bi-record-circle me-1"></i> LIVE NOW</span>
                      <?php elseif ($cls['status'] === 'scheduled'): ?>
                        <span class="badge bg-primary">Scheduled</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Ended</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php if ($cls['status'] === 'scheduled'): ?>
                        <a href="classroom.php?tab=live&action=start&class_id=<?= $cls['id'] ?>" class="btn btn-sm btn-success fw-bold">
                          <i class="bi bi-play-circle-fill me-1"></i> Start Live Studio
                        </a>
                      <?php elseif ($cls['status'] === 'live'): ?>
                        <a href="classroom.php?tab=live&room=<?= $cls['id'] ?>" class="btn btn-sm btn-danger fw-bold">
                          <i class="bi bi-camera-video-fill me-1"></i> Enter Studio
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">Concluded</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'assignments'): ?>
      <!-- TAB 1: ASSIGNMENTS MANAGEMENT -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-journal-text text-primary"></i> Coursework & Assignments</h5>
          <button class="bento-btn bento-btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAssignmentModal">
            <i class="bi bi-plus-lg me-1"></i> Create Assignment
          </button>
        </div>

        <?php if (empty($assignments)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            No assignments published yet.
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($assignments as $as): ?>
              <div class="col-md-6 col-lg-4">
                <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                  <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="badge bg-primary"><?= e($as['subject_code']) ?></span>
                      <small class="text-muted"><i class="bi bi-calendar-event me-1"></i> Due <?= date('M d, Y', strtotime($as['due_date'])) ?></small>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark"><?= e($as['title']) ?></h6>
                    <p class="small text-muted mb-2"><?= nl2br(e($as['description'])) ?></p>
                  </div>
                  <div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center small">
                      <span class="text-muted">Max: <strong><?= $as['max_marks'] ?> Marks</strong></span>
                      <?php if (!empty($as['attachment_path'])): ?>
                        <a href="<?= BASE_URL ?>/<?= e($as['attachment_path']) ?>" target="_blank" class="text-primary text-decoration-none"><i class="bi bi-paperclip"></i> Attachment</a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'submissions'): ?>
      <!-- TAB 2: EVALUATE SUBMISSIONS -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-patch-check text-primary"></i> Student Submissions</h5>
          <span class="text-muted small"><?= count($all_submissions) ?> Submissions</span>
        </div>

        <?php if (empty($all_submissions)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No student submissions received yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Student</th>
                  <th>Assignment</th>
                  <th>Submitted File</th>
                  <th>Submitted At</th>
                  <th>Status</th>
                  <th>Marks</th>
                  <th class="text-end">Grade</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_submissions as $sub): ?>
                  <tr>
                    <td>
                      <strong><?= e($sub['student_name']) ?></strong>
                      <div class="small text-muted">Roll: <?= e($sub['roll_no']) ?></div>
                    </td>
                    <td>
                      <span class="badge bg-light text-dark border me-1"><?= e($sub['subject_code']) ?></span>
                      <?= e($sub['assignment_title']) ?>
                    </td>
                    <td>
                      <?php if (!empty($sub['submitted_file'])): ?>
                        <a href="<?= BASE_URL ?>/<?= e($sub['submitted_file']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-download me-1"></i> View Submission
                        </a>
                      <?php else: ?>
                        <span class="text-muted small">Text Response</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($sub['submitted_at'])) ?></td>
                    <td>
                      <?php if ($sub['status'] === 'graded'): ?>
                        <span class="badge bg-success">Graded</span>

                      <?php else: ?>
                        <span class="badge bg-warning text-dark">Pending Evaluation</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong><?= $sub['marks_obtained'] !== null ? $sub['marks_obtained'] . ' / ' . $sub['max_marks'] : '-' ?></strong>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#gradeModal_<?= $sub['id'] ?>">
                        <i class="bi bi-pencil-square me-1"></i> Grade
                      </button>

                      <!-- Grade Modal -->
                      <div class="modal fade" id="gradeModal_<?= $sub['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content text-start">
                            <form method="POST" action="classroom.php?tab=submissions">
                              <input type="hidden" name="grade_submission" value="1">
                              <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                              <div class="modal-header">
                                <h5 class="modal-title fw-bold">Evaluate Submission</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <div class="mb-3">
                                  <label class="form-label small fw-bold">Marks Obtained (Max: <?= $sub['max_marks'] ?>) *</label>
                                  <input type="number" name="marks_obtained" class="form-control" max="<?= $sub['max_marks'] ?>" min="0" value="<?= $sub['marks_obtained'] ?? '' ?>" required>
                                </div>
                                <div class="mb-3">
                                  <label class="form-label small fw-bold">Faculty Feedback / Comments</label>
                                  <textarea name="feedback" class="form-control" rows="3" placeholder="Constructive remarks on coursework..."><?= e($sub['feedback'] ?? '') ?></textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="bento-btn bento-btn-primary">Save Grade</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>

                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'queries'): ?>
      <!-- TAB 3: STUDENT Q&A FORUM -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-question-circle text-primary"></i> Student Classroom Q&A</h5>
          <span class="text-muted small"><?= count($queries) ?> Total Questions</span>
        </div>

        <?php if (empty($queries)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-chat-square-dots fs-1 d-block mb-2"></i>
            No student questions posted yet.
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($queries as $q): ?>
              <div class="p-3 border rounded-3 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <span class="badge bg-light text-dark border me-1"><?= e($q['subject_code']) ?></span>
                    <strong><?= e($q['student_name']) ?></strong> <span class="small text-muted">(<?= e($q['roll_no']) ?>)</span>
                  </div>
                  <small class="text-muted"><?= date('M d, Y h:i A', strtotime($q['created_at'])) ?></small>
                </div>
                <div class="p-2 bg-light rounded text-dark mb-2"><?= nl2br(e($q['question'])) ?></div>
                
                <?php if (!empty($q['answer'])): ?>
                  <div class="p-2 bg-success-subtle border border-success rounded text-dark small mb-2">
                    <strong><i class="bi bi-reply-fill text-success"></i> Your Response:</strong>
                    <p class="mb-0 mt-1"><?= nl2br(e($q['answer'])) ?></p>
                  </div>
                <?php endif; ?>

                <form method="POST" action="classroom.php?tab=queries" class="mt-2">
                  <input type="hidden" name="answer_query" value="1">
                  <input type="hidden" name="query_id" value="<?= $q['id'] ?>">
                  <div class="input-group">
                    <input type="text" name="answer" class="form-control form-control-sm" placeholder="Write reply to student..." required>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send-fill"></i> Reply</button>
                  </div>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Schedule Live Session Modal -->
<div class="modal fade" id="scheduleLiveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="classroom.php?tab=live">
        <input type="hidden" name="schedule_live_class" value="1">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-camera-video-fill text-danger me-2"></i>Schedule Live Video Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Code *</label>
            <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS501" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Name *</label>
            <input type="text" name="subject_name" class="form-control" placeholder="e.g. Artificial Intelligence & Neural Networks" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Lecture Topic / Title *</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Chapter 4: Deep Learning Architectures" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Scheduled Date & Time *</label>
            <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Estimated Duration (Minutes)</label>
            <input type="number" name="duration_minutes" class="form-control" value="60" min="15" max="180">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Session Objective / Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Brief outline of concepts to be covered in this video class..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="bento-btn bento-btn-primary">Schedule Session</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: New Assignment -->
<div class="modal fade" id="newAssignmentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="classroom.php?tab=assignments" enctype="multipart/form-data">
        <input type="hidden" name="create_assignment" value="1">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">Publish New Assignment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold">Subject Code *</label>
              <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS501" required>
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold">Subject Name *</label>
              <input type="text" name="subject_name" class="form-control" placeholder="e.g. Database Systems" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Assignment Title *</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. SQL Complex Queries & Indexing Lab" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Instructions & Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Provide problem statement, formatting guidelines..."></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Submission Deadline *</label>
              <input type="datetime-local" name="due_date" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Maximum Points *</label>
              <input type="number" name="max_marks" class="form-control" value="100" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Attachment File (PDF, DOCX, ZIP)</label>
              <input type="file" name="attachment" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="bento-btn bento-btn-primary">Publish Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
