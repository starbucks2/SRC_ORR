# Database Column Migration Summary

## Changes Made

### Database Schema Changes
- **Old `student_id` column** → Renamed to **`id`** (legacy column, can be NULL)
- **Old `student_number` column** → Renamed to **`student_id`** (main student identifier, unique)

### Files Updated

#### Migration Scripts Created
1. `migrate_student_columns.php` - Database migration script
2. `update_column_references.php` - PHP code update script

#### PHP Files Updated (33 replacements across 5 files)
1. **student_profile.php** (8 replacements)
2. **subadmin_verify_students.php** (4 replacements)
3. **subadmin_view_students.php** (6 replacements)
4. **upload_research.php** (13 replacements)
5. **view_students.php** (2 replacements)

#### Core Files Updated Manually
1. **register.php** - Updated all references from `student_number` to `student_id`
2. **login.php** - Updated session variable from `student_number` to `student_id`

### Database Structure (After Migration)

**students table:**
- `id` - VARCHAR(32), NULL (old student_id, legacy)
- `student_id` - VARCHAR(32), UNIQUE (was student_number, main identifier)
- All other columns remain unchanged

### Session Variables Updated
- `$_SESSION['student_number']` → `$_SESSION['student_id']`

### Form Fields
- The registration form still uses `name="lrn"` for backward compatibility
- Backend processes it as `student_id`

## Testing Recommendations

1. Test student registration with new form
2. Test student login
3. Test research upload functionality
4. Verify student profile displays correctly
5. Check admin/subadmin student verification pages

## Notes

- The migration handles foreign key constraints automatically
- All student identification now uses the `student_id` column
- The old `id` column is kept for legacy compatibility but is no longer used
