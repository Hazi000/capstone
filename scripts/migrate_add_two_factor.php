<?php
require_once __DIR__ . '/../config.php'; // Adjust path if your config is elsewhere

if (!isset($connection) && function_exists('mysqli_connect')) {
    // if config exposes credentials differently, the require above should set $connection
    echo "Database connection not available from config.\n";
    exit(1);
}

$alter = "ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0";

if (mysqli_query($connection, $alter)) {
    echo "Migration succeeded: two_factor_enabled column added or already exists.\n";
} else {
    echo "Migration failed: " . mysqli_error($connection) . "\n";
    // Log for debugging
    error_log("migrate_add_two_factor.php: " . mysqli_error($connection));
}
?>
