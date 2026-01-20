-- Migration: Create roles table and link to employees
-- Safe to run multiple times

START TRANSACTION;

-- 1) Create roles table
CREATE TABLE IF NOT EXISTS roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(64) NOT NULL UNIQUE,
  display_name VARCHAR(128) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Seed base roles
INSERT IGNORE INTO roles (role_name, display_name, is_active) VALUES
  ('ADMIN', 'Administrator', 1),
  ('DEAN', 'Dean', 1),
  ('RESEARCH_ADVISER', 'Research Adviser', 1),
  ('FACULTY', 'Faculty', 1);

-- 3) Add role_id to employees if missing
SET @has_role_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role_id'
);

SET @sql := IF(@has_role_id = 0,
  'ALTER TABLE employees ADD COLUMN role_id INT NULL AFTER employee_type',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Add FK and index if missing
SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND REFERENCED_TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_id'
);
SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND INDEX_NAME = 'idx_employees_role_id'
);

SET @sql := IF(@has_idx = 0,
  'ALTER TABLE employees ADD INDEX idx_employees_role_id (role_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_fk = 0,
  'ALTER TABLE employees
     ADD CONSTRAINT fk_employees_role
     FOREIGN KEY (role_id) REFERENCES roles(role_id)
     ON UPDATE CASCADE ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Map existing textual role columns to role_id when possible
-- Try to use employees.role first if present, else employee_type, case-insensitive match
-- Normalize to uppercase and underscores
SET @has_role_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'
);
SET @has_type_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'
);

-- Update from employees.role
SET @sql := IF(@has_role_col > 0,
  'UPDATE employees e
     JOIN (
       SELECT role_id, role_name FROM roles
     ) r ON UPPER(REPLACE(TRIM(e.role)," ","_")) = r.role_name
   SET e.role_id = COALESCE(e.role_id, r.role_id)
   WHERE e.role_id IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Update from employees.employee_type
SET @sql := IF(@has_type_col > 0,
  'UPDATE employees e
     JOIN (
       SELECT role_id, role_name FROM roles
     ) r ON UPPER(REPLACE(TRIM(e.employee_type)," ","_")) = r.role_name
   SET e.role_id = COALESCE(e.role_id, r.role_id)
   WHERE e.role_id IS NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) Restrict employees.role values to only 'Dean' and 'Research Adviser'
SET @has_role_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@has_role_col > 0,
  'ALTER TABLE employees MODIFY COLUMN role VARCHAR(64) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Map any non-allowed values to Research Adviser (textual)
SET @sql := IF(@has_role_col > 0,
  'UPDATE employees SET role = CASE
      WHEN role = "Dean" THEN "Dean"
      WHEN UPPER(REPLACE(TRIM(role)," ","_")) = "RESEARCH_ADVISER" THEN "Research Adviser"
      ELSE "Research Adviser"
    END',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Constrain enum to allowed values and set default
SET @sql := IF(@has_role_col > 0,
  'ALTER TABLE employees MODIFY COLUMN role ENUM("Dean","Research Adviser") NOT NULL DEFAULT "Research Adviser"',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) Normalize employees.employee_type to only DEAN / RESEARCH_ADVISER (if column exists)
SET @has_type_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'
);
SET @sql := IF(@has_type_col > 0,
  'ALTER TABLE employees MODIFY COLUMN employee_type VARCHAR(64) NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_type_col > 0,
  'UPDATE employees SET employee_type = CASE
      WHEN UPPER(REPLACE(TRIM(employee_type)," ","_")) = "DEAN" THEN "DEAN"
      WHEN UPPER(REPLACE(TRIM(employee_type)," ","_")) = "RESEARCH_ADVISER" THEN "RESEARCH_ADVISER"
      ELSE "RESEARCH_ADVISER"
    END',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional: add a check constraint-like behavior via ENUM on employee_type
SET @sql := IF(@has_type_col > 0,
  'ALTER TABLE employees MODIFY COLUMN employee_type ENUM("DEAN","RESEARCH_ADVISER") NULL DEFAULT "RESEARCH_ADVISER"',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
