<?php
session_start();
require_once '../config.php';

// Handle volunteer signup from resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'volunteer_signup') {
    $resident_id = isset($_SESSION['resident_id']) ? intval($_SESSION['resident_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null);
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

    // Basic validation
    if (empty($resident_id) || $event_id <= 0) {
        $_SESSION['error_message'] = "Invalid request. Please try again.";
        header("Location: announcements.php");
        exit();
    }

    // 1) Prevent duplicate registrations
    $check_sql = "SELECT status FROM volunteer_registrations WHERE resident_id = ? AND event_id = ? LIMIT 1";
    $check_stmt = mysqli_prepare($connection, $check_sql);
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $resident_id, $event_id);
        mysqli_stmt_execute($check_stmt);
        $check_res = mysqli_stmt_get_result($check_stmt);
        if ($row = mysqli_fetch_assoc($check_res)) {
            $status = $row['status'];
            $_SESSION['error_message'] = "You have already " . ($status === 'pending' ? 'requested to volunteer' : 'volunteered') . " for this event.";
            mysqli_stmt_close($check_stmt);
            header("Location: announcements.php");
            exit();
        }
        mysqli_stmt_close($check_stmt);
    }

    // 2) Ensure a volunteer_requests row exists for this event and set it to pending
    $request_id = null;
    $req_select_sql = "SELECT id FROM volunteer_requests WHERE event_id = ? LIMIT 1";
    $req_select_stmt = mysqli_prepare($connection, $req_select_sql);
    if ($req_select_stmt) {
        mysqli_stmt_bind_param($req_select_stmt, "i", $event_id);
        mysqli_stmt_execute($req_select_stmt);
        $req_res = mysqli_stmt_get_result($req_select_stmt);
        if ($req_row = mysqli_fetch_assoc($req_res)) {
            $request_id = intval($req_row['id']);
            // update status to pending (explicitly mark as pending when a resident requests)
            $req_update_sql = "UPDATE volunteer_requests SET status = 'pending' WHERE id = ?";
            $req_update_stmt = mysqli_prepare($connection, $req_update_sql);
            if ($req_update_stmt) {
                mysqli_stmt_bind_param($req_update_stmt, "i", $request_id);
                mysqli_stmt_execute($req_update_stmt);
                mysqli_stmt_close($req_update_stmt);
            }
        }
        mysqli_stmt_close($req_select_stmt);
    }

    // If no request exists, create one with status = pending
    if (empty($request_id)) {
        $req_insert_sql = "INSERT INTO volunteer_requests (event_id, status, created_at) VALUES (?, 'pending', NOW())";
        $req_insert_stmt = mysqli_prepare($connection, $req_insert_sql);
        if ($req_insert_stmt) {
            mysqli_stmt_bind_param($req_insert_stmt, "i", $event_id);
            if (mysqli_stmt_execute($req_insert_stmt)) {
                $request_id = mysqli_insert_id($connection);
            }
            mysqli_stmt_close($req_insert_stmt);
        }
    }

    if (empty($request_id)) {
        $_SESSION['error_message'] = "System error. Unable to create volunteer request. Please try again later.";
        header("Location: announcements.php");
        exit();
    }

    // 3) Insert volunteer registration with the valid request_id and pending status
    $insert_sql = "INSERT INTO volunteer_registrations (event_id, request_id, resident_id, status, registration_date) VALUES (?, ?, ?, 'pending', NOW())";
    $insert_stmt = mysqli_prepare($connection, $insert_sql);
    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, "iii", $event_id, $request_id, $resident_id);
        if (mysqli_stmt_execute($insert_stmt)) {
            $_SESSION['success_message'] = "Volunteer request submitted successfully!";
        } else {
            $_SESSION['error_message'] = "Error submitting volunteer request. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $_SESSION['error_message'] = "System error. Please try again later.";
    }

    header("Location: announcements.php");
    exit();
}

// Modify events query to properly check volunteer status
$current_user_id = isset($_SESSION['resident_id']) ? intval($_SESSION['resident_id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);

// Add pagination settings
$per_page = 8; // Changed from 5 to 8 items per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get total events count
$count_query = "SELECT COUNT(*) as total FROM events e 
    WHERE e.status = 'upcoming'
    AND e.event_start_date >= CURDATE()";
    
$count_result = mysqli_query($connection, $count_query);
$total_events = mysqli_fetch_assoc($count_result)['total'];
$total_pages = max(1, ceil($total_events / $per_page));

// Ensure current page is within valid range
$page = min(max(1, $page), $total_pages);
$offset = ($page - 1) * $per_page;

// Modify main query to use events table
$events_query = "SELECT 
    e.*,
    u.full_name as created_by_name,
    COUNT(DISTINCT CASE WHEN vr.status = 'approved' THEN vr.id ELSE NULL END) as volunteer_count,
    SUM(CASE WHEN vr.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    v_user.status as user_volunteer_status
FROM events e 
LEFT JOIN users u ON e.created_by = u.id 
LEFT JOIN volunteer_registrations vr ON vr.event_id = e.id 
LEFT JOIN volunteer_registrations v_user ON v_user.event_id = e.id 
    AND v_user.resident_id = $current_user_id
WHERE e.status = 'upcoming'
AND e.event_start_date >= CURDATE()
GROUP BY e.id
ORDER BY e.event_start_date ASC
LIMIT $per_page OFFSET $offset";

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            font-size: 0.9rem;
    opacity: 1;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.15); /* Lighter background */
    padding: 6px 12px;
    border-radius: 20px;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .event-date i {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
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

        /* Update/replace these specific style rules */
.event-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    display: flex;  /* Add flex display */
    flex-direction: column; /* Stack children vertically */
    height: 100%;  /* Take full height of grid cell */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.event-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    flex: 1;  /* Allow body to grow */
}

.event-info {
    flex: 1;  /* Allow info section to grow */
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.event-description {
    margin: 1rem 0;
    color: #555;
    line-height: 1.6;
    /* Remove any margin-bottom to prevent spacing issues */
}

.event-actions {
    margin-top: auto; /* Push to bottom */
    padding-top: 1rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: center; /* Center the button */
    gap: 1rem;
}

.btn-volunteer, .btn-login, .volunteer-status {
    width: 100%; /* Make buttons full width */
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-align: center;
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

        /* New styles for events grid */
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.event-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    position: relative;
    transition: transform 0.3s ease;
}

.event-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.event-header {
    position: relative;
    padding: 1.5rem;
    background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
    color: white;
}

.event-title-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.info-icon {
    cursor: help;
    opacity: 0.8;
    transition: opacity 0.2s ease;
    font-size: 0.9rem;
}

.info-icon:hover {
    opacity: 1;
}

.event-tooltip {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    color: #333;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    z-index: 10;
    font-size: 0.9rem;
    margin-top: 0.5rem;
    max-width: 100%;
    white-space: normal;
}

.info-icon:hover + .event-tooltip {
    display: block;
}

.event-body {
    padding: 1.5rem;
}

.event-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.event-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.event-description {
    color: #333;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0;
}

.pagination-btn {
    background: #f8f9fa;
    color: #4a47a3;
    border: 1px solid #dee2e6;
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.2s ease;
}

.pagination-btn:hover {
    background: #e9ecef;
    color: #3a3782;
    border-color: #cbd3da;
}

.pagination-number {
    min-width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.5rem;
    font-size: 0.9rem;
    border-radius: 4px;
    color: #4a47a3;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    text-decoration: none;
}

.pagination-number.active {
    background: #4a47a3;
    color: white;
    border-color: #4a47a3;
}

.pagination-ellipsis {
    color: #6c757d;
    padding: 0 0.3rem;
}

/* --- STYLE: add attendance button styles --- */
.btn-attendance {
    background: #10b981;
    color: #fff;
    border: none;
    padding: 0.65rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(16,185,129,0.15);
}

.btn-attendance:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(16,185,129,0.18);
}

/* disabled look */
.btn-attendance[disabled] {
    background: #c7f0e0;
    color: #6b7280;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}

/* Ensure the button fits within the card actions layout */
.event-actions { 
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
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
                    Events
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
            <h1 class="page-title">Events</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Upcoming Events</h1>
                <!-- Removed subtitle -->
            </div>

            <div class="container">
                <?php if (empty($events)): ?>
                    <div class="empty">
                        <i class="fas fa-calendar-times"></i>
                        <p>No events available at this time</p>
                        <p style="color: #888; font-size: 0.9rem; margin-top: 0.5rem;">
                            Check back later for new events and activities
                        </p>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($events as $ev): 
                            // ensure not showing past events (safety)
                            if (!empty($ev['event_start_date']) && strtotime($ev['event_start_date']) < strtotime(date('Y-m-d'))) continue;
                        ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div class="event-title-container">
                                        <h3 class="event-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                                        <i class="fas fa-info-circle info-icon"></i>
                                        <div class="event-tooltip">
                                            <?php echo nl2br(htmlspecialchars($ev['description'] ?? '')); ?>
                                        </div>
                                    </div>
                                    <div class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('F j, Y', strtotime($ev['event_start_date'])); ?>
                                    </div>
                                </div>
                                <div class="event-body">
                                    <div class="event-info">
                                        <?php if (!empty($ev['event_time'])): ?>
                                            <div class="event-info-item">
                                                <i class="far fa-clock"></i> 
                                                <?php 
                                                    $time = date('g:i A', strtotime($ev['event_time'])); 
                                                    echo htmlspecialchars($time); 
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($ev['location'])): ?>
                                            <div class="event-info-item">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ev['location']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="event-info-item">
                                            <i class="fas fa-users"></i>
                                            Volunteers: <?php echo intval($ev['volunteer_count']); ?>
                                            <?php if (!empty($ev['max_volunteers'])): ?>
                                                /<?php echo intval($ev['max_volunteers']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                 
                                    <div class="event-actions">
                                        <?php
                                        $approved = intval($ev['volunteer_count']);
                                        $pending = intval($ev['pending_count'] ?? 0);
                                        $max_vol = !empty($ev['max_volunteers']) ? intval($ev['max_volunteers']) : 0;
                                        $is_full = ($max_vol > 0) && (($approved + $pending) >= $max_vol);
                                        $is_logged_in = isset($_SESSION['resident_id']) || isset($_SESSION['user_id']);
                                        $has_volunteered = !empty($ev['user_volunteer_status']);

                                        // Determine if attendance button should be enabled
                                        $attendance_enabled = false;
                                        if ($has_volunteered && $ev['user_volunteer_status'] === 'approved') {
                                            $start_date = $ev['event_start_date'] ?? null;
                                            $start_time = !empty($ev['event_time']) ? $ev['event_time'] : '00:00:00';
                                            if ($start_date) {
                                                $start_dt = strtotime($start_date . ' ' . $start_time);
                                                if ($start_dt !== false && $start_dt <= time()) {
                                                    $attendance_enabled = true;
                                                }
                                            }
                                        }

                                        if ($has_volunteered):
                                            // Explicit handling for statuses including 'rejected'
                                            if ($ev['user_volunteer_status'] === 'approved'): ?>
                                                <button 
                                                    type="button" 
                                                    class="btn-attendance" 
                                                    <?php echo $attendance_enabled ? '' : 'disabled'; ?> 
                                                    onclick="markAttendance(<?php echo intval($ev['id']); ?>)">
                                                    <i class="fas fa-clipboard-check"></i>
                                                    Attendance
                                                </button>
                                            <?php elseif ($ev['user_volunteer_status'] === 'pending'): ?>
                                                <span class="volunteer-status pending">
                                                    <i class="fas fa-clock"></i> Request Pending
                                                </span>
                                            <?php elseif ($ev['user_volunteer_status'] === 'rejected'): ?>
                                                <span class="volunteer-status rejected">
                                                    <i class="fas fa-times-circle"></i> Request Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="volunteer-status">
                                                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($ev['user_volunteer_status']); ?>
                                                </span>
                                            <?php endif;
                                        else:
                                            if ($is_full): ?>
                                                <span class="volunteer-status full"><i class="fas fa-users-slash"></i> Event Full</span>
                                            <?php elseif ($is_logged_in): ?>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="action" value="volunteer_signup">
                                                    <input type="hidden" name="event_id" value="<?php echo intval($ev['id']); ?>">
                                                    <button type="submit" class="btn-volunteer" onclick="return confirm('Are you sure you want to volunteer for this event?');">
                                                        <i class="fas fa-hands-helping"></i> Volunteer Now
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="../index.php?redirect=resident/announcements.php" class="btn-login">
                                                    <i class="fas fa-sign-in-alt"></i> Login to Join
                                                </a>
                                            <?php endif;
                                        endif;
                                         ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- pagination (unchanged) -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Prev
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $range = 2;
                            if ($page > $range + 1): ?>
                                <a href="?page=1" class="pagination-number">1</a>
                                <?php if ($page > $range + 2): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif;
                            endif;

                            for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="pagination-number active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="pagination-number"><?php echo $i; ?></a>
                                <?php endif;
                            endfor;

                            if ($page < $total_pages - $range): ?>
                                <?php if ($page < $total_pages - $range - 1): ?>
                                    <span class="pagination-ellipsis">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>" class="pagination-number"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SweetAlert message display -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success_message'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif (isset($_SESSION['error_message'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>

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
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarOverlay').classList.remove('active');
            }
        });

        // Mark attendance function
        function markAttendance(eventId) {
            if (confirm('Mark attendance for this event?')) {
                // TODO: Replace with your actual attendance marking logic (e.g., AJAX request)
                console.log('Attendance marked for event:', eventId);
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Attendance Recorded',
                    text: 'Your attendance has been successfully recorded.',
                    timer: 3000,
                    showConfirmButton: false
                });
            }
        }
    </script>
</body>
</html>
