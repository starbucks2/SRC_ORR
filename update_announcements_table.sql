ALTER TABLE announcements 
ADD COLUMN strand VARCHAR(10) DEFAULT NULL,
ADD INDEX idx_strand (strand);
