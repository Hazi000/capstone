<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// Get dashboard statistics
$stats = [];

// Count total complaints
$complaint_query = "SELECT COUNT(*) as total FROM complaints";
$result = mysqli_query($connection, $complaint_query);
$stats['total_complaints'] = mysqli_fetch_assoc($result)['total'];

// Count pending complaints
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_complaint_query);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];

// Count total appointments
$appointment_query = "SELECT COUNT(*) as total FROM appointments";
$result = mysqli_query($connection, $appointment_query);
$stats['total_appointments'] = mysqli_fetch_assoc($result)['total'];

// Count pending appointments
$pending_appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_appointment_query);
$stats['pending_appointments'] = mysqli_fetch_assoc($result)['pending'];

// Count total residents
$residents_query = "SELECT COUNT(*) as total FROM residents";
$result = mysqli_query($connection, $residents_query);
$stats['total_residents'] = mysqli_fetch_assoc($result)['total'];

// Get recent complaints
$recent_complaints_query = "SELECT c.*, r.full_name FROM complaints c 
                          LEFT JOIN residents r ON c.resident_id = r.id 
                          ORDER BY c.created_at DESC LIMIT 5";
$recent_complaints = mysqli_query($connection, $recent_complaints_query);

// Get recent announcements
$recent_announcements_query = "SELECT a.*, u.full_name as created_by_name 
                              FROM announcements a 
                              LEFT JOIN users u ON a.created_by = u.id 
                              WHERE a.status = 'active' 
                              AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                              ORDER BY a.priority DESC, a.created_at DESC 
                              LIMIT 5";
$recent_announcements = mysqli_query($connection, $recent_announcements_query);

// Get calendar events (appointments and announcements)
$calendar_events_query = "
    SELECT 
    'announcement' as type,
    an.id,
    COALESCE(an.event_date, an.created_at) as event_date, /* Use created_at if event_date is null */
    an.event_time as time,
    an.title,
    an.status,
    u.full_name as resident_name,
    'announcement' as category
FROM announcements an 
LEFT JOIN users u ON an.created_by = u.id 
WHERE (an.event_date IS NOT NULL AND an.event_date >= CURDATE() AND an.event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
   OR (an.event_date IS NULL AND DATE(an.created_at) >= CURDATE() AND DATE(an.created_at) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
AND an.status = 'active'";

$calendar_events = mysqli_query($connection, $calendar_events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            transition: transform 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand i {
            margin-right: 12px;
            font-size: 1.5rem;
            color: #3498db;
        }

        .user-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .user-name {
            font-weight: bold;
            font-size: 1rem;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.8;
            color: #3498db;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #bdc3c7;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            border-left-color: #3498db;
        }

        .nav-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left-color: #3498db;
        }

        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .nav-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: auto;
            float: right;
        }

        .nav-badge.blue {
            background: #3498db;
        }

        .nav-badge.orange {
            background: #f39c12;
        }

        .nav-badge.green {
            background: #27ae60;
        }

        /* Fixed logout section positioning */
        .logout-section {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
            margin-top: auto;
        }

        .logout-btn {
            width: 100%;
            background: rgba(231, 76, 60, 0.2);
            color: white;
            border: 1px solid rgba(231, 76, 60, 0.5);
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            border-color: #e74c3c;
            transform: translateY(-1px);
        }

        .logout-btn:active {
            transform: translateY(0);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
        }

        .page-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        .content-area {
            padding: 2rem;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-right: 1rem;
            width: 60px;
            text-align: center;
        }

        .stat-content h3 {
            font-size: 2rem;
            margin-bottom: 0.25rem;
            color: #333;
        }

        .stat-content p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .left-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .recent-activities {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .recent-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .recent-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .recent-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .recent-list {
            padding: 1rem;
        }

        .recent-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .recent-item:hover {
            background: #f8f9fa;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-info h4 {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .recent-info p {
            font-size: 0.8rem;
            color: #666;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-resolved {
            background: #d4edda;
            color: #155724;
        }

        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        /* Calendar Styles */
        .calendar-widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .calendar-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            text-align: center;
        }

        .calendar-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .calendar-nav button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .calendar-nav button:hover {
            background: rgba(255,255,255,0.3);
        }

        .calendar-grid {
            padding: 0.5rem;
        }

        .calendar-days-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 2px;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 0.5rem;
            text-align: center;
            font-weight: bold;
            font-size: 0.85rem;
            color: #333;
            border: 1px solid #e0e0e0;
        }

        .calendar-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .calendar-day {
            background: white;
            border: 1px solid #e0e0e0;
            min-height: 80px;
            padding: 0.5rem;
            position: relative;
            transition: background 0.2s ease;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .calendar-day.other-month {
            background: #fafafa;
        }

        .calendar-day.other-month .calendar-day-number {
            color: #ccc;
        }

        .calendar-day-number {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
        }

        .calendar-event {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 2px;
            line-height: 1.2;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .calendar-event:hover {
            overflow: visible;
            white-space: normal;
            z-index: 10;
            background: #fff;
            padding: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

     

        .upcoming-events {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: 1rem;
        }

        .upcoming-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .upcoming-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upcoming-list {
    max-height: 300px; /* Reduced from 400px */
    overflow-y: auto;
}

        .upcoming-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .upcoming-item:last-child {
            border-bottom: none;
        }

        .upcoming-date {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
            min-width: 60px;
        }

        .upcoming-date-day {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .upcoming-date-month {
            font-size: 0.7rem;
            color: #666;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-info h4 {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .upcoming-info p {
            font-size: 0.8rem;
            color: #666;
        }

        .upcoming-type {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .upcoming-type.appointment {
            background: #e3f2fd;
            color: #1976d2;
        }

        .upcoming-type.announcement {
            background: #fff3e0;
            color: #f57c00;
        }

        /* Mobile Responsiveness */
        @media (max-width: 1200px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .recent-activities {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .content-area {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .top-bar {
                padding: 1rem;
            }

            .calendar-day {
                min-height: 50px;
                padding: 0.25rem;
            }

            .upcoming-item {
                padding: 0.75rem 1rem;
            }
         @media (max-width: 768px) {
    .calendar-day {
        min-height: 60px;
        padding: 2px;
    }
    
    .calendar-day-number {
        font-size: 0.8rem;
    }
    
    .calendar-event {
        font-size: 0.6rem;
    }
}
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="fas fa-building"></i>
                Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Super Admin</div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Resident Management</div>
                <a href="resident-profiling.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    Resident Profiling
                </a>
                
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Service Management</div>
                <a href="community_service.php" class="nav-item">
                    <i class="fas fa-hands-helping"></i>
                    Community Service
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Announcements
                </a>
                <a href="complaints.php" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints
                    <?php if ($stats['pending_complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="certificates.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Certificates
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="account_management.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    Account Management
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
            </div>
        </div>

        <div class="logout-section">
            <form action="../logout.php" method="POST" id="logoutForm" style="width: 100%;">
                <button type="button" class="logout-btn" onclick="handleLogout()">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </form>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Welcome back, <?php echo $_SESSION['full_name']; ?>!</h1>
                <p class="dashboard-subtitle">Here's what's happening in your barangay today</p>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_complaints']; ?></h3>
                        <p>Pending Complaints</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_appointments']; ?></h3>
                        <p>Pending Appointments</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #27ae60;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_residents']; ?></h3>
                        <p>Total Residents</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #3498db;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_complaints'] + $stats['total_appointments']; ?></h3>
                        <p>Total Records</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Layout -->
            <div class="dashboard-layout">
                <div class="left-section">
                    <!-- Recent Activities -->
                    <div class="recent-activities">
                        <div class="recent-card">
                            <div class="recent-header">
                                <h3 class="recent-title">
                                    <i class="fas fa-exclamation-triangle" style="color: #e74c3c; margin-right: 0.5rem;"></i>
                                    Recent Complaints
                                </h3>
                            </div>
                            <div class="recent-list">
                                <?php if (mysqli_num_rows($recent_complaints) > 0): ?>
                                    <?php while ($complaint = mysqli_fetch_assoc($recent_complaints)): ?>
                                        <div class="recent-item">
                                            <div class="recent-info">
                                                <h4><?php echo htmlspecialchars($complaint['nature_of_complaint']); ?></h4>
                                                <p>by <?php echo htmlspecialchars($complaint['full_name'] ?? 'Unknown'); ?> • <?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></p>
                                            </div>
                                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                <?php echo ucfirst($complaint['status']); ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="recent-item">
                                        <div class="recent-info">
                                            <p style="text-align: center; color: #666;">No complaints found</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="recent-card">
                            <div class="recent-header">
                                <h3 class="recent-title">
                                    <i class="fas fa-bullhorn" style="color: #f39c12; margin-right: 0.5rem;"></i>
                                    Recent Announcements
                                </h3>
                            </div>
                            <div class="recent-list">
                                <?php if (mysqli_num_rows($recent_announcements) > 0): ?>
                                    <?php while ($announcement = mysqli_fetch_assoc($recent_announcements)): ?>
                                        <div class="recent-item">
                                            <div class="recent-info">
                                                <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                                <p>by <?php echo htmlspecialchars($announcement['created_by_name'] ?? 'Unknown'); ?> • <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></p>
                                            </div>
                                            <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                                <?php echo ucfirst($announcement['priority']); ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="recent-item">
                                        <div class="recent-info">
                                            <p style="text-align: center; color: #666;">No announcements found</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Section - Calendar -->
                <div class="right-section">
                    <div class="calendar-widget">
                        <div class="calendar-header">
                            <div class="calendar-title">
                                <i class="fas fa-calendar-alt"></i>
                                Event Calendar
                            </div>
                            <div class="calendar-nav">
                                <button onclick="previousMonth()" id="prevMonth">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span id="currentMonth"></span>
                                <button onclick="nextMonth()" id="nextMonth">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="calendar-grid">
                            <div class="calendar-days-header">
                                <div class="calendar-day-header">SUN</div>
                                <div class="calendar-day-header">MON</div>
                                <div class="calendar-day-header">TUE</div>
                                <div class="calendar-day-header">WED</div>
                                <div class="calendar-day-header">THU</div>
                                <div class="calendar-day-header">FRI</div>
                                <div class="calendar-day-header">SAT</div>
                            </div>
                            <div class="calendar-days-grid" id="calendarDays"></div>
                        </div>
                    </div>

                    <div class="upcoming-events">
                        <div class="upcoming-header">
                            <h3 class="upcoming-title">
                                <i class="fas fa-clock"></i>
                                Upcoming Events
                            </h3>
                        </div>
                        <div class="upcoming-list">
                            <?php 
                            $events_data = [];
                            mysqli_data_seek($calendar_events, 0);
                            if (mysqli_num_rows($calendar_events) > 0): 
                                while ($event = mysqli_fetch_assoc($calendar_events)): 
                                    $events_data[] = $event;
                            ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-date">
                                        <div class="upcoming-date-day"><?php echo date('j', strtotime($event['event_date'])); ?></div>
                                        <div class="upcoming-date-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                                    </div>
                                    <div class="upcoming-info">
                                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                        <p>
                                            <?php if ($event['time']): ?>
                                                <?php echo date('g:i A', strtotime($event['time'])); ?> • 
                                            <?php endif; ?>
                                            <?php if ($event['type'] == 'appointment'): ?>
                                                with <?php echo htmlspecialchars($event['resident_name'] ?? 'Unknown'); ?>
                                            <?php else: ?>
                                                by <?php echo htmlspecialchars($event['resident_name'] ?? 'Unknown'); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="upcoming-type <?php echo $event['type']; ?>">
                                        <?php echo ucfirst($event['type']); ?>
                                    </span>
                                </div>
                            <?php 
                                endwhile; 
                            else: 
                            ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-info">
                                        <p style="text-align: center; color: #666; padding: 2rem;">No upcoming events</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calendar functionality
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        // Events data from PHP
        const eventsData = <?php echo json_encode($events_data ?? []); ?>;

        function renderCalendar() {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            
            document.getElementById('currentMonth').textContent = `${monthNames[currentMonth]} ${currentYear}`;
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
            
            let calendarHTML = '';
            
            // Previous month's trailing days
            for (let i = firstDay - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                calendarHTML += `<div class="calendar-day other-month">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
            }
            
            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(currentYear, currentMonth, day);
                const dateString = date.toISOString().split('T')[0];
                const isToday = date.toDateString() === new Date().toDateString();
                
                // Get events for this day
                const dayEvents = eventsData.filter(event => event.event_date === dateString);
                
                let eventsHTML = '';
                dayEvents.slice(0, 2).forEach(event => { // Limit to 2 events per day
                    eventsHTML += `<div class="calendar-event" title="${event.title}">${event.title}</div>`;
                });
                
                if (dayEvents.length > 2) {
                    eventsHTML += `<div class="calendar-event" style="font-style: italic; color: #999;">+${dayEvents.length - 2} more</div>`;
                }
                
                calendarHTML += `<div class="calendar-day ${isToday ? 'today' : ''}">
                    <div class="calendar-day-number">${day}</div>
                    ${eventsHTML}
                </div>`;
            }
            
            // Next month's leading days
            const totalCells = firstDay + daysInMonth;
            const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let day = 1; day <= remainingCells; day++) {
                calendarHTML += `<div class="calendar-day other-month">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
            }
            
            document.getElementById('calendarDays').innerHTML = calendarHTML;
        }

        function previousMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        }

        function nextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function handleLogout() {
            if (confirm('Are you sure you want to logout?')) {
                document.getElementById('logoutForm').submit();
            }
        }

        // Initialize calendar on page load
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
            
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href === currentPage) {
                    item.classList.add('active');
                }
            });
        });

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });
    </script>
</body>
</html>