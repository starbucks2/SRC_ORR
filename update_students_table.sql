-- Add last_password_change column to students table if it doesn't exist
ALTER TABLE students 
ADD COLUMN last_password_change DATETIME DEFAULT NULL;
