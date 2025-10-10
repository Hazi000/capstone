<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a treasurer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'treasurer') {
    header("Location: ../index.php");
    exit();
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Initialize statistics array with default values
$stats = [
    'total_budget' => 0,
    'total_expenses' => 0,
    'monthly_expenses' => 0,
    'available_budget' => 0,
    'total_complaints' => 0,
    'pending_complaints' => 0,
    'total_appointments' => 0,
    'pending_appointments' => 0,
    'total_residents' => 0
];

// Update the budget query to use the new structure
$budget_query = "SELECT COALESCE(SUM(amount), 0) as total FROM budgets WHERE YEAR(budget_date) = $current_year";
$result = mysqli_query($connection, $budget_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_budget'] = mysqli_fetch_assoc($result)['total'];
}

// Update expenses query to use created_at
$expenses_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE YEAR(created_at) = $current_year";
$result = mysqli_query($connection, $expenses_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_expenses'] = mysqli_fetch_assoc($result)['total'];
}

// Calculate available budget
$stats['available_budget'] = $stats['total_budget'] - $stats['total_expenses'];

// Update monthly expenses query to use created_at
$monthly_expenses_query = "SELECT COALESCE(SUM(amount), 0) as total FROM expenses 
                         WHERE MONTH(created_at) = $current_month AND YEAR(created_at) = $current_year";
$result = mysqli_query($connection, $monthly_expenses_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['monthly_expenses'] = mysqli_fetch_assoc($result)['total'];
} else {
    $stats['monthly_expenses'] = 0;
}

// Update expense categories query to use budget items instead of categories
$expense_categories_query = "SELECT b.item as category, COALESCE(SUM(e.amount), 0) as total 
                           FROM budgets b 
                           LEFT JOIN expenses e ON b.id = e.budget_id 
                           WHERE YEAR(b.budget_date) = $current_year 
                           GROUP BY b.id, b.item";
$expense_categories = mysqli_query($connection, $expense_categories_query);
$expense_categories_data = [];
if ($expense_categories) {
    while($row = mysqli_fetch_assoc($expense_categories)) {
        $expense_categories_data[] = $row;
    }
}

// Count total complaints
$complaint_query = "SELECT COUNT(*) as total FROM complaints";
$result = mysqli_query($connection, $complaint_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_complaints'] = mysqli_fetch_assoc($result)['total'];
} else {
    $stats['total_complaints'] = 0;
}

// Count pending complaints
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_complaint_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
} else {
    $stats['pending_complaints'] = 0;
}

// Count total appointments
$appointment_query = "SELECT COUNT(*) as total FROM appointments";
$result = mysqli_query($connection, $appointment_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_appointments'] = mysqli_fetch_assoc($result)['total'];
} else {
    $stats['total_appointments'] = 0;
}

// Count pending appointments
$pending_appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_appointment_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['pending_appointments'] = mysqli_fetch_assoc($result)['pending'];
} else {
    $stats['pending_appointments'] = 0;
}

// Count total residents
$residents_query = "SELECT COUNT(*) as total FROM residents";
$result = mysqli_query($connection, $residents_query);
if ($result && mysqli_num_rows($result) > 0) {
    $stats['total_residents'] = mysqli_fetch_assoc($result)['total'];
} else {
    $stats['total_residents'] = 0;
}

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
                              ORDER BY a.created_at DESC 
                              LIMIT 5";
$recent_announcements = mysqli_query($connection, $recent_announcements_query);

// Get calendar events (use only events table, not announcements)
$calendar_events_query = "
    SELECT
        'event' AS type,
        e.id,
        DATE(e.event_start_date) AS event_date,
        e.event_time AS time,
        e.title,
        e.status,
        u.full_name AS created_by_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.event_start_date IS NOT NULL
      AND DATE(e.event_start_date) >= CURDATE()
      AND DATE(e.event_start_date) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND e.status IN ('upcoming','ongoing')
    ORDER BY e.event_start_date ASC, e.event_time ASC";
$calendar_events = mysqli_query($connection, $calendar_events_query);
$events_data = [];
if ($calendar_events && mysqli_num_rows($calendar_events) > 0) {
    while ($row = mysqli_fetch_assoc($calendar_events)) {
        $events_data[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'event_date' => date('Y-m-d', strtotime($row['event_date'])),
            'time' => $row['time'],
            'type' => $row['type'],
            'status' => $row['status'],
            'created_by_name' => $row['created_by_name'] ?? ''
        ];
    }
}

// Build reusable arrays for recent items so we can render them and expose to JS
$recent_complaints_data = [];
if ($recent_complaints && mysqli_num_rows($recent_complaints) > 0) {
    mysqli_data_seek($recent_complaints, 0);
    while ($row = mysqli_fetch_assoc($recent_complaints)) {
        $recent_complaints_data[] = $row;
    }
}

$recent_announcements_data = [];
if ($recent_announcements && mysqli_num_rows($recent_announcements) > 0) {
    mysqli_data_seek($recent_announcements, 0);
    while ($row = mysqli_fetch_assoc($recent_announcements)) {
        $recent_announcements_data[] = $row;
    }
}

// Recent appointments
$recent_appointments_query = "SELECT a.*, r.full_name FROM appointments a LEFT JOIN residents r ON a.resident_id = r.id ORDER BY a.created_at DESC LIMIT 5";
$recent_appointments = mysqli_query($connection, $recent_appointments_query);
$recent_appointments_data = [];
if ($recent_appointments && mysqli_num_rows($recent_appointments) > 0) {
    mysqli_data_seek($recent_appointments, 0);
    while ($row = mysqli_fetch_assoc($recent_appointments)) {
        $recent_appointments_data[] = $row;
    }
}

// Recent residents (newly registered)
$recent_residents_query = "SELECT id, full_name, created_at FROM residents ORDER BY created_at DESC LIMIT 5";
$recent_residents = mysqli_query($connection, $recent_residents_query);
$recent_residents_data = [];
if ($recent_residents && mysqli_num_rows($recent_residents) > 0) {
    mysqli_data_seek($recent_residents, 0);
    while ($row = mysqli_fetch_assoc($recent_residents)) {
        $recent_residents_data[] = $row;
    }
}

// Fetch budgets for detail panel
$budgets_query = "SELECT description, amount, budget_date, status FROM budgets WHERE YEAR(created_at) = $current_year ORDER BY budget_date DESC";
$budgets_result = mysqli_query($connection, $budgets_query);
$budgets_data = [];
if ($budgets_result && mysqli_num_rows($budgets_result) > 0) {
    while ($row = mysqli_fetch_assoc($budgets_result)) {
        $budgets_data[] = $row;
    }
}

// Update the monthly expenses detail query
$monthly_expenses_detail_query = "SELECT e.*, b.item as category 
                                FROM expenses e 
                                LEFT JOIN budgets b ON e.budget_id = b.id 
                                WHERE MONTH(e.created_at) = $current_month 
                                AND YEAR(e.created_at) = $current_year 
                                ORDER BY e.created_at DESC";
$monthly_expenses_detail_result = mysqli_query($connection, $monthly_expenses_detail_query);
$monthly_expenses_data = [];
if ($monthly_expenses_detail_result && mysqli_num_rows($monthly_expenses_detail_result) > 0) {
    while ($row = mysqli_fetch_assoc($monthly_expenses_detail_result)) {
        $monthly_expenses_data[] = $row;
    }
}

// Fetch all expenses for total expenses detail panel
$total_expenses_detail_query = "SELECT e.*, b.item as category, e.created_at as date 
                               FROM expenses e 
                               LEFT JOIN budgets b ON e.budget_id = b.id 
                               WHERE YEAR(e.created_at) = $current_year 
                               ORDER BY e.created_at DESC";
$total_expenses_detail_result = mysqli_query($connection, $total_expenses_detail_query);
$total_expenses_data = [];
if ($total_expenses_detail_result && mysqli_num_rows($total_expenses_detail_result) > 0) {
    while ($row = mysqli_fetch_assoc($total_expenses_detail_result)) {
        $total_expenses_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-3d"></script>
    <!-- Add SweetAlert2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
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
            max-width: 800px;  /* Add max-width */
            margin: 0 auto;    /* Center the calendar */
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
            min-height: 60px;  /* Reduce height from 80px */
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

        /* Mobile Responsiveness */
        @media (max-width: 1200px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .recent-activities {
                grid-template-columns: 1fr;
            }

            .stats-overview {
                grid-template-columns: 1fr !important;
            }
            
            .chart-container, .calendar-widget {
                height: 500px !important;
                margin-bottom: 2rem;
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

        .expense-chart-card {
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .detail-table th, .detail-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .detail-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .detail-amount {
            text-align: right;
            font-family: monospace;
            font-size: 0.9rem;
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
                <div class="user-role">Treasurer</div>
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
                <div class="nav-section-title">Finance</div>
                <a href="expense.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Expense
                </a>
                <a href="budgets.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    Budgets
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
            <div class="dashboard-header">
                <h1 class="dashboard-title">Welcome back, <?php echo $_SESSION['full_name']; ?>!</h1>
                <p class="dashboard-subtitle">Here's what's happening in your barangay today</p>
            </div>

            <!-- Update layout to position chart and calendar side by side -->
            <div class="stats-overview" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
                <div class="chart-container" style="position: relative; height: 600px;">
                    <canvas id="mainExpenseChart"></canvas>
                </div>

                <!-- Calendar moved to right side -->
                <div class="right-section">
                    <div class="calendar-widget" style="height: 600px;">
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

            <!-- Remove the old calendar section -->
            <div class="dashboard-layout">
                <!-- Empty or remove this section since calendar is moved -->
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
            Swal.fire({
                title: 'Logout Confirmation',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logoutForm').submit();
                }
            });
        }

        // Data exported from PHP
        const recentComplaints = <?php echo json_encode($recent_complaints_data ?? []); ?>;
        const recentAnnouncements = <?php echo json_encode($recent_announcements_data ?? []); ?>;
        const recentAppointments = <?php echo json_encode($recent_appointments_data ?? []); ?>;
        const recentResidents = <?php echo json_encode($recent_residents_data ?? []); ?>;

        // Initialize calendar on page load and wire up dashboard interactions
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
            
            // Add 3D plugin configuration
            const threeDConfig = {
                enabled: true,
                alpha: 30,      // Tilt angle
                beta: 20,       // Rotation angle
                depth: 45,      // Depth of 3D effect
                donut: false    // Make it a full pie instead of doughnut
            };

            // Enhanced 3D main chart
            const mainCtx = document.getElementById('mainExpenseChart').getContext('2d');
            new Chart(mainCtx, {
                type: 'pie',    // Changed from doughnut to pie
                data: {
                    labels: ['Available Budget', 'Monthly Expenses'],
                    datasets: [{
                        data: [
                            <?php echo $stats['available_budget']; ?>,
                            <?php echo $stats['monthly_expenses']; ?>
                        ],
                        backgroundColor: ['#16A34A', '#B91C1C'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { size: 16, weight: 'bold' },
                                padding: 25
                            }
                        },
                        title: {
                            display: true,
                            text: 'Budget Overview',
                            font: { size: 24, weight: 'bold' },
                            padding: 25
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ₱${context.raw.toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })}`;
                                }
                            }
                        },
                        '3d': threeDConfig
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true
                    }
                }
            });

            // Update expense categories chart with 3D effect
            const expenseCtx = document.getElementById('expenseChart');
            if (expenseCtx) {
                new Chart(expenseCtx.getContext('2d'), {
                    type: 'pie',    // Changed from doughnut to pie
                    data: {
                        labels: expenseCategories.map(cat => cat.category || 'Uncategorized'),
                        datasets: [{
                            data: expenseCategories.map(cat => cat.total),
                            backgroundColor: expenseCategories.map(cat => 
                                getCategoryColor(cat.category || 'Other')
                            ),
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    font: { size: 11, weight: 'bold' }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Budget Items Distribution',
                                font: { size: 14, weight: 'bold' }
                            },
                            '3d': threeDConfig
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true
                        }
                    }
                });
            }
        });

        function showDetailPanel(title, items) {
            document.getElementById('detailTitle').textContent = title;
            const container = document.getElementById('detailList');
            container.innerHTML = '';
            
            if (!items || items.length === 0) {
                container.innerHTML = '<p style="text-align:center;color:#666;padding:1rem;">No items to show</p>';
                return;
            }

            const table = document.createElement('table');
            table.className = 'detail-table';
            
            // Create table header based on type
            const thead = document.createElement('thead');
            let headerRow = '<tr>';
            
            if (title === 'Budgets') {
                headerRow += `
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                `;
            } else {
                headerRow += `
                    <th>Date</th>
                    <th>Category</th>
                    <th>Amount</th>
                `;
            }
            
            headerRow += '</tr>';
            thead.innerHTML = headerRow;
            table.appendChild(thead);

            // Create table body
            const tbody = document.createElement('tbody');
            items.forEach(it => {
                const row = document.createElement('tr');
                
                if (title === 'Budgets') {
                    row.innerHTML = `
                        <td>${it.budget_date ? new Date(it.budget_date).toLocaleDateString() : ''}</td>
                        <td>${escapeHtml(it.description)}</td>
                        <td class="detail-amount">₱${Number(it.amount).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                        <td><span class="status-badge status-${it.status}">${escapeHtml(it.status)}</span></td>
                    `;
                } else {
                    row.innerHTML = `
                        <td>${it.date ? new Date(it.date).toLocaleDateString() : ''}</td>
                        <td>${escapeHtml(it.category)}</td>
                        <td class="detail-amount">₱${Number(it.amount).toLocaleString(undefined, {minimumFractionDigits:2})}</td>
                    `;
                }
                
                tbody.appendChild(row);
            });
            
            table.appendChild(tbody);
            container.appendChild(table);
            
            document.getElementById('dashboardDetail').style.display = 'block';
            document.getElementById('dashboardDetail').scrollIntoView({behavior:'smooth'});
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return String(unsafe).replace(/[&<>"]+/g, function(match) {
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
                return map[match] || match;
            });
        }

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Add color mapping constant
        const EXPENSE_COLORS = {
            'Personnel Services': '#2563EB',      // Blue
            'MOOE': '#F97316',                   // Orange
            'Capital Outlay': '#16A34A',         // Green
            'DRRM': '#B91C1C',                   // Red
            'Health Services': '#EAB308',        // Yellow
            'Education': '#7C3AED',              // Purple
            'Peace & Order': '#374151',          // Dark Gray
            'Environmental': '#14B8A6',          // Teal
            'GAD': '#A78BFA',                    // Lavender
            'SK Fund': '#92400E',                // Brown
            'Senior & PWD': '#60A5FA',           // Light Blue
            'Other': '#94A3B8'                   // Default color
        };

        // Function to get color based on category
        function getCategoryColor(category) {
            const normalizedCategory = category.toLowerCase();
            for (const [key, value] of Object.entries(EXPENSE_COLORS)) {
                if (normalizedCategory.includes(key.toLowerCase())) {
                    return value;
                }
            }
            return EXPENSE_COLORS.Other;
        }

        // Update expense chart initialization with new data structure
        const expenseCategories = <?php echo json_encode($expense_categories_data); ?>;

        const ctx = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: expenseCategories.map(cat => cat.category || 'Uncategorized'),
                datasets: [{
                    data: expenseCategories.map(cat => cat.total),
                    backgroundColor: expenseCategories.map(cat => 
                        getCategoryColor(cat.category || 'Other')
                    )
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11,
                                weight: 'bold'
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Budget Items Distribution',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    '3d': {
                        alpha: 45,
                        beta: 45,
                        enabled: true,
                        depth: 35
                    }
                },
                cutout: '60%'
            }
        });
    </script>

</body></html>