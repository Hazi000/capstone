-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2025 at 04:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'low',
  `announcement_type` varchar(20) DEFAULT 'general',
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `needs_volunteers` tinyint(1) DEFAULT 0,
  `max_volunteers` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `priority`, `announcement_type`, `status`, `event_date`, `event_time`, `location`, `expiry_date`, `needs_volunteers`, `max_volunteers`, `created_by`, `created_at`, `updated_at`) VALUES
(7, 'dasbjdba', 'djabdaj', 'medium', 'event', 'expired', '0000-00-00', '00:00:00', '', '0000-00-00', 0, NULL, 2, '2025-07-24 07:28:50', '2025-07-24 07:28:50'),
(8, 'dakdna', 'ndsjada', 'medium', 'event', 'active', '2025-07-26', '03:29:00', 'adada', '2025-08-25', 1, 2, 2, '2025-07-24 07:29:41', '2025-07-24 07:31:11'),
(10, 'dasdja', 'dbsadah', 'medium', 'event', 'active', '2025-08-28', '09:00:00', 'dada', '2025-08-30', 1, 10, 2, '2025-08-19 05:20:37', '2025-08-19 05:20:37'),
(11, 'kfkkkj', 'nsadaj', 'low', 'event', 'active', '2025-08-29', '09:00:00', 'kdnsakd', '2026-05-05', 1, 9, 2, '2025-08-19 05:25:10', '2025-08-19 05:25:10');

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
(5, 'alhazier r. nasa', 22, 'da', '36217', '11', '2025-08-17 16:17:22', 2);

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
(2, 16, 'Barangay Clearance', 'da', 'bajsbd', 'approved', '2025-08-17 08:00:20', '2025-08-17 08:11:11', 2, NULL, NULL, NULL, NULL, '2025-08-17 08:11:11');

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
(3, 2, 'approved', 2, 'pending', 'approved', NULL, '2025-08-17 08:11:11');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_volunteers`
--

INSERT INTO `community_volunteers` (`id`, `resident_id`, `announcement_id`, `status`, `rejection_reason`, `attendance_status`, `hours_served`, `approved_by`, `approved_at`, `attendance_marked_at`, `created_at`) VALUES
(1, 16, 8, 'rejected', 'sorry', 'pending', 0.0, 2, '2025-08-19 13:19:36', NULL, '2025-08-17 13:16:01');

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
(13, 'gossip', 'whidz accused of being gossiper in the barangay', 'low', 'closed', NULL, '', '', '2025-07-15 07:09:16', '2025-07-20 08:53:16', 9, '', '', 'bhh', '2000-08-08');

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
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `age` int(11) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
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
  `volunteer_status` enum('inactive','active','outstanding') DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `first_name`, `middle_initial`, `last_name`, `full_name`, `status`, `age`, `contact_number`, `email`, `created_at`, `updated_at`, `photo_path`, `face_descriptor`, `face_embedding`, `face_photo`, `liveness_verified`, `total_volunteer_hours`, `total_volunteer_events`, `last_volunteer_date`, `volunteer_status`) VALUES
(9, 'Cherry', 'b', 'Gabionza', 'Cherry b. Gabionza', 'active', 35, '0955332142', NULL, '2025-07-02 08:34:21', '2025-07-02 08:34:21', 'uploads/residents/resident_6864ef0d1d26a.jpg', '[-0.14397430419921875,0.05425043776631355,0.07953716069459915,-0.13350877165794373,-0.12468057870864868,-0.03413264453411102,-0.08946795761585236,-0.20897407829761505,0.18795213103294373,-0.17681118845939636,0.2523611783981323,-0.11677242815494537,-0.17353525757789612,-0.1324233114719391,-0.013850669376552105,0.23378145694732666,-0.18028473854064941,-0.18463370203971863,-0.017803937196731567,-0.012820234522223473,0.03767484426498413,-0.05397477000951767,0.03958050161600113,0.0813690721988678,-0.1421467661857605,-0.418381005525589,-0.14166998863220215,-0.09887680411338806,-0.057409778237342834,-0.00887755211442709,-0.044200554490089417,0.1395033597946167,-0.21117128431797028,-0.0861235037446022,0.008784138597548008,0.1726359874010086,-0.020464640110731125,-0.06508591771125793,0.09189341217279434,-0.07636101543903351,-0.2889811098575592,-0.05018458142876625,0.13109120726585388,0.20665401220321655,0.18088890612125397,-0.00048010784666985273,0.02097008004784584,-0.05415702983736992,0.09004088491201401,-0.17708326876163483,0.035320863127708435,0.0454634428024292,0.15446576476097107,0.022616514936089516,0.07941493391990662,-0.16843600571155548,0.03128839656710625,0.10812289267778397,-0.15776972472667694,-0.030825231224298477,0.05788850039243698,-0.14910869300365448,-0.027690645307302475,-0.09757179766893387,0.2667187750339508,0.19747242331504822,-0.09142348170280457,-0.17224732041358948,0.18470169603824615,-0.06039142236113548,-0.0019337940029799938,0.06424437463283539,-0.1923881620168686,-0.15761545300483704,-0.34279510378837585,-0.040594495832920074,0.38995301723480225,0.025472521781921387,-0.15713652968406677,0.02348511852324009,-0.04740675911307335,0.03510933741927147,0.026525389403104782,0.11896312236785889,0.00924735702574253,0.023267872631549835,-0.0916561484336853,0.007711727172136307,0.16304907202720642,-0.1133616715669632,0.017236100509762764,0.18446622788906097,-0.07494428753852844,0.02264053001999855,0.010852953419089317,0.03799238055944443,-0.11011462658643723,0.09068908542394638,-0.14482241868972778,0.02391132526099682,-0.00908080954104662,-0.03345204517245293,0.00018965569324791431,0.0822489932179451,-0.17419536411762238,0.051012344658374786,0.01735498756170273,0.02017972245812416,0.006592790596187115,0.00223519466817379,-0.0830770879983902,-0.12038727849721909,0.12188722938299179,-0.2544234097003937,0.1321175992488861,0.17200323939323425,-0.007864479906857014,0.18978478014469147,0.08031587302684784,0.10622681677341461,-0.011109041050076485,-0.04323292523622513,-0.184305801987648,0.02672266587615013,0.11610140651464462,-0.056586913764476776,0.004617256112396717,0.05055345222353935]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(14, 'Jeramel', 'Q', 'Asid', 'Jeramel Q. Asid', 'active', 22, '09759533412', NULL, '2025-07-25 07:31:13', '2025-07-25 07:31:13', 'uploads/residents/resident_688332c18fe77.jpg', '[-0.21244865655899048,0.09996382892131805,0.01788197085261345,0.020010005682706833,-0.10392951220273972,-0.039726659655570984,-0.08736952394247055,-0.15759657323360443,0.14435824751853943,-0.054047584533691406,0.26850515604019165,0.0064662303775548935,-0.19502995908260345,-0.1120905727148056,0.001220686361193657,0.15601886808872223,-0.22102919220924377,-0.1083080992102623,0.024486910551786423,0.042514871805906296,0.08828884363174438,-0.0341598317027092,0.04155546799302101,0.08411319553852081,-0.1026061549782753,-0.31512320041656494,-0.10035178810358047,-0.12648409605026245,0.02193962037563324,-0.053486645221710205,-0.07630494982004166,-0.06376971304416656,-0.22393403947353363,-0.035191018134355545,-0.02307818830013275,0.0358375683426857,-0.002855845494195819,-0.05249928683042526,0.18837304413318634,-0.03171389549970627,-0.24089375138282776,-0.07011235505342484,0.012121770530939102,0.20624291896820068,0.1556519716978073,0.04441208764910698,0.02576284110546112,-0.08908642828464508,0.07744880020618439,-0.1353648602962494,0.08667795360088348,0.14472295343875885,0.1511736959218979,0.012463024817407131,-0.054537706077098846,-0.18891999125480652,-0.04753052070736885,0.06261736899614334,-0.0772181823849678,0.02858847938477993,0.0883825272321701,-0.0537421852350235,-0.030749021098017693,-0.0778692290186882,0.286937415599823,0.1280752718448639,-0.13644106686115265,-0.14347068965435028,0.1089622750878334,-0.09558872878551483,-0.07581102848052979,0.0570996068418026,-0.1763162463903427,-0.2328018844127655,-0.31103047728538513,0.023699400946497917,0.39954501390457153,0.05040877312421799,-0.23368282616138458,-0.017109330743551254,-0.10706325620412827,-0.002743454184383154,0.08045809715986252,0.19587688148021698,0.018503692001104355,0.0419178381562233,-0.10901579260826111,-0.012885140255093575,0.2189452201128006,-0.09029754996299744,-0.010395622812211514,0.19833068549633026,-0.007371935993432999,0.04915266111493111,-0.011256472207605839,0.03947010636329651,-0.0046153925359249115,0.025792501866817474,-0.04155788570642471,0.006181494332849979,0.019490499049425125,-0.009072385728359222,-0.0027840903494507074,0.10357749462127686,-0.15194718539714813,0.08459890633821487,0.07387810200452805,0.03515873849391937,0.026670217514038086,0.0004026084206998348,-0.09940306842327118,-0.126826211810112,0.09813059866428375,-0.20769287645816803,0.25929415225982666,0.13901537656784058,0.035888634622097015,0.17013759911060333,0.03929629921913147,0.1602959930896759,-0.02692531608045101,0.013434122316539288,-0.1568630486726761,-0.00235131592489779,0.08695459365844727,-0.038245782256126404,0.08515510708093643,0.01642031781375408]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(15, 'Leycia-Meay', 'M', 'Marcus', 'Leycia-Meay M. Marcus', 'active', 22, '123456765', NULL, '2025-07-25 07:38:50', '2025-07-25 07:38:50', 'uploads/residents/resident_6883348acf949.jpg', '[-0.07631894201040268,0.06796710938215256,-0.008939861319959164,-0.09110160171985626,-0.06543717533349991,0.00425328454002738,-0.06838063150644302,-0.10341303050518036,0.15581341087818146,-0.10505014657974243,0.19535718858242035,0.02264447882771492,-0.17970487475395203,-0.10090761631727219,-0.008935174904763699,0.1664154976606369,-0.2358698844909668,-0.15779176354408264,-0.042075544595718384,-0.04215231165289879,0.046230778098106384,0.008041190914809704,-0.04168413579463959,0.08132688701152802,-0.1251351237297058,-0.3438979983329773,-0.06812352687120438,-0.0688343197107315,0.01146471593528986,-0.04690353199839592,-0.09331655502319336,0.10254832357168198,-0.2494627982378006,-0.05258355289697647,0.049071379005908966,0.11076759546995163,-0.0023802577052265406,-0.012466193176805973,0.1731092482805252,0.06273914873600006,-0.20813240110874176,0.047415852546691895,0.009235256351530552,0.2825446128845215,0.27206817269325256,-0.025007423013448715,0.009754691272974014,-0.06206720694899559,0.12084371596574783,-0.18257318437099457,0.02636682242155075,0.22515685856342316,0.08785107731819153,0.10043134540319443,-0.050269998610019684,-0.1374523788690567,-0.016997583210468292,0.09452050924301147,-0.10438592731952667,0.018390195444226265,0.06758120656013489,-0.1309909075498581,0.0131465969607234,-0.0597386360168457,0.24646541476249695,0.035859931260347366,-0.1690596044063568,-0.17513743042945862,0.12180071324110031,-0.1012907326221466,-0.06307922303676605,0.012887981720268726,-0.2222130298614502,-0.19556289911270142,-0.31800541281700134,0.11180470883846283,0.41093724966049194,0.13790176808834076,-0.21931593120098114,0.07337602972984314,-0.10946447402238846,-0.02497989684343338,0.12779636681079865,0.1810750812292099,0.027059931308031082,0.04449111968278885,-0.028638824820518494,0.0690050795674324,0.2335273027420044,0.004754737019538879,-0.0702974945306778,0.22301173210144043,-0.05209296941757202,0.08909177780151367,0.038176242262125015,0.05103328078985214,-0.03081342950463295,0.0032990523613989353,-0.16339777410030365,0.008302850648760796,-0.04050422087311745,-0.04642576724290848,0.008333536796271801,0.17701609432697296,-0.10220172256231308,0.1396457999944687,0.01568688452243805,0.06419871002435684,-0.0284651517868042,0.030651671811938286,-0.11905103176832199,-0.10559187084436417,0.10979259014129639,-0.21846136450767517,0.251201868057251,0.193867027759552,0.08916604518890381,0.11982133239507675,0.1134997010231018,0.0719485729932785,0.0033950181677937508,-0.018895182758569717,-0.1704849749803543,-0.002807812998071313,0.10697121918201447,0.01362699642777443,0.11038554459810257,-0.007631187327206135]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(16, 'alhazier', 'r', 'nasa', 'alhazier r. nasa', 'active', 22, '1234567890', NULL, '2025-08-01 21:25:59', '2025-08-01 21:25:59', 'uploads/residents/resident_688d30e786a50.jpg', '[-0.16786766052246094,0.05152040719985962,0.017435375601053238,-0.06954573839902878,-0.03585652634501457,-0.0065636602230370045,-0.01002923771739006,-0.10955408960580826,0.22303271293640137,-0.140462726354599,0.24151262640953064,-0.010330968536436558,-0.1793452799320221,-0.15102630853652954,0.029623202979564667,0.12307922542095184,-0.16342051327228546,-0.10858244448900223,0.009132582694292068,-0.07582690566778183,0.08362022042274475,-0.0658426284790039,0.007269802503287792,0.07623991370201111,-0.1450335681438446,-0.36333826184272766,-0.09126324951648712,-0.15766431391239166,0.0731019601225853,-0.08120033144950867,0.007963810116052628,0.024301547557115555,-0.18682275712490082,-0.013343540951609612,-0.07286719232797623,0.06573875993490219,-0.017945684492588043,0.03179433196783066,0.13168953359127045,-0.02059476636350155,-0.1910763531923294,-0.08505120128393173,-0.07841722667217255,0.22874730825424194,0.14205336570739746,0.02348407357931137,0.05654004216194153,-0.018868230283260345,0.0687977597117424,-0.20802924036979675,0.03929200395941734,0.13040584325790405,0.12165545672178268,0.014722991734743118,-0.013473669067025185,-0.13780918717384338,-0.01943456381559372,0.0728331208229065,-0.18875017762184143,0.1004006564617157,0.06927629560232162,-0.12179584056138992,-0.08079363405704498,0.033771008253097534,0.2140529453754425,0.09556680917739868,-0.18227604031562805,-0.10473743081092834,0.13328030705451965,-0.1458199918270111,0.04546024277806282,0.05540899187326431,-0.14613541960716248,-0.2516544759273529,-0.3053736686706543,0.10087962448596954,0.39189380407333374,0.11500655859708786,-0.16206297278404236,-0.027428075671195984,-0.1339729130268097,0.06041998788714409,0.08536475151777267,0.1260601133108139,-0.0368100069463253,-0.01958496868610382,-0.09696275740861893,0.028298452496528625,0.08039092272520065,-0.05622038245201111,-0.025211550295352936,0.20284244418144226,-0.0457272045314312,0.05821031704545021,-0.07162652909755707,0.10401992499828339,-0.08790835738182068,0.008324416354298592,-0.011400870978832245,0.019236138090491295,0.03451656177639961,-0.04139360412955284,0.011882086284458637,0.0932835191488266,-0.15263152122497559,0.19087249040603638,0.0023579641710966825,0.010385921224951744,0.009013746865093708,0.030784228816628456,-0.09164253622293472,-0.11969483643770218,0.10155133903026581,-0.21036449074745178,0.1581013947725296,0.2559508979320526,0.042610492557287216,0.2055772989988327,0.05966322869062424,0.06940467655658722,0.009882967919111252,-0.03197290003299713,-0.15028561651706696,0.002412279136478901,0.01783447153866291,-0.043654754757881165,-0.01984129473567009,-0.03402809798717499]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(17, 'Khadaffe', 'A', 'Abubakar', 'Khadaffe A. Abubakar', 'active', 23, '091234567891', NULL, '2025-08-17 07:00:07', '2025-08-17 07:00:07', 'uploads/residents/resident_68a17df79b394.jpg', '[-0.04453220218420029,0.06594628095626831,-0.020153464749455452,0.014677929691970348,0.015430692583322525,-0.03342059254646301,-0.04523885250091553,-0.12873007357120514,0.07777392864227295,-0.07759324461221695,0.18480469286441803,-0.07904595136642456,-0.18740801513195038,-0.09495681524276733,0.028187695890665054,0.07995660603046417,-0.21221157908439636,-0.2073516696691513,-0.11495310068130493,-0.02892730012536049,-0.013233712874352932,-0.05124868452548981,-0.044123366475105286,0.04532041400671005,-0.1296275556087494,-0.3128616511821747,-0.05889549478888512,-0.1460384726524353,0.04537026956677437,-0.08610604703426361,-0.10175912082195282,0.032665789127349854,-0.19908910989761353,-0.06908362358808517,0.008903943933546543,0.10011503100395203,-0.026065533980727196,0.05002404376864433,0.1983187049627304,0.07669617235660553,-0.18968833982944489,0.06434360891580582,0.00328979454934597,0.35072094202041626,0.27181684970855713,0.04539613798260689,-0.05388014391064644,-0.026580922305583954,0.09160326421260834,-0.2506799101829529,0.07536062598228455,0.19183789193630219,0.20449060201644897,0.12666451930999756,0.012031437829136848,-0.13166464865207672,-0.030057499185204506,0.04027237743139267,-0.15526527166366577,0.062206048518419266,0.06093870848417282,-0.10698755085468292,0.028636179864406586,-0.04898317903280258,0.2327519804239273,0.08296205848455429,-0.11567239463329315,-0.03747110068798065,0.13814492523670197,-0.14867500960826874,-0.0682741329073906,-0.020563490688800812,-0.09924688935279846,-0.16516035795211792,-0.309001624584198,0.05297909677028656,0.369962602853775,0.10505270212888718,-0.2540922462940216,0.0008623837493360043,-0.06847824901342392,-0.06496021151542664,0.11368003487586975,0.09219374507665634,-0.04868515580892563,0.028737403452396393,-0.059493083506822586,0.022033020853996277,0.1876959651708603,-0.03536375239491463,-0.005452155135571957,0.11774660646915436,-0.053303491324186325,0.025394178926944733,0.10226447880268097,0.03525225818157196,-0.06001526862382889,-0.034593451768159866,-0.19908098876476288,-0.006909108720719814,-0.08296315371990204,-0.0897335410118103,-0.014309304766356945,0.08446240425109863,-0.11024202406406403,0.14206278324127197,0.07938921451568604,-0.05186187103390694,-0.039181169122457504,0.028950650244951248,-0.05181514099240303,-0.09395138919353485,0.14053913950920105,-0.2379569709300995,0.19487425684928894,0.20236054062843323,0.03238809481263161,0.12646561861038208,0.023206353187561035,0.05566246807575226,-0.05810388922691345,0.026319731026887894,-0.17213588953018188,-0.058673255145549774,0.0332195870578289,0.06551067531108856,0.03094734251499176,0.024634364992380142]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive');

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
(6, 16, 'resident', 'resident@gmail.com', '$2y$10$D1YdctePJpWXyZkAZktazeImrIBfFVfyrb2LHKoS5evX6Q8iQzPya', 1, '064a00841975e84b4e7fa08fbf7bd7c63ec9eb2ad66744a7be87728886695adf', '2025-08-19 13:06:32', 0, 0, '2025-08-01 21:27:39', '2025-08-19 05:06:32'),
(7, 17, 'KCprsnlcc', 'kcpersonalacc@gmail.com', '$2y$10$imdWXKZGyeS6wouNPUn2Bu3IdxIHg6GOnI6YxBu5jy1dZAjlZ2go6', 1, '8c34ce3016e2e6f0cfc1be7dd311e0098debf4d708593aee126b29bb0d24fff6', '2025-08-17 15:02:18', 0, 0, '2025-08-17 07:01:54', '2025-08-17 07:02:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('captain','secretary','super_admin') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `status`, `created_at`, `last_login`, `updated_at`) VALUES
(2, 'super admin', 'Jeramel@gmail.com', '$2y$10$9Yw.4Xuv7/FVUinUkluCg.HnRN7y8K.5aCDs8fn1X7rXt/pqhzwau', 'super_admin', 'active', '2025-06-25 04:51:40', '2025-08-19 12:30:21', '2025-08-19 12:30:21'),
(6, 'Jeramel Q. Asid', 'jeramelasid@gmail.com', '$2y$10$1VWcYXtAWqWn88P.rVJa1OaLy2aHcWWnclO2YiXui9sCMhvEtTDfG', 'secretary', 'active', '2025-07-02 05:59:25', '2025-08-17 08:13:08', '2025-08-17 08:13:08'),
(7, 'Whidzmar Taraji', 'whidztaraji@gmail.com', '$2y$10$tSFpTT54QCBPcVII1fHTTuc1EIY.MtHNS4y8FB8pSdHlKJtQaoD3S', 'secretary', 'active', '2025-07-02 06:00:46', '2025-07-25 06:57:01', '2025-07-31 09:07:33');

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
  ADD KEY `event_date` (`event_date`),
  ADD KEY `priority` (`priority`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `approved_by` (`approved_by`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `certificate_request_logs`
--
ALTER TABLE `certificate_request_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `certificate_types`
--
ALTER TABLE `certificate_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `community_volunteers`
--
ALTER TABLE `community_volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `resident_accounts`
--
ALTER TABLE `resident_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `community_volunteers_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`defendant_resident_id`) REFERENCES `residents` (`id`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
