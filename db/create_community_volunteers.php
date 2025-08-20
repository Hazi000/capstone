<?php
require_once __DIR__ . '/../config.php';

$sql = "CREATE TABLE IF NOT EXISTS community_volunteers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resident_id INT UNSIGNED NOT NULL,
  announcement_id INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  rejection_reason TEXT NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  attendance_status ENUM('pending','attended','absent') DEFAULT 'pending',
  hours_served DECIMAL(5,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($connection, $sql)) {
    echo "community_volunteers table is present or created successfully.\n";
} else {
    echo "Error creating table: " . mysqli_error($connection) . "\n";
}

// Note: Add foreign keys in a migration or via phpMyAdmin if you need them. This creates a minimal table.
?>
