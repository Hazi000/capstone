-- Add event_id column to community_volunteers table
ALTER TABLE community_volunteers
ADD COLUMN event_id INT NULL,
ADD FOREIGN KEY (event_id) REFERENCES events(id);

-- Update existing records to move from announcement_id to event_id
-- Only run this if you want to migrate existing data
UPDATE community_volunteers cv
INNER JOIN announcements a ON cv.announcement_id = a.id
SET cv.event_id = (
    SELECT e.id FROM events e 
    WHERE e.title = a.title 
    AND e.event_start_date = a.event_date
    LIMIT 1
);

-- Remove old announcement_id column (optional, only after confirming data migration)
-- ALTER TABLE community_volunteers DROP COLUMN announcement_id;
