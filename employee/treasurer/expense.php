<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a treasurer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'treasurer') {
    header("Location: ../index.php");
    exit();
}

// Handle expense operations (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate and sanitize inputs
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $amount = floatval($_POST['amount']);
                $budget_id = intval($_POST['budget_id']);
                
                // Check available budget before adding expense
                $budget_check_query = "SELECT b.amount - COALESCE(SUM(e.amount), 0) as available 
                                     FROM budgets b 
                                     LEFT JOIN expenses e ON b.id = e.budget_id 
                                     WHERE b.id = $budget_id 
                                     GROUP BY b.id, b.amount";
                $budget_result = mysqli_query($connection, $budget_check_query);
                $budget_data = mysqli_fetch_assoc($budget_result);
                
                if (!$budget_data) {
                    $_SESSION['error'] = "Invalid budget item selected.";
                    header("Location: expense.php");
                    exit();
                }

                if ($amount > $budget_data['available']) {
                    $_SESSION['error'] = "Amount exceeds available budget. Available: ₱" . number_format($budget_data['available'], 2);
                    header("Location: expense.php");
                    exit();
                }
                
                // Use current date for created_at
                $current_date = date('Y-m-d H:i:s');
                
                // Modified query to match the correct column name
                $query = "INSERT INTO expenses (description, amount, created_at, created_by, budget_id) 
                         VALUES (?, ?, ?, ?, ?)";
                         
                $stmt = mysqli_prepare($connection, $query);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sdsii", $description, $amount, $current_date, $_SESSION['user_id'], $budget_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['success'] = "Expense added successfully!";
                    } else {
                        $_SESSION['error'] = "Error adding expense: " . mysqli_error($connection);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $_SESSION['error'] = "Error preparing statement: " . mysqli_error($connection);
                }
                break;

            case 'edit':
                $id = intval($_POST['id']);
                $description = mysqli_real_escape_string($connection, $_POST['description']);
                $amount = floatval($_POST['amount']);
                $budget_id = intval($_POST['budget_id']);
                
                $query = "UPDATE expenses 
                         SET description = '$description', 
                             amount = $amount, 
                             budget_id = $budget_id
                         WHERE id = $id";
                mysqli_query($connection, $query);
                break;

            case 'delete':
                $id = intval($_POST['id']);
                $query = "DELETE FROM expenses WHERE id = $id";
                mysqli_query($connection, $query);
                break;
        }
        
        // Redirect with status message
        header("Location: expense.php");
        exit();
    }
}

// Initialize empty arrays and get filter parameters
$years = [];
$expenses = [];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// First get years for filter dropdown - Modified to handle empty table
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

// Get months array for display
$months = [
    1 => 'January', 2 => 'February', 3 => 'March',
    4 => 'April', 5 => 'May', 6 => 'June',
    7 => 'July', 8 => 'August', 9 => 'September',
    10 => 'October', 11 => 'November', 12 => 'December'
];

// Add pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Modify main query to include pagination
$query = "SELECT e.*, u.full_name as created_by_name, b.item as budget_item 
          FROM expenses e 
          LEFT JOIN users u ON e.created_by = u.id 
          LEFT JOIN budgets b ON e.budget_id = b.id
          WHERE YEAR(e.created_at) = $year 
          AND MONTH(e.created_at) = $month
          ORDER BY e.created_at DESC
          LIMIT $items_per_page OFFSET $offset";

// Add total pages calculation
$total_records_query = "SELECT COUNT(*) as count 
                       FROM expenses 
                       WHERE YEAR(created_at) = $year 
                       AND MONTH(created_at) = $month";
$total_result = mysqli_query($connection, $total_records_query);
$total_records = mysqli_fetch_assoc($total_result)['count'];
$total_pages = ceil($total_records / $items_per_page);

$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = $row;
    }
}

// Update stats query for monthly expenses
$stats = [
    'total_expenses' => 0  // Initialize with default value
];

$stats_query = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                FROM expenses 
                WHERE YEAR(created_at) = $year 
                AND MONTH(created_at) = $month";

$stats_result = mysqli_query($connection, $stats_query);
if ($stats_result && $row = mysqli_fetch_assoc($stats_result)) {
    $stats['total_expenses'] = $row['total_expenses'];
}

// Add after other query parameters
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add Select2 CSS + jQuery + Select2 JS (required so budget item search works) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Add SweetAlert2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <style>
        /* Reuse exact dashboard styles */
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

        /* Update sidebar styles to match dashboard */
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

        .sidebar.active {
            transform: translateX(0);
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

        /* Main content styles */
        .main-content {
            margin-left: 280px;
            padding: 1rem;
            transition: margin 0.3s ease;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: #007bff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 500;
        }

        .content-area {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Updated Budget Management Styles */
        .budget-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .budget-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filters {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filters select {
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: white;
            min-width: 150px;
            font-size: 0.9rem;
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filters select:hover {
            border-color: #3498db;
        }

        .btn-add {
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        /* Replace the existing budget-table styles with these updated ones */
        .budget-table {
            width: 100%;
            background: #F9FAFB;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #D1D5DB;
            margin-top: 2rem;
        }

        .budget-table th {
            background: #F3F4F6;
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            color: #111827;
            text-align: left;
            border-bottom: 2px solid #D1D5DB;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .budget-table td {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #D1D5DB;
            color: #111827;
            vertical-align: middle;
        }

        .budget-table tr:nth-child(even) {
            background: #F3F4F6;
        }

        .budget-table tr:hover {
            background: #F3F4F6;
            transition: background-color 0.3s ease;
        }

        .budget-amount {
            font-family: 'Monaco', monospace;
            font-size: 1rem;
            color: #15803D;
            font-weight: 500;
        }

        .budget-amount.text-danger {
            color: #B91C1C;
        }

        /* Modal Styles */
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
            display: flex;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            width: 95%;
            max-width: 550px;
            position: relative;
            animation: modalSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-header {
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .modal-close {
            position: absolute;
            right: -1rem;
            top: -1rem;
            background: #fff;
            border: none;
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            transform: rotate(90deg);
            color: #e74c3c;
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            font-size: 1rem;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        .action-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }

        .action-btn:active {
            transform: translateY(0);
        }

        @keyframes modalSlideIn {
            0% {
                transform: translateY(-60px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Enhanced Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 1.5rem;
        }

        .stat-content h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Enhanced Table Design */
        .budget-table {
            margin-top: 2rem;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .budget-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .budget-table td {
            padding: 1.2rem 1.5rem;
            vertical-align: middle;
        }

        .budget-table tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        /* Enhanced Buttons */
        .btn-add {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            padding: 0.8rem 1.8rem;
            border-radius: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .btn-edit, .btn-delete {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }

        /* Enhanced Status Badges */
        .status-badge {
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-active {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }

        .status-inactive {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }

        /* Enhanced Modal */
        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            background: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Enhanced Filters */
        .filters select {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            min-width: 180px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .filters select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Add Select2 Custom Styles */
        .select2-container--default {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single {
            height: 48px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 32px;
            color: #2c3e50;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }

        .select2-dropdown {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .select2-search__field {
            padding: 8px !important;
            border-radius: 8px !important;
        }

        .select2-results__option {
            padding: 8px 12px;
        }

        .select2-results__option--highlighted {
            background-color: #3498db !important;
        }

        /* Add this new CSS rule for the error state */
        .form-group input.error {
            border-color: #e74c3c;
            background-color: #fff6f6;
        }

        /* Add these new styles */
        .form-group .amount-indicator {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none;
        }

        .form-group .amount-exceeded {
            color: #e74c3c;
            display: block;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: #F9FAFB;
            border: 2px solid #D1D5DB;
            border-radius: 8px;
            color: #111827;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: #F3F4F6;
            border-color: #3498db;
            color: #3498db;
            transform: translateY(-2px);
        }

        .page-link.active {
            background: #3498db;
            border-color: #3498db;
            color: white;
            pointer-events: none;
        }

        .page-link i {
            font-size: 0.8rem;
            margin: 0 0.3rem;
        }

        /* Responsive pagination */
        @media (max-width: 768px) {
            .pagination {
                gap: 0.3rem;
            }
            
            .page-link {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
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
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    Dashboard
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Finance</div>
                <a href="expense.php" class="nav-item active">
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

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

        <div class="content-area">
            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #e74c3c;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>₱<?php echo number_format($stats['total_expenses'], 2); ?></h3>
                        <p>Total Expense</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color: #3498db;">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>₱<?php echo number_format($stats['total_expenses'], 2); ?></h3>
                        <p><?php echo $months[$month]; ?> Expenses</p>
                    </div>
                </div>
            </div>

            <!-- Budget Actions -->
            <div class="budget-actions">
                <div class="filters">
                    <select onchange="this.form.submit()" name="month" form="filter-form">
                        <?php
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March',
                            4 => 'April', 5 => 'May', 6 => 'June',
                            7 => 'July', 8 => 'August', 9 => 'September',
                            10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        foreach ($months as $m => $monthName): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                            <?php echo $monthName; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select onchange="this.form.submit()" name="year" form="filter-form">
                        <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <form id="filter-form" method="get"></form>
                </div>

                <button class="btn btn-add" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>

            <!-- Budget Table -->
            <table class="budget-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Budget Item</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($expense['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($expense['description']); ?></td>
                        <td><?php echo htmlspecialchars($expense['budget_item'] ?? 'No Budget Item'); ?></td>
                        <td class="budget-amount">₱<?php echo number_format($expense['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&page=<?php echo ($page-1); ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4) {
                    $start_page = max(1, $end_page - 4);
                }
                ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&page=<?php echo $i; ?>" 
                       class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?year=<?php echo $year; ?>&month=<?php echo $month; ?>&page=<?php echo ($page+1); ?>" class="page-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Add/Edit Modal -->
            <div id="budgetModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modalTitle">Add Expense</h2>
                        <button class="modal-close" onclick="closeModal()">×</button>
                    </div>
                    <form id="budgetForm" method="post">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="budgetId">
                        
                        <div class="form-group">
                            <label for="budget_id">
                                <i class="fas fa-wallet"></i> Budget Item
                            </label>
                            <select name="budget_id" id="budget_id" required class="form-control select2-search">
                                <option value="">Search Budget Item</option>
                                <?php foreach ($available_budgets as $budget): ?>
                                <option value="<?php echo $budget['id']; ?>" data-available="<?php echo $budget['available']; ?>">
                                    <?php echo htmlspecialchars($budget['item']); ?> 
                                    (Available: ₱<?php echo number_format($budget['available'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">
                                <i class="fas fa-file-alt"></i> Description
                            </label>
                            <textarea name="description" id="description" required 
                                placeholder="Enter budget description..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">
                                <i class="fas fa-peso-sign"></i> Amount
                            </label>
                            <input type="number" name="amount" id="amount" step="0.01" required 
                                placeholder="Enter amount">
                            <div class="amount-indicator" id="amountIndicator">Amount exceeded available budget!</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="budget_date">
                                <i class="fas fa-calendar"></i> Date
                            </label>
                            <input type="date" 
                                   name="budget_date" 
                                   id="budget_date" 
                                   value="<?php echo date('Y-m-d'); ?>" 
                                   readonly 
                                   style="background-color: #f8f9fa; cursor: not-allowed;">
                        </div>
                        
                        <button type="submit" class="action-btn">
                            <i class="fas fa-save"></i> Save Expense
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Keep only these essential functions
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }

        function showAddModal() {
            Swal.fire({
                title: 'Add New Expense',
                text: 'Do you want to add a new expense record?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add new',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#3085d6',
            }).then((result) => {
                if (result.isConfirmed) {
                    const modal = document.getElementById('budgetModal');
                    document.getElementById('modalTitle').textContent = 'Add Expense';
                    document.getElementById('formAction').value = 'add';
                    document.getElementById('budgetForm').reset();
                    
                    // Set current date and ensure it's read-only
                    const today = new Date().toISOString().split('T')[0];
                    const dateInput = document.getElementById('budget_date');
                    dateInput.value = today;
                    dateInput.setAttribute('readonly', true);
                    
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            });
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

        // Single place for modal + select2 + validation logic
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 on budget selector(s)
            if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
                $('.select2-search').select2({
                    dropdownParent: $('#budgetModal'),
                    placeholder: "Search budget item...",
                    allowClear: true,
                    width: '100%'
                });
            }

            // Modal helpers
            function openModal() {
                const modal = document.getElementById('budgetModal');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                const modal = document.getElementById('budgetModal');
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }

            // Expose showAddModal for buttons
            window.showAddModal = function() {
                // Reset form and UI
                const form = document.getElementById('budgetForm');
                if (form) form.reset();

                // Reset select2 selection
                if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
                    $('.select2-search').val(null).trigger('change');
                }

                // Reset available display & indicator
                const avail = document.querySelector('.available-budget');
                if (avail) avail.style.display = 'none';
                const indicator = document.getElementById('amountIndicator');
                if (indicator) indicator.style.display = 'none';

                openModal();
            };

            // Wire close button
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', closeModal);
            });

            // Close when clicking outside modal-content
            const budgetModal = document.getElementById('budgetModal');
            if (budgetModal) {
                budgetModal.addEventListener('click', function(e) {
                    if (e.target === this) closeModal();
                });
            }

            // Budget selection -> show available amount
            const budgetSelect = document.getElementById('budget_id');
            const availableBudgetDisplay = document.querySelector('.available-budget');
            const availableAmountEl = document.getElementById('availableAmount');

            function updateAvailableDisplay() {
                if (!budgetSelect) return;
                const opt = budgetSelect.options[budgetSelect.selectedIndex];
                if (opt && opt.dataset && opt.dataset.available) {
                    if (availableBudgetDisplay) availableBudgetDisplay.style.display = 'block';
                    if (availableAmountEl) availableAmountEl.textContent = parseFloat(opt.dataset.available).toFixed(2);
                } else {
                    if (availableBudgetDisplay) availableBudgetDisplay.style.display = 'none';
                    if (availableAmountEl) availableAmountEl.textContent = '0.00';
                }
            }

            if (budgetSelect) {
                budgetSelect.addEventListener('change', updateAvailableDisplay);
            }

            // Validation logic used on submit and in real-time
            function validateBudgetAmount() {
                const amountInput = document.getElementById('amount');
                const indicator = document.getElementById('amountIndicator');
                const opt = budgetSelect ? budgetSelect.options[budgetSelect.selectedIndex] : null;

                if (!opt || !opt.dataset || typeof opt.dataset.available === 'undefined' || opt.value === '') {
                    Swal.fire({
                        title: 'Error',
                        text: 'Please select a budget item first.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return false;
                }

                const available = parseFloat(opt.dataset.available) || 0;
                const amount = parseFloat(amountInput.value) || 0;

                if (amount > available) {
                    amountInput.classList.add('error');
                    if (indicator) {
                        indicator.classList.add('amount-exceeded');
                        indicator.style.display = 'block';
                    }
                    return false;
                }

                amountInput.classList.remove('error');
                if (indicator) {
                    indicator.classList.remove('amount-exceeded');
                    indicator.style.display = 'none';
                }
                return true;
            }

            // Real-time check
            const amountInputEl = document.getElementById('amount');
            if (amountInputEl) amountInputEl.addEventListener('input', validateBudgetAmount);

            // Form submission
            const budgetForm = document.getElementById('budgetForm');
            if (budgetForm) {
                budgetForm.addEventListener('submit', function(e) {
                    if (!validateBudgetAmount()) {
                        e.preventDefault();
                        return false;
                    }
                    // allow normal submission
                });
            }

            // Optionally pre-populate available if select has initial value
            updateAvailableDisplay();
        });

        // Show SweetAlert2 notifications for PHP session messages
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION["error"])): ?>
                Swal.fire({
                    title: 'Error!',
                    text: '<?php echo addslashes($_SESSION["error"]); ?>',
                    icon: 'error',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
                <?php unset($_SESSION["error"]); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION["success"])): ?>
                Swal.fire({
                    title: 'Success!',
                    text: '<?php echo addslashes($_SESSION["success"]); ?>',
                    icon: 'success',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
                <?php unset($_SESSION["success"]); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>

