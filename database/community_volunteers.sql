CREATE TABLE IF NOT EXISTS `community_volunteers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `resident_id` int(11) NOT NULL,
    `announcement_id` int(11) NOT NULL,
    `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `created_at` datetime NOT NULL,
    `updated_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `resident_id` (`resident_id`),
    KEY `announcement_id` (`announcement_id`),
    CONSTRAINT `cv_resident_fk` FOREIGN KEY (`resident_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `cv_announcement_fk` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
