<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// Get current year and filter parameters
$current_year = date('Y');
$year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// Get budgets with used amounts
$query = "SELECT b.*, 
          COALESCE(SUM(e.amount), 0) as used_amount,
          b.amount - COALESCE(SUM(e.amount), 0) as available_amount
          FROM budgets b 
          LEFT JOIN expenses e ON b.id = e.budget_id
          WHERE YEAR(b.budget_date) = $year
          GROUP BY b.id, b.item, b.amount, b.budget_date
          ORDER BY b.budget_date DESC";

$result = mysqli_query($connection, $query);
$budgets = [];
while ($row = mysqli_fetch_assoc($result)) {
    $budgets[] = $row;
}

// Get years for filter
$years_query = "SELECT DISTINCT YEAR(budget_date) as year FROM budgets 
                UNION 
                SELECT $current_year as year 
                ORDER BY year DESC";
$years_result = mysqli_query($connection, $years_query);
$years = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $years[] = $row['year'];
}
if (empty($years)) {
    $years[] = $current_year;
}

// Get budget statistics
$stats_query = "SELECT 
                    COALESCE(SUM(b.amount), 0) as total_budget,
                    COALESCE(SUM(e.amount), 0) as used_amount
                FROM budgets b 
                LEFT JOIN expenses e ON b.id = e.budget_id
                WHERE YEAR(b.budget_date) = $year";

$stats_result = mysqli_query($connection, $stats_query);
$stats = [
    'total_budget' => 0,
    'available_budget' => 0
];
if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
    $stats['total_budget'] = $row['total_budget'] ?? 0;
    $stats['available_budget'] = $stats['total_budget'] - ($row['used_amount'] ?? 0);
}

// New query to get pending complaints count
$pending_complaints_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$pending_result = mysqli_query($connection, $pending_complaints_query);
$pending_complaints = 0;
if ($pending_result && $row = mysqli_fetch_assoc($pending_result)) {
    $pending_complaints = $row['pending'];
}

// Add this PHP code after the initial PHP queries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item']) && isset($_POST['amount']) && !isset($_POST['edit_budget']) && !isset($_POST['delete_budget'])) {
    // Add new budget
    $item = trim($_POST['item']);
    $amount = floatval($_POST['amount']);
    $budget_date = !empty($_POST['budget_date']) ? $_POST['budget_date'] : date('Y-m-d');

    if ($item === '' || $amount <= 0) {
        $error = "Please provide a valid item name and amount greater than zero.";
    } else {
        $item_safe = mysqli_real_escape_string($connection, $item);
        // budgets table does not have created_by column — insert only item, amount, budget_date, created_at
        $stmt = mysqli_prepare($connection, "INSERT INTO budgets (item, amount, budget_date, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            // bind item (string), amount (double), budget_date (string)
            mysqli_stmt_bind_param($stmt, "sds", $item_safe, $amount, $budget_date);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header("Location: budgets.php?added=1");
                exit();
            } else {
                $error = "Failed to save budget: " . mysqli_error($connection);
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Failed to prepare statement: " . mysqli_error($connection);
        }
    }
}

// Add edit budget handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_budget'])) {
    $budget_id = isset($_POST['budget_id']) ? intval($_POST['budget_id']) : 0;
    $item = isset($_POST['item']) ? trim($_POST['item']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    if ($budget_id > 0 && !empty($item) && $amount > 0) {
        $item_safe = mysqli_real_escape_string($connection, $item);
        $update_query = "UPDATE budgets SET item = '$item_safe', amount = $amount WHERE id = $budget_id";
        if (mysqli_query($connection, $update_query)) {
            header("Location: budgets.php?success=1");
            exit();
        } else {
            $error = "Error updating budget: " . mysqli_error($connection);
        }
    }
}

// Add delete budget handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_budget'])) {
    $budget_id = isset($_POST['budget_id']) ? intval($_POST['budget_id']) : 0;
    
    if ($budget_id > 0) {
        // Check if budget has any associated expenses
        $check_query = "SELECT COUNT(*) as count FROM expenses WHERE budget_id = $budget_id";
        $check_result = mysqli_query($connection, $check_query);
        $has_expenses = ($check_result && mysqli_fetch_assoc($check_result)['count'] > 0);

        if ($has_expenses) {
            $error = "Cannot delete budget with existing expenses.";
        } else {
            $delete_query = "DELETE FROM budgets WHERE id = $budget_id";
            if (mysqli_query($connection, $delete_query)) {
                header("Location: budgets.php?delete_success=1");
                exit();
            } else {
                $error = "Error deleting budget: " . mysqli_error($connection);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Enhanced Modal Styles */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .modal.active {
        display: flex !important;
    }

    .modal-content {
        background: #ffffff;
        border-radius: 15px;
        padding: 2rem;
        width: 90%;
        max-width: 500px;
        position: relative;
        animation: modalSlideIn 0.3s ease;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .modal-header {
        margin-bottom: 1.5rem;
        text-align: center;
        position: relative;
    }

    .modal-close {
        position: absolute;
        right: -10px;
        top: -10px;
        background: #fff;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #666;
        transition: all 0.3s ease;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group input {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-group input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .action-btn {
        width: 100%;
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.2);
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Budget Actions Styles */
    .budget-actions {
        display: flex;
        justify-content: flex-end; /* Change to flex-end */
        align-items: center;
        gap: 1rem;
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .filters {
        margin-right: auto; /* Add this to push filter to left */
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
                <a href="dashboard.php" class="nav-item">
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
                    <?php if ($pending_complaints > 0): ?>
                        <span class="nav-badge"><?php echo $pending_complaints; ?></span>
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

            <!-- Finance -->
            <div class="nav-section">
                <div class="nav-section-title">Finance</div>
                <a href="budgets.php" class="nav-item active">
                    <i class="fas fa-wallet"></i>
                    Budgets
                </a>
                <a href="expenses.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Expenses
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
            <form action="../../employee/logout.php" method="POST" id="logoutForm" style="width: 100%;">
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
            <h1 class="page-title">Budget Management</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area" style="background: #f8f9fa; border-radius: 12px; box-shadow: none;">
            <?php if (!empty($error)): ?>
        <div style="background:#ffe6e6;border:1px solid #ffb3b3;padding:12px;border-radius:8px;margin-bottom:16px;color:#900;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Overview with updated styling -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: white;">
            <div class="stat-icon" style="color: white;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <h3 style="color: white;">₱<?php echo number_format($stats['total_budget'], 2); ?></h3>
                <p style="color: rgba(255,255,255,0.9);">Total Budget</p>
            </div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white;">
            <div class="stat-icon" style="color: white;">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content">
                <h3 style="color: white;">₱<?php echo number_format($stats['available_budget'], 2); ?></h3>
                <p style="color: rgba(255,255,255,0.9);">Available Budget</p>
            </div>
        </div>
    </div>

    <!-- Budget Actions with enhanced styling -->
    <div class="budget-actions">
        <div class="filters">
            <select onchange="window.location='budgets.php?year='+this.value" 
                    name="year" 
                    style="padding: 0.75rem 1.5rem; border: 2px solid #e0e0e0; border-radius: 8px; min-width: 150px;">
                <?php foreach ($years as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button class="btn btn-add" type="button" onclick="showAddModal()" 
                style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border: none; padding: 0.75rem 1.5rem; color: white; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus"></i> Add Budget
        </button>
    </div>

    <!-- Update the budget table section -->
    <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Update the table header to include Actions column -->
        <table class="budget-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: left;">Date</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: left;">Item</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: right;">Total Budget</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: right;">Used</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: right;">Available</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($budgets as $budget): ?>
                <tr style="transition: background 0.3s ease;">
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: left;">
                        <?php echo date('M j, Y', strtotime($budget['budget_date'])); ?>
                    </td>
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: left;">
                        <?php echo htmlspecialchars($budget['item']); ?>
                    </td>
                    <td class="budget-amount" style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; font-family: 'Monaco', monospace; color: #333; text-align: right;">
                        ₱<?php echo number_format($budget['amount'], 2); ?>
                    </td>
                    <td class="budget-amount" style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; font-family: 'Monaco', monospace; color: #333; text-align: right;">
                        ₱<?php echo number_format($budget['used_amount'], 2); ?>
                    </td>
                    <td class="budget-amount" style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; font-family: 'Monaco', monospace; color: #333; text-align: right;">
                        ₱<?php echo number_format($budget['available_amount'], 2); ?>
                    </td>
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: center;">
                        <button onclick="editBudget(<?php echo $budget['id']; ?>)" 
                                style="background: #3498db; color: white; border: none; padding: 0.5rem; border-radius: 4px; margin-right: 0.5rem; cursor: pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteBudget(<?php echo $budget['id']; ?>)"
                                style="background: #e74c3c; color: white; border: none; padding: 0.5rem; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Single cleaned modal -->
    <div id="budgetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Budget</h2>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="budgetForm" method="post" onsubmit="return validateForm(event)">
                <div class="form-group">
                    <label for="item">
                        <i class="fas fa-shopping-cart"></i> Item
                    </label>
                    <input type="text" name="item" id="item" required placeholder="Enter budget item">
                </div>
                <div class="form-group">
                    <label for="amount">
                        <i class="fas fa-peso-sign"></i> Amount
                    </label>
                    <input type="number" name="amount" id="amount" step="0.01" required placeholder="Enter amount">
                </div>
                <div class="form-group">
                    <label for="budget_date">
                        <i class="fas fa-calendar"></i> Date
                    </label>
                    <input type="date" name="budget_date" id="budget_date" value="<?php echo date('Y-m-d'); ?>" readonly>
                </div>
                <button type="submit" class="action-btn">
                    <i class="fas fa-save"></i> Save Budget
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Budget Modal -->
    <div id="editBudgetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Budget</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editBudgetForm" method="post">
                <input type="hidden" name="edit_budget" value="1">
                <input type="hidden" name="budget_id" id="edit_budget_id">
                <div class="form-group">
                    <label for="edit_item">
                        <i class="fas fa-shopping-cart"></i> Item
                    </label>
                    <input type="text" name="item" id="edit_item" required placeholder="Enter budget item">
                </div>
                <div class="form-group">
                    <label for="edit_amount">
                        <i class="fas fa-peso-sign"></i> Amount
                    </label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" required placeholder="Enter amount">
                </div>
                <button type="submit" class="action-btn">
                    <i class="fas fa-save"></i> Update Budget
                </button>
            </form>
        </div>
    </div>
    </div>

    <script>
        // Replace existing script with treasurer's script functions
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
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

        // Budget-specific functions
        function showAddModal() {
            const modal = document.getElementById('budgetModal');
            const form = document.getElementById('budgetForm');
            if (modal && form) {
                form.reset();
                document.getElementById('budget_date').value = '<?php echo date('Y-m-d'); ?>';
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            const modal = document.getElementById('budgetModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function editBudget(id) {
    const budgets = <?php echo json_encode($budgets); ?>;
    // Convert id to string for comparison since JSON might convert it differently
    const budget = budgets.find(b => parseInt(b.id) === id);
    
    if (!budget) {
        Swal.fire({
            title: 'Error',
            text: 'Budget not found',
            icon: 'error'
        });
        return;
    }

    // Populate edit modal
    document.getElementById('edit_budget_id').value = budget.id;
    document.getElementById('edit_item').value = budget.item;
    document.getElementById('edit_amount').value = budget.amount;

    // Show modal
    const modal = document.getElementById('editBudgetModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeEditModal() {
    const modal = document.getElementById('editBudgetModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Update the existing validateForm function to handle edit form
function validateForm(event, isEdit = false) {
    event.preventDefault();
    const formId = isEdit ? 'edit_' : '';
    const item = document.getElementById(formId + 'item').value.trim();
    const amount = parseFloat(document.getElementById(formId + 'amount').value);

    if (!item) {
        Swal.fire({
            title: 'Error',
            text: 'Please enter an item name',
            icon: 'error'
        });
        return false;
    }

    if (isNaN(amount) || amount <= 0) {
        Swal.fire({
            title: 'Error',
            text: 'Please enter a valid amount greater than zero',
            icon: 'error'
        });
        return false;
    }

    event.target.submit();
    return true;
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
    
    const editForm = document.getElementById('editBudgetForm');
    if (editForm) {
        editForm.addEventListener('submit', (e) => validateForm(e, true));
    }
});

// Add success message handler
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        Swal.fire({
            title: 'Success',
            text: 'Budget updated successfully',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }
};

function deleteBudget(id) {
    const budgetId = id;
    Swal.fire({
        title: 'Delete Budget',
        text: "Are you sure you want to delete this budget? This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a form programmatically
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'budgets.php';

            // Add budget_id field
            const budgetIdField = document.createElement('input');
            budgetIdField.type = 'hidden';
            budgetIdField.name = 'budget_id';
            budgetIdField.value = budgetId;
            form.appendChild(budgetIdField);

            // Add delete_budget field
            const deleteBudgetField = document.createElement('input');
            deleteBudgetField.type = 'hidden';
            deleteBudgetField.name = 'delete_budget';
            deleteBudgetField.value = '1';
            form.appendChild(deleteBudgetField);

            // Append form to body and submit
            document.body.appendChild(form);
            form.submit();
        }
    });
}
    </script>
</body>
</html>