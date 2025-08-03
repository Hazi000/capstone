<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is authorized
//if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    //header("Location: ../index.php");
    //exit();
//}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $full_name = mysqli_real_escape_string($connection, $_POST['full_name']);
            $email = mysqli_real_escape_string($connection, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = mysqli_real_escape_string($connection, $_POST['role']);
            $status = mysqli_real_escape_string($connection, $_POST['status']);
            
            // Check if email already exists
            $check_query = "SELECT id FROM users WHERE email = '$email'";
            $check_result = mysqli_query($connection, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $_SESSION['error'] = "Email already exists!";
            } else {
                $insert_query = "INSERT INTO users (full_name, email, password, role, status) 
                                VALUES ('$full_name', '$email', '$password', '$role', '$status')";
                
                if (mysqli_query($connection, $insert_query)) {
                    $_SESSION['success'] = "User added successfully!";
                } else {
                    $_SESSION['error'] = "Error adding user: " . mysqli_error($connection);
                }
            }
            header("Location: account_management.php");
            exit();
            break;
            
        case 'edit':
            $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
            $full_name = mysqli_real_escape_string($connection, $_POST['full_name']);
            $email = mysqli_real_escape_string($connection, $_POST['email']);
            $role = mysqli_real_escape_string($connection, $_POST['role']);
            $status = mysqli_real_escape_string($connection, $_POST['status']);
            
            // Get original email
            $original_email_query = "SELECT email FROM users WHERE id = '$user_id'";
            $original_email_result = mysqli_query($connection, $original_email_query);
            $original_user = mysqli_fetch_assoc($original_email_result);
            $original_email = $original_user['email'];
            
            // Check if email changed
            $email_changed = ($email !== $original_email);
            
            // If email changed and not verified
            if ($email_changed && ($_POST['is_email_verified'] ?? '0') !== '1') {
                $_SESSION['error'] = "Please verify your new email address before updating";
                header("Location: account_management.php");
                exit;
            }
            
            // Check if email exists for other users
            $check_query = "SELECT id FROM users WHERE email = '$email' AND id != '$user_id'";
            $check_result = mysqli_query($connection, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $_SESSION['error'] = "Email already exists for another user!";
            } else {
                $update_query = "UPDATE users SET 
                                full_name = '$full_name',
                                email = '$email',
                                role = '$role',
                                status = '$status'
                                WHERE id = '$user_id'";
                
                // If password is provided, update it too
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET 
                                    full_name = '$full_name',
                                    email = '$email',
                                    password = '$password',
                                    role = '$role',
                                    status = '$status'
                                    WHERE id = '$user_id'";
                }
                
                if (mysqli_query($connection, $update_query)) {
                    $_SESSION['success'] = "User updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating user: " . mysqli_error($connection);
                }
            }
            header("Location: account_management.php");
            exit;
            break;
            
        case 'delete':
            $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
            
            // Prevent deleting own account
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own account!";
            } else {
                $delete_query = "DELETE FROM users WHERE id = '$user_id'";
                
                if (mysqli_query($connection, $delete_query)) {
                    $_SESSION['success'] = "User deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting user: " . mysqli_error($connection);
                }
            }
            header("Location: account_management.php");
            exit();
            break;
            
        case 'send_verification':
            $email = mysqli_real_escape_string($connection, $_POST['email']);
            $user_id = mysqli_real_escape_string($connection, $_POST['user_id']);
            
            // Generate verification code
            $verification_code = rand(100000, 999999);
            
            // Store in session
            $_SESSION['email_verification'] = [
                'code' => $verification_code,
                'email' => $email,
                'user_id' => $user_id,
                'expires' => time() + 600 // 10 minutes
            ];
            
            // In a real application, send the code via email
            // For demo, we'll just return it
            echo json_encode([
                'status' => 'success',
                'code' => $verification_code, // Remove in production
                'message' => 'Verification code sent to ' . $email
            ]);
            exit;
            break;
            
        case 'verify_code':
            $code = $_POST['code'] ?? '';
            $session_code = $_SESSION['email_verification']['code'] ?? '';
            
            if ($code === $session_code) {
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid verification code']);
            }
            exit;
            break;
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = mysqli_query($connection, $users_query);

// Get statistics
$total_users = mysqli_num_rows($users_result);
$active_users_query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
$active_users = mysqli_fetch_assoc(mysqli_query($connection, $active_users_query))['count'];
$captain_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'captain'";
$captain_count = mysqli_fetch_assoc(mysqli_query($connection, $captain_count_query))['count'];
$secretary_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'secretary'";
$secretary_count = mysqli_fetch_assoc(mysqli_query($connection, $secretary_count_query))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - Barangay Management System</title>
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
        }

        /* Sidebar styles (same as dashboard) */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease;
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

        /* Account Management Styles */
        .management-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-title h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .header-title p {
            color: #666;
        }

        .add-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f5f5f5;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .user-info-cell {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .user-details h4 {
            margin: 0;
            font-size: 1rem;
            color: #333;
        }

        .user-details p {
            margin: 0;
            font-size: 0.85rem;
            color: #666;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .role-captain {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-secretary {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-edit, .btn-delete {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-edit {
            background: #f39c12;
            color: white;
        }

        .btn-edit:hover {
            background: #e67e22;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #666;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: #333;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
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
            font-size: 1.2rem;
        }

        /* Mobile Responsiveness */
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                gap: 1rem;
            }

            .search-box {
                width: 100%;
            }

            .management-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .add-btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-edit, .btn-delete {
                width: 100%;
                justify-content: center;
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
        
        /* Verification button styles */
        .email-verify-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .email-verify-container input {
            flex: 1;
        }
        
        .email-verify-container button {
            white-space: nowrap;
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
                </a>
                <a href="certificates.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Certificates
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="account_management.php" class="nav-item active">
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Account Management</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #3498db;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #27ae60;">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $active_users; ?></h3>
                        <p>Active Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #e74c3c;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $captain_count; ?></h3>
                        <p>Captains</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: #f39c12;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $secretary_count; ?></h3>
                        <p>Secretaries</p>
                    </div>
                </div>
            </div>

            <!-- Management Header -->
            <div class="management-header">
                <div class="header-title">
                    <h1>User Accounts</h1>
                    <p>Manage system users and their permissions</p>
                </div>
                <button class="add-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i>
                    Add New User
                </button>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 style="margin: 0; color: #333;">All Users</h3>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchUsers()">
                    </div>
                </div>
                
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($users_result, 0);
                        while ($user = mysqli_fetch_assoc($users_result)): 
                        ?>
                            <tr>
                                <td>
                                    <div class="user-info-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($user['last_login']) {
                                        echo date('M d, Y h:i A', strtotime($user['last_login']));
                                    } else {
                                        echo '<span style="color: #999;">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($user); ?>)'>
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
                                        <button class="btn-delete" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New User</h2>
                <button class="close-modal" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="account_management.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="super_admin">Super admin</option>
                            <option value="captain">Captain</option>
                            <option value="secretary">Secretary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit User</h2>
                <button class="close-modal" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="account_management.php" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="is_email_verified" id="is_email_verified" value="0">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="email-verify-container">
                            <input type="email" name="email" id="edit_email" class="form-control" required oninput="checkEmailChanged()">
                            <button type="button" id="verify_email_btn" class="btn btn-primary" style="display: none;" onclick="sendVerificationCode()">
                                <i class="fas fa-paper-plane"></i> Verify
                            </button>
                        </div>
                    </div>
                    
                    <div id="verificationSection" style="display: none; margin-top: 15px; border-top: 1px solid #eee; padding-top: 15px;">
                        <div class="form-group">
                            <label class="form-label">Verification Code</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="text" id="verification_code" class="form-control" placeholder="Enter 6-digit code">
                                <button type="button" class="btn btn-primary" onclick="verifyCode()">
                                    <i class="fas fa-check"></i> Confirm
                                </button>
                            </div>
                            <p id="verification_status" style="margin-top: 10px; font-size: 0.9rem;"></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="super_admin">Super admin</option>
                            <option value="captain">Captain</option>
                            <option value="secretary">Secretary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="updateUserBtn">
                        <i class="fas fa-save"></i>
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Form (Hidden) -->
    <form method="POST" action="account_management.php" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>

    <script>
        // Global variables
        let originalEmail = '';
        let currentEmail = '';

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        // Logout confirmation
        function handleLogout() {
            if (confirm('Are you sure you want to logout?')) {
                document.getElementById('logoutForm').submit();
            }
        }

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            
            // Store original email
            originalEmail = user.email;
            currentEmail = user.email;
            
            // Reset verification UI
            document.getElementById('verify_email_btn').style.display = 'none';
            document.getElementById('verificationSection').style.display = 'none';
            document.getElementById('is_email_verified').value = '0';
            document.getElementById('verification_status').innerHTML = '';
            document.getElementById('verification_code').value = '';
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Delete confirmation
        function confirmDelete(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Search functionality
        function searchUsers() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length - 1; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set active nav item
            const currentPage = 'account_management.php';
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });
        
        // Email verification functions
        function checkEmailChanged() {
            currentEmail = document.getElementById('edit_email').value;
            const verifyBtn = document.getElementById('verify_email_btn');
            
            if (currentEmail !== originalEmail && currentEmail !== '') {
                verifyBtn.style.display = 'block';
                document.getElementById('is_email_verified').value = '0';
            } else {
                verifyBtn.style.display = 'none';
                document.getElementById('is_email_verified').value = '1';
            }
        }
        
        function sendVerificationCode() {
            const email = document.getElementById('edit_email').value;
            const userId = document.getElementById('edit_user_id').value;
            
            // Validate email format
            if (!validateEmail(email)) {
                document.getElementById('verification_status').innerHTML = 
                    '<span style="color: red;">Please enter a valid email address</span>';
                return;
            }
            
            // Show loading indicator
            document.getElementById('verification_status').innerHTML = 
                '<span style="color: #3498db;"><i class="fas fa-spinner fa-spin"></i> Sending verification code...</span>';
            
            // Send AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'account_management.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        if (response.status === 'success') {
                            document.getElementById('verificationSection').style.display = 'block';
                            document.getElementById('verification_status').innerHTML = 
                                `<span style="color: green;">${response.message}</span>
                                 <br><small style="color: #777;">For demo purposes, verification code: ${response.code}</small>`;
                        } else {
                            document.getElementById('verification_status').innerHTML = 
                                `<span style="color: red;">Error: ${response.message || 'Failed to send code'}</span>`;
                        }
                    } catch (e) {
                        document.getElementById('verification_status').innerHTML = 
                            '<span style="color: red;">Error processing response</span>';
                    }
                } else {
                    document.getElementById('verification_status').innerHTML = 
                        '<span style="color: red;">Error sending verification code</span>';
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('verification_status').innerHTML = 
                    '<span style="color: red;">Network error</span>';
            };
            
            xhr.send(`action=send_verification&email=${encodeURIComponent(email)}&user_id=${encodeURIComponent(userId)}`);
        }
        
        function verifyCode() {
            const code = document.getElementById('verification_code').value;
            
            if (!code || code.length !== 6) {
                document.getElementById('verification_status').innerHTML = 
                    '<span style="color: red;">Please enter a 6-digit verification code</span>';
                return;
            }
            
            // Show loading indicator
            document.getElementById('verification_status').innerHTML = 
                '<span style="color: #3498db;"><i class="fas fa-spinner fa-spin"></i> Verifying code...</span>';
            
            // Send AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'account_management.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        if (response.status === 'success') {
                            document.getElementById('verification_status').innerHTML = 
                                '<span style="color: green;">Email verified successfully!</span>';
                            document.getElementById('is_email_verified').value = '1';
                            
                            // Hide verify button
                            document.getElementById('verify_email_btn').style.display = 'none';
                        } else {
                            document.getElementById('verification_status').innerHTML = 
                                `<span style="color: red;">${response.message || 'Verification failed'}</span>`;
                        }
                    } catch (e) {
                        document.getElementById('verification_status').innerHTML = 
                            '<span style="color: red;">Error processing response</span>';
                    }
                } else {
                    document.getElementById('verification_status').innerHTML = 
                        '<span style="color: red;">Verification failed</span>';
                }
            };
            
            xhr.onerror = function() {
                document.getElementById('verification_status').innerHTML = 
                    '<span style="color: red;">Network error</span>';
            };
            
            xhr.send(`action=verify_code&code=${encodeURIComponent(code)}`);
        }
        
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
</body>
</html>