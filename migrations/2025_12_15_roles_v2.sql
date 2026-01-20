-- Migration v2: Role model using mapping table (roles) with employee_id and fixed role_definitions
-- Requirements:
-- - roles table must include employee_id
-- - No auto-increment on role ids; use fixed IDs in role_definitions
-- - Only Dean and Research Adviser
-- Safe and idempotent

START TRANSACTION;

-- 1) Create role_definitions with fixed IDs (no AUTO_INCREMENT)
CREATE TABLE IF NOT EXISTS role_definitions (
  role_id INT NOT NULL,
  role_name VARCHAR(64) NOT NULL UNIQUE,
  display_name VARCHAR(128) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed only Dean (1) and Research Adviser (2)
INSERT INTO role_definitions (role_id, role_name, display_name, is_active) VALUES
  (1, 'DEAN', 'Dean', 1),
  (2, 'RESEARCH_ADVISER', 'Research Adviser', 1)
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), display_name = VALUES(display_name), is_active = VALUES(is_active);

-- 2) Create roles mapping table: employee_id + role_id (no auto-increment; PK on employee_id)
CREATE TABLE IF NOT EXISTS roles (
  employee_id INT NOT NULL,
  role_id INT NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (employee_id),
  KEY idx_roles_role_id (role_id),
  CONSTRAINT fk_roles_employee FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_roles_definition FOREIGN KEY (role_id) REFERENCES role_definitions(role_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Backfill roles table from existing employees.role_id / employees.role / employees.employee_type
-- Prefer existing employees.role_id if present and valid; else map from textual columns
SET @has_emp_role_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role_id'
);

-- Insert from employees.role_id when available and maps to our fixed definitions (1/2)
SET @sql := IF(@has_emp_role_id > 0,
  'INSERT INTO roles (employee_id, role_id)
     SELECT e.employee_id,
            CASE WHEN r.role_name = "DEAN" THEN 1 WHEN r.role_name = "RESEARCH_ADVISER" THEN 2 ELSE NULL END AS rid
       FROM employees e
       JOIN roles_old r2 ON 1=0 -- placeholder to prevent exec if next SELECT fails',
  'SELECT 1');
-- The above placeholder prevents execution; we will do explicit backfill steps below per textual role.

-- Insert from textual employees.role
SET @has_role_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@has_role_col > 0,
  'INSERT INTO roles (employee_id, role_id)
     SELECT e.employee_id,
            CASE WHEN UPPER(REPLACE(TRIM(e.role)," ","_")) = "DEAN" THEN 1 ELSE 2 END AS rid
       FROM employees e
       LEFT JOIN roles rr ON rr.employee_id = e.employee_id
      WHERE rr.employee_id IS NULL
        AND e.role IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Insert from textual employees.employee_type
SET @has_type_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'
);
SET @sql := IF(@has_type_col > 0,
  'INSERT INTO roles (employee_id, role_id)
     SELECT e.employee_id,
            CASE WHEN UPPER(REPLACE(TRIM(e.employee_type)," ","_")) = "DEAN" THEN 1 ELSE 2 END AS rid
       FROM employees e
       LEFT JOIN roles rr ON rr.employee_id = e.employee_id
      WHERE rr.employee_id IS NULL
        AND e.employee_type IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Optional: Normalize employees textual columns to match mapping (keep for UI compatibility)
SET @sql := IF(@has_role_col > 0,
  'UPDATE employees e
      JOIN roles r ON r.employee_id = e.employee_id
   SET e.role = CASE WHEN r.role_id = 1 THEN "Dean" ELSE "Research Adviser" END',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_type_col > 0,
  'UPDATE employees e
      JOIN roles r ON r.employee_id = e.employee_id
   SET e.employee_type = CASE WHEN r.role_id = 1 THEN "DEAN" ELSE "RESEARCH_ADVISER" END',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
