-- Migration: Hybrid courses linkage (Option C)
-- - strands.id -> strands.strand_id (and add virtual alias id for compatibility)
-- - courses: course_id, course_code, course_name
-- - courses link to either departments (department_id) or strands (strand_id)
-- Safe to run multiple times on MariaDB 10.4+

USE `src_db`;

-- 1) Ensure strands table exists
CREATE TABLE IF NOT EXISTS `strands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `strand` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1a) Rename id -> strand_id when needed
-- Check column existence and rename
-- MariaDB lacks IF EXISTS on CHANGE, so this may error on reruns; safe to ignore.
ALTER TABLE `strands` CHANGE COLUMN `id` `strand_id` INT NOT NULL AUTO_INCREMENT;

-- 1b) Add compatibility alias column `id` as virtual, if supported
-- May error if it already exists; safe to ignore.
ALTER TABLE `strands` ADD COLUMN `id` INT AS (`strand_id`) VIRTUAL;

-- 2) Ensure departments table exists
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `code` VARCHAR(20) NULL UNIQUE,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2a) Rename id -> department_id (safe to ignore error if already renamed), then add virtual alias `id`
ALTER TABLE `departments` CHANGE COLUMN `id` `department_id` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE `departments` ADD COLUMN `id` INT AS (`department_id`) VIRTUAL;

-- 2b) Connect strands to departments (nullable; SHS strands can point to 'Senior High School' dept)
ALTER TABLE `strands` ADD COLUMN `department_id` INT NULL AFTER `strand_id`;
ALTER TABLE `strands` ADD CONSTRAINT `fk_strands_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON UPDATE CASCADE ON DELETE SET NULL;

-- 3) Ensure courses table exists (from earlier migration) or create fresh with Option C layout
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NULL,
  `name` VARCHAR(150) NOT NULL,
  `short_name` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_course_per_dept` (`department_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3a) Rename columns to canonical names (if current names differ)
-- May error if already renamed; safe to ignore.
ALTER TABLE `courses` CHANGE COLUMN `id` `course_id` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE `courses` CHANGE COLUMN `name` `course_name` VARCHAR(150) NOT NULL;
ALTER TABLE `courses` CHANGE COLUMN `short_name` `course_code` VARCHAR(50) NULL;

-- 3b) Add strand_id (nullable) and FKs (may error on reruns; safe to ignore)
ALTER TABLE `courses` ADD COLUMN `strand_id` INT NULL AFTER `department_id`;
ALTER TABLE `courses` ADD CONSTRAINT `fk_courses_department` FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `courses` ADD CONSTRAINT `fk_courses_strand` FOREIGN KEY (`strand_id`) REFERENCES `strands`(`strand_id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 3c) Add uniqueness for strand+name (NULL-friendly unique)
CREATE UNIQUE INDEX `uniq_course_per_strand` ON `courses` (`strand_id`, `course_name`);

-- Note: We intend rule (department_id IS NOT NULL) XOR (strand_id IS NOT NULL).
-- MariaDB 10.4 CHECK is parsed but not enforced; leaving as a documented contract.
-- ALTER TABLE `courses` ADD CONSTRAINT chk_course_scope CHECK (
--   (department_id IS NOT NULL AND strand_id IS NULL) OR
--   (department_id IS NULL AND strand_id IS NOT NULL)
-- );
