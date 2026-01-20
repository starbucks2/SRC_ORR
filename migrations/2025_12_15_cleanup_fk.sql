-- Cleanup: Remove incorrect foreign key constraint
-- The fk_employees_role_id FK was incorrectly created and needs to be dropped

START TRANSACTION;

-- Drop the incorrect foreign key if it exists
SET @has_fk := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND CONSTRAINT_NAME = 'fk_employees_role_id'
);
SET @sql := IF(@has_fk > 0, 'ALTER TABLE employees DROP FOREIGN KEY fk_employees_role_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;
