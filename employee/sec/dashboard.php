<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
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

// Count total residents
$residents_query = "SELECT COUNT(*) as total FROM residents";
$result = mysqli_query($connection, $residents_query);
$stats['total_residents'] = mysqli_fetch_assoc($result)['total'];

// Get recent complaints - modified to show only last 7 days
$recent_complaints_query = "SELECT c.*, r.full_name 
                          FROM complaints c 
                          LEFT JOIN residents r ON c.resident_id = r.id 
                          WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          ORDER BY c.created_at DESC 
                          LIMIT 5";
$recent_complaints = mysqli_query($connection, $recent_complaints_query);

// Get recent announcements - modified to show only last 7 days
$recent_announcements_query = "SELECT a.*, u.full_name as created_by_name 
                              FROM announcements a 
                              LEFT JOIN users u ON a.created_by = u.id 
                              WHERE a.status = 'active' 
                              AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                              AND (a.expiry_date IS NULL OR a.expiry_date >= CURDATE())
                              ORDER BY a.created_at DESC 
                              LIMIT 5";
$recent_announcements = mysqli_query($connection, $recent_announcements_query);

// Update calendar events query to match community service events structure
// Get both current and future events for calendar display
$calendar_events_query = "
    SELECT 
        id,
        title,
        event_start_date,
        event_end_date,
        event_time,
        status,
        location
    FROM events 
    WHERE status IN ('upcoming', 'ongoing')
    AND (
        event_end_date >= CURDATE() 
        OR (event_end_date IS NULL AND event_start_date >= CURDATE())
        OR (event_end_date = '0000-00-00' AND event_start_date >= CURDATE())
    )
    ORDER BY event_start_date ASC, event_time ASC";

$calendar_events = mysqli_query($connection, $calendar_events_query);

// Debug: Check if query executed successfully
if (!$calendar_events) {
    error_log("Calendar events query failed: " . mysqli_error($connection));
}

// Process events data: expand multi-day events into individual date entries (inclusive)
$events_data = [];
$events_ranges = []; // collect start/end ranges per event

if ($calendar_events && mysqli_num_rows($calendar_events) > 0) {
    while ($ev = mysqli_fetch_assoc($calendar_events)) {
        // Get start and end dates
        $startRaw = $ev['event_start_date'] ?? '';
        $endRaw = $ev['event_end_date'] ?? '';
        
        // Skip if no start date
        if (empty($startRaw) || $startRaw === '0000-00-00') {
            continue;
        }

        try {
            $start = new DateTime($startRaw);
        } catch (Exception $e) {
            error_log("Invalid start date for event {$ev['id']}: {$startRaw}");
            continue;
        }

        // Handle end date - if empty or invalid, use start date
        if (empty($endRaw) || $endRaw === '0000-00-00') {
            $end = clone $start;
        } else {
            try {
                $end = new DateTime($endRaw);
            } catch (Exception $e) {
                error_log("Invalid end date for event {$ev['id']}: {$endRaw}");
                $end = clone $start;
            }
        }

        // Ensure end is not before start
        if ($end < $start) {
            $end = clone $start;
        }

        // Store the event range
        $events_ranges[] = [
            'id' => (int)$ev['id'],
            'title' => $ev['title'],
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'time' => $ev['event_time'] ?? '',
            'location' => $ev['location'] ?? ''
        ];

        // Create entries for each day in the range (inclusive)
        $period = new DatePeriod($start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
        foreach ($period as $date) {
            $events_data[] = [
                'id' => (int)$ev['id'],
                'title' => $ev['title'],
                'event_date' => $date->format('Y-m-d'),
                'time' => $ev['event_time'] ?? '',
                'status' => $ev['status'] ?? '',
                'location' => $ev['location'] ?? ''
            ];
        }
    }
}

// Debug output (remove after testing)
error_log("Total events found: " . count($events_ranges));
error_log("Total event days: " . count($events_data));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard - Cawit Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add SweetAlert2 CDN -->
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
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            background: #f8f9fa;
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
            padding: 0.35rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid rgba(133, 100, 4, 0.1);
        }

        .status-resolved {
            background: #d4edda;
            color: #155724;
            border: 1px solid rgba(21, 87, 36, 0.1);
        }

        .status-inprogress, .status-in_progress {
            background: #cce5ff;
            color: #004085;
            border: 1px solid rgba(0, 64, 133, 0.1);
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
            overflow: visible; /* CHANGED: Allow tooltips to overflow */
        }

        .calendar-header {
            padding: 1.25rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            text-align: center;
        }

        .calendar-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.75rem;
        }

        .calendar-nav button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 0.95rem;
        }

        .calendar-nav button:hover {
            background: rgba(255,255,255,0.3);
        }

        .calendar-grid {
            padding: 0.5rem;
            overflow: visible; /* CHANGED: Allow tooltips to overflow */
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
            overflow: visible; /* ADDED: Allow tooltips to overflow */
        }

        .calendar-day {
            background: white;
            border: 1px solid #e0e0e0;
            height: 80px;
            padding: 0.5rem;
            position: relative;
            transition: background 0.2s ease;
            overflow: visible; /* CHANGED: Allow tooltips to overflow */
            cursor: pointer;
        }

        .calendar-day:hover {
            background: #f8f9fa;
            z-index: 100; /* INCREASED: Higher z-index for hover */
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
            font-size: 0.95rem;
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
        }

        /* Container for events with hidden overflow */
        .calendar-events-container {
            max-height: 50px;
            overflow: hidden;
        }

        /* ENHANCED: Hover tooltip for events */
        .calendar-day-tooltip {
            display: none;
            position: absolute;
            min-width: 280px;
            max-width: 380px;
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border: 2px solid #3498db;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            z-index: 2000; /* INCREASED: Very high z-index */
            padding: 0.75rem;
            pointer-events: none; /* ADDED: Don't block clicks initially */
        }

        /* ADDED: Enable pointer events when hovering the day */
        .calendar-day:hover .calendar-day-tooltip {
            pointer-events: auto;
        }

        /* DEFAULT: Position tooltip below and to the left */
        .calendar-day-tooltip {
            top: 100%;
            left: 0;
            margin-top: 5px;
        }

        /* Position tooltip above if day is in bottom rows */
        .calendar-day.tooltip-top .calendar-day-tooltip {
            bottom: 100%;
            top: auto;
            margin-top: 0;
            margin-bottom: 5px;
        }

        /* Position tooltip to the right if day is on left edge */
        .calendar-day.tooltip-right .calendar-day-tooltip {
            left: auto;
            right: 0;
        }

        /* ADDED: Center positioning for middle columns */
        .calendar-day.tooltip-center .calendar-day-tooltip {
            left: 50%;
            transform: translateX(-50%);
        }

        .calendar-day.tooltip-top.tooltip-center .calendar-day-tooltip {
            left: 50%;
            transform: translateX(-50%);
        }

        /* Show tooltip on hover */
        .calendar-day:hover .calendar-day-tooltip {
            display: block !important;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ADDED: Different animation for centered tooltips */
        .calendar-day.tooltip-center:hover .calendar-day-tooltip {
            animation: fadeInCenter 0.2s ease;
        }

        @keyframes fadeInCenter {
            from {
                opacity: 0;
                transform: translate(-50%, -5px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        .tooltip-event-item {
            padding: 0.6rem;
            margin-bottom: 0.6rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
            transition: all 0.2s ease;
        }

        .tooltip-event-item:last-child {
            margin-bottom: 0;
        }

        .tooltip-event-item:hover {
            background: #e3f2fd;
            border-left-color: #1976d2;
            transform: translateX(3px);
            cursor: pointer;
        }

        .tooltip-event-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
            line-height: 1.3;
        }

        .tooltip-event-details {
            font-size: 0.8rem;
            color: #666;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .tooltip-event-details i {
            width: 16px;
            margin-right: 6px;
            color: #3498db;
        }

        /* Calendar highlight for event ranges */
        .calendar-day.event-range {
            background: linear-gradient(135deg, #4fc3f7 0%, #29b6f6 100%) !important;
            border: 2px solid #0288d1 !important;
            box-shadow: 0 2px 8px rgba(41, 182, 246, 0.3) !important;
        }

        /* when today is also an event day, make it visible */
        .calendar-day.event-range.today {
            background: linear-gradient(135deg, #ffd54f 0%, #ffca28 100%) !important;
            border: 3px solid #f57c00 !important;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.5) !important;
        }

        /* Make event pill more readable */
        .calendar-event {
            background: #0d47a1 !important;
            color: white !important;
            border-left: 3px solid #fff !important;
            padding: 4px 8px !important;
            margin-bottom: 3px !important;
            border-radius: 4px !important;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.7rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
            text-shadow: 0 1px 1px rgba(0,0,0,0.2);
        }

        .calendar-event:hover {
            background: #1565c0 !important;
            transform: translateX(2px) scale(1.01);
            box-shadow: 0 2px 4px rgba(0,0,0,0.3) !important;
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
            max-height: 250px;
            overflow-y: auto;
        }

        .upcoming-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .upcoming-item:last-child {
            border-bottom: none;
        }

        .upcoming-date {
            background: #f8f9fa;
            padding: 0.4rem;
            border-radius: 6px;
            text-align: center;
            min-width: 50px;
        }

        .upcoming-date-day {
            font-size: 1rem;
            font-weight: bold;
            color: #333;
        }

        .upcoming-date-month {
            font-size: 0.65rem;
            color: #666;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-info h4 {
            font-size: 0.85rem;
            color: #333;
            margin-bottom: 0.2rem;
        }

        .upcoming-info p {
            font-size: 0.75rem;
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
                min-height: 60px;
                padding: 2px;
            }
            
            .calendar-day-number {
                font-size: 0.8rem;
            }
            
            .calendar-event {
                font-size: 0.6rem;
            }

            /* ADDED: Better mobile tooltip positioning */
            .calendar-day-tooltip {
                min-width: 220px;
                max-width: 90vw;
            }

            /* On mobile, always center tooltips for better visibility */
            .calendar-day.tooltip-top .calendar-day-tooltip {
                left: 50%;
                right: auto;
                transform: translateX(-50%);
            }

            .upcoming-item {
                padding: 0.75rem 1rem;
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
                Cawit Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Secretary</div>
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
                <a href="resident_family.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Resident Family
                </a>
                <a href="resident_account.php" class="nav-item">
					<i class="fas fa-user-shield"></i>
					Resident Accounts
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
                 <a href="disaster_management.php" class="nav-item">
                    <i class="fas fa-house-damage"></i>
                    Disaster Management
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
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
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <a href="complaints.php" class="stat-card">
                    <div class="stat-icon" style="color: #e74c3c;">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['pending_complaints']; ?></h3>
                        <p>Pending Complaints</p>
                    </div>
                </a>
                
                <a href="resident-profiling.php" class="stat-card">
                    <div class="stat-icon" style="color: #27ae60;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_residents']; ?></h3>
                        <p>Total Residents</p>
                    </div>
                </a>
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
                                                <?php if ($complaint['status'] == 'pending'): ?>
                                                    <i class="fas fa-clock"></i>
                                                <?php elseif ($complaint['status'] == 'in_progress' || $complaint['status'] == 'inprogress'): ?>
                                                    <i class="fas fa-spinner"></i>
                                                <?php elseif ($complaint['status'] == 'resolved'): ?>
                                                    <i class="fas fa-check"></i>
                                                <?php endif; ?>
                                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
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

                   
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calendar functionality
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        // Events data from PHP (per-day entries)
        const eventsData = <?php echo json_encode($events_data ?? []); ?>;
        // --- NEW: ranges used to highlight full event spans ---
        const eventsRanges = <?php echo json_encode($events_ranges ?? []); ?>;

        // helper to pad month/day
        function pad(n){ return n < 10 ? '0' + n : String(n); }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // Helper: check if a date (YYYY-MM-DD) falls within any event range
        function isDateInAnyRange(dateStr) {
            for (let i = 0; i < eventsRanges.length; i++) {
                const r = eventsRanges[i];
                if (!r.start || !r.end) continue;
                if (dateStr >= r.start && dateStr <= r.end) return true;
            }
            return false;
        }

        function renderCalendar() {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            
            document.getElementById('currentMonth').textContent = `${monthNames[currentMonth]} ${currentYear}`;
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
            
            let calendarHTML = '';
            let dayCounter = 0; // ADDED: Track position for better tooltip placement
            
            // Previous month's trailing days
            for (let i = firstDay - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                calendarHTML += `<div class="calendar-day other-month">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
                dayCounter++;
            }
            
            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(currentYear, currentMonth, day);
                const dateString = `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`;
                const isToday = (new Date()).getFullYear() === date.getFullYear() &&
                                (new Date()).getMonth() === date.getMonth() &&
                                (new Date()).getDate() === date.getDate();

                // collect unique events for this date (dedupe by id)
                const uniqueEvents = {};
                for (let i = 0; i < eventsData.length; i++) {
                    const ev = eventsData[i];
                    if (ev.event_date === dateString) uniqueEvents[ev.id] = ev;
                }
                const dayEvents = Object.values(uniqueEvents);

                let eventsHTML = '';
                let tooltipHTML = '';
                
                if (dayEvents.length > 0) {
                    eventsHTML = '<div class="calendar-events-container">';
                    // Show only first event to save space
                    const firstEvent = dayEvents[0];
                    eventsHTML += `
                        <div class="calendar-event" data-id="${firstEvent.id}" title="${escapeHtml(firstEvent.title)}">
                            ${escapeHtml(firstEvent.title)}
                        </div>`;
                    
                    // Show indicator if there are more events
                    if (dayEvents.length > 1) {
                        eventsHTML += `<div style="font-size:0.65rem;color:#1976d2;font-weight:600;margin-top:2px;">+${dayEvents.length - 1} more event${dayEvents.length > 2 ? 's' : ''}</div>`;
                    }
                    eventsHTML += '</div>';

                    // Build tooltip with ALL events (always show tooltip if there are events)
                    tooltipHTML = '<div class="calendar-day-tooltip">';
                    tooltipHTML += `<div style="font-weight:700;color:#2c3e50;margin-bottom:0.5rem;padding-bottom:0.5rem;border-bottom:2px solid #3498db;">${dayEvents.length} Event${dayEvents.length > 1 ? 's' : ''} on this day</div>`;
                    
                    dayEvents.forEach((event, index) => {
                        let timeTxt = '';
                        if (event.time) {
                            const m = event.time.match(/^(\d{1,2}:\d{2})/);
                            if (m) timeTxt = m[1];
                        }
                        
                        tooltipHTML += `
                            <div class="tooltip-event-item" data-event-id="${event.id}">
                                <div class="tooltip-event-title">${index + 1}. ${escapeHtml(event.title)}</div>
                                <div class="tooltip-event-details">
                                    ${timeTxt ? `<span><i class="far fa-clock"></i>${timeTxt}</span>` : '<span><i class="far fa-clock"></i>All day</span>'}
                                    ${event.location ? `<span><i class="fas fa-map-marker-alt"></i>${escapeHtml(event.location)}</span>` : ''}
                                </div>
                            </div>`;
                    });
                    tooltipHTML += '</div>';
                }

                // Add classes for highlighting
                const classes = ['calendar-day'];
                if (isToday) classes.push('today');

                // Check if this date is in any event range
                const isInRange = isDateInAnyRange(dateString);
                
                // Add event-range class if date is within any event's date range
                if (isInRange) {
                    classes.push('event-range');
                }

                // ENHANCED: Calculate tooltip position based on row and column
                const currentRow = Math.floor(dayCounter / 7);
                const totalRows = Math.ceil((firstDay + daysInMonth) / 7);
                const dayOfWeek = dayCounter % 7;
                
                // If in last 2 rows, show tooltip above
                if (currentRow >= totalRows - 2) {
                    classes.push('tooltip-top');
                }
                
                // ENHANCED: Better horizontal positioning
                if (dayOfWeek === 0 || dayOfWeek === 1) {
                    // First two columns - align to left
                    // No additional class needed (default is left)
                } else if (dayOfWeek === 5 || dayOfWeek === 6) {
                    // Last two columns - align to right
                    classes.push('tooltip-right');
                } else {
                    // Middle columns - center the tooltip
                    classes.push('tooltip-center');
                }

                calendarHTML += `
                    <div class="${classes.join(' ')}" data-date="${dateString}">
                        <div class="calendar-day-number">${day}</div>
                        ${eventsHTML}
                        ${tooltipHTML}
                    </div>`;
                
                dayCounter++;
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

            // Attach click handlers to events after render
            document.querySelectorAll('.calendar-event[data-id]').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const id = this.getAttribute('data-id');
                    if (id) {
                        window.location.href = `community_service.php?id=${encodeURIComponent(id)}`;
                    }
                });
            });

            // Attach click handlers to tooltip event items
            document.querySelectorAll('.tooltip-event-item[data-event-id]').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const id = this.getAttribute('data-event-id');
                    if (id) {
                        window.location.href = `community_service.php?id=${encodeURIComponent(id)}`;
                    }
                });
                // Add hover effect
                el.style.cursor = 'pointer';
                el.addEventListener('mouseenter', function() {
                    this.style.background = '#e3f2fd';
                });
                el.addEventListener('mouseleave', function() {
                    this.style.background = '#f8f9fa';
                });
            });
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
            Swal.fire({
                title: 'Logout Confirmation',
                text: "Are you sure you want to logout?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logoutForm').submit();
                }
            });
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