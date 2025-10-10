<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize empty arrays and get filter parameters
$years = [];
$expenses = [];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Get years for filter dropdown - Modified to handle empty table
$years_query = "SELECT DISTINCT YEAR(created_at) as year FROM expenses 
                UNION 
                SELECT YEAR(CURRENT_DATE) 
                ORDER BY year DESC";
$years_result = mysqli_query($connection, $years_query);
if ($years_result) {
    while ($row = mysqli_fetch_assoc($years_result)) {
        $years[] = $row['year'];
    }
}

// If no years found, add current year
if (empty($years)) {
    $years[] = date('Y');
}

// Get expenses with related data
$query = "SELECT e.*, u.full_name as created_by_name, b.item as budget_item 
          FROM expenses e 
          LEFT JOIN users u ON e.created_by = u.id 
          LEFT JOIN budgets b ON e.budget_id = b.id
          WHERE YEAR(e.created_at) = $year 
          AND MONTH(e.created_at) = $month
          ORDER BY e.created_at DESC";

$result = mysqli_query($connection, $query);
$expenses = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = $row;
    }
}

// Update stats query for monthly expenses
$stats = [
    'total_expenses' => 0,
    'monthly_expenses' => 0
];

$stats_query = "SELECT 
                COALESCE(SUM(amount), 0) as total_expenses,
                COALESCE(SUM(CASE WHEN YEAR(created_at) = $year AND MONTH(created_at) = $month THEN amount ELSE 0 END), 0) as monthly_expenses
                FROM expenses";

$stats_result = mysqli_query($connection, $stats_query);
if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
    $stats['total_expenses'] = $row['total_expenses'];
    $stats['monthly_expenses'] = $row['monthly_expenses'];
}

// Get available budgets
$budgets_query = "SELECT id, item, amount FROM budgets WHERE YEAR(budget_date) = $year";
$budgets_result = mysqli_query($connection, $budgets_query);
$available_budgets = [];
while ($row = mysqli_fetch_assoc($budgets_result)) {
    // Calculate available budget
    $used_query = "SELECT COALESCE(SUM(amount), 0) as used_amount FROM expenses WHERE budget_id = {$row['id']}";
    $used_result = mysqli_query($connection, $used_query);
    $used = mysqli_fetch_assoc($used_result)['used_amount'];
    $row['available'] = $row['amount'] - $used;
    $available_budgets[] = $row;
}

// Get pending complaints count
$pending_complaints_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$pending_result = mysqli_query($connection, $pending_complaints_query);
$pending_complaints = 0;
if ($pending_result && $row = mysqli_fetch_assoc($pending_result)) {
    $pending_complaints = $row['pending'];
}

// Handle expense operations (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $amount = floatval($_POST['amount']);
                $budget_id = intval($_POST['budget_id']);
                
                // Check available budget
                $budget_check_query = "SELECT b.amount - COALESCE(SUM(e.amount), 0) as available 
                                     FROM budgets b 
                                     LEFT JOIN expenses e ON b.id = e.budget_id 
                                     WHERE b.id = $budget_id 
                                     GROUP BY b.id, b.amount";
                $budget_result = mysqli_query($connection, $budget_check_query);
                $budget_data = mysqli_fetch_assoc($budget_result);
                
                if (!$budget_data) {
                    $_SESSION['error'] = "Invalid budget item selected.";
                    break;
                }

                if ($amount > $budget_data['available']) {
                    $_SESSION['error'] = "Amount exceeds available budget. Available: ₱" . number_format($budget_data['available'], 2);
                    break;
                }
                
                $current_date = date('Y-m-d H:i:s');
                $query = "INSERT INTO expenses (description, amount, created_at, created_by, budget_id) 
                         VALUES (?, ?, ?, ?, ?)";
                         
                $stmt = mysqli_prepare($connection, $query);
                mysqli_stmt_bind_param($stmt, "sdsii", $description, $amount, $current_date, $_SESSION['user_id'], $budget_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = "Expense added successfully!";
                } else {
                    $_SESSION['error'] = "Error adding expense: " . mysqli_error($connection);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'edit':
                $id = intval($_POST['id']);
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $amount = floatval($_POST['amount']);
                $budget_id = intval($_POST['budget_id']);
                
                // Check available budget
                $budget_check_query = "SELECT b.amount - COALESCE(SUM(e.amount), 0) as available 
                                     FROM budgets b 
                                     LEFT JOIN expenses e ON b.id = e.budget_id 
                                     WHERE b.id = $budget_id 
                                     GROUP BY b.id, b.amount";
                $budget_result = mysqli_query($connection, $budget_check_query);
                $budget_data = mysqli_fetch_assoc($budget_result);
                
                if (!$budget_data) {
                    $_SESSION['error'] = "Invalid budget item selected.";
                    break;
                }

                if ($amount > $budget_data['available']) {
                    $_SESSION['error'] = "Amount exceeds available budget. Available: ₱" . number_format($budget_data['available'], 2);
                    break;
                }
                
                $query = "UPDATE expenses SET description = ?, amount = ?, budget_id = ? WHERE id = ?";
                $stmt = mysqli_prepare($connection, $query);
                mysqli_stmt_bind_param($stmt, "sdii", $description, $amount, $budget_id, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = "Expense updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating expense: " . mysqli_error($connection);
                }
                mysqli_stmt_close($stmt);
                break;

            case 'delete':
                $id = intval($_POST['id']);
                $query = "DELETE FROM expenses WHERE id = $id";
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success'] = "Expense deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting expense: " . mysqli_error($connection);
                }
                break;
        }
        header("Location: expenses.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses Management - Barangay Management System</title>
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
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .modal.active {
        display: flex !important;
        animation: modalFadeIn 0.3s ease;
    }

    .modal-content {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 24px;
        padding: 2.5rem 3rem;
        width: 95%;
        max-width: 600px;
        position: relative;
        animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.9);
    }

    .modal-header {
        text-align: center;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid rgba(52, 152, 219, 0.1);
        position: relative;
    }

    .modal-header h2 {
        font-size: 1.8rem;
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0;
    }

    .modal-close {
        position: absolute;
        right: -15px;
        top: -15px;
        width: 36px;
        height: 36px;
        background: white;
        border: none;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #2c3e50;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 2px solid rgba(52, 152, 219, 0.1);
    }

    .modal-close:hover {
        transform: rotate(90deg);
        background: #f8f9fa;
        color: #e74c3c;
    }

    .form-group {
        margin-bottom: 2rem;
    }

    .form-group label {
        margin-bottom: 1rem;
        font-size: 1rem;
        color: #2c3e50;
        font-weight: 600;
    }

    .form-group label i {
        color: #3498db;
        margin-right: 8px;
    }

    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 1rem 1.2rem;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 1rem;
        color: #2c3e50;
        background: white;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .form-group textarea {
        min-height: 120px;
        font-size: 1rem;
        line-height: 1.5;
        padding: 1rem;
    }

    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    .form-group .amount-indicator {
        display: none;
        color: #e74c3c;
        font-size: 0.85rem;
        margin-top: 0.5rem;
        padding: 0.5rem;
        background: #fff3f3;
        border-radius: 6px;
        border: 1px solid #ffd1d1;
    }

    .action-btn {
        width: 100%;
        padding: 1.2rem;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: white;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
    }

    .action-btn i {
        font-size: 1.1rem;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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

    /* Custom Select2 Styles */
.select2-container {
    width: 100% !important;
}

.select2-container--default .select2-selection--single {
    height: 56px !important;
    padding: 12px !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 12px !important;
    background: white !important;
    display: flex !important;
    align-items: center !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #2c3e50 !important;
    line-height: 1.5 !important;
    padding-left: 5px !important;
    font-size: 1rem !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 54px !important;
    width: 40px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-width: 6px 6px 0 !important;
}

.select2-dropdown {
    border: 2px solid #e0e0e0 !important;
    border-radius: 12px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    padding: 8px !important;
}

.select2-search__field {
    padding: 12px !important;
    border-radius: 8px !important;
    border: 2px solid #e0e0e0 !important;
    font-size: 1rem !important;
}

.select2-container--default .select2-results__option {
    padding: 12px !important;
    border-radius: 8px !important;
    margin-bottom: 2px !important;
    font-size: 0.95rem !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3498db !important;
    border-radius: 8px !important;
}

/* Budget Actions Styles */
.budget-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filters {
    margin-right: auto;
}

.btn-add {
    margin-left: auto;
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
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
                <a href="budgets.php" class="nav-item">
                    <i class="fas fa-wallet"></i>
                    Budgets
                </a>
                <a href="expenses.php" class="nav-item active">
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
            <h1 class="page-title">Expenses Management</h1>
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
        <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white;">
            <div class="stat-icon" style="color: white;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3 style="color: white;">₱<?php echo number_format($stats['total_expenses'], 2); ?></h3>
                <p style="color: rgba(255,255,255,0.9);">Total Expenses</p>
            </div>
        </div>
        <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white;">
            <div class="stat-icon" style="color: white;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3 style="color: white;">₱<?php echo number_format($stats['monthly_expenses'], 2); ?></h3>
                <p style="color: rgba(255,255,255,0.9);">Monthly Expenses</p>
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

        <button class="btn btn-add" onclick="showAddModal()" 
                style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); border: none; padding: 0.75rem 1.5rem; color: white; border-radius: 8px; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus"></i> Add Expense
        </button>
    </div>

    <!-- Update the table section -->
    <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Update the table header -->
        <table class="budget-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: left;">Date</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: left;">Description</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: left;">Budget Item</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: right;">Amount</th>
                    <th style="background: #f8f9fa; padding: 1.2rem 1.5rem; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e9ecef; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                <tr style="transition: background 0.3s ease;">
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: left;">
                        <?php echo date('M j, Y', strtotime($expense['created_at'])); ?>
                    </td>
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: left;">
                        <?php echo htmlspecialchars($expense['description']); ?>
                    </td>
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: left;">
                        <?php echo htmlspecialchars($expense['budget_item'] ?? 'No Budget Item'); ?>
                    </td>
                    <td class="budget-amount" style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; font-family: 'Monaco', monospace; color: #333; text-align: right;">
                        ₱<?php echo number_format($expense['amount'], 2); ?>
                    </td>
                    <td style="padding: 1.2rem 1.5rem; border-bottom: 1px solid #f1f1f1; text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 0.5rem;">
                            <button onclick="editExpense(<?php echo $expense['id']; ?>, '<?php echo htmlspecialchars($expense['description']); ?>', <?php echo $expense['amount']; ?>, <?php echo $expense['budget_id']; ?>)" 
                                    class="btn-action btn-edit" 
                                    style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="confirmDelete(<?php echo $expense['id']; ?>)" 
                                    class="btn-action btn-delete" 
                                    style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Replace the modal HTML structure -->
<div id="budgetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="fas fa-plus-circle" style="margin-right: 10px; color: #3498db;"></i>
                Add New Expense
            </h2>
            <button class="modal-close" onclick="closeModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="budgetForm" method="post">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="budget_id">
                    <i class="fas fa-wallet"></i>
                    Select Budget Item
                </label>
                <select name="budget_id" id="budget_id" required class="form-control select2-search">
                    <option value="">Search budget item...</option>
                    <?php foreach ($available_budgets as $budget): ?>
                        <?php if ($budget['available'] > 0): ?>
                        <option value="<?php echo $budget['id']; ?>" data-available="<?php echo $budget['available']; ?>">
                            <?php echo htmlspecialchars($budget['item']); ?> 
                            (Available: ₱<?php echo number_format($budget['available'], 2); ?>)
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="available-budget" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    Available Budget: ₱<span id="availableAmount">0.00</span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">
                    <i class="fas fa-file-alt"></i>
                    Description
                </label>
                <textarea name="description" id="description" required 
                    placeholder="Enter a detailed description of the expense..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="amount">
                    <i class="fas fa-peso-sign"></i>
                    Amount
                </label>
                <input type="number" name="amount" id="amount" step="0.01" required 
                    placeholder="Enter expense amount">
                <div class="amount-indicator" id="amountIndicator">
                    <i class="fas fa-exclamation-circle"></i>
                    Amount exceeds available budget!
                </div>
            </div>
            
            <button type="submit" class="action-btn">
                <i class="fas fa-save"></i>
                Save Expense
            </button>
        </form>
    </div>
</div>

<!-- Make sure to add Select2 CSS and JS in the head section -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Update the JavaScript section -->
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}

function showAddModal() {
    const modal = document.getElementById('budgetModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('budgetModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
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

function validateBudgetAmount() {
    const amountInput = document.getElementById('amount');
    const budgetSelect = document.getElementById('budget_id');
    const amountIndicator = document.getElementById('amountIndicator');
    const selectedOption = budgetSelect.options[budgetSelect.selectedIndex];

    if (!selectedOption || !selectedOption.dataset.available) {
        return false;
    }

    const available = parseFloat(selectedOption.dataset.available);
    const amount = parseFloat(amountInput.value) || 0;

    if (amount > available) {
        amountInput.classList.add('error');
        amountIndicator.style.display = 'block';
        return false;
    }

    amountInput.classList.remove('error');
    amountIndicator.style.display = 'none';
    return true;
}

// Initialize everything when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2
    $('.select2-search').select2({
        dropdownParent: $('#budgetModal'),
        placeholder: "Search budget item...",
        allowClear: true,
        width: '100%'
    });

    // Handle budget selection change
    const budgetSelect = document.getElementById('budget_id');
    const availableBudgetDisplay = document.querySelector('.available-budget');
    const availableAmount = document.getElementById('availableAmount');

    if (budgetSelect) {
        budgetSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.available) {
                availableBudgetDisplay.style.display = 'block';
                availableAmount.textContent = parseFloat(selectedOption.dataset.available).toFixed(2);
            } else {
                availableBudgetDisplay.style.display = 'none';
            }
        });
    }

    // Handle amount input validation
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.addEventListener('input', validateBudgetAmount);
    }

    // Handle form submission
    const form = document.getElementById('budgetForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            if (validateBudgetAmount()) {
                this.submit();
            }
        });
    }

    // Handle modal closing
    const modal = document.getElementById('budgetModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    }
});

function editExpense(id, description, amount, budgetId) {
    const modal = document.getElementById('budgetModal');
    const form = document.getElementById('budgetForm');
    const modalTitle = document.getElementById('modalTitle');
    
    // Update form fields
    form.action.value = 'edit';
    form.id.value = id;
    form.description.value = description;
    form.amount.value = amount;
    $('#budget_id').val(budgetId).trigger('change');
    
    // Update modal title
    modalTitle.innerHTML = '<i class="fas fa-edit" style="margin-right: 10px; color: #3498db;"></i>Edit Expense';
    
    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Delete Expense',
        text: 'Are you sure you want to delete this expense?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#3085d6',
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

</body>
</html>