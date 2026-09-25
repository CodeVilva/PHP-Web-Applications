<?php
$db_hosts = ['127.0.0.1', 'localhost'];
$db_ports = [3306];
$db_user = 'root';
$db_pass = 'root';
$db_name = 'campus_db';

$pdo = null;
$conn_error = null;

foreach ($db_ports as $port) {
    foreach ($db_hosts as $host) {
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            break 2;
        } catch (PDOException $e) {

            try {
                $pdo_init = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                
                $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                
                $sql_file = __DIR__ . '/../database/schema.sql';
                if (file_exists($sql_file)) {
                    $sql = file_get_contents($sql_file);
                    $pdo->exec($sql);
                }
                break 2;
            } catch (PDOException $ex) {
                $conn_error = $ex->getMessage();
            }
        }
    }
}

if (!$pdo) {
    // Only die if not being included in setup or if direct call
    if (php_sapi_name() !== 'cli') {
        die("<div style='font-family:sans-serif;padding:30px;'><h2 style='color:#0a66c2;'>Database Setup Required</h2><p>Please make sure MySQL is running in WAMP (WampServer green icon) and visit <a href='setup_db.php'>setup_db.php</a> to initialize database.</p><p><small style='color:#666;'>Error Details: " . htmlspecialchars($conn_error ?? '') . "</small></p></div>");
    }
} else {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM webrtc_participants LIKE 'live_class_id'")->fetch();
        if (!$chk) {
            $pdo->exec("DROP TABLE IF EXISTS webrtc_signals;");
            $pdo->exec("DROP TABLE IF EXISTS webrtc_participants;");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `webrtc_participants` (
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
                CREATE TABLE IF NOT EXISTS `webrtc_signals` (
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
        // Table already healthy
    }
}

