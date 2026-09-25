<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$parts = explode('/campus_management', $script_path);
$base_sub = count($parts) > 1 ? $parts[0] . '/campus_management' : (strpos($script_path, 'campus_management') !== false ? '/campus_management' : '');
define('BASE_URL', $protocol . $host . $base_sub);
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($path) {

    $url = (strpos($path, 'http') === 0) ? $path : BASE_URL . '/' . ltrim($path, '/');
    header("Location: " . $url);
    exit;
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_role() {
    return $_SESSION['role'] ?? null;
}

function require_auth($allowed_roles = []) {
    if (!is_logged_in()) {
        set_flash('warning', 'Please login to access the system.');
        redirect('auth/login.php');
    }
    if (!empty($allowed_roles)) {
        if (is_string($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        if (!in_array($_SESSION['role'], $allowed_roles)) {
            set_flash('danger', 'Unauthorized access! You do not have permission to view that page.');
            $role_dashboards = [
                'admin' => 'admin/index.php',
                'faculty' => 'faculty/index.php',
                'student' => 'student/index.php'
            ];
            $target = $role_dashboards[$_SESSION['role']] ?? 'index.php';
            redirect($target);
        }
    }
}

function require_admin() {
    require_auth('admin');
}

function require_faculty() {
    require_auth('faculty');
}

function require_student() {
    require_auth('student');
}


function get_unread_notifications_count($pdo, $user_id, $role) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR role_target = ? OR role_target = 'all') AND is_read = 0");
        $stmt->execute([$user_id, $role]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function format_bytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function time_elapsed_string($datetime, $full = false) {
    if (empty($datetime)) return 'just now';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = (int)floor($diff->d / 7);
    $days = $diff->d % 7;

    $string = [
        ['val' => $diff->y, 'label' => 'year'],
        ['val' => $diff->m, 'label' => 'month'],
        ['val' => $weeks,   'label' => 'week'],
        ['val' => $days,    'label' => 'day'],
        ['val' => $diff->h, 'label' => 'hour'],
        ['val' => $diff->i, 'label' => 'min'],
        ['val' => $diff->s, 'label' => 'sec'],
    ];

    $result = [];
    foreach ($string as $item) {
        if ($item['val'] > 0) {
            $result[] = $item['val'] . ' ' . $item['label'] . ($item['val'] > 1 ? 's' : '');
        }
    }

    if (!$full) {
        $result = array_slice($result, 0, 1);
    }
    return !empty($result) ? implode(', ', $result) . ' ago' : 'just now';
}
