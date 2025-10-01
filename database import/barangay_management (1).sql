-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 05:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `barangay_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `announcement_type` varchar(20) DEFAULT 'general',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `announcement_type`, `status`, `expiry_date`, `created_by`, `created_at`, `updated_at`) VALUES
(18, 'feeding Program', 'program for childer', 'general', 'active', '2025-10-03', 6, '2025-09-26 11:54:09', '2025-09-26 11:54:09');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `resident_id`, `purpose`, `description`, `appointment_date`, `appointment_time`, `status`, `created_at`, `updated_at`, `approved_by`, `approval_date`, `notes`) VALUES
(1, 1, 'Barangay Clearance', 'Need barangay clearance for job application', '2025-06-20', '09:00:00', 'pending', '2025-06-17 07:52:56', '2025-06-17 07:52:56', NULL, NULL, NULL),
(2, 2, 'Business Permit', 'Apply for business permit renewal', '2025-06-21', '10:30:00', 'approved', '2025-06-17 07:52:56', '2025-06-17 07:52:56', NULL, NULL, NULL),
(3, 3, 'Certificate of Residency', 'Request certificate of residency', '2025-06-22', '14:00:00', 'pending', '2025-06-17 07:52:56', '2025-06-17 07:52:56', NULL, NULL, NULL),
(4, 1, 'Complaint Follow-up', 'Follow-up on street light repair', '2025-06-23', '11:00:00', 'pending', '2025-06-17 07:52:56', '2025-06-17 07:52:56', NULL, NULL, NULL),
(5, 2, 'Community Meeting', 'Discuss neighborhood security concerns', '2025-06-24', '15:30:00', 'approved', '2025-06-17 07:52:56', '2025-06-17 07:52:56', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `item` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `budget_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `item`, `description`, `amount`, `budget_date`, `created_at`, `updated_at`, `status`) VALUES
(1, 'dajsbd', NULL, 7999.00, '2025-09-10', '2025-09-10 14:41:50', '2025-09-10 14:41:50', 'active'),
(2, 'uguguy', NULL, 20000.00, '2025-09-26', '2025-09-26 12:50:58', '2025-09-26 12:50:58', 'active'),
(3, 'Flood control', NULL, 1111.00, '2025-09-26', '2025-09-26 12:54:51', '2025-09-26 12:54:51', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('announcement','meeting') NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','cancelled','completed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `resident_name` varchar(255) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `or_number` varchar(50) DEFAULT NULL,
  `amount_paid` varchar(50) DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `resident_name`, `age`, `purpose`, `or_number`, `amount_paid`, `issued_date`, `issued_by`) VALUES
(1, 'dwdjan', 12, 'ndjan', 'i313i12', '22', '2025-07-01 11:20:23', 2),
(2, 'jeramel', 22, 'non', '1111', '200', '2025-07-02 13:53:57', 2),
(3, 'jeramel', 22, 'non', '1111', '200', '2025-07-02 15:54:47', 6),
(4, 'dwdjan', 44, 'ghgh', '111', '9', '2025-07-02 19:21:36', 2),
(5, 'alhazier r. nasa', 22, 'da', '36217', '11', '2025-08-17 16:17:22', 2),
(6, 'jeramel pangilinan', 18, 'Need for license to operate for DTI', '123', '10.00', '2025-09-26 20:21:39', 6);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_requests`
--

CREATE TABLE `certificate_requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `purpose` text NOT NULL,
  `additional_info` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_date` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `claim_date` timestamp NULL DEFAULT NULL,
  `or_number` varchar(50) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_requests`
--

INSERT INTO `certificate_requests` (`id`, `resident_id`, `certificate_type`, `purpose`, `additional_info`, `status`, `created_at`, `processed_date`, `processed_by`, `rejection_reason`, `claim_date`, `or_number`, `amount_paid`, `updated_at`) VALUES
(1, 16, 'Barangay Clearance', 'sa', 'sasas', 'claimed', '2025-08-03 09:36:39', '2025-08-03 09:41:56', 2, NULL, '2025-08-03 10:02:16', '1111', NULL, '2025-08-03 10:02:16'),
(2, 16, 'Barangay Clearance', 'da', 'bajsbd', 'approved', '2025-08-17 08:00:20', '2025-08-17 08:11:11', 2, NULL, NULL, NULL, NULL, '2025-08-17 08:11:11'),
(3, 18, 'Barangay Business Permit', 'Need for license to operate for DTI', 'N/A', 'approved', '2025-09-26 12:20:36', '2025-09-26 12:21:16', 6, NULL, NULL, NULL, NULL, '2025-09-26 12:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_request_logs`
--

CREATE TABLE `certificate_request_logs` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `performed_by` int(11) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_request_logs`
--

INSERT INTO `certificate_request_logs` (`id`, `request_id`, `action`, `performed_by`, `old_status`, `new_status`, `remarks`, `created_at`) VALUES
(1, 1, 'approved', 2, 'pending', 'approved', NULL, '2025-08-03 09:41:56'),
(2, 1, 'claimed', 2, 'approved', 'claimed', NULL, '2025-08-03 10:02:16'),
(3, 2, 'approved', 2, 'pending', 'approved', NULL, '2025-08-17 08:11:11'),
(4, 3, 'approved', 6, 'pending', 'approved', NULL, '2025-09-26 12:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_settings`
--

CREATE TABLE `certificate_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `barangay_name` varchar(255) DEFAULT NULL,
  `municipality` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `captain_name` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_settings`
--

INSERT INTO `certificate_settings` (`id`, `barangay_name`, `municipality`, `province`, `country`, `captain_name`, `logo_path`) VALUES
(1, 'Barangay Cawit', 'Zamboanga City', 'Province of Zamboanga Del Sur', 'Republic of the Philippines', 'N/A', '../sec/assets/images/barangay-logo-1751454914.png');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_types`
--

CREATE TABLE `certificate_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `processing_days` int(11) DEFAULT 3,
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificate_types`
--

INSERT INTO `certificate_types` (`id`, `name`, `description`, `fee`, `processing_days`, `requirements`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Barangay Clearance', 'General purpose clearance for various transactions', 50.00, 1, 'Valid ID, Proof of Residency', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(2, 'Certificate of Indigency', 'For indigent residents requiring assistance', 0.00, 1, 'Valid ID, Proof of Income', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(3, 'Certificate of Residency', 'Proof of residence in the barangay', 30.00, 1, 'Valid ID, Proof of Address', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(4, 'Business Clearance', 'For business permit applications', 200.00, 3, 'Valid ID, Business Registration, Tax Clearance', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(5, 'Certificate of Good Moral Character', 'Character reference certificate', 50.00, 2, 'Valid ID, NBI Clearance', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(6, 'Barangay ID', 'Official barangay identification card', 100.00, 5, 'Valid ID, 2x2 Photo, Proof of Residency', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(7, 'Certificate of Employment', 'For locally employed residents', 30.00, 1, 'Valid ID, Employment Contract', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29'),
(8, 'Certificate of Student Status', 'For enrolled students in the barangay', 0.00, 1, 'Valid ID, School ID, Enrollment Form', 1, '2025-07-02 06:06:29', '2025-07-02 06:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `community_volunteers`
--

CREATE TABLE `community_volunteers` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `attendance_status` enum('pending','attended','absent') DEFAULT 'pending',
  `hours_served` decimal(4,1) DEFAULT 0.0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `attendance_marked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `community_volunteers`
--
DELIMITER $$
CREATE TRIGGER `update_resident_volunteer_stats` AFTER UPDATE ON `community_volunteers` FOR EACH ROW BEGIN
    IF NEW.attendance_status = 'attended' AND OLD.attendance_status != 'attended' THEN
        UPDATE residents 
        SET total_volunteer_hours = total_volunteer_hours + NEW.hours_served,
            total_volunteer_events = total_volunteer_events + 1,
            last_volunteer_date = CURDATE(),
            volunteer_status = CASE 
                WHEN total_volunteer_hours + NEW.hours_served >= 100 THEN 'outstanding'
                WHEN total_volunteer_hours + NEW.hours_served >= 20 THEN 'active'
                ELSE 'inactive'
            END
        WHERE id = NEW.resident_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `nature_of_complaint` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in-progress','resolved','closed') NOT NULL DEFAULT 'pending',
  `resident_id` int(11) DEFAULT NULL,
  `complainant_name` varchar(255) DEFAULT NULL,
  `complainant_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `defendant_resident_id` int(11) DEFAULT NULL,
  `defendant_name` varchar(255) DEFAULT NULL,
  `defendant_contact` varchar(20) DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `mediation_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `nature_of_complaint`, `description`, `priority`, `status`, `resident_id`, `complainant_name`, `complainant_contact`, `created_at`, `updated_at`, `defendant_resident_id`, `defendant_name`, `defendant_contact`, `resolution`, `mediation_date`) VALUES
(12, 'dsda', 'njadsnj', 'medium', 'resolved', NULL, '', '', '2025-06-26 08:06:08', '2025-07-02 13:46:34', NULL, NULL, NULL, NULL, NULL),
(13, 'gossip', 'whidz accused of being gossiper in the barangay', 'low', 'closed', NULL, '', '', '2025-07-15 07:09:16', '2025-07-20 08:53:16', 9, '', '', 'bhh', '2000-08-08'),
(14, 'dsad', 'bufdsgfs', 'medium', 'in-progress', NULL, 'fshfsa R. nassa', '09493742948', '2025-09-25 12:13:29', '2025-09-25 12:13:47', 16, '', '', NULL, NULL),
(15, 'Gossip', 'Spreading of inaccurate info', 'medium', 'in-progress', NULL, 'Jeraml Q. Asid', '09493742948', '2025-09-25 12:21:40', '2025-09-25 12:23:26', NULL, 'Cherry F. Gabionza', '09493742949', NULL, NULL),
(16, 'sdssa', 'shds', 'medium', 'in-progress', 18, '', '', '2025-09-26 12:26:23', '2025-09-26 12:26:31', 15, '', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `code_expiry` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `user_id`, `new_email`, `verification_code`, `code_expiry`, `created_at`) VALUES
(1, 7, 'jeramelasid@gmail.com', '421033', '2025-07-31 10:25:55', '2025-07-31 16:10:55'),
(2, 7, 'jeramel@gmail.com', '160594', '2025-07-31 10:34:23', '2025-07-31 16:19:23');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_start_date` date NOT NULL,
  `event_end_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `needs_volunteers` tinyint(1) DEFAULT 0,
  `max_volunteers` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_start_date`, `event_end_date`, `event_time`, `location`, `needs_volunteers`, `max_volunteers`, `created_by`, `status`, `created_at`, `updated_at`) VALUES
(1, '1da', 'dadad', '2025-09-25', '2025-09-30', '14:33:00', 'dadad', 1, 12, 6, 'upcoming', '2025-09-26 08:20:13', '2025-09-26 08:20:13'),
(2, 'dasda', 'addasdasd', '2025-09-27', '2025-09-30', '16:28:00', 'dsadasd', 1, 1, 6, 'upcoming', '2025-09-26 08:26:53', '2025-09-26 08:26:53'),
(3, 'dsada', 'dsadasd', '2025-10-05', '2025-10-22', '16:32:00', 'sdadas', 1, 21, 6, 'upcoming', '2025-09-26 08:30:09', '2025-09-26 08:30:09'),
(4, 'dassd', 'adadad', '2025-10-07', '2025-10-23', '16:34:00', 'dadad', 1, 1, 6, 'upcoming', '2025-09-26 08:32:10', '2025-09-26 08:32:10'),
(5, 'dsada', 'dasdada', '2025-09-30', '2025-10-01', '22:42:00', 'dsada', 0, 21, 6, 'upcoming', '2025-09-30 14:41:42', '2025-09-30 14:41:42'),
(6, 'dasa', 'ddadad', '2025-10-10', '2025-10-22', '22:44:00', '22', 0, 22, 6, 'upcoming', '2025-09-30 14:42:13', '2025-09-30 14:42:13'),
(7, 'dadad', 'dadad', '2025-09-30', '2025-10-06', '22:45:00', 'dsad', 0, 1, 6, 'upcoming', '2025-09-30 14:44:07', '2025-09-30 14:44:07'),
(8, 'dasda', 'dadada', '2025-10-01', '2025-10-03', '23:05:00', 'dddd', 0, 1, 6, 'upcoming', '2025-09-30 15:02:09', '2025-09-30 15:02:09'),
(9, 'hiii', 'djashdj', '2025-10-08', '2025-10-08', '23:10:00', 'dadasda', 0, 21, 6, 'upcoming', '2025-09-30 15:05:14', '2025-09-30 15:05:14'),
(10, 'dada', 'dadada', '2025-10-09', '2025-10-09', '23:13:00', 'dada', 0, 1, 9, 'upcoming', '2025-10-01 15:11:37', '2025-10-01 15:11:37'),
(11, 'ddada', 'dadada', '2025-10-31', '2025-10-31', '23:16:00', '111', 0, 21, 2, 'upcoming', '2025-10-01 15:13:24', '2025-10-01 15:13:24');

--
-- Triggers `events`
--
DELIMITER $$
CREATE TRIGGER `create_volunteer_request` AFTER INSERT ON `events` FOR EACH ROW BEGIN
    IF NEW.needs_volunteers = 1 THEN
        INSERT INTO volunteer_requests (event_id, required_volunteers, status)
        VALUES (NEW.id, NEW.max_volunteers, 'open');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `description`, `amount`, `budget_id`, `created_by`, `category`, `created_at`, `updated_at`) VALUES
(1, 'dsad', 3131.00, 1, 7, NULL, '2025-09-10 08:42:00', '2025-09-10 14:42:00'),
(2, 'dwaesdwq', 500.00, 1, 2, NULL, '2025-09-26 06:37:32', '2025-09-26 12:37:32'),
(4, 'tambak', 1000.00, 2, 2, NULL, '2025-09-26 06:57:03', '2025-09-26 12:57:03');

-- --------------------------------------------------------

--
-- Table structure for table `families`
--

CREATE TABLE `families` (
  `id` int(11) NOT NULL,
  `household_number` varchar(100) DEFAULT NULL,
  `head_id` int(11) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `families`
--

INSERT INTO `families` (`id`, `household_number`, `head_id`, `zone`, `created_at`, `updated_at`) VALUES
(1, '1', 16, 'Zone 1A', '2025-09-25 09:37:38', '2025-09-25 09:41:36'),
(2, '2', NULL, 'Zone 1B', '2025-09-25 10:10:17', '2025-09-25 10:10:17'),
(3, '3', NULL, 'Zone 1A', '2025-09-25 10:16:35', '2025-09-25 10:16:35'),
(4, '4', NULL, 'Zone 1A', '2025-09-25 10:18:36', '2025-09-25 10:18:36'),
(5, '33', NULL, 'Zone 1A', '2025-09-25 10:21:12', '2025-09-25 10:21:12'),
(7, '12', NULL, 'Zone 1A', '2025-09-25 10:21:31', '2025-09-25 10:21:31'),
(8, '8', NULL, 'Zone 1A', '2025-09-25 10:24:47', '2025-09-25 10:24:47'),
(15, '21', NULL, 'Zone 1A', '2025-09-25 10:41:57', '2025-09-25 10:41:57'),
(16, '333', NULL, 'Zone 1A', '2025-09-25 10:42:03', '2025-09-25 10:42:03'),
(17, '5', NULL, 'Zone 1A', '2025-09-25 10:42:09', '2025-09-25 10:42:09'),
(18, '77', NULL, 'Zone 1A', '2025-09-25 10:42:15', '2025-09-25 10:42:15'),
(19, '99', NULL, 'Zone 1A', '2025-09-25 10:42:19', '2025-09-25 10:42:19');

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `relationship` enum('head','spouse','father','mother','child','guardian','other') NOT NULL DEFAULT 'other',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `family_members`
--

INSERT INTO `family_members` (`id`, `family_id`, `resident_id`, `relationship`, `created_at`, `updated_at`) VALUES
(4, 1, 9, 'mother', '2025-09-25 10:09:57', '2025-09-25 10:09:57'),
(7, 1, 15, 'father', '2025-09-25 10:24:55', '2025-09-25 10:24:55');

-- --------------------------------------------------------

--
-- Stand-in structure for view `family_with_members`
-- (See below for the actual view)
--
CREATE TABLE `family_with_members` (
);

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `login_status` enum('success','failed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(1) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `age` int(11) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `zone` enum('Zone 1A','Zone 1B','Zone 2A','Zone 2B','Zone 3A','Zone 3B','Zone 4A','Zone 4B','Zone 5A','Zone 5B','Zone 6A','Zone 6B','Zone 7A','Zone 7B') NOT NULL DEFAULT 'Zone 1A',
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL,
  `face_descriptor` text DEFAULT NULL,
  `face_embedding` text DEFAULT NULL,
  `face_photo` longtext DEFAULT NULL,
  `liveness_verified` tinyint(1) DEFAULT 0,
  `total_volunteer_hours` decimal(6,1) DEFAULT 0.0,
  `total_volunteer_events` int(11) DEFAULT 0,
  `last_volunteer_date` date DEFAULT NULL,
  `volunteer_status` enum('inactive','active','outstanding') DEFAULT 'inactive',
  `status` varchar(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `first_name`, `middle_initial`, `last_name`, `full_name`, `suffix`, `age`, `contact_number`, `zone`, `email`, `created_at`, `updated_at`, `photo_path`, `face_descriptor`, `face_embedding`, `face_photo`, `liveness_verified`, `total_volunteer_hours`, `total_volunteer_events`, `last_volunteer_date`, `volunteer_status`, `status`) VALUES
(9, 'Cherry', 'b', 'Gabionza', 'Cherry b. Gabionza', NULL, 35, '09553321424', 'Zone 3B', NULL, '2025-07-02 08:34:21', '2025-09-25 08:43:40', 'uploads/residents/resident_6864ef0d1d26a.jpg', '[-0.14397430419921875,0.05425043776631355,0.07953716069459915,-0.13350877165794373,-0.12468057870864868,-0.03413264453411102,-0.08946795761585236,-0.20897407829761505,0.18795213103294373,-0.17681118845939636,0.2523611783981323,-0.11677242815494537,-0.17353525757789612,-0.1324233114719391,-0.013850669376552105,0.23378145694732666,-0.18028473854064941,-0.18463370203971863,-0.017803937196731567,-0.012820234522223473,0.03767484426498413,-0.05397477000951767,0.03958050161600113,0.0813690721988678,-0.1421467661857605,-0.418381005525589,-0.14166998863220215,-0.09887680411338806,-0.057409778237342834,-0.00887755211442709,-0.044200554490089417,0.1395033597946167,-0.21117128431797028,-0.0861235037446022,0.008784138597548008,0.1726359874010086,-0.020464640110731125,-0.06508591771125793,0.09189341217279434,-0.07636101543903351,-0.2889811098575592,-0.05018458142876625,0.13109120726585388,0.20665401220321655,0.18088890612125397,-0.00048010784666985273,0.02097008004784584,-0.05415702983736992,0.09004088491201401,-0.17708326876163483,0.035320863127708435,0.0454634428024292,0.15446576476097107,0.022616514936089516,0.07941493391990662,-0.16843600571155548,0.03128839656710625,0.10812289267778397,-0.15776972472667694,-0.030825231224298477,0.05788850039243698,-0.14910869300365448,-0.027690645307302475,-0.09757179766893387,0.2667187750339508,0.19747242331504822,-0.09142348170280457,-0.17224732041358948,0.18470169603824615,-0.06039142236113548,-0.0019337940029799938,0.06424437463283539,-0.1923881620168686,-0.15761545300483704,-0.34279510378837585,-0.040594495832920074,0.38995301723480225,0.025472521781921387,-0.15713652968406677,0.02348511852324009,-0.04740675911307335,0.03510933741927147,0.026525389403104782,0.11896312236785889,0.00924735702574253,0.023267872631549835,-0.0916561484336853,0.007711727172136307,0.16304907202720642,-0.1133616715669632,0.017236100509762764,0.18446622788906097,-0.07494428753852844,0.02264053001999855,0.010852953419089317,0.03799238055944443,-0.11011462658643723,0.09068908542394638,-0.14482241868972778,0.02391132526099682,-0.00908080954104662,-0.03345204517245293,0.00018965569324791431,0.0822489932179451,-0.17419536411762238,0.051012344658374786,0.01735498756170273,0.02017972245812416,0.006592790596187115,0.00223519466817379,-0.0830770879983902,-0.12038727849721909,0.12188722938299179,-0.2544234097003937,0.1321175992488861,0.17200323939323425,-0.007864479906857014,0.18978478014469147,0.08031587302684784,0.10622681677341461,-0.011109041050076485,-0.04323292523622513,-0.184305801987648,0.02672266587615013,0.11610140651464462,-0.056586913764476776,0.004617256112396717,0.05055345222353935]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive', 'active'),
(15, 'Leycia-Meay', 'M', 'Marcus', 'Leycia-Meay M. Marcus', NULL, 22, '09677686687', 'Zone 1A', NULL, '2025-07-25 07:38:50', '2025-09-25 08:36:50', 'uploads/residents/resident_6883348acf949.jpg', '[-0.07631894201040268,0.06796710938215256,-0.008939861319959164,-0.09110160171985626,-0.06543717533349991,0.00425328454002738,-0.06838063150644302,-0.10341303050518036,0.15581341087818146,-0.10505014657974243,0.19535718858242035,0.02264447882771492,-0.17970487475395203,-0.10090761631727219,-0.008935174904763699,0.1664154976606369,-0.2358698844909668,-0.15779176354408264,-0.042075544595718384,-0.04215231165289879,0.046230778098106384,0.008041190914809704,-0.04168413579463959,0.08132688701152802,-0.1251351237297058,-0.3438979983329773,-0.06812352687120438,-0.0688343197107315,0.01146471593528986,-0.04690353199839592,-0.09331655502319336,0.10254832357168198,-0.2494627982378006,-0.05258355289697647,0.049071379005908966,0.11076759546995163,-0.0023802577052265406,-0.012466193176805973,0.1731092482805252,0.06273914873600006,-0.20813240110874176,0.047415852546691895,0.009235256351530552,0.2825446128845215,0.27206817269325256,-0.025007423013448715,0.009754691272974014,-0.06206720694899559,0.12084371596574783,-0.18257318437099457,0.02636682242155075,0.22515685856342316,0.08785107731819153,0.10043134540319443,-0.050269998610019684,-0.1374523788690567,-0.016997583210468292,0.09452050924301147,-0.10438592731952667,0.018390195444226265,0.06758120656013489,-0.1309909075498581,0.0131465969607234,-0.0597386360168457,0.24646541476249695,0.035859931260347366,-0.1690596044063568,-0.17513743042945862,0.12180071324110031,-0.1012907326221466,-0.06307922303676605,0.012887981720268726,-0.2222130298614502,-0.19556289911270142,-0.31800541281700134,0.11180470883846283,0.41093724966049194,0.13790176808834076,-0.21931593120098114,0.07337602972984314,-0.10946447402238846,-0.02497989684343338,0.12779636681079865,0.1810750812292099,0.027059931308031082,0.04449111968278885,-0.028638824820518494,0.0690050795674324,0.2335273027420044,0.004754737019538879,-0.0702974945306778,0.22301173210144043,-0.05209296941757202,0.08909177780151367,0.038176242262125015,0.05103328078985214,-0.03081342950463295,0.0032990523613989353,-0.16339777410030365,0.008302850648760796,-0.04050422087311745,-0.04642576724290848,0.008333536796271801,0.17701609432697296,-0.10220172256231308,0.1396457999944687,0.01568688452243805,0.06419871002435684,-0.0284651517868042,0.030651671811938286,-0.11905103176832199,-0.10559187084436417,0.10979259014129639,-0.21846136450767517,0.251201868057251,0.193867027759552,0.08916604518890381,0.11982133239507675,0.1134997010231018,0.0719485729932785,0.0033950181677937508,-0.018895182758569717,-0.1704849749803543,-0.002807812998071313,0.10697121918201447,0.01362699642777443,0.11038554459810257,-0.007631187327206135]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive', 'active'),
(16, 'jeramel', '', 'dj', 'jeramel dj', NULL, 22, '09934912717', 'Zone 5B', NULL, '2025-08-01 21:25:59', '2025-09-26 11:55:48', 'uploads/residents/resident_688d30e786a50.jpg', '[-0.16786766052246094,0.05152040719985962,0.017435375601053238,-0.06954573839902878,-0.03585652634501457,-0.0065636602230370045,-0.01002923771739006,-0.10955408960580826,0.22303271293640137,-0.140462726354599,0.24151262640953064,-0.010330968536436558,-0.1793452799320221,-0.15102630853652954,0.029623202979564667,0.12307922542095184,-0.16342051327228546,-0.10858244448900223,0.009132582694292068,-0.07582690566778183,0.08362022042274475,-0.0658426284790039,0.007269802503287792,0.07623991370201111,-0.1450335681438446,-0.36333826184272766,-0.09126324951648712,-0.15766431391239166,0.0731019601225853,-0.08120033144950867,0.007963810116052628,0.024301547557115555,-0.18682275712490082,-0.013343540951609612,-0.07286719232797623,0.06573875993490219,-0.017945684492588043,0.03179433196783066,0.13168953359127045,-0.02059476636350155,-0.1910763531923294,-0.08505120128393173,-0.07841722667217255,0.22874730825424194,0.14205336570739746,0.02348407357931137,0.05654004216194153,-0.018868230283260345,0.0687977597117424,-0.20802924036979675,0.03929200395941734,0.13040584325790405,0.12165545672178268,0.014722991734743118,-0.013473669067025185,-0.13780918717384338,-0.01943456381559372,0.0728331208229065,-0.18875017762184143,0.1004006564617157,0.06927629560232162,-0.12179584056138992,-0.08079363405704498,0.033771008253097534,0.2140529453754425,0.09556680917739868,-0.18227604031562805,-0.10473743081092834,0.13328030705451965,-0.1458199918270111,0.04546024277806282,0.05540899187326431,-0.14613541960716248,-0.2516544759273529,-0.3053736686706543,0.10087962448596954,0.39189380407333374,0.11500655859708786,-0.16206297278404236,-0.027428075671195984,-0.1339729130268097,0.06041998788714409,0.08536475151777267,0.1260601133108139,-0.0368100069463253,-0.01958496868610382,-0.09696275740861893,0.028298452496528625,0.08039092272520065,-0.05622038245201111,-0.025211550295352936,0.20284244418144226,-0.0457272045314312,0.05821031704545021,-0.07162652909755707,0.10401992499828339,-0.08790835738182068,0.008324416354298592,-0.011400870978832245,0.019236138090491295,0.03451656177639961,-0.04139360412955284,0.011882086284458637,0.0932835191488266,-0.15263152122497559,0.19087249040603638,0.0023579641710966825,0.010385921224951744,0.009013746865093708,0.030784228816628456,-0.09164253622293472,-0.11969483643770218,0.10155133903026581,-0.21036449074745178,0.1581013947725296,0.2559508979320526,0.042610492557287216,0.2055772989988327,0.05966322869062424,0.06940467655658722,0.009882967919111252,-0.03197290003299713,-0.15028561651706696,0.002412279136478901,0.01783447153866291,-0.043654754757881165,-0.01984129473567009,-0.03402809798717499]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive', 'active'),
(18, 'jeramel', '', 'pangilinan', 'jeramel pangilinan', NULL, 18, '09033287381', 'Zone 1A', NULL, '2025-09-26 12:02:28', '2025-09-26 12:02:28', 'uploads/residents/resident_68d680d446591.jpg', '[-0.22536657750606537,0.059116337448358536,0.046532031148672104,-0.00972206611186266,-0.10013645887374878,-0.06165198236703873,-0.04130813479423523,-0.15744546055793762,0.13659131526947021,-0.054167117923498154,0.25627371668815613,-0.07676879316568375,-0.18936923146247864,-0.11318735778331757,-0.023732036352157593,0.1826237589120865,-0.2597188949584961,-0.1150045171380043,-0.006419030949473381,-0.05215832591056824,0.03708001598715782,-0.07005662471055984,0.04499924182891846,0.09227883815765381,-0.10523627698421478,-0.31781768798828125,-0.12126421183347702,-0.15768641233444214,0.05694165825843811,-0.046853531152009964,-0.0731888934969902,-0.043890707194805145,-0.20503756403923035,-0.07564682513475418,-0.03446609899401665,0.03345087915658951,0.03378847613930702,-0.05756329745054245,0.17212939262390137,-0.055960360914468765,-0.1847180724143982,-0.07271519303321838,0.00897609069943428,0.2062612771987915,0.19095376133918762,0.05047471076250076,0.04152963310480118,-0.08911354839801788,0.07948150485754013,-0.1410287618637085,0.09645276516675949,0.12652811408042908,0.11137054860591888,0.04540783911943436,-0.07235900312662125,-0.13787518441677094,-0.04504486173391342,0.12868548929691315,-0.0933694988489151,0.060998693108558655,0.10602588206529617,-0.04114086180925369,-0.017032530158758163,-0.054103679955005646,0.30013182759284973,0.14762340486049652,-0.13428035378456116,-0.17134323716163635,0.15179404616355896,-0.1165912076830864,-0.08420928567647934,0.035489194095134735,-0.18590906262397766,-0.18152610957622528,-0.3557863235473633,0.03933555632829666,0.43208566308021545,0.06345363706350327,-0.20094816386699677,-0.04228871315717697,-0.15637995302677155,0.01980237476527691,0.09675543755292892,0.16505445539951324,-0.02366054803133011,0.0265277698636055,-0.09576954692602158,-0.021831165999174118,0.20309264957904816,-0.047382209450006485,-0.07531609386205673,0.18618474900722504,-0.016419608145952225,0.046780508011579514,0.011067833751440048,0.06355049461126328,-0.005048917606472969,0.011056864634156227,-0.053623612970113754,-0.010537572205066681,0.03520924225449562,0.001697656698524952,0.0049825869500637054,0.11299730837345123,-0.134513258934021,0.10996898263692856,0.028113700449466705,0.0557437390089035,-0.017979051917791367,0.001642910297960043,-0.07987400889396667,-0.08170683681964874,0.15081536769866943,-0.20911045372486115,0.21638011932373047,0.19042405486106873,0.06578081101179123,0.1594771444797516,0.0414271280169487,0.12485948204994202,-0.030006568878889084,0.007111596874892712,-0.14115501940250397,0.025162722915410995,0.08911082148551941,-0.03538338467478752,0.10951346904039383,0.06064967066049576]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `resident_accounts`
--

CREATE TABLE `resident_accounts` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `account_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_accounts`
--

INSERT INTO `resident_accounts` (`id`, `resident_id`, `username`, `email`, `password`, `is_verified`, `verification_token`, `last_login`, `login_attempts`, `account_locked`, `created_at`, `updated_at`) VALUES
(4, 9, 'cherry', 'cherry@gmail.com', '$2y$10$NxRSNHpYSnvi/MR4QzgDJOC5OMjHNkAzTA4s0JhknekWTzBVnLTBq', 1, '5f873b2514cfdb3c6f197bf1ebbe66aa76fc8b581b88e644997408d0cb1539ad', '2025-07-03 08:50:40', 0, 0, '2025-07-02 08:38:14', '2025-07-03 00:50:40'),
(5, 15, 'marcussssss', 'leyciameay@gmail.com', '$2y$10$/Y3xkvHFcHYfad/gw68S8uOIblHs6tBGoMgDhwJEvptq4JncVbzZy', 1, 'dcc8176e9d30534bbd7bc83487335085d8d8abb42dcd64e30c325b8c347a019b', NULL, 0, 0, '2025-07-25 07:39:53', '2025-07-25 07:39:53'),
(8, 18, 'donny', 'donnypogiasid@gmail.com', '$2y$10$0HSpaATX2RYUle7AmkH80.e4H0gTHx4V1PyjPSSj8lSM5ywt.ZzSa', 1, '586d5c8a2f5e54f91e8cd2d3a4bfbc39e36240912bb0a502eacebced447485b7', '2025-09-30 22:45:29', 0, 0, '2025-09-26 12:10:16', '2025-09-30 14:45:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('captain','secretary','super_admin','treasurer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `status`, `created_at`, `last_login`, `updated_at`) VALUES
(2, 'super admin', 'Jeramel@gmail.com', '$2y$10$AGXRV5a3BeV9/RCshIzJ5Oq36WLEnUvNpRORl7gnj/CT7jMIfRVIe', 'super_admin', 'active', '2025-06-25 04:51:40', '2025-10-01 15:12:56', '2025-10-01 15:12:56'),
(6, 'Jeramel Q. Asid', 'jeramelasid@gmail.com', '$2y$10$ARXpPuEH2nw2esecnon44ejZ0ytN5kRiPXiqZ1pe5FcnhXbZxtMCC', 'secretary', 'active', '2025-07-02 05:59:25', '2025-09-30 14:38:36', '2025-09-30 14:38:36'),
(7, 'Whidzmar Taraji', 'whidzmartaraji03@gmail.com', '$2y$10$OF4i.8P4AOmKLFFVRTg07.TCraMPniYGYHpyvY29ZI7DYsp0SDpRe', 'treasurer', 'active', '2025-07-02 06:00:46', '2025-10-01 15:10:44', '2025-10-01 15:10:44'),
(9, 'Donny Asid', 'jerpub20@gmail.com', '$2y$10$LeaJyMbGOKL6W2ZzcZv96O0uX1vLcfOs9CVr0BXvkQ.LtkOadJ9Hi', 'captain', 'active', '2025-09-26 14:06:31', '2025-10-01 15:11:16', '2025-10-01 15:11:16');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_registrations`
--

CREATE TABLE `volunteer_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hours_served` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_registrations`
--

INSERT INTO `volunteer_registrations` (`id`, `event_id`, `request_id`, `resident_id`, `status`, `rejection_reason`, `registration_date`, `updated_at`, `hours_served`) VALUES
(5, 2, 2, 18, 'approved', NULL, '2025-09-26 12:17:33', '2025-09-26 12:19:02', NULL),
(6, 3, 3, 18, 'approved', NULL, '2025-09-30 14:45:44', '2025-09-30 14:47:32', NULL),
(7, 4, 4, 18, 'rejected', 'jbjfdbs', '2025-09-30 14:45:58', '2025-09-30 14:47:28', NULL),
(14, 8, 5, 18, 'approved', NULL, '2025-09-30 16:05:49', '2025-09-30 16:05:59', NULL),
(15, 9, 6, 18, 'approved', NULL, '2025-09-30 16:06:10', '2025-09-30 16:09:20', NULL),
(16, 6, 7, 18, 'rejected', '7t', '2025-09-30 16:09:01', '2025-09-30 16:09:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_requests`
--

CREATE TABLE `volunteer_requests` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `required_volunteers` int(11) DEFAULT 1,
  `filled_positions` int(11) DEFAULT 0,
  `status` enum('open','filled','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_requests`
--

INSERT INTO `volunteer_requests` (`id`, `event_id`, `role`, `description`, `required_volunteers`, `filled_positions`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, 12, 0, 'open', '2025-09-26 08:52:38', '2025-09-26 08:52:38'),
(2, 2, NULL, NULL, 1, 0, 'open', '2025-09-26 08:52:38', '2025-09-26 08:52:38'),
(3, 3, NULL, NULL, 21, 0, 'open', '2025-09-26 08:52:38', '2025-09-26 08:52:38'),
(4, 4, NULL, NULL, 1, 0, 'open', '2025-09-26 08:52:38', '2025-09-26 08:52:38'),
(5, 8, NULL, NULL, 1, 0, '', '2025-09-30 16:05:49', '2025-09-30 16:05:49'),
(6, 9, NULL, NULL, 1, 0, '', '2025-09-30 16:06:10', '2025-09-30 16:06:10'),
(7, 6, NULL, NULL, 1, 0, '', '2025-09-30 16:09:01', '2025-09-30 16:09:01');

-- --------------------------------------------------------

--
-- Structure for view `family_with_members`
--
DROP TABLE IF EXISTS `family_with_members`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `family_with_members`  AS SELECT `f`.`id` AS `family_id`, `f`.`household_number` AS `household_number`, `f`.`head_id` AS `head_id`, `f`.`address` AS `address`, `f`.`zone` AS `zone`, `fm`.`id` AS `family_member_id`, `fm`.`resident_id` AS `resident_id`, `fm`.`relationship` AS `relationship`, `fm`.`created_at` AS `member_added`, `r`.`full_name` AS `full_name`, `r`.`age` AS `age`, `r`.`contact_number` AS `contact_number` FROM ((`families` `f` join `family_members` `fm` on(`fm`.`family_id` = `f`.`id`)) join `residents` `r` on(`r`.`id` = `fm`.`resident_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_announcement_type` (`announcement_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_request_logs`
--
ALTER TABLE `certificate_request_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_settings`
--
ALTER TABLE `certificate_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificate_types`
--
ALTER TABLE `certificate_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `community_volunteers`
--
ALTER TABLE `community_volunteers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `announcement_id` (`announcement_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `fk_community_volunteers_event` (`event_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `status` (`status`),
  ADD KEY `priority` (`priority`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `defendant_resident_id` (`defendant_resident_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_event_dates` (`event_start_date`,`event_end_date`),
  ADD KEY `idx_event_status` (`status`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `household_number` (`household_number`),
  ADD KEY `fk_families_head` (`head_id`);

--
-- Indexes for table `family_members`
--
ALTER TABLE `family_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_family_resident` (`family_id`,`resident_id`),
  ADD KEY `fk_family_members_resident` (`resident_id`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_login` (`account_id`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_liveness_verified` (`liveness_verified`);

--
-- Indexes for table `resident_accounts`
--
ALTER TABLE `resident_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `volunteer_registrations`
--
ALTER TABLE `volunteer_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`event_id`,`request_id`,`resident_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `idx_volunteer_registration_status` (`status`);

--
-- Indexes for table `volunteer_requests`
--
ALTER TABLE `volunteer_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `idx_volunteer_request_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `certificate_request_logs`
--
ALTER TABLE `certificate_request_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificate_types`
--
ALTER TABLE `certificate_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `community_volunteers`
--
ALTER TABLE `community_volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `family_members`
--
ALTER TABLE `family_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `resident_accounts`
--
ALTER TABLE `resident_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `volunteer_registrations`
--
ALTER TABLE `volunteer_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `volunteer_requests`
--
ALTER TABLE `volunteer_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `community_volunteers`
--
ALTER TABLE `community_volunteers`
  ADD CONSTRAINT `community_volunteers_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `community_volunteers_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`),
  ADD CONSTRAINT `community_volunteers_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_community_volunteers_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`defendant_resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `families`
--
ALTER TABLE `families`
  ADD CONSTRAINT `fk_families_head` FOREIGN KEY (`head_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `family_members`
--
ALTER TABLE `family_members`
  ADD CONSTRAINT `fk_family_members_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_family_members_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `resident_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `resident_accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resident_accounts`
--
ALTER TABLE `resident_accounts`
  ADD CONSTRAINT `resident_accounts_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `volunteer_registrations`
--
ALTER TABLE `volunteer_registrations`
  ADD CONSTRAINT `volunteer_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_registrations_ibfk_2` FOREIGN KEY (`request_id`) REFERENCES `volunteer_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `volunteer_registrations_ibfk_3` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`);

--
-- Constraints for table `volunteer_requests`
--
ALTER TABLE `volunteer_requests`
  ADD CONSTRAINT `volunteer_requests_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
