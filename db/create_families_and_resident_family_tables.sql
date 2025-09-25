-- Families (household) table
CREATE TABLE IF NOT EXISTS families (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  family_name VARCHAR(150) DEFAULT NULL,    -- optional family/household name
  address VARCHAR(255) DEFAULT NULL,        -- optional household address
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot table linking residents to families with a role
CREATE TABLE IF NOT EXISTS resident_family (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  family_id INT UNSIGNED NOT NULL,
  resident_id INT UNSIGNED NOT NULL,
  role ENUM('mother','father','spouse','child','guardian','other') NOT NULL DEFAULT 'other',
  is_head TINYINT(1) NOT NULL DEFAULT 0,   -- mark household head if needed
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_family_resident (family_id, resident_id),
  KEY idx_family_id (family_id),
  KEY idx_resident_id (resident_id),
  CONSTRAINT fk_resident_family_family FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_resident_family_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
