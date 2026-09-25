<?php
require_once __DIR__ . '/../config/config.php';

try {
    // 1. Table: webrtc_signals
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `webrtc_signals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `live_class_id` INT NOT NULL,
            `sender_id` INT NOT NULL,
            `sender_role` ENUM('faculty', 'student') NOT NULL,
            `receiver_id` INT NULL DEFAULT NULL,
            `signal_type` ENUM('offer', 'answer', 'ice_candidate', 'leave', 'end_call') NOT NULL,
            `signal_data` LONGTEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`live_class_id`),
            INDEX (`receiver_id`),
            INDEX (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified webrtc_signals table\n";

    // 2. Table: webrtc_participants
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `webrtc_participants` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `live_class_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `user_role` ENUM('faculty', 'student') NOT NULL,
            `display_name` VARCHAR(100) NOT NULL,
            `socket_id` VARCHAR(60) NOT NULL,
            `status` ENUM('connected', 'disconnected') DEFAULT 'connected',
            `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_room_user` (`live_class_id`, `user_id`),
            INDEX (`live_class_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified webrtc_participants table\n";

    // 3. Table: faculty_face_profiles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `faculty_face_profiles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `faculty_id` INT NOT NULL UNIQUE,
            `embedding` LONGTEXT NOT NULL,
            `sample_image` VARCHAR(255) DEFAULT NULL,
            `model_name` VARCHAR(50) DEFAULT 'opencv_sface',
            `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified faculty_face_profiles table\n";

} catch (Exception $e) {
    echo "[ERROR] Database migration error: " . $e->getMessage() . "\n";
}
