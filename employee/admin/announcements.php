<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// Default expiry date set to 1 week from now
$default_expiry = date('Y-m-d', strtotime('+1 week'));

// Handle Create/Update/Delete operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = mysqli_real_escape_string($connection, $_POST['title']);
                $content = mysqli_real_escape_string($connection, $_POST['content']);
                $announcement_type = mysqli_real_escape_string($connection, $_POST['announcement_type']);
                // Force expiry date to be 1 week from now
                $expiry_date = date('Y-m-d', strtotime('+1 week'));
                $created_by = $_SESSION['user_id'];

                $query = "INSERT INTO announcements (title, content, announcement_type, expiry_date, created_by, status) 
                         VALUES ('$title', '$content', '$announcement_type', '$expiry_date', '$created_by', 'active')";
                
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success_message'] = "Announcement created successfully!";
                } else {
                    $_SESSION['error_message'] = "Error creating announcement: " . mysqli_error($connection);
                }
                break;
                
            case 'update':
                $id = mysqli_real_escape_string($connection, $_POST['id']);
                $title = mysqli_real_escape_string($connection, $_POST['title']);
                $content = mysqli_real_escape_string($connection, $_POST['content']);
                $announcement_type = mysqli_real_escape_string($connection, $_POST['announcement_type']);
                $status = mysqli_real_escape_string($connection, $_POST['status']);

                $query = "UPDATE announcements 
                         SET title = '$title', content = '$content', 
                             announcement_type = '$announcement_type', 
                             status = '$status', 
                             updated_at = NOW()
                         WHERE id = '$id'";
                
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success_message'] = "Announcement updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Error updating announcement: " . mysqli_error($connection);
                }
                break;
                
            case 'delete':
                $id = mysqli_real_escape_string($connection, $_POST['id']);
                $query = "DELETE FROM announcements WHERE id = '$id'";
                
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success_message'] = "Announcement deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting announcement: " . mysqli_error($connection);
                }
                break;
        }
        
        header("Location: announcements.php");
        exit();
    }
}

// Get dashboard statistics for sidebar
$stats = [];
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];

$appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $appointment_query);
$stats['pending_appointments'] = mysqli_fetch_assoc($result)['pending'];

// Fetch all announcements
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : '';
$filter_type = isset($_GET['type']) ? mysqli_real_escape_string($connection, $_GET['type']) : '';

// --- added: pagination settings (10 per page) and COUNT query that respects filters
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_query = "SELECT COUNT(*) as total FROM announcements a WHERE 1=1";
if ($search) {
    $count_query .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
}
if ($filter_status) {
    $count_query .= " AND a.status = '$filter_status'";
}
if ($filter_type) {
    $count_query .= " AND a.announcement_type = '$filter_type'";
}
$count_result = mysqli_query($connection, $count_query);
$total_events = (int)mysqli_fetch_assoc($count_result)['total'];
$total_pages = max(1, (int)ceil($total_events / $per_page));
$page = min(max(1, $page), $total_pages);
$offset = ($page - 1) * $per_page;
// --- end added

$query = "SELECT a.*, u.full_name as created_by_name
          FROM announcements a 
          LEFT JOIN users u ON a.created_by = u.id 
          WHERE 1=1";

if ($search) {
    $query .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
}

if ($filter_status) {
    $query .= " AND a.status = '$filter_status'";
}

if ($filter_type) {
    $query .= " AND a.announcement_type = '$filter_type'";
}

$query .= " ORDER BY a.created_at DESC";
// apply limit/offset
$query .= " LIMIT $per_page OFFSET $offset";
$announcements = mysqli_query($connection, $query);

// Get announcement for editing if ID is provided
$edit_announcement = null;
if (isset($_GET['edit'])) {
    $edit_id = mysqli_real_escape_string($connection, $_GET['edit']);
    $edit_query = "SELECT * FROM announcements WHERE id = '$edit_id'";
    $edit_result = mysqli_query($connection, $edit_query);
    $edit_announcement = mysqli_fetch_assoc($edit_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add SweetAlert CDN -->
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Action Header */
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-title {
            font-size: 2rem;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Updated Filter Section Styles */
        .filter-section {
            background: white;
            padding: 0.5rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            width: fit-content;
        }

        .filter-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .filter-group {
            min-width: 120px;
            margin: 0;
        }

        .filter-group:first-child {
            min-width: 180px;
        }

        .filter-group label {
            display: none;
        }

        .form-control {
            height: 38px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .btn-filter {
            height: 38px;
            padding: 0 1rem;
            font-size: 0.875rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Action Header */
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-title {
            font-size: 2rem;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Updated Filter Section Styles */
        .filter-section {
            background: white;
            padding: 0.5rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            width: fit-content;
        }

        .filter-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .filter-group {
            min-width: 120px;
            margin: 0;
        }

        .filter-group:first-child {
            min-width: 180px;
        }

        .filter-group label {
            display: none;
        }

        .form-control {
            height: 38px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .btn-filter {
            height: 38px;
            padding: 0 1rem;
            font-size: 0.875rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-close {
            margin-left: auto;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Action Header */
        .action-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-title {
            font-size: 2rem;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Updated Filter Section Styles */
        .filter-section {
            background: white;
            padding: 0.5rem;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            width: fit-content;
        }

        .filter-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .filter-group {
            min-width: 120px;
            margin: 0;
        }

        .filter-group:first-child {
            min-width: 180px;
        }

        .filter-group label {
            display: none;
        }

        .form-control {
            height: 38px;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
        }

        .form-control::placeholder {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .btn-filter {
            height: 38px;
            padding: 0 1rem;
            font-size: 0.875rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .table tbody tr {
            transition: background 0.3s ease;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .announcement-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .announcement-content {
            color: #666;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .type-event {
            background: #e8d4f1;
            color: #7b4397;
        }

        .type-meeting {
            background: #d4edda;
            color: #155724;
        }

        .type-general {
            background: #ffecd1;
            color: #964b00;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .status-active {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .volunteer-info-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            background: #e8f4fd;
            color: #0066cc;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .volunteer-info-badge i {
            font-size: 0.7rem;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
        }

        .btn-edit:hover {
            background: #e67e22;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1050;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 95%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transform: translateY(-20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal.active {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal.active .modal-content {
            transform: translateY(0) scale(1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a202c;
        }

        .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: #f1f5f9;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 1.25rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Enhanced Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-control:hover {
            border-color: #cbd5e1;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* Enhanced Type Select */
        .type-select {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .type-label {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .type-label:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .type-option input[type="radio"]:checked + .type-label {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .type-option input[type="radio"]:checked + .type-label i {
            color: #3b82f6;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
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

        /* pagination styles */
        .pagination { display:flex; gap:0.5rem; justify-content:center; align-items:center; margin:1rem 0; flex-wrap:wrap; }
        .page-link { padding:0.45rem 0.75rem; border-radius:6px; background:#fff; border:1px solid #e6e6ea; color:#2c3e50; text-decoration:none; font-weight:600; }
        .page-link:hover { background:#f1f1f8; }
        .page-link.active { background:#2c3e50; color:#fff; border-color:#2c3e50; }
        .page-link.disabled { opacity:0.5; pointer-events:none; }
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
                <a href="announcements.php" class="nav-item active">
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
             <!-- Finance -->
            <div class="nav-section">
                <div class="nav-section-title">Finance</div>
                <a href="budgets.php" class="nav-item">
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
            <h1 class="page-title">Announcements</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <script>
                    window.onload = function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: '<?php echo addslashes($_SESSION['success_message']); ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        // Remove message from session to prevent repeat
                        <?php unset($_SESSION['success_message']); ?>
                        // Prevent form resubmission on refresh
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, window.location.href);
                        }
                    };
                </script>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <script>
                    window.onload = function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '<?php echo addslashes($_SESSION['error_message']); ?>',
                            timer: 2500,
                            showConfirmButton: false
                        });
                        <?php unset($_SESSION['error_message']); ?>
                        if (window.history.replaceState) {
                            window.history.replaceState(null, null, window.location.href);
                        }
                    };
                </script>
            <?php endif; ?>

            <!-- Action Header -->
            <div class="action-header">
                <h1 class="action-title">Manage Announcements</h1>
                <div class="action-buttons">
                    <!-- changed: add type="button" -->
                    <button type="button" class="btn btn-primary" onclick="openModal('create')">
                        <i class="fas fa-plus"></i>
                        New Announcement
                    </button>
                </div>
            </div>

            <div class="filter-section">
                <form method="GET" action="announcements.php" class="filter-row">
                    <div class="filter-group">
                        <input type="text" 
                               id="search" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search title or content"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <select id="type" name="type" class="form-control">
                            <option value="">All Types</option>
                            <option value="general" <?php echo $filter_type === 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="meeting" <?php echo $filter_type === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                </form>
            </div>

            <!-- Announcements Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($announcements) > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Announcement</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Expiry Date</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($announcement = mysqli_fetch_assoc($announcements)): ?>
                                <?php
                                    // Check if announcement is expired
                                    if ($announcement['expiry_date'] && strtotime($announcement['expiry_date']) < time() && $announcement['status'] !== 'expired') {
                                        $update_status = "UPDATE announcements SET status = 'expired' WHERE id = " . $announcement['id'];
                                        mysqli_query($connection, $update_status);
                                        $announcement['status'] = 'expired';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                        <div class="announcement-content"><?php echo htmlspecialchars($announcement['content']); ?></div>
                                    </td>
                                    <td>
                                        <?php
                                            $type = $announcement['announcement_type'] ?? 'general';
                                            $icon = '';
                                            switch($type) {
                                                case 'event':
                                                    $icon = 'fa-calendar-check';
                                                    break;
                                                case 'meeting':
                                                    $icon = 'fa-users';
                                                    break;
                                                default:
                                                    $icon = 'fa-info-circle';
                                            }
                                        ?>
                                        <span class="type-badge type-<?php echo $type; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                            <?php echo ucfirst($type); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $announcement['status']; ?>">
                                            <?php echo ucfirst($announcement['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $announcement['expiry_date'] ? date('M j, Y', strtotime($announcement['expiry_date'])) : 'No expiry'; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($announcement['created_by_name'] ?? 'Unknown'); ?>
                                        <br>
                                        <small style="color: #666;"><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-sm btn-edit" onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $announcement['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- added: pagination control -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="pagination" aria-label="Announcements pagination">
                            <?php if ($page > 1): ?>
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>

                            <?php
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);
                            if ($start > 1) {
                                echo '<a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page'=>1])).'">1</a>';
                                if ($start > 2) echo '<span class="page-link disabled">...</span>';
                            }
                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $page) echo '<span class="page-link active">'.$i.'</span>';
                                else echo '<a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page'=>$i])).'">'.$i.'</a>';
                            }
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) echo '<span class="page-link disabled">...</span>';
                                echo '<a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page'=>$total_pages])).'">'.$total_pages.'</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No announcements found</h3>
                        <p>Create your first announcement to get started.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Announcement Form Modal -->
    <div class="modal" id="announcementModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Create Announcement</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="announcementForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="announcementId">

                    <div class="form-group">
                        <label for="title">
                            <i class="fas fa-heading"></i> 
                            Title <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" 
                               id="title" 
                               name="title" 
                               class="form-control" 
                               placeholder="Enter announcement title"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="content">
                            <i class="fas fa-align-left"></i> 
                            Content <span style="color: #ef4444;">*</span>
                        </label>
                        <textarea id="content" 
                                  name="content" 
                                  class="form-control" 
                                  placeholder="Enter announcement content..."
                                  required></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-tag"></i> 
                            Announcement Type <span style="color: #ef4444;">*</span>
                        </label>
                        <div class="type-select">
                            <div class="type-option">
                                <input type="radio" id="type_general" name="announcement_type" value="general" checked>
                                <label for="type_general" class="type-label">
                                    <i class="fas fa-info-circle"></i>
                                    General
                                </label>
                            </div>
                            <div class="type-option">
                                <input type="radio" id="type_meeting" name="announcement_type" value="meeting">
                                <label for="type_meeting" class="type-label">
                                    <i class="fas fa-users"></i>
                                    Meeting
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-alt"></i> 
                            Expiry Period
                        </label>
                        <p style="color: #6b7280; margin-top: 0.5rem;">
                            All announcements automatically expire after 1 week from creation date.
                        </p>
                    </div>

                    <div class="form-group" id="statusGroup" style="display: none;">
                        <label for="status">
                            <i class="fas fa-toggle-on"></i> 
                            Status
                        </label>
                        <select id="status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <span id="submitButtonText">Create Announcement</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Handle logout
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

        // Modal functions
        function openModal(mode) {
            const modal = document.getElementById('announcementModal');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const submitButtonText = document.getElementById('submitButtonText');
            const statusGroup = document.getElementById('statusGroup');
            const form = document.getElementById('announcementForm');

            // Reset form
            form.reset();

            if (mode === 'create') {
                modalTitle.textContent = 'Create Announcement';
                formAction.value = 'create';
                submitButtonText.textContent = 'Create Announcement';
                statusGroup.style.display = 'none';
            }

            modal.classList.add('active');
        }

        function closeModal() {
            const modal = document.getElementById('announcementModal');
            modal.classList.remove('active');
        }

        // Edit announcement
        function editAnnouncement(id) {
            // Fetch announcement data
            window.location.href = `announcements.php?edit=${id}`;
        }

        // Handle edit mode
        <?php if ($edit_announcement): ?>
        window.onload = function() {
            const modal = document.getElementById('announcementModal');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const submitButtonText = document.getElementById('submitButtonText');
            const statusGroup = document.getElementById('statusGroup');

            modalTitle.textContent = 'Edit Announcement';
            formAction.value = 'update';
            submitButtonText.textContent = 'Update Announcement';
            statusGroup.style.display = 'block';

            // Populate form fields
            document.getElementById('announcementId').value = '<?php echo $edit_announcement['id']; ?>';
            document.getElementById('title').value = '<?php echo addslashes($edit_announcement['title']); ?>';
            document.getElementById('content').value = '<?php echo addslashes($edit_announcement['content']); ?>';
            
            // Set announcement type
            <?php $ann_type = $edit_announcement['announcement_type'] ?? 'general'; ?>
            document.getElementById('type_<?php echo $ann_type; ?>').checked = true;
            
            document.getElementById('status').value = '<?php echo $edit_announcement['status']; ?>';

            modal.classList.add('active');
        };
        <?php endif; ?>

        // Confirm delete
        function confirmDelete() {
            return confirm('Are you sure you want to delete this announcement? This action cannot be undone.');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcementModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close alert messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>