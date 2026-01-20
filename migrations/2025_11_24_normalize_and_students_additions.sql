-- Migration: Normalize core tables and add missing columns to students
-- Server: MariaDB 10.4 (compatible syntax)
-- Safe to run multiple times; uses IF NOT EXISTS and additive changes only

USE `src_db`;

-- 1) Students: add commonly used columns found across the codebase and other systems
ALTER TABLE `students` 
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) NULL AFTER `suffix`,
  ADD COLUMN IF NOT EXISTS `department` VARCHAR(50) NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `course_strand` VARCHAR(50) NULL AFTER `department`,
  ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NULL AFTER `course_strand`,
  ADD COLUMN IF NOT EXISTS `profile_pic` VARCHAR(255) NULL AFTER `profile_picture`,
  ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `profile_pic`,
  ADD COLUMN IF NOT EXISTS `research_file` VARCHAR(255) NULL AFTER `is_verified`,
  ADD COLUMN IF NOT EXISTS `reset_token` VARCHAR(255) NULL AFTER `research_file`,
  ADD COLUMN IF NOT EXISTS `reset_token_expiry` DATETIME NULL AFTER `reset_token`,
  ADD COLUMN IF NOT EXISTS `last_password_change` DATETIME NULL AFTER `reset_token_expiry`,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `last_password_change`;

-- Optional: an index on email if present (MariaDB 10.4 lacks IF NOT EXISTS for indexes; ignore on rerun if it errors)
-- CREATE UNIQUE INDEX `uniq_student_email` ON `students` (`email`);

-- 2) Normalized lookup for strands (SHS strands or college programs taxonomy)
CREATE TABLE IF NOT EXISTS `strands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `strand` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Books (acts as unified research submissions repository)
-- Note: `student_id` matches current students.student_id type/length (VARCHAR(10))
CREATE TABLE IF NOT EXISTS `books` (
  `book_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `abstract` TEXT NULL,
  `keywords` TEXT NULL,
  `authors` TEXT NULL,
  `department` VARCHAR(50) NULL,
  `course_strand` VARCHAR(50) NULL,
  `image` VARCHAR(255) NULL,
  `document` VARCHAR(255) NULL,
  `views` INT NOT NULL DEFAULT 0,
  `submission_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `student_id` VARCHAR(10) NULL,
  `adviser_id` INT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 0,
  `year` VARCHAR(25) NULL,
  INDEX `idx_books_status` (`status`),
  INDEX `idx_books_year` (`year`),
  INDEX `idx_books_student` (`student_id`),
  INDEX `idx_books_title_lower` ((LOWER(TRIM(`title`))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3a) Add foreign keys where compatible with current schema
-- Will succeed only if referenced tables exist with matching definitions
ALTER TABLE `books`
  ADD CONSTRAINT `fk_books_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_books_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `employees`(`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 4) Bookmarks for saved papers
CREATE TABLE IF NOT EXISTS `bookmarks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(10) NOT NULL,
  `book_id` INT NOT NULL,
  `bookmarked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_bookmark` (`student_id`, `book_id`),
  INDEX `idx_bm_student` (`student_id`),
  INDEX `idx_bm_book` (`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bookmarks`
  ADD CONSTRAINT `fk_bookmarks_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`book_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookmarks_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 5) Activity logs (generic audit trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `actor_type` VARCHAR(20) NOT NULL,
  `actor_id` VARCHAR(64) NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Helpful computed alias for legacy code using paper_id (virtual column)
-- Ignored if already exists; MariaDB will error on duplicate add, which is safe to ignore during repeated runs
ALTER TABLE `bookmarks`
  ADD COLUMN `paper_id` INT AS (`book_id`) VIRTUAL;
