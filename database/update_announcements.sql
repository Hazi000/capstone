-- Remove unused columns from announcements table
ALTER TABLE announcements
DROP COLUMN priority,
DROP COLUMN event_date,
DROP COLUMN event_time,
DROP COLUMN location,
DROP COLUMN needs_volunteers,
DROP COLUMN max_volunteers;

-- Add index for better query performance
ALTER TABLE announcements
ADD INDEX idx_announcement_type (announcement_type),
ADD INDEX idx_status (status),
ADD INDEX idx_created_at (created_at);
