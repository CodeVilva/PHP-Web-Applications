<?php
require_once __DIR__ . '/../config/config.php';

try {
    // 1. Add gender to students if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'gender'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN gender ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male' AFTER social_category;");
        echo "[OK] Added gender column to students table\n";
    } else {
        echo "[INFO] gender column already exists in students table\n";
    }

    // 2. Create live_classes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `live_classes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `faculty_id` INT NOT NULL,
            `subject_code` VARCHAR(30) NOT NULL,
            `subject_name` VARCHAR(100) NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT,
            `scheduled_at` DATETIME NOT NULL,
            `duration_minutes` INT DEFAULT 60,
            `room_id` VARCHAR(50) NOT NULL,
            `status` ENUM('scheduled', 'live', 'ended') DEFAULT 'scheduled',
            `meeting_passcode` VARCHAR(20) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified live_classes table\n";

    // 3. Create live_class_attendees table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `live_class_attendees` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `live_class_id` INT NOT NULL,
            `student_id` INT NOT NULL,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified live_class_attendees table\n";

    // 4. Create live_class_messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `live_class_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `live_class_id` INT NOT NULL,
            `sender_name` VARCHAR(100) NOT NULL,
            `sender_role` ENUM('faculty', 'student') NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Created/Verified live_class_messages table\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
