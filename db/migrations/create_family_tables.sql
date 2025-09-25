-- Remove address columns if they exist, then (re)create families and family_members tables / view without address.

-- Drop address column from residents and families if present (safe for re-run)
ALTER TABLE residents DROP COLUMN IF EXISTS address;
ALTER TABLE families DROP COLUMN IF EXISTS address;

-- Ensure families table exists without address column
CREATE TABLE IF NOT EXISTS families (
    id INT AUTO_INCREMENT PRIMARY KEY,
    household_number VARCHAR(100) UNIQUE,
    head_id INT DEFAULT NULL,
    zone VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_families_head FOREIGN KEY (head_id) REFERENCES residents(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Family members: links residents to a family with a relationship (head, father, mother, etc)
CREATE TABLE IF NOT EXISTS family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    resident_id INT NOT NULL,
    relationship ENUM('head','spouse','father','mother','child','guardian','other') NOT NULL DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_family_resident UNIQUE (family_id, resident_id),
    CONSTRAINT fk_family_members_family FOREIGN KEY (family_id) REFERENCES families(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_family_members_resident FOREIGN KEY (resident_id) REFERENCES residents(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Recreate/replace a convenience view (no address fields)
CREATE OR REPLACE VIEW family_with_members AS
SELECT f.id AS family_id, f.household_number, f.head_id, f.zone,
       fm.id AS family_member_id, fm.resident_id, fm.relationship, fm.created_at AS member_added,
       r.full_name, r.age, r.contact_number
FROM families f
JOIN family_members fm ON fm.family_id = f.id
JOIN residents r ON r.id = fm.resident_id;
