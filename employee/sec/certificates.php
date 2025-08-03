<?php
session_start();
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get certificate settings from database (if exists)
$settings_query = "SELECT * FROM certificate_settings WHERE id = 1";
$settings_result = mysqli_query($connection, $settings_query);
$settings = mysqli_fetch_assoc($settings_result);

// Default settings if not in database
if (!$settings) {
    $settings = [
        'barangay_name' => 'Barangay Cawit',
        'municipality' => 'Zamboanga City',
        'province' => 'Province of Zamboanga Del Sur',
        'country' => 'Republic of the Philippines',
        'captain_name' => 'N/A',
        'logo_path' => '../sec/assets/images/barangay-logo.png'
    ];
}

// Get statistics for nav badges
$stats = [];

// Get pending complaints count (with error handling)
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_complaint_query);
if ($result) {
    $stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
} else {
    $stats['pending_complaints'] = 0;
}

// Get pending certificate requests count (with error handling)
$pending_cert_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_cert_query);
if ($result) {
    $stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
} else {
    $stats['pending_certificates'] = 0;
}

// Handle certificate request actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $request_id = intval($_POST['request_id']);
    
    if ($action == 'approve') {
        $update_query = "UPDATE certificate_requests SET 
                        status = 'approved',
                        processed_date = NOW(),
                        processed_by = {$_SESSION['user_id']}
                        WHERE id = $request_id";
        if (mysqli_query($connection, $update_query)) {
            // Log the action (if table exists)
            $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status) 
                          VALUES ($request_id, 'approved', {$_SESSION['user_id']}, 'pending', 'approved')";
            mysqli_query($connection, $log_query); // Don't check for errors on log table
        }
        
        header("Location: certificates.php?tab=requests&msg=approved");
        exit();
    } elseif ($action == 'reject') {
        $reason = mysqli_real_escape_string($connection, $_POST['rejection_reason']);
        $update_query = "UPDATE certificate_requests SET 
                        status = 'rejected',
                        processed_date = NOW(),
                        processed_by = {$_SESSION['user_id']},
                        rejection_reason = '$reason'
                        WHERE id = $request_id";
        if (mysqli_query($connection, $update_query)) {
            // Log the action (if table exists)
            $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status, remarks) 
                          VALUES ($request_id, 'rejected', {$_SESSION['user_id']}, 'pending', 'rejected', '$reason')";
            mysqli_query($connection, $log_query); // Don't check for errors on log table
        }
        
        header("Location: certificates.php?tab=requests&msg=rejected");
        exit();
    } elseif ($action == 'mark_claimed') {
        $or_number = mysqli_real_escape_string($connection, $_POST['or_number']);
        $update_query = "UPDATE certificate_requests SET 
                        status = 'claimed',
                        claim_date = NOW(),
                        or_number = '$or_number'
                        WHERE id = $request_id";
        if (mysqli_query($connection, $update_query)) {
            // Log the action (if table exists)
            $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status) 
                          VALUES ($request_id, 'claimed', {$_SESSION['user_id']}, 'approved', 'claimed')";
            mysqli_query($connection, $log_query); // Don't check for errors on log table
        }
        
        header("Location: certificates.php?tab=requests&msg=claimed");
        exit();
    }
}

// Get certificate requests with resident information (with better error handling)
$certificate_requests = null;

// First, try the full query with JOIN
$requests_query = "SELECT cr.*, r.full_name, r.phone, r.email, r.address,
                   cr.certificate_type as certificate_name,
                   u.full_name as processed_by_name
                   FROM certificate_requests cr
                   LEFT JOIN residents r ON cr.resident_id = r.id
                   LEFT JOIN users u ON cr.processed_by = u.id
                   ORDER BY cr.created_at DESC";
$certificate_requests = mysqli_query($connection, $requests_query);

// If that fails, try a simpler query without JOIN
if (!$certificate_requests) {
    $requests_query = "SELECT cr.*, cr.certificate_type as certificate_name
                       FROM certificate_requests cr
                       ORDER BY cr.created_at DESC";
    $certificate_requests = mysqli_query($connection, $requests_query);
}

// If still fails, create empty result
if (!$certificate_requests) {
    $certificate_requests = [];
    $db_error = "Database tables are not properly set up. Please run the SQL setup script first.";
}

// Handle form submission for certificate generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    $resident_name = mysqli_real_escape_string($connection, $_POST['resident_name']);
    $age = mysqli_real_escape_string($connection, $_POST['age']);
    $purpose = mysqli_real_escape_string($connection, $_POST['purpose']);
    $or_number = mysqli_real_escape_string($connection, $_POST['or_number']);
    $amount_paid = mysqli_real_escape_string($connection, $_POST['amount_paid']);
    
    // Save certificate record (if table exists)
    $insert_query = "INSERT INTO certificates (resident_name, age, purpose, or_number, amount_paid, issued_date, issued_by) 
                     VALUES ('$resident_name', '$age', '$purpose', '$or_number', '$amount_paid', NOW(), '{$_SESSION['user_id']}')";
    mysqli_query($connection, $insert_query);
    $certificate_id = mysqli_insert_id($connection);
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $barangay_name = mysqli_real_escape_string($connection, $_POST['barangay_name']);
    $municipality = mysqli_real_escape_string($connection, $_POST['municipality']);
    $province = mysqli_real_escape_string($connection, $_POST['province']);
    $country = mysqli_real_escape_string($connection, $_POST['country']);
    $captain_name = mysqli_real_escape_string($connection, $_POST['captain_name']);
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../sec/assets/images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = 'barangay-logo-' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
            $logo_path = $upload_path;
        }
    } else {
        $logo_path = $settings['logo_path'];
    }
    
    // Check if settings exist
    $check_query = "SELECT id FROM certificate_settings WHERE id = 1";
    $check_result = mysqli_query($connection, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $update_query = "UPDATE certificate_settings SET 
                        barangay_name = '$barangay_name',
                        municipality = '$municipality',
                        province = '$province',
                        country = '$country',
                        captain_name = '$captain_name',
                        logo_path = '$logo_path'
                        WHERE id = 1";
    } else {
        $update_query = "INSERT INTO certificate_settings (barangay_name, municipality, province, country, captain_name, logo_path) 
                        VALUES ('$barangay_name', '$municipality', '$province', '$country', '$captain_name', '$logo_path')";
    }
    
    mysqli_query($connection, $update_query);
    header("Location: certificates.php?settings=updated");
    exit();
}

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'generate';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Certificates - Barangay Management System</title>
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

        /* Sidebar Styles - Copied from dashboard */
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

        /* Certificate Specific Styles */
        .certificate-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .certificate-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #eee;
        }

        .certificate-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tabs {
            display: flex;
            gap: 10px;
            padding: 0 2rem;
            background: white;
            border-bottom: 2px solid #ecf0f1;
        }

        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: #666;
            position: relative;
        }

        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #e74c3c;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s;
            display: inline-flex;
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

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
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

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Certificate Preview Styles */
        .certificate-preview {
            border: 3px double #000;
            padding: 40px;
            margin: 20px auto;
            max-width: 800px;
            background: white;
            font-family: 'Times New Roman', serif;
        }

        .cert-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .cert-logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
        }

        .cert-title {
            font-size: 28px;
            font-weight: bold;
            margin: 20px 0;
            letter-spacing: 2px;
        }

        .cert-body {
            margin: 30px 0;
            line-height: 2;
            text-align: justify;
        }

        .cert-footer {
            margin-top: 80px;
            text-align: right;
        }

        .signature-block {
            display: inline-block;
            text-align: center;
            margin-top: 60px;
        }

        .signature-line {
            border-bottom: 2px solid #000;
            width: 250px;
            margin-bottom: 5px;
        }

        .cert-details {
            margin-top: 40px;
        }

        .detail-row {
            margin: 10px 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .certificate-preview {
                border: none;
                margin: 0;
                padding: 20px;
            }
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .logo-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Requests Table Styles */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .requests-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .requests-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .requests-table tr:hover {
            background: #f8f9fa;
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

        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }

        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-claimed {
            background: #d4edda;
            color: #155724;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .close {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .request-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
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

            .content-area {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .requests-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .tabs {
                flex-wrap: wrap;
                padding: 0 1rem;
            }

            .tab {
                flex: 1;
                text-align: center;
                font-size: 0.9rem;
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
                Barangay Management
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role">Secretary</div>
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
                    <?php if ($stats['pending_complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="certificates.php" class="nav-item active">
                    <i class="fas fa-certificate"></i>
                    Certificates
                    <?php if ($stats['pending_certificates'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_certificates']; ?></span>
                    <?php endif; ?>
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
            <h1 class="page-title">Certificate Management</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($db_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $db_error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['settings']) && $_GET['settings'] == 'updated'): ?>
                <div class="alert alert-success no-print">
                    <i class="fas fa-check-circle"></i> Certificate settings updated successfully!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] == 'approved'): ?>
                    <div class="alert alert-success no-print">
                        <i class="fas fa-check-circle"></i> Certificate request approved successfully!
                    </div>
                <?php elseif ($_GET['msg'] == 'rejected'): ?>
                    <div class="alert alert-warning no-print">
                        <i class="fas fa-times-circle"></i> Certificate request rejected!
                    </div>
                <?php elseif ($_GET['msg'] == 'claimed'): ?>
                    <div class="alert alert-info no-print">
                        <i class="fas fa-check-circle"></i> Certificate marked as claimed!
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="certificate-container">
                <div class="certificate-header no-print">
                    <h2 class="certificate-title">
                        <i class="fas fa-certificate" style="color: #3498db;"></i>
                        Barangay Certificate Management
                    </h2>
                </div>

                <div class="tabs no-print">
                    <div class="tab <?php echo $active_tab == 'generate' ? 'active' : ''; ?>" onclick="switchTab(event, 'generate')">
                        <i class="fas fa-file-alt"></i> Generate Certificate
                    </div>
                    <div class="tab <?php echo $active_tab == 'requests' ? 'active' : ''; ?>" onclick="switchTab(event, 'requests')">
                        <i class="fas fa-inbox"></i> Requested Certificates
                        <?php if ($stats['pending_certificates'] > 0): ?>
                            <span class="tab-badge"><?php echo $stats['pending_certificates']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="tab <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" onclick="switchTab(event, 'settings')">
                        <i class="fas fa-cog"></i> Certificate Settings
                    </div>
                </div>

                <!-- Generate Certificate Tab -->
                <div id="generate-tab" class="tab-content <?php echo $active_tab == 'generate' ? 'active' : ''; ?>">
                    <?php if (!isset($_POST['generate'])): ?>
                        <form method="POST" class="no-print">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="resident_name">Resident Name</label>
                                    <input type="text" id="resident_name" name="resident_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="age">Age</label>
                                    <input type="number" id="age" name="age" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="purpose">Purpose</label>
                                <input type="text" id="purpose" name="purpose" class="form-control" 
                                       placeholder="e.g., for whatever legal purposes it may serve" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="or_number">O.R. Number</label>
                                    <input type="text" id="or_number" name="or_number" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="amount_paid">Amount Paid</label>
                                    <input type="text" id="amount_paid" name="amount_paid" class="form-control" required>
                                </div>
                            </div>

                            <button type="submit" name="generate" class="btn btn-primary">
                                <i class="fas fa-print"></i> Generate Certificate
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Certificate Preview -->
                        <div class="no-print" style="margin-bottom: 20px;">
                            <button onclick="window.print()" class="btn btn-success">
                                <i class="fas fa-print"></i> Print Certificate
                            </button>
                            <a href="certificates.php" class="btn btn-secondary">
                                <i class="fas fa-plus"></i> Generate New
                            </a>
                        </div>

                        <div class="certificate-preview">
                            <div class="cert-header">
                                <?php if (file_exists($settings['logo_path'])): ?>
                                    <img src="<?php echo $settings['logo_path']; ?>" alt="Barangay Logo" class="cert-logo">
                                <?php else: ?>
                                    <div class="cert-logo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-building" style="font-size: 48px; color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div><?php echo $settings['country']; ?></div>
                                <div><?php echo $settings['province']; ?></div>
                                <div><?php echo $settings['municipality']; ?></div>
                                <div style="font-size: 24px; font-weight: bold; margin-top: 10px;">
                                    <?php echo $settings['barangay_name']; ?>
                                </div>
                                <div style="border-bottom: 3px solid #000; margin: 20px auto; width: 80%;"></div>
                                
                                <div style="margin-top: 30px; font-weight: bold;">
                                    OFFICE OF THE BARANGAY CAPTAIN
                                </div>
                                
                                <h1 class="cert-title">BARANGAY CLEARANCE</h1>
                            </div>

                            <div class="cert-body">
                                <p style="font-weight: bold; margin-bottom: 30px;">TO WHOM IT MAY CONCERN:</p>
                                
                                <p style="text-indent: 50px;">
                                    This is to certify that <strong style="border-bottom: 1px solid #000; padding: 0 10px;">
                                    <?php echo htmlspecialchars($_POST['resident_name']); ?></strong>, 
                                    <strong style="border-bottom: 1px solid #000; padding: 0 10px;"><?php echo htmlspecialchars($_POST['age']); ?></strong> years old, 
                                    and a resident of <?php echo $settings['barangay_name']; ?>, <?php echo $settings['municipality']; ?>, 
                                    <?php echo str_replace('Province of ', '', $settings['province']); ?> is known to be of good moral 
                                    character and law-abiding citizen in the community.
                                </p>
                                
                                <p style="text-indent: 50px; margin-top: 20px;">
                                    To certify further, that he/she has no derogatory and/or criminal records filed in this barangay.
                                </p>
                                
                                <p style="text-indent: 50px; margin-top: 20px;">
                                    <strong>ISSUED</strong> this <strong style="border-bottom: 1px solid #000; padding: 0 10px;">
                                    <?php echo date('jS'); ?></strong> day of 
                                    <strong style="border-bottom: 1px solid #000; padding: 0 10px;"><?php echo date('F Y'); ?></strong> 
                                    at <?php echo $settings['barangay_name']; ?>, <?php echo $settings['municipality']; ?>, 
                                    <?php echo str_replace('Province of ', '', $settings['province']); ?> upon request of the interested party 
                                    for <?php echo htmlspecialchars($_POST['purpose']); ?>.
                                </p>
                            </div>

                            <div class="cert-footer">
                                <div class="signature-block">
                                    <div class="signature-line"></div>
                                    <div style="font-weight: bold;"><?php echo $settings['captain_name']; ?></div>
                                    <div>Barangay Captain</div>
                                </div>
                            </div>

                            <div class="cert-details">
                                <div class="detail-row">O.R No. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <strong style="border-bottom: 1px solid #000; padding: 0 50px;"><?php echo htmlspecialchars($_POST['or_number']); ?></strong></div>
                                <div class="detail-row">Date Issued : <strong style="border-bottom: 1px solid #000; padding: 0 50px;"><?php echo date('m/d/Y'); ?></strong></div>
                                <div class="detail-row">Doc. Stamp : <strong>Paid</strong></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Requested Certificates Tab -->
                <div id="requests-tab" class="tab-content <?php echo $active_tab == 'requests' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 20px;">Certificate Requests from Residents</h3>
                    
                    <?php if (is_array($certificate_requests) || (is_object($certificate_requests) && mysqli_num_rows($certificate_requests) > 0)): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Request Date</th>
                                    <th>Resident Name</th>
                                    <th>Certificate Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (is_object($certificate_requests)) {
                                    while ($request = mysqli_fetch_assoc($certificate_requests)): 
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['full_name'] ?? 'Resident Name'); ?></strong><br>
                                            <small><?php echo htmlspecialchars($request['phone'] ?? 'No phone'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['certificate_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($request['status'] == 'pending'): ?>
                                                    <button class="btn btn-success btn-sm" onclick="showApproveModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['full_name'] ?? 'Resident'); ?>', '<?php echo htmlspecialchars($request['certificate_name']); ?>')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $request['id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($request['status'] == 'approved'): ?>
                                                    <button class="btn btn-primary btn-sm" onclick="generateCertificate('<?php echo htmlspecialchars($request['full_name'] ?? 'Resident'); ?>', '<?php echo htmlspecialchars($request['purpose']); ?>')">
                                                        <i class="fas fa-print"></i> Generate
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="showClaimModal(<?php echo $request['id']; ?>)">
                                                        <i class="fas fa-hand-holding"></i> Mark Claimed
                                                    </button>
                                                <?php elseif ($request['status'] == 'claimed'): ?>
                                                    <span style="color: #27ae60;">
                                                        <i class="fas fa-check-circle"></i> Claimed on <?php echo $request['claim_date'] ? date('M d, Y', strtotime($request['claim_date'])) : 'N/A'; ?>
                                                    </span>
                                                <?php elseif ($request['status'] == 'rejected'): ?>
                                                    <span style="color: #e74c3c;" title="<?php echo htmlspecialchars($request['rejection_reason'] ?? ''); ?>">
                                                        <i class="fas fa-times-circle"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endwhile;
                                } 
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem;">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <p style="color: #666;">No certificate requests at this time.</p>
                            <?php if (isset($db_error)): ?>
                                <p style="color: #e74c3c; margin-top: 1rem;">
                                    <small>Please make sure all database tables are created and the resident has submitted requests.</small>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Settings Tab -->
                <div id="settings-tab" class="tab-content <?php echo $active_tab == 'settings' ? 'active' : ''; ?>">
                    <form method="POST" enctype="multipart/form-data" class="no-print">
                        <h3 style="margin-bottom: 20px;">Certificate Settings</h3>
                        
                        <div class="settings-grid">
                            <div>
                                <div class="form-group">
                                    <label for="barangay_name">Barangay Name</label>
                                    <input type="text" id="barangay_name" name="barangay_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['barangay_name']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="municipality">Municipality</label>
                                    <input type="text" id="municipality" name="municipality" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['municipality']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['province']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['country']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="captain_name">Barangay Captain Name</label>
                                    <input type="text" id="captain_name" name="captain_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($settings['captain_name']); ?>" required>
                                </div>
                            </div>

                            <div>
                                <div class="form-group">
                                    <label for="logo">Barangay Logo</label>
                                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*" onchange="previewLogo(this)">
                                    <div class="logo-preview">
                                        <?php if (file_exists($settings['logo_path'])): ?>
                                            <img src="<?php echo $settings['logo_path']; ?>" alt="Current Logo" id="logo-preview">
                                        <?php else: ?>
                                            <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Approve Certificate Request</h2>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this certificate request?</p>
                <div class="request-details">
                    <div class="detail-item">
                        <span class="detail-label">Resident:</span>
                        <span id="approve-resident-name"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Certificate Type:</span>
                        <span id="approve-cert-type"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" id="approve-request-id">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Request</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Certificate Request</h2>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" id="reject-request-id">
                    <div class="form-group">
                        <label for="rejection_reason">Reason for Rejection:</label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" form="rejectForm" class="btn btn-danger">Reject Request</button>
            </div>
        </div>
    </div>

    <!-- Claim Modal -->
    <div id="claimModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Mark Certificate as Claimed</h2>
                <span class="close" onclick="closeModal('claimModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="claimForm">
                    <input type="hidden" name="action" value="mark_claimed">
                    <input type="hidden" name="request_id" id="claim-request-id">
                    <div class="form-group">
                        <label for="or_number">O.R. Number:</label>
                        <input type="text" name="or_number" id="or_number" class="form-control" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('claimModal')">Cancel</button>
                <button type="submit" form="claimForm" class="btn btn-success">Mark as Claimed</button>
            </div>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.currentTarget.classList.add('active');
        }

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    var preview = document.querySelector('.logo-preview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview" id="logo-preview">';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

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

        function showApproveModal(requestId, residentName, certType) {
            document.getElementById('approve-request-id').value = requestId;
            document.getElementById('approve-resident-name').textContent = residentName;
            document.getElementById('approve-cert-type').textContent = certType;
            document.getElementById('approveModal').style.display = 'block';
        }

        function showRejectModal(requestId) {
            document.getElementById('reject-request-id').value = requestId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function showClaimModal(requestId) {
            document.getElementById('claim-request-id').value = requestId;
            document.getElementById('claimModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function generateCertificate(residentName, purpose) {
            // Switch to generate tab first
            const generateTab = document.querySelector('.tab:first-child');
            generateTab.click();
            
            // Wait a moment for tab to switch, then fill the form
            setTimeout(() => {
                document.getElementById('resident_name').value = residentName;
                document.getElementById('purpose').value = purpose;
                document.getElementById('resident_name').focus();
            }, 100);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set active nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                item.classList.remove('active');
                const href = item.getAttribute('href');
                if (href === currentPage) {
                    item.classList.add('active');
                }
            });

            // Check if there's a tab parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab) {
                // Find and click the corresponding tab
                document.querySelectorAll('.tab').forEach(tab => {
                    if (tab.textContent.toLowerCase().includes(activeTab)) {
                        tab.click();
                    }
                });
            }
        });

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });
    </script>
</body>
</html>