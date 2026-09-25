<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

// Self-healing schema validation for webrtc tables
try {
    $chk = $pdo->query("SHOW COLUMNS FROM webrtc_participants LIKE 'live_class_id'")->fetch();
    if (!$chk) {
        $pdo->exec("DROP TABLE IF EXISTS webrtc_signals;");
        $pdo->exec("DROP TABLE IF EXISTS webrtc_participants;");

        $pdo->exec("
            CREATE TABLE `webrtc_participants` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `live_class_id` INT NOT NULL,
              `user_id` INT NOT NULL,
              `user_role` ENUM('faculty', 'student') NOT NULL DEFAULT 'student',
              `display_name` VARCHAR(100) NOT NULL,
              `socket_id` VARCHAR(100) DEFAULT NULL,
              `status` VARCHAR(20) DEFAULT 'connected',
              `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `uniq_class_user` (`live_class_id`, `user_id`),
              INDEX (`live_class_id`),
              INDEX (`last_ping`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE `webrtc_signals` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `live_class_id` INT NOT NULL,
              `sender_id` INT NOT NULL,
              `sender_role` VARCHAR(20) NOT NULL DEFAULT 'student',
              `receiver_id` INT NULL DEFAULT NULL,
              `signal_type` VARCHAR(50) NOT NULL,
              `signal_data` LONGTEXT NOT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX (`live_class_id`),
              INDEX (`receiver_id`),
              INDEX (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
} catch (Exception $e) {
    // Continue if already healthy
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$class_id = (int)($_POST['live_class_id'] ?? $_GET['live_class_id'] ?? 0);

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid live class ID.']);
    exit;
}

// 1. JOIN ROOM
if ($action === 'join') {
    $socket_id = 'peer_' . $user_id . '_' . bin2hex(random_bytes(4));
    
    // Check if class is active or ended
    $stmt = $pdo->prepare("SELECT status FROM live_classes WHERE id = ?");
    $stmt->execute([$class_id]);
    $class_info = $stmt->fetch();

    if (!$class_info || $class_info['status'] === 'ended') {
        echo json_encode(['success' => false, 'error' => 'This live classroom session has ended.']);
        exit;
    }

    try {
        $ins = $pdo->prepare("
            INSERT INTO webrtc_participants (live_class_id, user_id, user_role, display_name, socket_id, status, last_ping)
            VALUES (?, ?, ?, ?, ?, 'connected', NOW())
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), socket_id = VALUES(socket_id), status = 'connected', last_ping = NOW()
        ");
        $ins->execute([$class_id, $user_id, $user_role, $user_name, $socket_id]);

        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'user_role' => $user_role,
            'display_name' => $user_name,
            'socket_id' => $socket_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 2. SEND SIGNAL (Offer, Answer, ICE Candidate, Join Request)
if ($action === 'send_signal') {
    $receiver_id = !empty($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : null;
    $signal_type = $_POST['signal_type'] ?? '';
    $signal_data = $_POST['signal_data'] ?? '';

    if (!in_array($signal_type, ['offer', 'answer', 'ice_candidate', 'leave', 'end_call', 'join_request'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid signal type.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO webrtc_signals (live_class_id, sender_id, sender_role, receiver_id, signal_type, signal_data) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $user_id, $user_role, $receiver_id, $signal_type, $signal_data]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 3. POLL SIGNALS & DISCOVER PARTICIPANTS
if ($action === 'poll_signals') {
    $last_id = (int)($_GET['last_signal_id'] ?? 0);

    try {
        // Update heartbeat
        $upd = $pdo->prepare("UPDATE webrtc_participants SET last_ping = NOW(), status = 'connected' WHERE live_class_id = ? AND user_id = ?");
        $upd->execute([$class_id, $user_id]);

        // Check if class ended
        $stmt = $pdo->prepare("SELECT status FROM live_classes WHERE id = ?");
        $stmt->execute([$class_id]);
        $class_info = $stmt->fetch();
        $class_ended = ($class_info && $class_info['status'] === 'ended');

        // Get active participants (pinged in last 20 seconds)
        $part_stmt = $pdo->prepare("
            SELECT user_id, user_role, display_name, socket_id, status 
            FROM webrtc_participants 
            WHERE live_class_id = ? AND status = 'connected' AND last_ping >= (NOW() - INTERVAL 20 SECOND)
        ");
        $part_stmt->execute([$class_id]);
        $participants = $part_stmt->fetchAll();

        // Fetch new incoming signals directed to me or broadcast (receiver_id is null or receiver_id == user_id)
        $sig_stmt = $pdo->prepare("
            SELECT s.*, p.display_name as sender_name
            FROM webrtc_signals s
            LEFT JOIN webrtc_participants p ON (s.live_class_id = p.live_class_id AND s.sender_id = p.user_id)
            WHERE s.live_class_id = ? 
              AND s.id > ? 
              AND s.sender_id != ? 
              AND (s.receiver_id IS NULL OR s.receiver_id = ?)
            ORDER BY s.id ASC
        ");
        $sig_stmt->execute([$class_id, $last_id, $user_id, $user_id]);
        $signals = $sig_stmt->fetchAll();

        $new_last_id = $last_id;
        foreach ($signals as $s) {
            if ($s['id'] > $new_last_id) {
                $new_last_id = (int)$s['id'];
            }
        }

        echo json_encode([
            'success' => true,
            'class_ended' => $class_ended,
            'participants' => $participants,
            'signals' => $signals,
            'last_signal_id' => $new_last_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 4. LEAVE ROOM
if ($action === 'leave') {
    try {
        $pdo->prepare("UPDATE webrtc_participants SET status = 'disconnected' WHERE live_class_id = ? AND user_id = ?")->execute([$class_id, $user_id]);
        $pdo->prepare("INSERT INTO webrtc_signals (live_class_id, sender_id, sender_role, signal_type, signal_data) VALUES (?, ?, ?, 'leave', '{}')")->execute([$class_id, $user_id, $user_role]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action.']);
