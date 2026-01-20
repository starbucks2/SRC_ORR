-- Migration: Add role_id column to employees table and remove old role column
-- This denormalizes the role_id from the roles table for convenience

START TRANSACTION;

-- 1) Add role_id column if it doesn't exist
SET @has_role_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role_id'
);
SET @sql := IF(@has_role_id = 0, 'ALTER TABLE employees ADD COLUMN role_id INT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Populate role_id from the roles table based on employee_id
UPDATE employees e
SET e.role_id = (
  SELECT r.role_id FROM roles r WHERE r.employee_id = e.employee_id LIMIT 1
)
WHERE e.role_id IS NULL AND EXISTS (
  SELECT 1 FROM roles r WHERE r.employee_id = e.employee_id
);

-- 3) For employees without a matching role record, default to role_id 2 (RESEARCH_ADVISER)
UPDATE employees SET role_id = 2 WHERE role_id IS NULL;

-- 4) Make role_id NOT NULL
SET @has_role_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role_id'
);
SET @sql := IF(@has_role_col > 0, 'ALTER TABLE employees MODIFY role_id INT NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Drop the old role column if it exists
SET @has_role_enum := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@has_role_enum > 0, 'ALTER TABLE employees DROP COLUMN role', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
