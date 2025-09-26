<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['resident_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$resident_id = $_SESSION['resident_id'];

// Get dashboard statistics for resident
$stats = [];

// Count total complaints by resident
$complaint_query = "SELECT COUNT(*) as total FROM complaints WHERE resident_id = ?";
$stmt = mysqli_prepare($connection, $complaint_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['my_complaints'] = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);
} else {
    $stats['my_complaints'] = 0;
}

// Count pending complaints by resident
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_complaint_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
    mysqli_stmt_close($stmt);
} else {
    $stats['pending_complaints'] = 0;
}

// Count total certificate requests by resident
$certificate_query = "SELECT COUNT(*) as total FROM certificate_requests WHERE resident_id = ?";
$stmt = mysqli_prepare($connection, $certificate_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['my_certificates'] = mysqli_fetch_assoc($result)['total'];
    mysqli_stmt_close($stmt);
} else {
    $stats['my_certificates'] = 0;
}

// Count pending certificate requests by resident
$pending_certificate_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_certificate_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
    mysqli_stmt_close($stmt);
} else {
    $stats['pending_certificates'] = 0;
}

// Handle certificate request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $certificate_type = mysqli_real_escape_string($connection, $_POST['certificate_type']);
    $purpose = mysqli_real_escape_string($connection, $_POST['purpose']);
    $additional_info = mysqli_real_escape_string($connection, $_POST['additional_info']);
    
    $insert_query = "INSERT INTO certificate_requests (resident_id, certificate_type, purpose, additional_info, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = mysqli_prepare($connection, $insert_query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isss", $resident_id, $certificate_type, $purpose, $additional_info);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: request-certificate.php?msg=success");
            exit();
        } else {
            $error = "Error submitting request: " . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        $error = "Database error: " . mysqli_error($connection) . ". Please contact the administrator.";
    }
}

// Get resident's certificate requests
$requests_query = "SELECT * FROM certificate_requests WHERE resident_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($connection, $requests_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $resident_id);
    mysqli_stmt_execute($stmt);
    $my_requests = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    // return false when prepare fails instead of attempting to instantiate mysqli_result
    $my_requests = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Certificate - Barangay Cawit</title>
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

        .certificate-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .certificate-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4a 100%);
            color: #333;
        }

        .certificate-title {
            font-size: 1.5rem;
            font-weight: bold;
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
        }

        .tab.active {
            color: #4a47a3;
            border-bottom-color: #4a47a3;
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
            border-color: #4a47a3;
            box-shadow: 0 0 0 3px rgba(74, 71, 163, 0.1);
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
            background: #4a47a3;
            color: white;
        }

        .btn-primary:hover {
            background: #3a3782;
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

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .certificate-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .cert-type-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cert-type-card:hover {
            border-color: #4a47a3;
            background: #f1f0ff;
        }

        .cert-type-card.selected {
            border-color: #4a47a3;
            background: #f1f0ff;
        }

        .cert-type-card input[type="radio"] {
            display: none;
        }

        .cert-type-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cert-type-desc {
            color: #666;
            font-size: 0.9rem;
        }

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

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1rem;
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

            .certificate-types {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .top-bar {
                padding: 1rem;
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

            .requests-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
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
                <i class="fas fa-home"></i>
                Barangay Cawit
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
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
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Events
                </a>
               
                <a href="request-certificate.php" class="nav-item active">
                    <i class="fas fa-certificate"></i>
                    Request Certificate
                    <?php if ($stats['pending_certificates'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_certificates']; ?></span>
                    <?php endif; ?>
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

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Certificate Request</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Your certificate request has been submitted successfully! You will be notified once it's processed.
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="certificate-container">
                <div class="certificate-header">
                    <h2 class="certificate-title">
                        <i class="fas fa-certificate"></i>
                        Certificate Request Service
                    </h2>
                </div>

                <div class="tabs">
                    <div class="tab active" onclick="switchTab(event, 'request')">
                        <i class="fas fa-plus-circle"></i> New Request
                    </div>
                    <div class="tab" onclick="switchTab(event, 'history')">
                        <i class="fas fa-history"></i> My Requests
                    </div>
                </div>

                <!-- New Request Tab -->
                <div id="request-tab" class="tab-content active">
                    <form method="POST" action="request-certificate.php">
                        <h3 style="margin-bottom: 1.5rem; color: #333;">Request a New Certificate</h3>
                        
                        <div class="form-group">
                            <label>Select Certificate Type:</label>
                            <div class="certificate-types">
                                <div class="cert-type-card" onclick="selectCertType(this, 'barangay_clearance')">
                                    <input type="radio" name="certificate_type" value="Barangay Clearance" id="barangay_clearance">
                                    <div class="cert-type-title">
                                        <i class="fas fa-shield-alt" style="color: #4a47a3;"></i>
                                        Barangay Clearance
                                    </div>
                                    <div class="cert-type-desc">
                                        Certifies good moral character and criminal record status. Required for employment, travel, and other legal purposes.
                                    </div>
                                </div>

                                <div class="cert-type-card" onclick="selectCertType(this, 'certificate_indigency')">
                                    <input type="radio" name="certificate_type" value="Certificate of Indigency" id="certificate_indigency">
                                    <div class="cert-type-title">
                                        <i class="fas fa-hand-holding-heart" style="color: #e74c3c;"></i>
                                        Certificate of Indigency
                                    </div>
                                    <div class="cert-type-desc">
                                        Certifies low-income status for scholarship applications, medical assistance, and social services.
                                    </div>
                                </div>

                                <div class="cert-type-card" onclick="selectCertType(this, 'certificate_residency')">
                                    <input type="radio" name="certificate_type" value="Certificate of Residency" id="certificate_residency">
                                    <div class="cert-type-title">
                                        <i class="fas fa-home" style="color: #27ae60;"></i>
                                        Certificate of Residency
                                    </div>
                                    <div class="cert-type-desc">
                                        Certifies that you are a bonafide resident of the barangay. Required for voter registration and other transactions.
                                    </div>
                                </div>

                                <div class="cert-type-card" onclick="selectCertType(this, 'business_permit')">
                                    <input type="radio" name="certificate_type" value="Barangay Business Permit" id="business_permit">
                                    <div class="cert-type-title">
                                        <i class="fas fa-store" style="color: #f39c12;"></i>
                                        Barangay Business Permit
                                    </div>
                                    <div class="cert-type-desc">
                                        Permit to operate a small business within the barangay jurisdiction.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="purpose">Purpose of Certificate:</label>
                            <input type="text" id="purpose" name="purpose" class="form-control" 
                                   placeholder="e.g., Employment requirements, School application, etc." required>
                        </div>

                        <div class="form-group">
                            <label for="additional_info">Additional Information (Optional):</label>
                            <textarea id="additional_info" name="additional_info" class="form-control" rows="4"
                                      placeholder="Any additional details or special requests..."></textarea>
                        </div>

                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                            <h4 style="color: #333; margin-bottom: 1rem;">
                                <i class="fas fa-info-circle" style="color: #3498db;"></i>
                                Important Information
                            </h4>
                            <ul style="color: #666; margin-left: 1.5rem; line-height: 1.8;">
                                <li>Processing time is typically 3-5 business days</li>
                                <li>You will be notified via SMS when your certificate is ready</li>
                                <li>Please bring a valid ID when claiming your certificate</li>
                                <li>Certificate fees will be collected upon claiming</li>
                                <li>Certificates are valid for 6 months from date of issuance</li>
                            </ul>
                        </div>

                        <button type="submit" name="submit_request" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Submit Request
                        </button>
                    </form>
                </div>

                <!-- Request History Tab -->
                <div id="history-tab" class="tab-content">
                    <h3 style="margin-bottom: 1.5rem; color: #333;">My Certificate Requests</h3>
                    
                    <?php if ($my_requests && mysqli_num_rows($my_requests) > 0): ?>
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>Request Date</th>
                                    <th>Certificate Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Processed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = mysqli_fetch_assoc($my_requests)): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['certificate_type']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php 
                                                switch($request['status']) {
                                                    case 'pending':
                                                        echo '<i class="fas fa-clock"></i> Pending';
                                                        break;
                                                    case 'processing':
                                                        echo '<i class="fas fa-spinner"></i> Processing';
                                                        break;
                                                    case 'approved':
                                                        echo '<i class="fas fa-check"></i> Ready for Pickup';
                                                        break;
                                                    case 'claimed':
                                                        echo '<i class="fas fa-check-circle"></i> Claimed';
                                                        break;
                                                    case 'rejected':
                                                        echo '<i class="fas fa-times"></i> Rejected';
                                                        break;
                                                    default:
                                                        echo ucfirst($request['status']);
                                                }
                                                ?>
                                            </span>
                                            <?php if ($request['status'] == 'rejected' && !empty($request['rejection_reason'])): ?>
                                                <div style="font-size: 0.8rem; color: #e74c3c; margin-top: 0.25rem;">
                                                    <i class="fas fa-info-circle"></i> 
                                                    <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($request['processed_date']): ?>
                                                <?php echo date('M d, Y', strtotime($request['processed_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">Not yet processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>You haven't submitted any certificate requests yet.</p>
                            <button onclick="switchToRequestTab()" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Make Your First Request
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
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

        function switchToRequestTab() {
            // Click the request tab
            document.querySelector('.tab:first-child').click();
        }

        function selectCertType(cardEl, certType) {
            // Remove selected class from all cards
            document.querySelectorAll('.cert-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to clicked card (cardEl is the element passed via onclick="selectCertType(this, 'id')")
            if (cardEl && cardEl.classList) {
                cardEl.classList.add('selected');
            }
            
            // Check the radio button
            const input = document.getElementById(certType);
            if (input) input.checked = true;
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
        });
    </script>
</body>
</html>