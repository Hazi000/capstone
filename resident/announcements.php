<?php
session_start();
require_once '../config.php';

// Handle volunteer signup from resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'volunteer_signup') {
    $resident_id = isset($_SESSION['resident_id']) ? intval($_SESSION['resident_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null);
    
    if (!$resident_id) {
        echo "<script>alert('Please log in first.'); window.location.href='announcements.php';</script>";
        exit();
    }

    $announcement_id = isset($_POST['announcement_id']) ? intval($_POST['announcement_id']) : 0;
    
    // Check for existing signup
    $check_signup = mysqli_query($connection, "SELECT status FROM community_volunteers 
        WHERE resident_id = $resident_id AND announcement_id = $announcement_id");
    
    if (mysqli_num_rows($check_signup) > 0) {
        $status = mysqli_fetch_assoc($check_signup)['status'];
        echo "<script>alert('You have already " . ($status == 'pending' ? 'requested to volunteer' : 'volunteered') . " for this event.'); 
            window.location.href='announcements.php';</script>";
        exit();
    }

    // Insert new volunteer signup
    $insert_query = "INSERT INTO community_volunteers (resident_id, announcement_id, status, created_at) 
                    VALUES ($resident_id, $announcement_id, 'pending', NOW())";
    
    if (mysqli_query($connection, $insert_query)) {
        echo "<script>alert('Volunteer request submitted successfully!'); window.location.href='announcements.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error submitting volunteer request. Please try again.'); window.location.href='announcements.php';</script>";
        exit();
    }
}

// Modify events query to properly check volunteer status
$current_user_id = isset($_SESSION['resident_id']) ? intval($_SESSION['resident_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);

$events_query = "SELECT 
    a.*,
    u.full_name as created_by_name,
    COUNT(DISTINCT cv.id) as volunteer_count,
    cv_user.status as user_volunteer_status
FROM announcements a 
LEFT JOIN users u ON a.created_by = u.id 
LEFT JOIN community_volunteers cv ON cv.announcement_id = a.id 
LEFT JOIN community_volunteers cv_user ON cv_user.announcement_id = a.id 
    AND cv_user.resident_id = $current_user_id
WHERE a.announcement_type = 'event' 
AND a.status = 'active'
AND a.needs_volunteers = 1
AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
GROUP BY a.id";

$result = mysqli_query($connection, $events_query);
$events = [];
if ($result) {
    $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - Barangay Cawit</title>
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

        /* Events Card Styles */
        .container { 
            margin: 0 auto;
            padding: 1rem;
        }
        
        .card { 
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-body { padding: 1.5rem; }
        
        .event-list { 
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .event-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.3s ease;
            position: relative;
        }
        
        .event-item:hover {
            background: #f8f9fa;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-date {
            min-width: 90px;
            text-align: center;
            background: #fff7e6;
            border-radius: 8px;
            padding: 0.5rem;
        }
        
        .event-date .day {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f39c12;
        }
        
        .event-info h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            color: #333;
        }
        
        .event-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .event-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .event-info h3 {
            color: #333;
            font-size: 1.2rem;
            margin-right: 1rem;
        }
        
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
            background: linear-gradient(to bottom, rgba(74, 71, 163, 0.03), rgba(74, 71, 163, 0.01));
            border-radius: 12px;
        }
        
        .empty i {
            font-size: 4rem;
            color: rgba(74, 71, 163, 0.2);
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite ease-in-out;
        }
        
        .empty p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 0.8; }
        }

        /* Mobile Responsiveness */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
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

            .event-item {
                flex-direction: column;
            }

            .event-date {
                align-self: flex-start;
            }
        }

        /* Sidebar Overlay */
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

        /* Logout Section */
        .logout-section {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
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

        /* Add or update these styles in the <style> section */
.event-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-left: auto;
    gap: 1rem;
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.event-info {
    position: relative;
    padding-right: 200px; /* Make space for the button */
    flex: 1;
}

.btn-volunteer {
    background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(74, 71, 163, 0.2);
    white-space: nowrap;
}

.btn-volunteer:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(74, 71, 163, 0.3);
    background: linear-gradient(135deg, #5552b5 0%, #4a4799 100%);
}

.btn-login {
    background: white;
    color: #4a47a3;
    border: 2px solid #4a47a3;
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.volunteer-status {
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    font-weight: 600;
}

.volunteer-status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.volunteer-status.approved {
    background: #ecfdf5;
    color: #047857;
}

.volunteer-status.full {
    background: #fee2e2;
    color: #b91c1c;
}

        /* Sidebar Overlay */
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

        /* Logout Section */
        .logout-section {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-shrink: 0;
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

        /* Add these new button styles */
        .event-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid #eee;
        }

        .btn-volunteer {
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(74, 71, 163, 0.2);
        }

        .btn-volunteer:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(74, 71, 163, 0.3);
            background: linear-gradient(135deg, #5552b5 0%, #4a4799 100%);
        }

        .btn-volunteer:active {
            transform: translateY(0);
        }

        .btn-volunteer.disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-login {
            background: white;
            color: #4a47a3;
            border: 2px solid #4a47a3;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #4a47a3;
            color: white;
        }

        .volunteer-status {
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 600;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
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
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Resident'); ?></div>
                <div class="user-role">Resident</div>
            </div>
        </div>

        <div class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main Menu</div>
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="announcements.php" class="nav-item active">
                    <i class="fas fa-bullhorn"></i>
                    Announcements
                </a>
                <a href="request-certificate.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Request Certificate
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Announcements</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Upcoming Events</h1>
                <p class="dashboard-subtitle">Stay updated with our community events and activities</p>
            </div>

            <div class="container">
                <div class="card">
                    <?php if (empty($events)): ?>
                        <div class="empty">
                            <i class="fas fa-calendar-times"></i>
                            <p>No events available at this time</p>
                            <p style="color: #888; font-size: 0.9rem; margin-top: 0.5rem;">
                                Check back later for new events and activities
                            </p>
                        </div>
                    <?php else: ?>
                        <ul class="event-list">
                            <?php foreach ($events as $ev): ?>
                                <li class="event-item">
                                    <div class="event-date">
                                        <?php $d = strtotime($ev['event_date']); ?>
                                        <div class="day"><?php echo date('j', $d); ?></div>
                                        <div class="month"><?php echo date('M', $d); ?></div>
                                    </div>
                                    <div class="event-info">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
                                            <?php
                            $priorityClass = '';
                            $priorityText = '';
                            switch($ev['priority']) {
                                case 'high':
                                    $priorityClass = 'priority-high';
                                    $priorityText = 'Important';
                                    break;
                                case 'medium':
                                    $priorityClass = 'priority-medium';
                                    $priorityText = 'Medium Priority';
                                    break;
                                case 'low':
                                    $priorityClass = 'priority-low';
                                    $priorityText = 'Low Priority';
                                    break;
                            }
                            if (!empty($priorityClass)): ?>
                                <span class="priority-badge <?php echo $priorityClass; ?>">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $priorityText; ?>
                                </span>
                            <?php endif; ?>
                                        </div>
                                        <div class="event-meta">
                                            <?php if (!empty($ev['event_time'])): ?>
                                                <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($ev['event_time']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($ev['location'])): ?>
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($ev['created_by_name'] ?? 'Admin'); ?></span>
                                            <?php // Show volunteer count for all events ?>
                                                <?php if (!empty($ev['max_volunteers'])): ?>
                                                    <span class="volunteer-count">
                                                        <i class="fas fa-users"></i>
                                                        Volunteers: <?php echo $ev['volunteer_count']; ?>/<?php echo $ev['max_volunteers']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="volunteer-count">
                                                        <i class="fas fa-users"></i>
                                                        Volunteers: <?php echo $ev['volunteer_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                        </div>
                                        <?php if (!empty($ev['content'])): ?>
                                            <p style="margin-top:0.75rem;color:#555;line-height:1.5;">
                                                <?php echo nl2br(htmlspecialchars($ev['content'])); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="event-actions">
                                            <?php
                                            $is_full = !empty($ev['max_volunteers']) && intval($ev['volunteer_count']) >= intval($ev['max_volunteers']);
                                            $is_logged_in = isset($_SESSION['resident_id']) || isset($_SESSION['user_id']);
                                            $has_volunteered = isset($ev['user_volunteer_status']);

                                            if ($has_volunteered): ?>
                                                <span class="volunteer-status <?php echo $ev['user_volunteer_status']; ?>">
                                                    <i class="fas <?php echo $ev['user_volunteer_status'] === 'pending' ? 'fa-clock' : 'fa-check'; ?>"></i>
                                                    <?php echo $ev['user_volunteer_status'] === 'pending' ? 'Request Pending' : 'Already Volunteering'; ?>
                                                </span>
                                            <?php elseif ($is_full): ?>
                                                <span class="volunteer-status full">
                                                    <i class="fas fa-users-slash"></i> Event Full
                                                </span>
                                            <?php elseif ($is_logged_in): ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="volunteer_signup">
                                                    <input type="hidden" name="announcement_id" value="<?php echo intval($ev['id']); ?>">
                                                    <button type="submit" class="btn-volunteer" onclick="return confirm('Are you sure you want to volunteer for this event?');">
                                                        <i class="fas fa-hands-helping"></i> Volunteer Now
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="../index.php?redirect=resident/announcements.php" class="btn-login">
                                                    <i class="fas fa-sign-in-alt"></i> Login to Join
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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
