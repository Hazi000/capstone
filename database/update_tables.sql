-- Check and add event_id column if it doesn't exist
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_NAME = 'community_volunteers'
    AND COLUMN_NAME = 'event_id'
    AND TABLE_SCHEMA = DATABASE()
);

SET @query = IF(
    @exists = 0,
    'ALTER TABLE community_volunteers ADD COLUMN event_id INT',
    'SELECT "event_id column already exists"'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint if it doesn't exist
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'community_volunteers'
    AND CONSTRAINT_NAME = 'fk_community_volunteers_event'
    AND TABLE_SCHEMA = DATABASE()
);

SET @fk_query = IF(
    @fk_exists = 0,
    'ALTER TABLE community_volunteers ADD CONSTRAINT fk_community_volunteers_event FOREIGN KEY (event_id) REFERENCES events(id)',
    'SELECT "Foreign key constraint already exists"'
);

PREPARE stmt FROM @fk_query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if foreign key exists before dropping
SET @fk_exists_check := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_NAME = 'community_volunteers'
    AND CONSTRAINT_NAME = 'fk_community_volunteers_announcement'
    AND TABLE_SCHEMA = DATABASE()
);

SET @drop_fk_query = IF(
    @fk_exists_check > 0,
    'ALTER TABLE community_volunteers DROP FOREIGN KEY fk_community_volunteers_announcement',
    'SELECT "No foreign key constraint to drop"'
);

PREPARE stmt FROM @drop_fk_query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Then drop the column if it exists
ALTER TABLE community_volunteers
DROP COLUMN IF EXISTS announcement_id;

-- Remove event columns from announcements if they exist
ALTER TABLE announcements
DROP COLUMN IF EXISTS needs_volunteers,
DROP COLUMN IF EXISTS event_date,
DROP COLUMN IF EXISTS event_time;
