-- Migration v3: Single roles table with employee_id mapping and fixed role ids (no auto-increment)
-- Removes role_definitions usage. Keeps only `roles` table with:
--   employee_id (PK), role_id (1=DEAN, 2=RESEARCH_ADVISER), role_name, display_name, is_active, assigned_at
-- Idempotent and backfills from existing schema (v1/v2).

START TRANSACTION;

-- 0) Create roles table if missing (new structure). No AUTO_INCREMENT anywhere.
CREATE TABLE IF NOT EXISTS roles (
  employee_id INT NOT NULL,
  role_id INT NOT NULL,
  role_name VARCHAR(64) NOT NULL,
  display_name VARCHAR(128) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure there is no UNIQUE index on role_name (we need multiple rows per role)
SET @role_name_unique_idx := (
  SELECT INDEX_NAME FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_name' AND NON_UNIQUE = 0
  LIMIT 1
);
SET @sql := IF(@role_name_unique_idx IS NOT NULL,
  CONCAT('ALTER TABLE roles DROP INDEX `', REPLACE(@role_name_unique_idx,'`',''), '`'),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure roles table has employee_id column (legacy roles table from v1 may not have it)
SET @has_emp_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'employee_id'
);
SET @sql := IF(@has_emp_col = 0,
  'ALTER TABLE roles ADD COLUMN employee_id INT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure PRIMARY KEY is on employee_id, not on role_id
SET @pk_cols := (
  SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND INDEX_NAME = 'PRIMARY'
);
-- If role_id currently has AUTO_INCREMENT, drop that attribute BEFORE dropping its key
SET @rid_ai := (
  SELECT EXTRA FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_id'
);
SET @sql := IF(@rid_ai LIKE '%auto_increment%',
  'ALTER TABLE roles MODIFY role_id INT NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- If the primary key is on role_id (or includes it), drop and recreate on employee_id
SET @sql := IF(@pk_cols IS NOT NULL AND (@pk_cols = 'role_id' OR @pk_cols LIKE 'role_id,%' OR @pk_cols LIKE '%,role_id,%' OR @pk_cols LIKE '%,role_id'),
  'ALTER TABLE roles DROP PRIMARY KEY',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add primary key on employee_id if missing
SET @has_pk_emp := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'employee_id'
);
-- Only add PK if column exists and no NULLs remain yet
SET @null_emp_cnt := (
  SELECT COUNT(*) FROM roles WHERE employee_id IS NULL
);
SET @sql := IF(@has_pk_emp = 0 AND @has_emp_col > 0 AND @null_emp_cnt = 0,
  'ALTER TABLE roles ADD PRIMARY KEY (employee_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop any UNIQUE index on role_id
SET @uniq_role_id := (
  SELECT INDEX_NAME FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_id' AND NON_UNIQUE = 0
  LIMIT 1
);
SET @sql := IF(@uniq_role_id IS NOT NULL,
  CONCAT('ALTER TABLE roles DROP INDEX `', REPLACE(@uniq_role_id,'`',''), '`'),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1) If an older mapping exists that references role_definitions, try to merge metadata onto roles
-- Add columns if they are missing (safe on re-run)
SET @need_role_name := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'role_name'
);
SET @sql := IF(@need_role_name = 0, 'ALTER TABLE roles ADD COLUMN role_name VARCHAR(64) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @need_disp := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'display_name'
);
SET @sql := IF(@need_disp = 0, 'ALTER TABLE roles ADD COLUMN display_name VARCHAR(128) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @need_active := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(@need_active = 0, 'ALTER TABLE roles ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) If role_definitions exists, pull names onto roles, then we can safely drop role_definitions
SET @has_defs := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'role_definitions'
);
SET @sql := IF(@has_defs > 0,
  'UPDATE roles r
      JOIN role_definitions d ON d.role_id = r.role_id
   SET r.role_name = d.role_name, r.display_name = COALESCE(d.display_name, r.display_name), r.is_active = d.is_active',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Ensure only the two allowed fixed roles with ids 1 and 2
UPDATE roles SET role_id = 1, role_name = 'DEAN', display_name = 'Dean' WHERE UPPER(REPLACE(TRIM(role_name), ' ', '_')) = 'DEAN' OR role_id = 1;
UPDATE roles SET role_id = 2, role_name = 'RESEARCH_ADVISER', display_name = 'Research Adviser' WHERE UPPER(REPLACE(TRIM(role_name), ' ', '_')) IN ('RESEARCH_ADVISER','RESEARCH_ADVISOR','RESEARCH-ADVISER','RESEARCH ADVISER') OR role_id = 2;

-- Any other role_name gets coerced to Research Adviser
UPDATE roles SET role_id = 2, role_name = 'RESEARCH_ADVISER', display_name = 'Research Adviser' WHERE role_id NOT IN (1,2);

-- 4) Backfill missing roles from employees textual columns
SET @ins_from_role := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'
);
SET @sql := IF(@ins_from_role > 0, 'INSERT INTO roles (employee_id, role_id, role_name, display_name, is_active) SELECT e.employee_id, CASE WHEN UPPER(REPLACE(TRIM(e.role)," ","_")) = "DEAN" THEN 1 ELSE 2 END, CASE WHEN UPPER(REPLACE(TRIM(e.role)," ","_")) = "DEAN" THEN "DEAN" ELSE "RESEARCH_ADVISER" END, CASE WHEN UPPER(REPLACE(TRIM(e.role)," ","_")) = "DEAN" THEN "Dean" ELSE "Research Adviser" END, 1 FROM employees e LEFT JOIN roles r ON r.employee_id = e.employee_id WHERE r.employee_id IS NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ins_from_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'
);
SET @sql := IF(@ins_from_type > 0, 'INSERT INTO roles (employee_id, role_id, role_name, display_name, is_active) SELECT e.employee_id, CASE WHEN UPPER(REPLACE(TRIM(e.employee_type)," ","_")) = "DEAN" THEN 1 ELSE 2 END, CASE WHEN UPPER(REPLACE(TRIM(e.employee_type)," ","_")) = "DEAN" THEN "DEAN" ELSE "RESEARCH_ADVISER" END, CASE WHEN UPPER(REPLACE(TRIM(e.employee_type)," ","_")) = "DEAN" THEN "Dean" ELSE "Research Adviser" END, 1 FROM employees e LEFT JOIN roles r ON r.employee_id = e.employee_id WHERE r.employee_id IS NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4b) Cleanup legacy definition rows (those without employee_id), enforce NOT NULL and PK
DELETE FROM roles WHERE employee_id IS NULL;

-- Enforce NOT NULL on employee_id now that rows are backfilled
SET @has_emp_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'employee_id'
);
SET @sql := IF(@has_emp_col > 0,
  'ALTER TABLE roles MODIFY employee_id INT NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure PK on employee_id now if still missing
SET @has_pk_emp := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'employee_id'
);
SET @sql := IF(@has_pk_emp = 0 AND @has_emp_col > 0,
  'ALTER TABLE roles ADD PRIMARY KEY (employee_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Optionally drop role_definitions if it exists (no longer needed)
SET @sql := IF(@has_defs > 0, 'DROP TABLE role_definitions', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
