-- Migration: Restrict roles to DEAN and RESEARCH_ADVISER only
-- Safe to run multiple times

START TRANSACTION;

-- Ensure base roles exist
INSERT IGNORE INTO roles (role_name, display_name, is_active) VALUES
  ('DEAN', 'Dean', 1),
  ('RESEARCH_ADVISER', 'Research Adviser', 1);

-- If other roles exist, remap employees to the closest allowed role then disable the extra roles
-- Prefer mapping to RESEARCH_ADVISER by default
UPDATE employees e
JOIN roles r_admin ON r_admin.role_id = e.role_id AND r_admin.role_name IN ('ADMIN','FACULTY')
JOIN roles r_ra ON r_ra.role_name = 'RESEARCH_ADVISER'
SET e.role_id = r_ra.role_id
WHERE e.role_id IS NOT NULL;

-- Deactivate any non-allowed roles
UPDATE roles SET is_active = 0
WHERE role_name NOT IN ('DEAN','RESEARCH_ADVISER');

-- Optionally, hard delete non-allowed roles that are not referenced
-- (kept commented out for safety)
-- DELETE r FROM roles r
-- LEFT JOIN employees e ON e.role_id = r.role_id
-- WHERE r.role_name NOT IN ('DEAN','RESEARCH_ADVISER') AND e.role_id IS NULL;

COMMIT;
