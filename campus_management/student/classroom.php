<?php
require_once __DIR__ . '/../config/config.php';
require_student();

$user_id = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'live';
$action = $_GET['action'] ?? '';

// Fetch Student Profile
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? 0;
$dept = $student['department'] ?? '';

// Fetch Joined Room for Live WebRTC
$joined_room = null;
$join_param = (int)($_GET['join'] ?? 0);
if ($join_param > 0) {
    $stmt = $pdo->prepare("
        SELECT lc.*, f.full_name as faculty_name, f.user_id as faculty_user_id
        FROM live_classes lc
        JOIN faculty f ON lc.faculty_id = f.id
        WHERE lc.id = ? AND lc.status = 'live'
    ");
    $stmt->execute([$join_param]);
    $joined_room = $stmt->fetch();

    if ($joined_room) {
        $att = $pdo->prepare("INSERT IGNORE INTO live_class_attendees (live_class_id, student_id) VALUES (?, ?)");
        $att->execute([$joined_room['id'], $student_id]);
    } else {
        set_flash('danger', 'This live classroom session has ended or is not active.');
        redirect('student/classroom.php?tab=live');
    }
}

// -------------------------------------------------------------
// POST: Submit Assignment
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    
    $file_path = null;
    if (!empty($_FILES['submission_file']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/submissions/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
        $file_name = 'sub_' . $student_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $upload_dir . $file_name)) {
            $file_path = 'uploads/submissions/' . $file_name;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO assignment_submissions (assignment_id, student_id, submitted_file, status, submitted_at)
        VALUES (?, ?, ?, 'submitted', NOW())
        ON DUPLICATE KEY UPDATE submitted_file = VALUES(submitted_file), status = 'submitted', submitted_at = NOW()
    ");
    $stmt->execute([$assignment_id, $student_id, $file_path]);

    set_flash('success', "Assignment coursework submitted successfully!");
    redirect('student/classroom.php?tab=assignments');
}

// -------------------------------------------------------------
// POST: Post Student Question / Query
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ask_query'])) {
    $subject_code = sanitize($_POST['subject_code'] ?? '');
    $question = sanitize($_POST['question'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO classroom_queries (subject_code, student_id, question) VALUES (?, ?, ?)");
    $stmt->execute([$subject_code, $student_id, $question]);

    set_flash('success', "Question submitted to course instructor.");
    redirect('student/classroom.php?tab=queries');
}

// Fetch Active and Upcoming Live Classes
$stmt = $pdo->prepare("
    SELECT lc.*, f.full_name as faculty_name, f.designation as faculty_designation
    FROM live_classes lc
    JOIN faculty f ON lc.faculty_id = f.id
    ORDER BY (lc.status = 'live') DESC, lc.scheduled_at DESC
    LIMIT 30
");
$stmt->execute();
$all_live_classes = $stmt->fetchAll();

// Fetch Assignments with Student's Submission Status
$stmt = $pdo->prepare("
    SELECT a.*, f.full_name as faculty_name, s.id as submission_id, s.submitted_file, s.marks_obtained, s.feedback, s.status as sub_status
    FROM assignments a
    JOIN faculty f ON a.faculty_id = f.id
    LEFT JOIN assignment_submissions s ON (a.id = s.assignment_id AND s.student_id = ?)
    ORDER BY a.due_date ASC
");
$stmt->execute([$student_id]);
$assignments = $stmt->fetchAll();


// Fetch Student's Queries
$stmt = $pdo->prepare("
    SELECT q.*, f.full_name as faculty_responder
    FROM classroom_queries q
    LEFT JOIN faculty f ON q.answered_by_faculty_id = f.id
    WHERE q.student_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$student_id]);
$queries = $stmt->fetchAll();

$page_title = 'Online Classroom & Live Learning';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-slate);">Online Classroom</h3>
        <p class="text-muted small mb-0">Live WebRTC Interactive Video Lectures & Coursework Hub</p>
      </div>
      <div>
        <button class="bento-btn bento-btn-primary" data-bs-toggle="modal" data-bs-target="#askQuestionModal">
          <i class="bi bi-chat-left-text me-1"></i> Ask Course Query
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'live' ? 'active' : '' ?>" href="classroom.php?tab=live">
          <i class="bi bi-broadcast text-danger me-1"></i> Live Sessions
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>" href="classroom.php?tab=assignments">
          <i class="bi bi-journal-check me-1"></i> Assignments & Homework
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $tab === 'queries' ? 'active' : '' ?>" href="classroom.php?tab=queries">
          <i class="bi bi-question-circle me-1"></i> My Queries (<?= count($queries) ?>)
        </a>
      </li>
    </ul>

    <?php if ($tab === 'live'): ?>
      <!-- TAB 0: LIVE STREAM PORTAL -->
      <?php if ($joined_room): ?>
        <div class="bento-card mb-4 p-3 bg-dark text-white rounded-3 position-relative" style="background: #0F172A !important;">
          <!-- Header Bar -->
          <div class="d-flex justify-content-between align-items-center mb-3 px-2 flex-wrap gap-2">
            <div>
              <span class="badge bg-danger animate-pulse me-2"><i class="bi bi-record-circle me-1"></i> CONNECTED TO LIVE LECTURE</span>
              <strong class="text-white fs-5"><?= e($joined_room['title']) ?></strong> (<code><?= e($joined_room['subject_code']) ?></code>)
            </div>
            <div class="d-flex align-items-center gap-3">
              <span id="stuConnectionBadge" class="badge bg-secondary p-2">Connecting WebRTC...</span>
              <a href="classroom.php?tab=live" class="btn btn-outline-danger btn-sm fw-bold">
                <i class="bi bi-box-arrow-right me-1"></i> Leave Class
              </a>
            </div>
          </div>

          <!-- Alert Container for Errors -->
          <div id="stuWebrtcAlert" class="alert alert-danger d-none mb-3" role="alert"></div>

          <!-- Video Grid Stage -->
          <div class="row g-3">
            <!-- Faculty Spotlight Stage -->
            <div class="col-lg-9">
              <div class="position-relative bg-black rounded-3 overflow-hidden d-flex align-items-center justify-content-center" style="height: 480px; border: 1px solid #334155;">
                <video id="remoteFacultyVideo" autoplay playsinline style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 2; display: none;"></video>
                
                <!-- Placeholder if faculty video not received yet -->
                <div id="facultyVideoPlaceholder" class="text-center p-4" style="z-index: 1;">
                  <div class="rounded-circle bg-dark d-inline-flex p-3 border border-secondary mb-2">
                    <i class="bi bi-person-video3 fs-1 text-primary"></i>
                  </div>
                  <h6 class="text-white mb-1"><?= e($joined_room['faculty_name']) ?>'s Broadcast</h6>
                  <p class="text-muted small mb-0">Connecting to instructor's WebRTC video stream...</p>
                </div>

                <div class="position-absolute bottom-0 start-0 m-3 px-3 py-1 rounded bg-dark bg-opacity-75 text-white fw-bold small">
                  <i class="bi bi-mortarboard-fill me-1 text-warning"></i> <?= e($joined_room['faculty_name']) ?> (Instructor)
                </div>

                <!-- Picture-in-Picture Local Student Preview -->
                <div class="position-absolute bottom-0 end-0 m-3 rounded-3 overflow-hidden border border-secondary shadow" style="width: 170px; height: 125px; background: #1E293B; z-index: 10;">
                  <video id="localStudentVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                  <div class="position-absolute bottom-0 start-0 w-100 px-2 py-1 bg-dark bg-opacity-75 text-white" style="font-size: 10px;">
                    You (<?= e($_SESSION['full_name'] ?? 'Student') ?>)
                  </div>
                </div>
              </div>

              <!-- Media Control Toolbar -->
              <div class="d-flex justify-content-between align-items-center mt-3 p-2 bg-black rounded border border-secondary">
                <div class="d-flex gap-2">
                  <button type="button" id="stuToggleMicBtn" class="btn btn-outline-light btn-sm fw-semibold">
                    <i class="bi bi-mic-fill me-1"></i> Mic Mute
                  </button>
                  <button type="button" id="stuToggleCamBtn" class="btn btn-outline-light btn-sm fw-semibold">
                    <i class="bi bi-camera-video-fill me-1"></i> Camera Off
                  </button>
                </div>
                <div class="small text-white-50">
                  <i class="bi bi-shield-lock-fill text-success me-1"></i> Connected to Live Session
                </div>
              </div>
            </div>

            <!-- Classmates / Participants Strip -->
            <div class="col-lg-3">
              <div class="bento-card bg-black text-white p-3 h-100 d-flex flex-column" style="background: #020617 !important; border: 1px solid #1E293B;">
                <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom border-secondary">
                  <h6 class="mb-0 text-white fw-bold"><i class="bi bi-people-fill text-primary me-2"></i>Classroom (<span id="stuPartCount">1</span>)</h6>
                  <span class="badge bg-success">Mesh</span>
                </div>

                <div id="stuClassmatesList" class="flex-grow-1 overflow-y-auto d-flex flex-column gap-2" style="max-height: 440px;">
                  <div class="p-2 rounded bg-dark border border-secondary small">
                    <i class="bi bi-mortarboard-fill text-warning me-1"></i> <strong><?= e($joined_room['faculty_name']) ?></strong> (Instructor)
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
            const statusBadge = document.getElementById('stuConnectionBadge');
            const alertBox = document.getElementById('stuWebrtcAlert');
            const localVideo = document.getElementById('localStudentVideo');
            const remoteFacultyVideo = document.getElementById('remoteFacultyVideo');
            const placeholder = document.getElementById('facultyVideoPlaceholder');
            const partCount = document.getElementById('stuPartCount');
            const classmatesList = document.getElementById('stuClassmatesList');

            const facultyUserId = <?= (int)($joined_room['faculty_user_id'] ?? 0) ?>;

            const webrtc = new WebRTCClassroom({
                classId: <?= $joined_room['id'] ?>,
                userId: <?= $user_id ?>,
                userRole: 'student',
                userName: '<?= e($_SESSION['full_name'] ?? 'Student') ?>',
                baseUrl: '<?= BASE_URL ?>',
                onStatusChange: (statusText, statusType) => {
                    statusBadge.innerText = statusText;
                    statusBadge.className = `badge bg-${statusType} p-2`;
                },
                onParticipantUpdate: (participants) => {
                    partCount.innerText = participants.length;
                    classmatesList.innerHTML = '';
                    participants.forEach(p => {
                        const isFac = (p.user_role === 'faculty');
                        const item = document.createElement('div');
                        item.className = 'p-2 rounded bg-dark border border-secondary small';
                        item.innerHTML = isFac ? 
                            `<i class="bi bi-mortarboard-fill text-warning me-1"></i> <strong>${p.display_name}</strong> (Instructor)` :
                            `<i class="bi bi-person-fill text-info me-1"></i> ${p.display_name}`;
                        classmatesList.appendChild(item);
                    });
                },
                onRemoteTrack: (peerId, peerName, peerRole, stream) => {
                    console.log("[Student WebRTC] Received remote stream from peer:", peerId, peerName, peerRole);
                    if (placeholder) placeholder.style.setProperty('display', 'none', 'important');
                    if (remoteFacultyVideo) {
                        remoteFacultyVideo.style.display = 'block';
                        attachMediaStream(remoteFacultyVideo, stream, false);
                    }
                },
                onRemoteLeave: (peerId) => {
                    console.log("[Student WebRTC] Peer left:", peerId);
                    if (placeholder) placeholder.style.display = 'block';
                    if (remoteFacultyVideo) remoteFacultyVideo.srcObject = null;
                },
                onClassEnded: (msg) => {
                    alert(msg);
                    window.location.href = "classroom.php?tab=live";
                },
                onError: (errorMsg) => {
                    alertBox.classList.remove('d-none');
                    alertBox.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2"></i> ${errorMsg}`;
                    statusBadge.innerText = "Error";
                    statusBadge.className = "badge bg-danger p-2";
                }
            });

            const ok = await webrtc.init();
            if (ok && webrtc.localStream) {
                attachMediaStream(localVideo, webrtc.localStream, true);
            }

            const micBtn = document.getElementById('stuToggleMicBtn');
            const camBtn = document.getElementById('stuToggleCamBtn');

            micBtn.addEventListener('click', () => {
                const enabled = webrtc.toggleMic();
                micBtn.innerHTML = enabled ? '<i class="bi bi-mic-fill me-1"></i> Mic Mute' : '<i class="bi bi-mic-mute-fill text-danger me-1"></i> Unmute Mic';
            });

            camBtn.addEventListener('click', () => {
                const enabled = webrtc.toggleCam();
                camBtn.innerHTML = enabled ? '<i class="bi bi-camera-video-fill me-1"></i> Camera Off' : '<i class="bi bi-camera-video-off-fill text-danger me-1"></i> Turn Cam On';
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
          <h5 class="bento-title"><i class="bi bi-broadcast text-danger"></i> Live & Upcoming Lectures</h5>
        </div>

        <?php if (empty($all_live_classes)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-camera-video-off fs-1 d-block mb-2"></i>
            No active or scheduled live lectures right now.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light small">
                <tr>
                  <th>Lecture Topic</th>
                  <th>Instructor</th>
                  <th>Subject</th>
                  <th>Schedule</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($all_live_classes as $cls): ?>
                  <tr>
                    <td>
                      <strong class="text-dark"><?= e($cls['title']) ?></strong>
                      <?php if (!empty($cls['description'])): ?>
                        <div class="small text-muted text-truncate" style="max-width: 260px;"><?= e($cls['description']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <i class="bi bi-person-fill text-primary me-1"></i> <?= e($cls['faculty_name']) ?>
                      <div class="small text-muted"><?= e($cls['faculty_designation']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= e($cls['subject_code']) ?></span></td>
                    <td><?= date('M d, Y h:i A', strtotime($cls['scheduled_at'])) ?></td>
                    <td>
                      <?php if ($cls['status'] === 'live'): ?>
                        <span class="badge bg-danger animate-pulse"><i class="bi bi-record-circle me-1"></i> LIVE NOW</span>
                      <?php elseif ($cls['status'] === 'scheduled'): ?>
                        <span class="badge bg-primary">Upcoming</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Concluded</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php if ($cls['status'] === 'live'): ?>
                        <a href="classroom.php?tab=live&join=<?= $cls['id'] ?>" class="btn btn-sm btn-danger fw-bold shadow-sm">
                          <i class="bi bi-camera-video-fill me-1"></i> Join Live Session
                        </a>
                      <?php elseif ($cls['status'] === 'scheduled'): ?>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i> Starts Soon</span>
                      <?php else: ?>
                        <span class="text-muted small">Ended</span>
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
      <!-- TAB 1: STUDENT ASSIGNMENTS -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-journal-bookmark text-primary"></i> Coursework & Homework</h5>
          <span class="text-muted small"><?= count($assignments) ?> Total Assignments</span>
        </div>

        <?php if (empty($assignments)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            No assignments published for your courses yet.
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($assignments as $as): ?>
              <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-white h-100 d-flex flex-column justify-content-between shadow-sm">
                  <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="badge bg-primary"><?= e($as['subject_code']) ?></span>
                      <small class="text-muted"><i class="bi bi-calendar-event me-1"></i> Due: <?= date('M d, Y h:i A', strtotime($as['due_date'])) ?></small>
                    </div>
                    <h6 class="fw-bold mb-1 text-dark"><?= e($as['title']) ?></h6>
                    <p class="small text-muted mb-2"><?= nl2br(e($as['description'])) ?></p>
                    
                    <div class="p-2 bg-light rounded small mb-2">
                      <div class="d-flex justify-content-between">
                        <span><strong>Max Marks:</strong> <?= $as['max_marks'] ?></span>
                        <span><strong>Faculty:</strong> <?= e($as['faculty_name']) ?></span>
                      </div>
                      <?php if (!empty($as['attachment_path'])): ?>
                        <div class="mt-2 pt-1 border-top">
                          <a href="<?= BASE_URL ?>/<?= e($as['attachment_path']) ?>" target="_blank" class="text-primary text-decoration-none fw-semibold">
                            <i class="bi bi-paperclip me-1"></i> Download Assignment Materials
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>


                  <div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                      <div>
                        <?php if ($as['sub_status'] === 'graded'): ?>
                          <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i> Graded: <?= $as['marks_obtained'] ?> / <?= $as['max_marks'] ?></span>
                          <?php if ($as['feedback']): ?>
                            <div class="small text-muted mt-1"><em>"<?= e($as['feedback']) ?>"</em></div>
                          <?php endif; ?>
                        <?php elseif ($as['sub_status'] === 'submitted'): ?>
                          <span class="badge bg-info text-dark"><i class="bi bi-check-circle me-1"></i> Submitted &bull; Awaiting Grade</span>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i> Pending Submission</span>
                        <?php endif; ?>
                      </div>

                      <button class="btn btn-sm <?= $as['sub_status'] ? 'btn-outline-primary' : 'btn-primary' ?>" data-bs-toggle="modal" data-bs-target="#submitModal_<?= $as['id'] ?>">
                        <i class="bi bi-upload me-1"></i> <?= $as['sub_status'] ? 'Resubmit' : 'Submit Work' ?>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Submit Modal -->
              <div class="modal fade" id="submitModal_<?= $as['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content text-start">
                    <form method="POST" action="classroom.php?tab=assignments" enctype="multipart/form-data">
                      <input type="hidden" name="submit_assignment" value="1">
                      <input type="hidden" name="assignment_id" value="<?= $as['id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold">Submit Assignment: <?= e($as['title']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p class="small text-muted mb-3">Upload your completed assignment file (PDF, DOCX, ZIP, or Code).</p>
                        <div class="mb-3">
                          <label class="form-label small fw-bold">Select File to Upload *</label>
                          <input type="file" name="submission_file" class="form-control" required>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="bento-btn bento-btn-primary">Upload & Submit</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'queries'): ?>
      <!-- TAB 2: MY CLASSROOM QUERIES -->
      <div class="bento-card mb-4">
        <div class="bento-header">
          <h5 class="bento-title"><i class="bi bi-chat-left-dots text-primary"></i> My Course Inquiries & Answers</h5>
          <button class="bento-btn bento-btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#askQuestionModal">
            <i class="bi bi-plus-lg me-1"></i> Ask New Question
          </button>
        </div>

        <?php if (empty($queries)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
            You haven't asked any course questions yet.
          </div>
        <?php else: ?>
          <div class="d-flex flex-column gap-3">
            <?php foreach ($queries as $q): ?>
              <div class="p-3 border rounded-3 bg-white shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="badge bg-light text-dark border"><?= e($q['subject_code']) ?></span>
                  <small class="text-muted"><?= date('M d, Y h:i A', strtotime($q['created_at'])) ?></small>
                </div>
                <h6 class="fw-bold mb-2 text-dark">Q: <?= nl2br(e($q['question'])) ?></h6>
                
                <?php if (!empty($q['answer'])): ?>
                  <div class="p-3 bg-success-subtle border border-success rounded text-dark small">
                    <strong class="text-success"><i class="bi bi-reply-fill"></i> Faculty Response (<?= e($q['faculty_responder'] ?? 'Instructor') ?>):</strong>
                    <p class="mb-0 mt-1"><?= nl2br(e($q['answer'])) ?></p>
                    <div class="text-muted mt-1" style="font-size: 11px;">Answered on <?= date('M d, Y h:i A', strtotime($q['answered_at'])) ?></div>
                  </div>
                <?php else: ?>
                  <div class="p-2 bg-warning-subtle border border-warning rounded small text-dark">
                    <i class="bi bi-hourglass-split text-warning me-1"></i> Awaiting instructor's response...
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<!-- Modal: Ask Question -->
<div class="modal fade" id="askQuestionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="classroom.php?tab=queries">
        <input type="hidden" name="ask_query" value="1">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-question-circle-fill text-primary me-2"></i>Ask Coursework Question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Subject Code *</label>
            <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS501" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Your Question / Doubt *</label>
            <textarea name="question" class="form-control" rows="4" placeholder="Clearly describe your question regarding lectures, lab assignments, or concepts..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="bento-btn bento-btn-primary">Post Question</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
