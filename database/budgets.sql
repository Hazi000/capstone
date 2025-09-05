CREATE TABLE IF NOT EXISTS `budgets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `description` varchar(255) NOT NULL,
    `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `budget_date` date NOT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    KEY `budget_date` (`budget_date`),
    CONSTRAINT `budgets_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
