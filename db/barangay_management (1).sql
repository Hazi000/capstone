-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 24, 2025 at 09:36 AM
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
(6, 'dada', 'dshabdah', 'medium', 'general', 'expired', '0000-00-00', '00:00:00', '', '0000-00-00', 0, NULL, 2, '2025-07-24 07:28:30', '2025-07-24 07:28:30'),
(7, 'dasbjdba', 'djabdaj', 'medium', 'event', 'expired', '0000-00-00', '00:00:00', '', '0000-00-00', 0, NULL, 2, '2025-07-24 07:28:50', '2025-07-24 07:28:50'),
(8, 'dakdna', 'ndsjada', 'medium', 'event', 'active', '2025-07-26', '03:29:00', 'adada', '2025-08-25', 1, 2, 2, '2025-07-24 07:29:41', '2025-07-24 07:31:11');

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
(4, 'dwdjan', 44, 'ghgh', '111', '9', '2025-07-02 19:21:36', 2);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_requests`
--

CREATE TABLE `certificate_requests` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `purpose` text NOT NULL,
  `status` enum('pending','processing','approved','rejected','claimed') NOT NULL DEFAULT 'pending',
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_date` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `claim_date` datetime DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','paid','waived') DEFAULT 'unpaid',
  `payment_date` datetime DEFAULT NULL,
  `or_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(13, 'gossip', 'whidz accused of being gossiper in the barangay', 'low', 'closed', 8, '', '', '2025-07-15 07:09:16', '2025-07-20 08:53:16', 9, '', '', 'bhh', '2000-08-08');

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
(8, 'Jeramel', 'Q', 'Asid', 'Jeramel Q. Asid', 'active', 18, '09759533412', NULL, '2025-07-02 07:39:56', '2025-07-02 07:39:56', 'uploads/residents/resident_6864e24c01226.jpg', '[-0.1919017881155014,0.08268166333436966,0.026070384308695793,0.02390880323946476,-0.059994783252477646,-0.05461621657013893,-0.09666140377521515,-0.160446897149086,0.14658428728580475,-0.03201188147068024,0.25774574279785156,-0.03590851277112961,-0.14254078269004822,-0.11728477478027344,-0.009885666891932487,0.1533007174730301,-0.2732992470264435,-0.10574612766504288,0.04611644893884659,-0.010521374642848969,0.10950934141874313,-0.049874309450387955,0.028014911338686943,0.08019828051328659,-0.12329976260662079,-0.3130345344543457,-0.09946748614311218,-0.14627493917942047,0.12430303543806076,-0.06852298974990845,-0.08393876254558563,-0.0640796646475792,-0.2184731364250183,-0.05134265124797821,-0.013739793561398983,0.07634937018156052,0.03188376501202583,-0.06592847406864166,0.1744234561920166,-0.060495514422655106,-0.21506917476654053,-0.034564364701509476,0.024322887882590294,0.19889213144779205,0.19573043286800385,0.05177519842982292,0.03210587054491043,-0.10533758997917175,0.13724163174629211,-0.12029516696929932,0.09539148956537247,0.14508682489395142,0.13321265578269958,0.06864920258522034,-0.03634748235344887,-0.12852638959884644,-0.050730638206005096,0.10673292726278305,-0.0940832868218422,0.07331978529691696,0.1150241270661354,-0.05354473739862442,0.034949883818626404,-0.061855051666498184,0.25163909792900085,0.09629783034324646,-0.1209258958697319,-0.1540934294462204,0.11290856450796127,-0.11269070953130722,-0.1120278388261795,0.0371074378490448,-0.14753197133541107,-0.19206970930099487,-0.3657247722148895,0.04093610495328903,0.39964473247528076,0.05964362248778343,-0.1937592625617981,0.03845454379916191,-0.08514469116926193,-0.009915456175804138,0.12148799747228622,0.19257861375808716,0.021220581606030464,0.03306695818901062,-0.09299421310424805,-0.020471541211009026,0.24225860834121704,-0.05932796001434326,-0.016288964077830315,0.16862225532531738,-0.05156043916940689,0.07703223824501038,-0.010726355947554111,0.09003618359565735,-0.03507877141237259,0.036975182592868805,-0.036233820021152496,-0.006504819728434086,0.02264060266315937,0.017363090068101883,0.027048317715525627,0.10896451771259308,-0.1358758956193924,0.08904564380645752,0.05167616903781891,0.06014705076813698,0.024858932942152023,0.021869447082281113,-0.07944007217884064,-0.09259035438299179,0.09752755612134933,-0.2377917468547821,0.2832125425338745,0.15828311443328857,0.05578852817416191,0.15532433986663818,0.042081885039806366,0.14065849781036377,-0.007362105883657932,0.005344511941075325,-0.13223686814308167,-0.004049713723361492,0.060502611100673676,-0.006399574689567089,0.07464252412319183,0.039623551070690155]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(9, 'Cherry', 'b', 'Gabionza', 'Cherry b. Gabionza', 'active', 35, '0955332142', NULL, '2025-07-02 08:34:21', '2025-07-02 08:34:21', 'uploads/residents/resident_6864ef0d1d26a.jpg', '[-0.14397430419921875,0.05425043776631355,0.07953716069459915,-0.13350877165794373,-0.12468057870864868,-0.03413264453411102,-0.08946795761585236,-0.20897407829761505,0.18795213103294373,-0.17681118845939636,0.2523611783981323,-0.11677242815494537,-0.17353525757789612,-0.1324233114719391,-0.013850669376552105,0.23378145694732666,-0.18028473854064941,-0.18463370203971863,-0.017803937196731567,-0.012820234522223473,0.03767484426498413,-0.05397477000951767,0.03958050161600113,0.0813690721988678,-0.1421467661857605,-0.418381005525589,-0.14166998863220215,-0.09887680411338806,-0.057409778237342834,-0.00887755211442709,-0.044200554490089417,0.1395033597946167,-0.21117128431797028,-0.0861235037446022,0.008784138597548008,0.1726359874010086,-0.020464640110731125,-0.06508591771125793,0.09189341217279434,-0.07636101543903351,-0.2889811098575592,-0.05018458142876625,0.13109120726585388,0.20665401220321655,0.18088890612125397,-0.00048010784666985273,0.02097008004784584,-0.05415702983736992,0.09004088491201401,-0.17708326876163483,0.035320863127708435,0.0454634428024292,0.15446576476097107,0.022616514936089516,0.07941493391990662,-0.16843600571155548,0.03128839656710625,0.10812289267778397,-0.15776972472667694,-0.030825231224298477,0.05788850039243698,-0.14910869300365448,-0.027690645307302475,-0.09757179766893387,0.2667187750339508,0.19747242331504822,-0.09142348170280457,-0.17224732041358948,0.18470169603824615,-0.06039142236113548,-0.0019337940029799938,0.06424437463283539,-0.1923881620168686,-0.15761545300483704,-0.34279510378837585,-0.040594495832920074,0.38995301723480225,0.025472521781921387,-0.15713652968406677,0.02348511852324009,-0.04740675911307335,0.03510933741927147,0.026525389403104782,0.11896312236785889,0.00924735702574253,0.023267872631549835,-0.0916561484336853,0.007711727172136307,0.16304907202720642,-0.1133616715669632,0.017236100509762764,0.18446622788906097,-0.07494428753852844,0.02264053001999855,0.010852953419089317,0.03799238055944443,-0.11011462658643723,0.09068908542394638,-0.14482241868972778,0.02391132526099682,-0.00908080954104662,-0.03345204517245293,0.00018965569324791431,0.0822489932179451,-0.17419536411762238,0.051012344658374786,0.01735498756170273,0.02017972245812416,0.006592790596187115,0.00223519466817379,-0.0830770879983902,-0.12038727849721909,0.12188722938299179,-0.2544234097003937,0.1321175992488861,0.17200323939323425,-0.007864479906857014,0.18978478014469147,0.08031587302684784,0.10622681677341461,-0.011109041050076485,-0.04323292523622513,-0.184305801987648,0.02672266587615013,0.11610140651464462,-0.056586913764476776,0.004617256112396717,0.05055345222353935]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive'),
(12, 'alhazier', 'R', 'nasa', 'alhazier R. nasa', 'active', 12, '09635810729', NULL, '2025-07-22 21:38:24', '2025-07-22 21:38:24', 'uploads/residents/resident_688004d0923e2.jpg', '[-0.1954726278781891,0.04301619529724121,0.04340752959251404,-0.062305279076099396,-0.004694916307926178,-0.021831197664141655,-0.015466194599866867,-0.12269473075866699,0.22858011722564697,-0.12898455560207367,0.27788591384887695,-0.02921886555850506,-0.22247885167598724,-0.14263905584812164,0.027597829699516296,0.12154699862003326,-0.18556278944015503,-0.08725697547197342,0.004047585651278496,-0.0832655131816864,0.07377734780311584,-0.022180046886205673,0.006061446852982044,0.060337480157613754,-0.1699332743883133,-0.37110838294029236,-0.11080195009708405,-0.1436385214328766,0.016620345413684845,-0.058991938829422,-0.006249572150409222,0.02248428761959076,-0.17631952464580536,-0.017392003908753395,-0.027770116925239563,0.11556991934776306,-0.051450926810503006,-0.0010151369497179985,0.11994805932044983,-0.019574251025915146,-0.1830054670572281,-0.09367020428180695,-0.03186070919036865,0.2151881605386734,0.12179180234670639,0.06284403800964355,0.007725626230239868,-0.04311882704496384,0.07747498154640198,-0.21910667419433594,0.01735733635723591,0.15888412296772003,0.07626672089099884,0.02020867168903351,0.04560917988419533,-0.15234385430812836,-0.011303393170237541,0.089787557721138,-0.19575996696949005,0.05015060305595398,0.030669741332530975,-0.12996622920036316,-0.05717663839459419,0.0038167499005794525,0.2038026601076126,0.12682302296161652,-0.18387828767299652,-0.073787160217762,0.18039356172084808,-0.13863690197467804,0.03340800851583481,0.08412059396505356,-0.11877834796905518,-0.28230664134025574,-0.26605916023254395,0.0586586594581604,0.44482263922691345,0.08578374981880188,-0.14276129007339478,-0.018017608672380447,-0.10611247271299362,-0.007353693246841431,0.0958169624209404,0.14064942300319672,-0.016809698194265366,-0.014068283140659332,-0.09546473622322083,0.010470818728208542,0.12187482416629791,-0.029809236526489258,0.010351981967687607,0.21752211451530457,-0.052902042865753174,0.07163827866315842,-0.030582383275032043,0.07621574401855469,-0.09043359756469727,0.011447332799434662,-0.027615869417786598,0.010920198634266853,0.02111748233437538,-0.07581428438425064,-0.01563851535320282,0.07046125829219818,-0.1544228345155716,0.17575295269489288,0.0563662089407444,-0.016207359731197357,0.009078461676836014,-0.0017149029299616814,-0.056276194751262665,-0.0772446021437645,0.11229299753904343,-0.2821720242500305,0.17359375953674316,0.22403952479362488,0.018888454884290695,0.23355670273303986,0.014059947803616524,0.07357645779848099,-0.004734905436635017,-0.06831719726324081,-0.18648533523082733,-0.034539125859737396,-0.011912684887647629,-0.05812951177358627,-0.01689957082271576,-0.03956644982099533]', NULL, NULL, 0, 0.0, 0, NULL, 'inactive');

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
(3, 8, 'resident', 'resident@gmail.com', '$2y$10$MgSfPik0.UzYlVb2oKTMS.DqsZPKPWHMKUuSBuVBI1WKjnFQnnbsW', 1, '960ca92597e11f8d3590ed449a6c876403bdaaacb4dcd603b90038e83c750899', '2025-07-24 15:06:10', 0, 0, '2025-07-02 08:03:02', '2025-07-24 07:06:10'),
(4, 9, 'cherry', 'cherry@gmail.com', '$2y$10$NxRSNHpYSnvi/MR4QzgDJOC5OMjHNkAzTA4s0JhknekWTzBVnLTBq', 1, '5f873b2514cfdb3c6f197bf1ebbe66aa76fc8b581b88e644997408d0cb1539ad', '2025-07-03 08:50:40', 0, 0, '2025-07-02 08:38:14', '2025-07-03 00:50:40');

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
(2, 'super admin', 'Jeramel@gmail.com', '$2y$10$9Yw.4Xuv7/FVUinUkluCg.HnRN7y8K.5aCDs8fn1X7rXt/pqhzwau', 'super_admin', 'active', '2025-06-25 04:51:40', '2025-07-24 07:07:23', '2025-07-24 07:07:23'),
(6, 'Jeramel Q. Asid', 'jeramelasid@gmail.com', '$2y$10$dyIyu/klmv7VrqG7Y/iSjuzPZRm64uosEDQm2hVs2PTwmR7AXAgFC', 'secretary', 'active', '2025-07-02 05:59:25', '2025-07-03 07:01:32', '2025-07-03 07:01:32'),
(7, 'Whidzmar Taraji', 'whidztaraji@gmail.com', '$2y$10$1g134Fu0rudeQBab5lUpceWIh43/ZzGlJV5rAY5DqYvebFoO4REXu', 'secretary', 'active', '2025-07-02 06:00:46', '2025-07-24 07:34:57', '2025-07-24 07:34:57');

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `status` (`status`),
  ADD KEY `certificate_type` (`certificate_type`),
  ADD KEY `request_date` (`request_date`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificate_types`
--
ALTER TABLE `certificate_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `community_volunteers`
--
ALTER TABLE `community_volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `resident_accounts`
--
ALTER TABLE `resident_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `certificate_requests`
--
ALTER TABLE `certificate_requests`
  ADD CONSTRAINT `certificate_requests_processor_fk` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `certificate_requests_resident_fk` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE;

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
