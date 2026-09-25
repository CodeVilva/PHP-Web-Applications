-- Integrated Student Campus Management System (ISCMS)
-- Master Production & Testing Schema

CREATE DATABASE IF NOT EXISTS `campus_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campus_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `classroom_queries`;
DROP TABLE IF EXISTS `assignment_submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `external_events`;
DROP TABLE IF EXISTS `faculty_attendance`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `scholarship_applications`;
DROP TABLE IF EXISTS `scholarships`;
DROP TABLE IF EXISTS `event_registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `community_service_participants`;
DROP TABLE IF EXISTS `community_service`;
DROP TABLE IF EXISTS `learning_materials`;
DROP TABLE IF EXISTS `timetable`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `faculty`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'faculty', 'student') NOT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default-avatar.png',
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `roll_no` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `semester` VARCHAR(20) NOT NULL,
  `batch_year` VARCHAR(20) NOT NULL,
  `social_category` ENUM('SC', 'ST', 'BC', 'MBC', 'OC') DEFAULT 'BC',
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
  `phone` VARCHAR(25),
  `address` TEXT,
  `gpa` DECIMAL(3,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `faculty` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `employee_id` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `designation` VARCHAR(100) NOT NULL,
  `is_hod` TINYINT(1) NOT NULL DEFAULT 0,
  `qualification` VARCHAR(100),
  `phone` VARCHAR(25),
  `office_room` VARCHAR(50),
  `bio` TEXT,
  `subjects_handled` VARCHAR(255) DEFAULT 'Data Structures, Database Management, Distributed Systems',
  `face_registered` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `faculty_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `check_in_time` TIME NOT NULL,
  `check_out_time` TIME DEFAULT NULL,
  `verification_method` ENUM('face_recognition', 'manual_override') DEFAULT 'face_recognition',
  `confidence_score` DECIMAL(4,2) DEFAULT 98.50,
  `status` ENUM('present', 'late', 'half_day', 'absent') DEFAULT 'present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `faculty_id` INT NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `timetable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department` VARCHAR(100) NOT NULL,
  `semester` VARCHAR(20) NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `faculty_id` INT NOT NULL,
  `room_no` VARCHAR(50) NOT NULL,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `learning_materials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `file_path` VARCHAR(255) NOT NULL,
  `file_type` VARCHAR(50) DEFAULT 'PDF',
  `file_size` VARCHAR(30) DEFAULT '1.0 MB',
  `download_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NOT NULL,
  `subject_code` VARCHAR(30) NOT NULL,
  `subject_name` VARCHAR(100) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `due_date` DATETIME NOT NULL,
  `max_marks` INT DEFAULT 100,
  `attachment_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `assignment_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `submitted_file` VARCHAR(255) NOT NULL,
  `submission_notes` TEXT,
  `marks_obtained` INT DEFAULT NULL,
  `feedback` TEXT,
  `status` ENUM('submitted', 'graded', 'resubmit_requested') DEFAULT 'submitted',
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `classroom_queries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_code` VARCHAR(30) NOT NULL,
  `student_id` INT NOT NULL,
  `question` TEXT NOT NULL,
  `answer` TEXT DEFAULT NULL,
  `answered_by_faculty_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `answered_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `live_classes` (
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

CREATE TABLE `live_class_attendees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `live_class_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `live_class_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `live_class_id` INT NOT NULL,
  `sender_name` VARCHAR(100) NOT NULL,
  `sender_role` ENUM('faculty', 'student') NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`live_class_id`) REFERENCES `live_classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE `faculty_face_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NOT NULL UNIQUE,
  `embedding` LONGTEXT NOT NULL,
  `model_name` VARCHAR(50) DEFAULT 'opencv_sface',
  `sample_image_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `community_service` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `organizer` VARCHAR(100) NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `event_date` DATE NOT NULL,
  `hours_credited` INT NOT NULL DEFAULT 4,
  `max_participants` INT NOT NULL DEFAULT 50,
  `status` ENUM('upcoming', 'completed', 'cancelled') DEFAULT 'upcoming',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `community_service_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activity_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('registered', 'attended', 'verified') DEFAULT 'registered',
  `hours_awarded` INT DEFAULT 0,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`activity_id`) REFERENCES `community_service`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(50) DEFAULT 'Academic',
  `event_type` VARCHAR(100) DEFAULT 'Campus Symposium',
  `organizer_id` INT DEFAULT NULL,
  `venue` VARCHAR(150) NOT NULL,

  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `number_of_guests` INT DEFAULT 5,
  `max_seats` INT DEFAULT 100,
  `awards_distributed` INT DEFAULT 0,
  `status` ENUM('draft', 'published', 'completed', 'cancelled') DEFAULT 'published',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `event_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('confirmed', 'attended', 'cancelled') DEFAULT 'confirmed',
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `external_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `event_name` VARCHAR(200) NOT NULL,
  `organizing_institution` VARCHAR(200) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `event_date` DATE NOT NULL,
  `award_received` VARCHAR(150) NOT NULL,
  `certificate_file` VARCHAR(255) DEFAULT NULL,
  `verification_status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scholarships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `provider` VARCHAR(150) NOT NULL,
  `scheme_type` ENUM('Government', 'Institutional', 'Merit', 'Special Quota') DEFAULT 'Government',
  `amount` DECIMAL(10,2) NOT NULL,
  `deadline` DATE NOT NULL,
  `min_gpa` DECIMAL(3,2) DEFAULT 3.00,
  `max_income` DECIMAL(12,2) DEFAULT 500000.00,
  `target_beneficiary` VARCHAR(200) DEFAULT 'All Eligible Undergraduates',
  `status` ENUM('active', 'closed') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `scholarship_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholarship_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `gpa` DECIMAL(3,2) NOT NULL,
  `annual_family_income` DECIMAL(12,2) NOT NULL,
  `statement` TEXT,
  `document_path` VARCHAR(255),
  `faculty_verified` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
  `faculty_id` INT NULL DEFAULT NULL,
  `faculty_remarks` TEXT,
  `admin_remarks` TEXT,
  `verified_at` DATETIME NULL DEFAULT NULL,
  `admin_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `disbursed_amount` DECIMAL(10,2) DEFAULT 0.00,
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complainant_type` ENUM('student', 'faculty') DEFAULT 'student',
  `student_id` INT NULL DEFAULT NULL,
  `faculty_id` INT NULL DEFAULT NULL,
  `category` ENUM('Academic', 'Hostel', 'Library', 'Infrastructure', 'Faculty Welfare', 'Administrative', 'Harassment', 'Other') NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
  `faculty_response` TEXT DEFAULT NULL,
  `admin_response` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `role_target` ENUM('all', 'student', 'faculty', 'admin') DEFAULT 'all',
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pre-seed Key Government & Institutional Scholarship Schemes
INSERT INTO `scholarships` (`id`, `title`, `description`, `provider`, `scheme_type`, `amount`, `deadline`, `min_gpa`, `max_income`, `target_beneficiary`, `status`) VALUES
(1, 'SC / ST Post-Matric Government Scholarship', 'Centrally sponsored tuition and maintenance financial aid supporting higher education for SC/ST students.', 'Dept. of Adi Dravidar and Tribal Welfare', 'Government', 35000.00, DATE_ADD(CURDATE(), INTERVAL 45 DAY), 2.50, 250000.00, 'SC / ST Community Students', 'active'),
(2, 'Pudhumai Penn Scheme (Higher Education Incentive)', 'Monthly financial assistance scheme encouraging girl students who studied in Government schools to pursue higher education.', 'Social Welfare & Women Empowerment Dept.', 'Government', 12000.00, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 2.50, 500000.00, 'Female Students from Govt Schools (Class 6-12)', 'active'),
(3, '7.5% Preferential Government School Quota Scheme', 'Complete tuition fee, hostel fee, and development charge exemption for students admitted under the 7.5% Govt School quota.', 'Higher Education Department', 'Special Quota', 50000.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 2.00, 1000000.00, 'Students admitted via 7.5% Govt Quota', 'active'),
(4, 'BC / MBC / DNC Welfare Financial Aid', 'State post-matric grant covering tuition and specialized academic allowance for Backward Classes.', 'BC, MBC & Minorities Welfare Department', 'Government', 20000.00, DATE_ADD(CURDATE(), INTERVAL 45 DAY), 2.75, 200000.00, 'BC / MBC / DNC Students', 'active'),
(5, 'Dean\'s Academic Excellence & Innovation Fellowship', 'Institutional endowment honoring top batch academic rank holders and breakthrough software/hardware research projects.', 'University Endowment Trust', 'Merit', 25000.00, DATE_ADD(CURDATE(), INTERVAL 40 DAY), 3.75, 1000000.00, 'High Performing Scholars (GPA >= 3.75)', 'active');

-- Default Root Administrator
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `avatar`, `status`) VALUES
(1, 'admin', 'admin@campus.edu', '$2y$10$/PKe.GLuyrVgqrzSqASZOeR0wRk6isGSLDjRh1RNYtO5qWBhfBqDq', 'admin', 'default-avatar.png', 'active');
