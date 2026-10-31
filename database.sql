-- Database schema for Cafe Kamaal Hotel Bill Calculator
CREATE DATABASE IF NOT EXISTS `cafe_kamaal_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cafe_kamaal_db`;

-- Drop existing tables if re-initializing
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_trail`;
DROP TABLE IF EXISTS `meal_eaters`;
DROP TABLE IF EXISTS `meals`;
DROP TABLE IF EXISTS `billing_months`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'hosteller') NOT NULL DEFAULT 'hosteller',
  `code` CHAR(1) NULL UNIQUE, -- Single alphabet code e.g. 'A', 'B'
  `phone` VARCHAR(25) NULL,
  `room_no` VARCHAR(20) NULL,
  `plain_password_hint` VARCHAR(100) NULL, -- For admin convenience to share login credentials to group
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Billing Months / Cycles
CREATE TABLE `billing_months` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Meals Table
CREATE TABLE `meals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `month_id` INT NOT NULL,
  `meal_date` DATE NOT NULL,
  `meal_time` TIME NOT NULL,
  `meal_type` ENUM('Breakfast', 'Lunch', 'Dinner', 'Tea & Snacks', 'Special Mess', 'Other') NOT NULL DEFAULT 'Lunch',
  `title` VARCHAR(150) NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `split_count` INT NOT NULL,
  `per_person_price` DECIMAL(10,2) NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_edited_by` INT NOT NULL,
  `last_edited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `edit_summary` VARCHAR(255) NULL,
  FOREIGN KEY (`month_id`) REFERENCES `billing_months`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`last_edited_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Meal Eaters (Dividers) Table
CREATE TABLE `meal_eaters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `meal_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `hosteller_code` CHAR(1) NOT NULL,
  `share_amount` DECIMAL(10,2) NOT NULL,
  UNIQUE KEY `uk_meal_user` (`meal_id`, `user_id`),
  FOREIGN KEY (`meal_id`) REFERENCES `meals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Audit Trail Table
CREATE TABLE `audit_trail` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `meal_id` INT NULL,
  `action` ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
  `user_id` INT NOT NULL,
  `description` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Admin
-- Passwords: admin123, password123 (hashed with BCRYPT)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `code`, `phone`, `room_no`, `plain_password_hint`) VALUES
(1, 'admin', '$2y$10$Twh4umEKLsuw4SwwIPHbWudXNQYMEJRQH9selTyYQRMUMqby4IxMm', 'Cafe Kamaal Admin (Manager)', 'admin', NULL, '+92 300 1234567', 'Admin Office', 'admin123');

-- Seed Hostellers with single alphabetic codes (A to F)
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `code`, `phone`, `room_no`, `plain_password_hint`) VALUES
(2, 'hosteller_a', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Zubair Ahmed', 'hosteller', 'A', '+92 301 1111111', 'Room 101', 'password123'),
(3, 'hosteller_b', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Bilal Khan', 'hosteller', 'B', '+92 302 2222222', 'Room 102', 'password123'),
(4, 'hosteller_c', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Hamza Ali', 'hosteller', 'C', '+92 303 3333333', 'Room 103', 'password123'),
(5, 'hosteller_d', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Danish Raza', 'hosteller', 'D', '+92 304 4444444', 'Room 104', 'password123'),
(6, 'hosteller_e', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Ehtisham Ul Haq', 'hosteller', 'E', '+92 305 5555555', 'Room 105', 'password123'),
(7, 'hosteller_f', '$2y$10$0Oo6ktUTQJJ6meUPo486Pu.MlE/bwNrCdP1v8Lt7ux4D8J6dmFtJS', 'Farhan Saeed', 'hosteller', 'F', '+92 306 6666666', 'Room 106', 'password123');

-- Seed Current Billing Month
INSERT INTO `billing_months` (`id`, `name`, `start_date`, `end_date`, `is_current`, `notes`) VALUES
(1, 'October 2026 Mess Bill', '2026-10-01', '2026-10-31', 1, 'Official monthly mess calculation for Hostel Block B');

-- Seed Sample Meals
-- Meal 1: Biryani - 1200 total, 4 hostellers (A, B, C, D) -> 300 each
INSERT INTO `meals` (`id`, `month_id`, `meal_date`, `meal_time`, `meal_type`, `title`, `total_price`, `split_count`, `per_person_price`, `created_by`, `created_at`, `last_edited_by`, `last_edited_at`, `edit_summary`) VALUES
(1, 1, '2026-10-28', '13:30:00', 'Lunch', 'Chicken Biryani Special + Cold Drinks', 1200.00, 4, 300.00, 2, '2026-10-28 14:00:00', 2, '2026-10-28 14:00:00', 'Initial entry logged by Zubair Ahmed (Code A)');

INSERT INTO `meal_eaters` (`meal_id`, `user_id`, `hosteller_code`, `share_amount`) VALUES
(1, 2, 'A', 300.00),
(1, 3, 'B', 300.00),
(1, 4, 'C', 300.00),
(1, 5, 'D', 300.00);

-- Meal 2: Karahi - 1500 total, all 6 hostellers (A, B, C, D, E, F) -> 250 each
INSERT INTO `meals` (`id`, `month_id`, `meal_date`, `meal_time`, `meal_type`, `title`, `total_price`, `split_count`, `per_person_price`, `created_by`, `created_at`, `last_edited_by`, `last_edited_at`, `edit_summary`) VALUES
(2, 1, '2026-10-29', '20:45:00', 'Dinner', 'Mutton Karahi with Fresh Tandoori Naan', 1500.00, 6, 250.00, 3, '2026-10-29 21:00:00', 3, '2026-10-29 21:00:00', 'Initial entry logged by Bilal Khan (Code B)');

INSERT INTO `meal_eaters` (`meal_id`, `user_id`, `hosteller_code`, `share_amount`) VALUES
(2, 2, 'A', 250.00),
(2, 3, 'B', 250.00),
(2, 4, 'C', 250.00),
(2, 5, 'D', 250.00),
(2, 6, 'E', 250.00),
(2, 7, 'F', 250.00);

-- Meal 3: Halwa Puri - 450 total, 3 hostellers (B, C, E) -> 150 each
INSERT INTO `meals` (`id`, `month_id`, `meal_date`, `meal_time`, `meal_type`, `title`, `total_price`, `split_count`, `per_person_price`, `created_by`, `created_at`, `last_edited_by`, `last_edited_at`, `edit_summary`) VALUES
(3, 1, '2026-10-30', '09:00:00', 'Breakfast', 'Halwa Puri & Chana Morning Breakfast', 450.00, 3, 150.00, 4, '2026-10-30 09:30:00', 4, '2026-10-30 09:30:00', 'Initial entry logged by Hamza Ali (Code C)');

INSERT INTO `meal_eaters` (`meal_id`, `user_id`, `hosteller_code`, `share_amount`) VALUES
(3, 3, 'B', 150.00),
(3, 4, 'C', 150.00),
(3, 6, 'E', 150.00);

-- Meal 4: Special Mess - 600 total, initially 2 hostellers (A, D), then updated by Danish (Code D) to 3 hostellers (A, D, F) -> 200 each
INSERT INTO `meals` (`id`, `month_id`, `meal_date`, `meal_time`, `meal_type`, `title`, `total_price`, `split_count`, `per_person_price`, `created_by`, `created_at`, `last_edited_by`, `last_edited_at`, `edit_summary`) VALUES
(4, 1, '2026-10-30', '19:15:00', 'Special Mess', 'Fruit Chaat, Juices & Evening Snack', 600.00, 3, 200.00, 5, '2026-10-30 19:30:00', 5, '2026-10-30 22:15:00', 'Edited by Danish Raza (Code D): Added Farhan Saeed (Code F) to the split');

INSERT INTO `meal_eaters` (`meal_id`, `user_id`, `hosteller_code`, `share_amount`) VALUES
(4, 2, 'A', 200.00),
(4, 5, 'D', 200.00),
(4, 7, 'F', 200.00);

-- Audit logs
INSERT INTO `audit_trail` (`meal_id`, `action`, `user_id`, `description`, `created_at`) VALUES
(1, 'CREATE', 2, 'Zubair Ahmed (Code A) created Lunch entry "Chicken Biryani Special" (Rs 1200) split between A, B, C, D (Rs 300 each).', '2026-10-28 14:00:00'),
(2, 'CREATE', 3, 'Bilal Khan (Code B) created Dinner entry "Mutton Karahi with Fresh Naan" (Rs 1500) split between A, B, C, D, E, F (Rs 250 each).', '2026-10-29 21:00:00'),
(3, 'CREATE', 4, 'Hamza Ali (Code C) created Breakfast entry "Halwa Puri & Chana" (Rs 450) split between B, C, E (Rs 150 each).', '2026-10-30 09:30:00'),
(4, 'CREATE', 5, 'Danish Raza (Code D) created Special Mess entry "Fruit Chaat, Juices" (Rs 600).', '2026-10-30 19:30:00'),
(4, 'UPDATE', 5, 'Danish Raza (Code D) updated meal #4: Included Farhan Saeed (Code F) in the split (3 eaters, Rs 200 each).', '2026-10-30 22:15:00');
