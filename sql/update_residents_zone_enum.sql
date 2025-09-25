-- 1) Backup your DB before running.
-- 2) This script maps old values like 'Zone 1'..'Zone 7' to 'Zone 1A'..'Zone 7A', then updates the enum.

START TRANSACTION;

-- Map legacy single-zone values to the new "A" variant to avoid ENUM constraint errors.
UPDATE residents
SET zone = CASE
    WHEN zone = 'Zone 1' THEN 'Zone 1A'
    WHEN zone = 'Zone 2' THEN 'Zone 2A'
    WHEN zone = 'Zone 3' THEN 'Zone 3A'
    WHEN zone = 'Zone 4' THEN 'Zone 4A'
    WHEN zone = 'Zone 5' THEN 'Zone 5A'
    WHEN zone = 'Zone 6' THEN 'Zone 6A'
    WHEN zone = 'Zone 7' THEN 'Zone 7A'
    ELSE zone
END
WHERE zone IN ('Zone 1','Zone 2','Zone 3','Zone 4','Zone 5','Zone 6','Zone 7');

-- If there are NULL or empty zones, set a safe default
UPDATE residents
SET zone = 'Zone 1A'
WHERE zone IS NULL OR zone = '';

-- Now alter the column to the new ENUM (Zone 1A..Zone 7B)
ALTER TABLE residents
MODIFY COLUMN zone ENUM(
  'Zone 1A','Zone 1B',
  'Zone 2A','Zone 2B',
  'Zone 3A','Zone 3B',
  'Zone 4A','Zone 4B',
  'Zone 5A','Zone 5B',
  'Zone 6A','Zone 6B',
  'Zone 7A','Zone 7B'
) NOT NULL DEFAULT 'Zone 1A';

COMMIT;

-- After running: verify values
SELECT zone, COUNT(*) AS cnt FROM residents GROUP BY zone ORDER BY zone;
