<?php
session_start();
require_once '../config.php';

// Handle volunteer signup from resident (supports AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'volunteer_signup') {
    // Determine resident id from session
    $resident_id = isset($_SESSION['resident_id']) ? $_SESSION['resident_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

    if (!$resident_id) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'code' => 'not_logged_in', 'message' => 'You must be logged in to volunteer.']);
            exit();
        }
        header('Location: announcements.php?vol_status=not_logged_in');
        exit();
    }

    $announcement_id = intval($_POST['announcement_id']);

    // Check if announcement exists and is active
    $check_q = "SELECT id, max_volunteers, event_date FROM announcements WHERE id = $announcement_id AND status = 'active' AND announcement_type = 'event'";
    $check_r = mysqli_query($connection, $check_q);
    if (!$check_r || mysqli_num_rows($check_r) === 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'code' => 'invalid_event', 'message' => 'The selected event does not exist or is not active.']);
            exit();
        }
        header('Location: announcements.php?vol_status=invalid_event');
        exit();
    }
    $ann = mysqli_fetch_assoc($check_r);

    // Prevent duplicate signup
    $dup_q = "SELECT id FROM community_volunteers WHERE resident_id = $resident_id AND announcement_id = $announcement_id";
    $dup_r = mysqli_query($connection, $dup_q);
    if ($dup_r && mysqli_num_rows($dup_r) > 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'code' => 'already_signed', 'message' => 'You have already requested to volunteer for this event.']);
            exit();
        }
        header('Location: announcements.php?vol_status=already_signed');
        exit();
    }

    // Check capacity if max_volunteers is set
    if (!empty($ann['max_volunteers'])) {
        $count_q = "SELECT COUNT(*) as c FROM community_volunteers WHERE announcement_id = $announcement_id AND status = 'approved'";
        $count_r = mysqli_query($connection, $count_q);
        $c = $count_r ? intval(mysqli_fetch_assoc($count_r)['c']) : 0;
        if ($c >= intval($ann['max_volunteers'])) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'code' => 'full', 'message' => 'This event is already full.']);
                exit();
            }
            header('Location: announcements.php?vol_status=full');
            exit();
        }
    }

    // Insert volunteer signup as pending
    $resident_id = intval($resident_id);
    $ins_q = "INSERT INTO community_volunteers (resident_id, announcement_id, status, created_at) VALUES ($resident_id, $announcement_id, 'pending', NOW())";
    if (mysqli_query($connection, $ins_q)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'code' => 'success', 'message' => 'Volunteer request submitted. You will be notified when it is approved.']);
            exit();
        }
        header('Location: announcements.php?vol_status=success');
        exit();
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'code' => 'error', 'message' => 'Unable to submit volunteer request. Please try again later.']);
            exit();
        }
        header('Location: announcements.php?vol_status=error');
        exit();
    }
}

// Get upcoming events (announcements with event type)
$events_query = "SELECT 
    a.id,
    a.event_date,
    a.event_time,
    a.title,
    a.content,
    a.location,
    a.status,
    a.priority,
    a.needs_volunteers,
    u.full_name as created_by_name,
    COUNT(cv.id) as volunteer_count,
    a.max_volunteers
FROM announcements a 
LEFT JOIN users u ON a.created_by = u.id 
LEFT JOIN community_volunteers cv ON cv.announcement_id = a.id AND cv.status = 'approved'
WHERE a.announcement_type = 'event' 
AND a.status = 'active'
AND a.event_date >= CURDATE() 
AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
GROUP BY a.id
ORDER BY a.event_date ASC, a.event_time ASC";

$result = mysqli_query($connection, $events_query);
$events = mysqli_fetch_all($result, MYSQLI_ASSOC);

// If there are no upcoming events, fall back to recent past events so residents still see events
$fallback_showing_past = false;
if (empty($events)) {
    $past_query = "SELECT 
        a.id,
        a.event_date,
        a.event_time,
        a.title,
        a.content,
        a.location,
        a.status,
        a.needs_volunteers,
        a.priority,
        u.full_name as created_by_name,
        COUNT(cv.id) as volunteer_count,
        a.max_volunteers
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    LEFT JOIN community_volunteers cv ON cv.announcement_id = a.id AND cv.status = 'approved'
    WHERE a.announcement_type = 'event' 
    AND a.status = 'active'
    AND a.event_date < CURDATE()
    AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
    GROUP BY a.id
    ORDER BY a.event_date DESC, a.event_time DESC
    LIMIT 5";

    $result = mysqli_query($connection, $past_query);
    $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
    if (!empty($events)) {
        $fallback_showing_past = true;
    }
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
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-body { 
            padding: 1.5rem;
            background: linear-gradient(to bottom, #ffffff, #fafafa);
        }
        
        .event-list { 
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .event-item {
            display: flex;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .event-item:hover {
            background: rgba(74, 71, 163, 0.02);
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: transparent;
            transition: background-color 0.3s ease;
        }
        
        .event-item:hover::before {
            background: #4a47a3;
        }
        
        .event-date {
            min-width: 100px;
            text-align: center;
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
            border-radius: 12px;
            padding: 1rem 0.75rem;
            color: white;
            box-shadow: 0 4px 15px rgba(74, 71, 163, 0.15);
            transition: transform 0.2s ease;
        }
        
        .event-item:hover .event-date {
            transform: translateY(-2px);
        }
        
        .event-date .day {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 0.25rem;
            text-shadow: 1px 1px 0 rgba(0,0,0,0.1);
        }
        
        .event-date .month {
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            opacity: 0.9;
        }
        
        .event-info h3 {
            margin: 0 0 0.75rem 0;
            font-size: 1.25rem;
            color: #1a1a1a;
            font-weight: 600;
            line-height: 1.3;
            transition: color 0.2s ease;
        }
        
        .event-item:hover .event-info h3 {
            color: #4a47a3;
        }
        
        .event-meta {
            color: #666;
            font-size: 0.95rem;
            display: flex;
            gap: 1.25rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 0.75rem;
            line-height: 1.6;
        }
        
        .event-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: rgba(74, 71, 163, 0.05);
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .event-meta span:hover {
            background: rgba(74, 71, 163, 0.1);
            transform: translateY(-1px);
        }
        
        .event-meta i {
            color: #4a47a3;
            font-size: 1rem;
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
        
        /* Priority Badges */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            animation: fadeIn 0.3s ease;
        }
        
        .priority-high {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid rgba(185, 28, 28, 0.1);
        }
        
        .priority-medium {
            background: #fff7e6;
            color: #b45309;
            border: 1px solid rgba(180, 83, 9, 0.1);
        }
        
        .priority-low {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid rgba(4, 120, 87, 0.1);
        }
        
        /* Volunteer Button Styles */
        .btn-volunteer {
            background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%) !important;
            color: white !important;
            border: none !important;
            padding: 0.75rem 1.25rem !important;
            border-radius: 8px !important;
            cursor: pointer;
            font-size: 0.95rem !important;
            font-weight: 500;
            display: inline-flex !important;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 15px rgba(74, 71, 163, 0.15) !important;
        }
        
        .btn-volunteer:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(74, 71, 163, 0.25) !important;
        }
        
        .btn-volunteer:active {
            transform: translateY(0);
        }
        
        .btn-volunteer i {
            font-size: 1rem;
        }
        
        .event-full-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            box-shadow: 0 2px 8px rgba(185, 28, 28, 0.1);
        }
        
        .login-to-volunteer {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: #f3f4f6;
            color: #1f2937;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .login-to-volunteer:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }
        
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
            background: linear-gradient(to bottom, rgba(74, 71, 163, 0.03), rgba(74, 71, 163, 0.01));
            border-radius: 12px;
            margin: 2rem 0;
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

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .event-item {
            animation: fadeIn 0.3s ease backwards;
        }
        
        .event-item:nth-child(1) { animation-delay: 0.1s; }
        .event-item:nth-child(2) { animation-delay: 0.2s; }
        .event-item:nth-child(3) { animation-delay: 0.3s; }
        .event-item:nth-child(4) { animation-delay: 0.4s; }
        .event-item:nth-child(5) { animation-delay: 0.5s; }
        
        /* Loading State */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001;
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
                gap: 1rem;
                padding: 1.25rem;
            }

            .event-date {
                align-self: flex-start;
                width: 120px;
            }
            
            .event-meta {
                gap: 0.75rem;
            }
            
            .event-meta span {
                font-size: 0.9rem;
                padding: 0.2rem 0.4rem;
            }
            
            .btn-volunteer, .login-to-volunteer, .event-full-badge {
                width: 100%;
                justify-content: center;
                margin-top: 1rem;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .dashboard-subtitle {
                font-size: 1rem;
            }
            
            .priority-badge {
                margin-bottom: 0.5rem;
                display: inline-flex;
            }
        }
        
        /* Tablet Responsive Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .event-meta {
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            
            .event-item {
                padding: 1.25rem;
            }
            
            .content-area {
                padding: 1.5rem;
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

        /* Toast Notifications */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-item {
            padding: 12px 24px;
            border-radius: 4px;
            margin-bottom: 10px;
            color: white;
            font-size: 14px;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.3s forwards, fadeOut 0.5s 2.5s forwards;
        }
        
        .toast-item.success {
            background-color: #2ecc71;
        }
        
        .toast-item.error {
            background-color: #e74c3c;
        }
        
        .toast-item.info {
            background-color: #3498db;
        }
        
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-100%);
            }
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

        /* Toast Notifications */
        #toastContainer {
            position: fixed;
            right: 20px;
            top: 20px;
            z-index: 2000;
        }

        .toast-item {
            margin-top: 8px;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            color: #0f172a;
            background: #f8fafc;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .toast-item.success {
            color: #064e3b;
            background: #ecfdf5;
        }

        .toast-item.error {
            color: #7f1d1d;
            background: #fee2e2;
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
                        <?php if (!empty($fallback_showing_past) && $fallback_showing_past): ?>
                            <div class="notice-banner">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Showing Recent Past Events</strong>
                                    <p>There are no upcoming events at the moment. Keep an eye out for newly scheduled activities.</p>
                                </div>
                            </div>
                            <style>
                                .notice-banner {
                                    padding: 1rem 1.5rem;
                                    background: linear-gradient(to right, #fff7e6, #fff9ee);
                                    border: 1px solid #ffe7b5;
                                    border-radius: 12px;
                                    margin: 1.5rem;
                                    color: #7a4b00;
                                    display: flex;
                                    align-items: flex-start;
                                    gap: 1rem;
                                    animation: slideIn 0.3s ease;
                                }
                                .notice-banner i {
                                    font-size: 1.5rem;
                                    color: #f59e0b;
                                }
                                .notice-banner strong {
                                    display: block;
                                    margin-bottom: 0.25rem;
                                }
                                .notice-banner p {
                                    margin: 0;
                                    font-size: 0.95rem;
                                    opacity: 0.9;
                                }
                            </style>
                        <?php endif; ?>
                        <ul class="event-list">
                            <?php foreach ($events as $ev): ?>
                                <li class="event-item">
                                    <div class="event-date">
                                        <?php $d = strtotime($ev['event_date']); ?>
                                        <div class="day"><?php echo date('j', $d); ?></div>
                                        <div class="month"><?php echo date('M', $d); ?></div>
                                    </div>
                                    <div class="event-info">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                                            <div style="flex: 1;">
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

                                <div class="event-meta" style="margin-top: 0.5rem;">
                                    <?php if (!empty($ev['event_time'])): ?>
                                        <span><i class="far fa-clock"></i> <?php echo htmlspecialchars($ev['event_time']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['location'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($ev['created_by_name'] ?? 'Admin'); ?></span>
                                    
                                    <?php if ($ev['needs_volunteers']): ?>
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
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($ev['needs_volunteers']): ?>
                            <div style="display: flex; align-items: center;">
                                <?php
                                $is_upcoming = strtotime($ev['event_date']) >= strtotime(date('Y-m-d'));
                                $is_full = !empty($ev['max_volunteers']) && intval($ev['volunteer_count']) >= intval($ev['max_volunteers']);
                                $is_logged_in = isset($_SESSION['resident_id']) || isset($_SESSION['user_id']);
                                $user_request = null;
                                
                                if ($is_logged_in) {
                                    $resident_id_sess = isset($_SESSION['resident_id']) ? intval($_SESSION['resident_id']) : intval($_SESSION['user_id']);
                                    $rq_q = "SELECT id, status FROM community_volunteers WHERE resident_id = $resident_id_sess AND announcement_id = " . intval($ev['id']) . " LIMIT 1";
                                    $rq_r = mysqli_query($connection, $rq_q);
                                    if ($rq_r && mysqli_num_rows($rq_r) > 0) {
                                        $user_request = mysqli_fetch_assoc($rq_r);
                                    }
                                    
                                    if (!$user_request && $is_upcoming && !$is_full) {
                                        ?>
                                        <form class="volunteer-form" method="post" style="margin: 0;">
                                            <input type="hidden" name="action" value="volunteer_signup">
                                            <input type="hidden" name="announcement_id" value="<?php echo htmlspecialchars($ev['id']); ?>">
                                            <button type="submit" class="btn btn-primary btn-volunteer">
                                                Request as Volunteer
                                            </button>
                                        </form>
                                        <?php
                                    } elseif ($user_request) {
                                        ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.7; cursor: default;">
                                            <?php echo $user_request['status'] === 'pending' ? 'Request Pending' : 'Already Volunteered'; ?>
                                        </button>
                                        <?php
                                    } elseif ($is_full) {
                                        ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.7; cursor: default;">
                                            Event Full
                                        </button>
                                        <?php
                                    } elseif (!$is_upcoming) {
                                        ?>
                                        <button class="btn btn-secondary" disabled style="opacity: 0.7; cursor: default;">
                                            Event Passed
                                        </button>
                                        <?php
                                    }
                                } else {
                                    ?>
                                    <a href="../index.php?redirect=resident/announcements.php" class="btn btn-primary">
                                        Login to Volunteer
                                    </a>
                                    <?php
                                }
                                
                                if ($user_request && $is_upcoming) {
                                    $statusBadge = '';
                                    if ($user_request['status'] === 'pending') {
                                        $statusBadge = '<span class="event-full-badge" style="margin-left:0.5rem;"><i class="fas fa-clock"></i> Requested (Pending)</span>';
                                    } elseif ($user_request['status'] === 'approved') {
                                        $statusBadge = '<span class="event-full-badge" style="margin-left:0.5rem;background:#ecfdf5;color:#065f46;"><i class="fas fa-check-circle"></i> Approved</span>';
                                    } elseif ($user_request['status'] === 'rejected') {
                                        $statusBadge = '<span class="event-full-badge" style="margin-left:0.5rem;background:#fff5f5;color:#7f1d1d;"><i class="fas fa-times-circle"></i> Rejected</span>';
                                    }
                                    echo $statusBadge;
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($ev['content'])): ?>
                                <p style="margin-top:0.75rem;color:#555;line-height:1.5;">
                                    <?php echo nl2br(htmlspecialchars($ev['content'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
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
        
        

        function submitVolunteer(announcementId, form = null) {
            console.debug('submitVolunteer called for', announcementId);
            showToast('Sending request...', 'info');
            const xhr = new XMLHttpRequest();
            const params = 'action=volunteer_signup&ajax=1&announcement_id=' + encodeURIComponent(announcementId);
            // Use pathname to avoid injecting querystrings
            const target = window.location.pathname;
            console.debug('POST target:', target, 'params:', params);
            xhr.open('POST', target, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;
                console.debug('XHR status', xhr.status, 'response', xhr.responseText);
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.status === 'ok') {
                        showToast(res.message || 'Request submitted', 'success');
                        // disable the button(s) for this announcement
                        disableVolunteerButtons(announcementId);
                    } else {
                        showToast(res.message || 'Unable to submit', 'error');
                        if (res.code === 'not_logged_in') {
                            // redirect to login after short delay
                            setTimeout(() => { window.location.href = '../index.php?redirect=resident/announcements.php'; }, 1200);
                        }
                    }
                } catch (err) {
                    console.error('Error parsing response', err);
                    showToast('Unexpected response from server', 'error');
                }
            };
            xhr.onerror = function(e) {
                console.error('XHR error', e);
                showToast('Network error while sending request', 'error');
            };
            xhr.send(params);
        }

        function disableVolunteerButtons(announcementId) {
            const forms = document.querySelectorAll('form input[name="announcement_id"][value="' + announcementId + '"]');
            forms.forEach(inp => {
                const f = inp.closest('form');
                if (!f) return;
                const btn = f.querySelector('.btn-volunteer');
                    if (btn) {
                    btn.disabled = true;
                    btn.innerText = 'Requested as Volunteer';
                    btn.setAttribute('aria-disabled', 'true');
                    btn.style.opacity = '0.7';
                    btn.style.cursor = 'default';
                }
            });
        }

        // Initialize volunteer forms
        function initializeVolunteerForms() {
            document.querySelectorAll('form.volunteer-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const announcementId = this.querySelector('input[name="announcement_id"]').value;
                    submitVolunteer(announcementId, this);
                });
            });
        }

        // Call initialization when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeVolunteerForms();
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
+
        function showToast(message, type = 'info') {
            const containerId = 'toastContainer';
            let container = document.getElementById(containerId);
            if (!container) {
                container = document.createElement('div');
                container.id = containerId;
                container.style.position = 'fixed';
                container.style.right = '20px';
                container.style.top = '20px';
                container.style.zIndex = '2000';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = `toast-item ${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-message">${message}</span>
                </div>
            `;

            container.appendChild(toast);
            
            // Remove the toast after 3 seconds
            setTimeout(() => {
                toast.remove();
            }, 3000);
+            toast.style.background = type === 'error' ? '#fee2e2' : (type === 'success' ? '#ecfdf5' : '#f8fafc');
+            toast.innerText = message;
+
+            container.appendChild(toast);
+            setTimeout(() => {
+                toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
+                toast.style.opacity = '0';
+                toast.style.transform = 'translateY(-8px)';
+                setTimeout(() => toast.remove(), 350);
+            }, 3500);
+        }
+
+        document.addEventListener('click', function(e) {
+            const target = e.target.closest('.btn-volunteer');
+            if (!target) return;
+            e.preventDefault();
+
+            // Find the form or announcement id nearby
+            let form = target.closest('form');
+            if (!form) {
+                // try to find hidden input
+                const annId = target.getAttribute('data-ann-id');
+                if (!annId) return;
+                // create a small payload
+                submitVolunteer(annId);
+                return;
+            }
+
+            const annInput = form.querySelector('input[name="announcement_id"]');
+            if (!annInput) return;
+            const annId = annInput.value;
+            submitVolunteer(annId, form);
+        });
+
+        function submitVolunteer(announcementId, form = null) {
+            const xhr = new XMLHttpRequest();
+            const params = 'action=volunteer_signup&ajax=1&announcement_id=' + encodeURIComponent(announcementId);
+            xhr.open('POST', window.location.href, true);
+            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
+            xhr.onreadystatechange = function() {
+                if (xhr.readyState !== 4) return;
+                try {
+                    const res = JSON.parse(xhr.responseText);
+                    if (res.status === 'ok') {
+                        showToast(res.message || 'Request submitted', 'success');
+                        // disable the button(s) for this announcement
+                        disableVolunteerButtons(announcementId);
+                    } else {
+                        showToast(res.message || 'Unable to submit', 'error');
+                        if (res.code === 'not_logged_in') {
+                            // redirect to login after short delay
+                            setTimeout(() => { window.location.href = '../index.php?redirect=resident/announcements.php'; }, 1200);
+                        }
+                    }
+                } catch (err) {
+                    showToast('Unexpected response from server', 'error');
+                }
+            };
+            xhr.send(params);
+        }
+
+        function disableVolunteerButtons(announcementId) {
+            const forms = document.querySelectorAll('form input[name="announcement_id"][value="' + announcementId + '"]');
+            forms.forEach(inp => {
+                const f = inp.closest('form');
+                if (!f) return;
+                const btn = f.querySelector('.btn-volunteer');
+                if (btn) {
+                    btn.disabled = true;
+                    btn.innerText = 'Requested';
+                    btn.style.opacity = '0.7';
+                    btn.style.cursor = 'default';
+                }
+            });
+        }
    </script>
</body>
</html>
