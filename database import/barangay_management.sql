-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2025 at 02:46 PM
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
(18, 'feeding Program', 'program for childer', 'general', 'expired', '2025-10-03', 6, '2025-09-26 11:54:09', '2025-10-05 23:55:04');

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
(6, 'jeramel pangilinan', 18, 'Need for license to operate for DTI', '123', '10.00', '2025-09-26 20:21:39', 6),
(7, 'jeramel pangilinan', 21, 'dsada', '212121', '21', '2025-10-06 07:07:54', 6),
(8, 'jeramel pangilinan', 22, 'dsada', '23', '1', '2025-10-06 07:23:16', 6),
(9, 'jeramel pangilinan', 22, 'dsada', '23', '8', '2025-10-06 07:42:27', 6),
(10, 'Jeramel Q. Pangilinan', 33, 'dsada', '8787', '99', '2025-10-10 19:19:07', 6);

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
(3, 18, 'Barangay Business Permit', 'Need for license to operate for DTI', 'N/A', 'approved', '2025-09-26 12:20:36', '2025-09-26 12:21:16', 6, NULL, NULL, NULL, NULL, '2025-09-26 12:21:16'),
(4, 18, 'Barangay Clearance', 'dsada', 'addada', 'approved', '2025-10-05 23:07:36', '2025-10-05 23:07:44', 6, NULL, NULL, NULL, NULL, '2025-10-05 23:07:44'),
(5, 18, 'Barangay Clearance', 'dasd', 'adad', 'pending', '2025-10-05 23:10:29', NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-05 23:10:29'),
(6, 18, 'Barangay Clearance', 'dadad', 'dad', 'rejected', '2025-10-05 23:10:33', '2025-10-05 23:10:46', 6, 'wdwd', NULL, NULL, NULL, '2025-10-05 23:10:46');

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
(4, 3, 'approved', 6, 'pending', 'approved', NULL, '2025-09-26 12:21:16'),
(5, 4, 'approved', 6, 'pending', 'approved', NULL, '2025-10-05 23:07:44'),
(6, 6, 'rejected', 6, 'pending', 'rejected', 'wdwd', '2025-10-05 23:10:46');

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
(16, 'sdssa', 'shds', 'medium', 'in-progress', 18, '', '', '2025-09-26 12:26:23', '2025-09-26 12:26:31', 15, '', '', NULL, NULL),
(17, 'Bullying', 'through online', 'medium', 'pending', 16, '', '', '2025-10-10 12:00:55', '2025-10-10 12:00:55', 15, '', '', NULL, NULL);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(9, 'hiii', 'djashdj', '2025-10-08', '2025-10-08', '22:45:00', 'dadasda', 0, 21, 6, 'upcoming', '2025-09-30 15:05:14', '2025-10-08 14:44:50'),
(10, 'dada', 'dadada', '2025-10-09', '2025-10-09', '10:00:00', 'dada', 0, 1, 9, 'upcoming', '2025-10-01 15:11:37', '2025-10-09 02:10:01'),
(11, 'ddada', 'dadada', '2025-10-09', '2025-10-31', '12:16:00', '111', 0, 21, 2, 'upcoming', '2025-10-01 15:13:24', '2025-10-09 04:14:47'),
(12, 'klokjfasdk', 'jifhaisfhai', '2025-10-08', '2025-10-10', '15:22:00', 'GREENBAY UPAI', 0, 1, 6, 'upcoming', '2025-10-08 14:20:02', '2025-10-08 14:20:02'),
(13, 'dasdad', 'dadada', '2025-10-09', '2025-10-09', '12:39:00', 'dsadas', 0, 2, 6, 'upcoming', '2025-10-09 04:38:34', '2025-10-09 04:38:34'),
(14, 'dnajdna', 'ndajbas', '2025-10-10', '2025-10-11', '16:56:00', 'dksadj', 0, 2, 6, 'upcoming', '2025-10-10 10:54:32', '2025-10-10 10:57:14');

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
(8, 1, 18, 'father', '2025-10-05 23:03:42', '2025-10-05 23:03:42');

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
-- Table structure for table `online_profiling_requests`
--

CREATE TABLE `online_profiling_requests` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_initial` varchar(1) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `contact_number` varchar(11) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `clearance_path` varchar(255) DEFAULT NULL,
  `clearance_text` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
(18, 'Jeramel', 'Q', 'Asid', 'Jeramel Q. Asid', NULL, 22, '09033287381', 'Zone 1A', NULL, '2025-09-26 12:02:28', '2025-10-10 11:39:08', 'uploads/residents/resident_68e8f05c61e61.jpg', '[-0.14661861956119537,0.08341653645038605,0.04078025370836258,0.021927013993263245,-0.08404980599880219,-0.014368858188390732,-0.0747266635298729,-0.07261145114898682,0.14594556391239166,-0.045669011771678925,0.276767373085022,-0.03488391265273094,-0.14856329560279846,-0.07074043154716492,0.013300566002726555,0.13030138611793518,-0.20723673701286316,-0.036682337522506714,0.0018755579367280006,-0.019235339015722275,0.08312635123729706,0.008133136667311192,0.012214799411594868,0.05863344296813011,-0.11069343239068985,-0.346241295337677,-0.09670081734657288,-0.1754719763994217,0.10562901198863983,-0.12372836470603943,-0.0834856927394867,-0.04709242284297943,-0.197023406624794,-0.04098568856716156,-0.009943854063749313,0.009668935090303421,0.003828235901892185,0.00003551226109266281,0.17067566514015198,-0.04278044030070305,-0.18069525063037872,-0.07164118438959122,-0.008784864097833633,0.22225840389728546,0.1945827752351761,0.06281998753547668,0.051766104996204376,-0.07800343632698059,0.10484912246465683,-0.12112022936344147,0.10932166874408722,0.11984819918870926,0.15167757868766785,0.04829692468047142,-0.019606687128543854,-0.14336028695106506,-0.07398983836174011,0.09930526465177536,-0.1391744166612625,0.12189561128616333,0.11312959343194962,-0.03451531380414963,-0.024707604199647903,-0.028242330998182297,0.2661576271057129,0.08038268238306046,-0.12484855204820633,-0.18858127295970917,0.16289567947387695,-0.1473075896501541,-0.046018462628126144,0.0714987963438034,-0.15794077515602112,-0.2270686775445938,-0.32375845313072205,0.02462139166891575,0.398333340883255,0.0996527150273323,-0.23138637840747833,-0.013685585930943489,-0.12324188649654388,-0.02328227087855339,0.11495355516672134,0.14525310695171356,0.01896820031106472,0.02699483558535576,-0.08964324742555618,0.014166852459311485,0.21737554669380188,-0.0628821924328804,-0.031059881672263145,0.18119530379772186,-0.04182051494717598,0.03440513834357262,-0.022949615493416786,0.04836709424853325,-0.055219024419784546,-0.011435423046350479,-0.05877857655286789,-0.03292634338140488,-0.02456938847899437,-0.011119780130684376,-0.004601849243044853,0.14100466668605804,-0.14982479810714722,0.17768150568008423,0.020358668640255928,0.043651554733514786,-0.023726264014840126,0.020975705236196518,-0.09895308315753937,-0.08087101578712463,0.16100801527500153,-0.21274608373641968,0.21919098496437073,0.13790762424468994,0.0679619088768959,0.15642417967319489,0.004511465784162283,0.12999795377254486,-0.005551549606025219,-0.017136164009571075,-0.20256681740283966,0.0019872060511261225,0.030790027230978012,-0.04737264662981033,0.06955467909574509,0.020062677562236786]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive', '');

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
(8, 18, 'donny', 'donnypogiasid@gmail.com', '$2y$10$EvuxZdKCd3K.7Kg9CMnpLePHMjleUE6RDj3M.NW6HgTU6Rs.EPAOC', 1, '586d5c8a2f5e54f91e8cd2d3a4bfbc39e36240912bb0a502eacebced447485b7', '2025-10-10 18:54:49', 0, 0, '2025-09-26 12:10:16', '2025-10-10 11:42:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role` enum('captain','secretary','super_admin','treasurer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `first_name`, `middle_initial`, `last_name`, `role`, `status`, `created_at`, `last_login`, `updated_at`, `two_factor_enabled`) VALUES
(2, 'super admin', 'Jeramel@gmail.com', '$2y$10$gKtYKsDY5wQs019RZdPiyeYXoHR7FRW1wojg/teBm0XHEKHJxckym', 'super', 's', 'admin', 'super_admin', 'active', '2025-06-25 04:51:40', '2025-10-10 12:12:46', '2025-10-10 12:13:10', 0),
(6, 'Jeramel Q. Asid', 'jeramelasid@gmail.com', '$2y$10$.16AtRcwOPbfQCq7xzjNRuSeQ6fODVH87OXLD8VCZYgZagptSTtTy', 'Jeramel', 'Q', 'Asid', 'secretary', 'active', '2025-07-02 05:59:25', '2025-10-10 12:12:23', '2025-10-10 12:12:23', 0),
(7, 'Whidzmar Taraji', 'whidzmartaraji03@gmail.com', '$2y$10$OF4i.8P4AOmKLFFVRTg07.TCraMPniYGYHpyvY29ZI7DYsp0SDpRe', 'Whidzmar', 'W', 'Taraji', 'treasurer', 'active', '2025-07-02 06:00:46', '2025-10-10 11:26:26', '2025-10-10 11:26:26', 0),
(9, 'Donny Asid', 'jerpub20@gmail.com', '$2y$10$Yn5Nas8/QEJniUGIPTAHKuaK73v3UhOIAfI69DEsH1AEeHxWLQA56', 'Donny', 'D', 'Asid', 'captain', 'active', '2025-09-26 14:06:31', '2025-10-10 12:13:24', '2025-10-10 12:13:51', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  `revoked` tinyint(1) DEFAULT 0,
  `created_at` int(11) NOT NULL,
  `last_active` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `last_activity`, `revoked`, `created_at`, `last_active`) VALUES
(1, 'mtp8cm755u91entks2lja96jbt', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 1759897435, 0, 1759897271, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_proofs`
--

CREATE TABLE `volunteer_proofs` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_registrations`
--

CREATE TABLE `volunteer_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','attended') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hours_served` decimal(5,2) DEFAULT NULL,
  `attended_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `volunteer_registrations`
--

INSERT INTO `volunteer_registrations` (`id`, `event_id`, `request_id`, `resident_id`, `status`, `rejection_reason`, `registration_date`, `updated_at`, `hours_served`, `attended_at`) VALUES
(5, 2, 2, 18, 'approved', NULL, '2025-09-26 12:17:33', '2025-09-26 12:19:02', NULL, NULL),
(6, 3, 3, 18, 'approved', NULL, '2025-09-30 14:45:44', '2025-09-30 14:47:32', NULL, NULL),
(7, 4, 4, 18, 'rejected', 'jbjfdbs', '2025-09-30 14:45:58', '2025-09-30 14:47:28', NULL, NULL),
(14, 8, 5, 18, 'approved', NULL, '2025-09-30 16:05:49', '2025-09-30 16:05:59', NULL, NULL),
(15, 9, 6, 18, 'approved', NULL, '2025-09-30 16:06:10', '2025-09-30 16:09:20', NULL, NULL),
(16, 6, 7, 18, 'rejected', '7t', '2025-09-30 16:09:01', '2025-09-30 16:09:15', NULL, NULL),
(17, 10, 8, 18, 'approved', NULL, '2025-10-05 23:04:34', '2025-10-09 07:37:13', NULL, '2025-10-09 15:29:17'),
(18, 12, 9, 18, 'approved', NULL, '2025-10-08 14:47:30', '2025-10-08 15:07:16', NULL, NULL),
(19, 11, 10, 18, 'rejected', 'sasa', '2025-10-09 02:35:59', '2025-10-09 07:37:19', NULL, '2025-10-09 15:31:19'),
(20, 6, 11, 6, 'approved', NULL, '2025-10-09 04:25:31', '2025-10-09 05:49:35', NULL, NULL),
(21, 11, 10, 6, 'rejected', 'da', '2025-10-09 04:25:51', '2025-10-09 05:41:15', NULL, '2025-10-09 13:37:26'),
(22, 13, 12, 6, 'approved', NULL, '2025-10-09 04:38:40', '2025-10-09 07:36:36', NULL, '2025-10-09 13:41:31'),
(23, 14, 13, 18, 'attended', NULL, '2025-10-10 10:54:57', '2025-10-10 10:57:43', NULL, '2025-10-10 18:57:43');

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
(8, 10, NULL, NULL, 1, 0, '', '2025-10-05 23:04:34', '2025-10-05 23:04:34'),
(9, 12, NULL, NULL, 1, 0, '', '2025-10-08 14:47:30', '2025-10-08 14:47:30'),
(10, 11, NULL, NULL, 1, 0, '', '2025-10-09 02:35:59', '2025-10-09 02:35:59'),
(11, 6, NULL, NULL, 1, 0, '', '2025-10-09 04:25:31', '2025-10-09 04:25:31'),
(12, 13, NULL, NULL, 1, 0, '', '2025-10-09 04:38:40', '2025-10-09 04:38:40'),
(13, 14, NULL, NULL, 1, 0, '', '2025-10-10 10:54:57', '2025-10-10 10:54:57');

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
-- Indexes for table `online_profiling_requests`
--
ALTER TABLE `online_profiling_requests`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`);

--
-- Indexes for table `volunteer_proofs`
--
ALTER TABLE `volunteer_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registration_id` (`registration_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `certificate_request_logs`
--
ALTER TABLE `certificate_request_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `online_profiling_requests`
--
ALTER TABLE `online_profiling_requests`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `volunteer_proofs`
--
ALTER TABLE `volunteer_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `volunteer_registrations`
--
ALTER TABLE `volunteer_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `volunteer_requests`
--
ALTER TABLE `volunteer_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Constraints for table `volunteer_proofs`
--
ALTER TABLE `volunteer_proofs`
  ADD CONSTRAINT `fk_vp_registration` FOREIGN KEY (`registration_id`) REFERENCES `volunteer_registrations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
