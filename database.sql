-- ============================================================
-- Blood Donor Management System - Database Schema
-- Version: 1.0
-- Engine: MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS `blood_donor_system`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `blood_donor_system`;

-- ============================================================
-- 1. ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. DONORS
-- ============================================================
CREATE TABLE IF NOT EXISTS `donors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donor_name` VARCHAR(255) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `whatsapp` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `blood_group` VARCHAR(5) NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `last_donation_date` DATE DEFAULT NULL,
    `status` ENUM('Active','Inactive') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. BLOOD CAMPS
-- ============================================================
CREATE TABLE IF NOT EXISTS `blood_camps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `camp_date` DATE NOT NULL,
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `location` TEXT NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('Upcoming','Completed','Cancelled') DEFAULT 'Upcoming',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CAMP REGISTRATIONS (attendance register)
--    One row per person per camp - the digital register book.
-- ============================================================
CREATE TABLE IF NOT EXISTS `camp_registrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `camp_id` INT NOT NULL,
    `donor_id` INT DEFAULT NULL,
    `serial_no` INT DEFAULT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `donor_name` VARCHAR(255) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `blood_group` VARCHAR(5) DEFAULT NULL,
    `gender` ENUM('Male','Female','Other') DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `status` ENUM('Registered','Donated','Rejected','No Show') DEFAULT 'Registered',
    `remarks` TEXT DEFAULT NULL,
    `registered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `registered_by` INT DEFAULT NULL,
    FOREIGN KEY (`camp_id`) REFERENCES `blood_camps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`donor_id`) REFERENCES `donors`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_camp_mobile` (`camp_id`, `mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. MESSAGE LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `message_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donor_id` INT DEFAULT NULL,
    `message_type` ENUM('WhatsApp','SMS') NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `api_response` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`donor_id`) REFERENCES `donors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. MESSAGE TEMPLATES
-- ============================================================
CREATE TABLE IF NOT EXISTS `message_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_name` VARCHAR(255) NOT NULL,
    `template_body` TEXT NOT NULL,
    `template_type` ENUM('Camp Notification','Emergency Request','General') DEFAULT 'General',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. SETTINGS (key-value store)
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INDEXES for performance
-- ============================================================
ALTER TABLE `donors` ADD INDEX `idx_blood_group` (`blood_group`);
ALTER TABLE `donors` ADD INDEX `idx_status` (`status`);
ALTER TABLE `donors` ADD INDEX `idx_last_donation` (`last_donation_date`);
ALTER TABLE `blood_camps` ADD INDEX `idx_camp_date` (`camp_date`);
ALTER TABLE `blood_camps` ADD INDEX `idx_camp_status` (`status`);
ALTER TABLE `message_logs` ADD INDEX `idx_sent_at` (`sent_at`);
ALTER TABLE `message_logs` ADD INDEX `idx_message_type` (`message_type`);
ALTER TABLE `camp_registrations` ADD INDEX `idx_camp` (`camp_id`);
ALTER TABLE `camp_registrations` ADD INDEX `idx_reg_status` (`status`);
ALTER TABLE `camp_registrations` ADD INDEX `idx_reg_mobile` (`mobile`);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default admin: admin@admin.com / password123
INSERT INTO `admins` (`name`, `email`, `password`) VALUES
('Administrator', 'admin@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Default message templates
INSERT INTO `message_templates` (`template_name`, `template_body`, `template_type`) VALUES
('Blood Camp Notification', 'Hello {NAME},\n\nOur upcoming blood donation camp will be held on:\n\nDate: {DATE}\nLocation: {LOCATION}\n\nWe would be grateful for your participation.\n\nThank you.', 'Camp Notification'),
('Emergency Blood Request', 'Urgent Blood Request\n\nBlood Group: {BLOOD_GROUP}\nLocation: {LOCATION}\n\nPlease contact us immediately if you can donate.\n\nThank you.', 'Emergency Request'),
('General Announcement', 'Hello {NAME},\n\n{MESSAGE}\n\nThank you.', 'General');

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'Blood Donor Management System'),
('organization_name', 'Blood Donor Organization'),
('country_code', '+94'),
('whatsapp_api_token', ''),
('whatsapp_phone_number_id', ''),
('whatsapp_api_version', 'v23.0'),
('sms_gateway', 'twilio'),
('sms_api_key', ''),
('sms_api_secret', ''),
('sms_sender_id', '');
