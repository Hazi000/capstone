<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a resident
//if (!isset($_SESSION['resident_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    //header("Location: ../index.php");
  //  exit();
//}

$resident_id = $_SESSION['resident_id'];

// Get dashboard statistics for resident
$stats = [];

// Count total certificate requests by resident
$certificate_query = "SELECT COUNT(*) as total FROM certificate_requests WHERE resident_id = ?";
$stmt = mysqli_prepare($connection, $certificate_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['my_certificates'] = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Count pending certificate requests by resident
$pending_certificate_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_certificate_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
mysqli_stmt_close($stmt);

// Get recent announcements
$recent_announcements_query = "SELECT a.*, u.full_name as created_by_name 
                              FROM announcements a 
                              LEFT JOIN users u ON a.created_by = u.id 
                              WHERE a.status = 'active' 
                              AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                              ORDER BY a.created_at DESC 
                              LIMIT 5";
$recent_announcements = mysqli_query($connection, $recent_announcements_query);

// Get recent certificate requests by resident
$recent_certificates_query = "SELECT * FROM certificate_requests WHERE resident_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = mysqli_prepare($connection, $recent_certificates_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$recent_certificates = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get upcoming events (use events table instead of announcements)
$upcoming_events_query = "SELECT 
    'event' as type,
    e.id,
    e.event_start_date as event_date,
    e.event_time as time,
    e.title,
    e.status,
    u.full_name as created_by_name
FROM events e
LEFT JOIN users u ON e.created_by = u.id
WHERE e.event_start_date IS NOT NULL
  AND e.event_start_date >= CURDATE()
  AND e.event_start_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
  AND e.status IN ('upcoming','ongoing')
ORDER BY e.event_start_date ASC, e.event_time ASC
LIMIT 10";

$upcoming_events = mysqli_query($connection, $upcoming_events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Barangay Cawit</title>
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
            background: linear-gradient(180deg, #4a47a3 0%, #3a3782 100%);
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
            color: #ffd700;
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
            color: #ffd700;
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
            color: rgba(255,255,255,0.6);
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
            border-left-color: #ffd700;
        }

        .nav-item.active {
            background: rgba(255, 215, 0, 0.2);
            border-left-color: #ffd700;
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
            max-height: 350px;
            overflow-y: auto;
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

        .recent-info {
            flex: 1;
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

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
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

        /* Announcements Widget */
        .announcements-widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .announcements-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4a 100%);
            color: #333;
        }

        .announcements-title {
            font-size: 1.3rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .announcements-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .announcement-item {
            padding: 1.5rem;
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.3s ease;
        }

        .announcement-item:hover {
            background: #f8f9fa;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-title {
            font-size: 1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .announcement-content {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .announcement-meta {
            font-size: 0.8rem;
            color: #999;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Upcoming Events */
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
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
            color: white;
        }

        .upcoming-title {
            font-size: 1.2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upcoming-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .upcoming-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.3s ease;
        }

        .upcoming-item:hover {
            background: #f8f9fa;
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
            background: #fff3e0;
            color: #f57c00;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1rem;
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
                <i class="fas fa-home"></i>
                Barangay Cawit
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Resident</div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Events
                </a>
                
                <a href="request-certificate.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Request Certificate
                    <?php if ($stats['pending_certificates'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_certificates']; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>
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
                <p class="dashboard-subtitle">Manage your barangay services and stay updated with community news</p>
            </div>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #f39c12;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_certificates']; ?></h3>
                        <p>Pending Certificates</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #27ae60;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['my_certificates']; ?></h3>
                        <p>Total Certificates</p>
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
                                    <i class="fas fa-certificate" style="color: #3498db; margin-right: 0.5rem;"></i>
                                    My Certificate Requests
                                </h3>
                            </div>
                            <div class="recent-list">
                                <?php if (mysqli_num_rows($recent_certificates) > 0): ?>
                                    <?php while ($certificate = mysqli_fetch_assoc($recent_certificates)): ?>
                                        <div class="recent-item">
                                            <div class="recent-info">
                                                <h4><?php echo htmlspecialchars($certificate['certificate_type']); ?></h4>
                                                <p><?php echo date('M j, Y', strtotime($certificate['created_at'])); ?></p>
                                            </div>
                                            <span class="status-badge status-<?php echo $certificate['status']; ?>">
                                                <?php echo ucfirst($certificate['status']); ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-file"></i>
                                        <p>No certificate requests yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Section - Announcements -->
                <div class="right-section">
                    <div class="announcements-widget">
                        <div class="announcements-header">
                            <div class="announcements-title">
                                <i class="fas fa-bullhorn"></i>
                                Latest Announcements
                            </div>
                        </div>
                        <div class="announcements-list">
                            <?php if (mysqli_num_rows($recent_announcements) > 0): ?>
                                <?php while ($announcement = mysqli_fetch_assoc($recent_announcements)): ?>
                                    <div class="announcement-item">
                                        <h4 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                        <p class="announcement-content">
                                            <?php 
                                            $content = strip_tags($announcement['content']);
                                            echo htmlspecialchars(substr($content, 0, 150)) . (strlen($content) > 150 ? '...' : '');
                                            ?>
                                        </p>
                                        <div class="announcement-meta">
                                            <span><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-megaphone"></i>
                                    <p>No announcements at this time</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set active navigation item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Auto-refresh dashboard every 5 minutes
            setInterval(() => {
                location.reload();
            }, 300000);
        });

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarOverlay').classList.remove('active');
            }
        });
    </script>
</body>
</html>