<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['resident_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$resident_id = $_SESSION['resident_id'];

// Get resident information
$resident_query = "SELECT * FROM residents WHERE id = ?";
$stmt = mysqli_prepare($connection, $resident_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$resident_result = mysqli_stmt_get_result($stmt);
$resident_info = mysqli_fetch_assoc($resident_result);
mysqli_stmt_close($stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $certificate_type = mysqli_real_escape_string($connection, $_POST['certificate_type']);
    $purpose = mysqli_real_escape_string($connection, $_POST['purpose']);
    $delivery_option = mysqli_real_escape_string($connection, $_POST['delivery_option']);
    
    // Insert certificate request
    $insert_query = "INSERT INTO certificate_requests (resident_id, certificate_type, purpose, delivery_option, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = mysqli_prepare($connection, $insert_query);
    mysqli_stmt_bind_param($stmt, "isss", $resident_id, $certificate_type, $purpose, $delivery_option);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Your certificate request has been submitted successfully!";
        
        // Log the request
        $request_id = mysqli_insert_id($connection);
        $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, new_status) 
                      VALUES (?, 'created', ?, 'pending')";
        $log_stmt = mysqli_prepare($connection, $log_query);
        mysqli_stmt_bind_param($log_stmt, "ii", $request_id, $resident_id);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    } else {
        $error_message = "Failed to submit certificate request. Please try again.";
    }
    mysqli_stmt_close($stmt);
}

// Get certificate request history
$history_query = "SELECT cr.*, u.full_name as processed_by_name
                  FROM certificate_requests cr
                  LEFT JOIN users u ON cr.processed_by = u.id
                  WHERE cr.resident_id = ?
                  ORDER BY cr.created_at DESC";
$stmt = mysqli_prepare($connection, $history_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$request_history = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get statistics for nav badges
$stats = [];
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_complaint_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
mysqli_stmt_close($stmt);

$pending_cert_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_cert_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
mysqli_stmt_close($stmt);

// Certificate types available
$certificate_types = [
    'Barangay Clearance' => 'General purpose clearance for various legal requirements',
    'Certificate of Residency' => 'Proof of residence in the barangay',
    'Certificate of Indigency' => 'For medical assistance and other social services',
    'Business Clearance' => 'Required for business permit applications',
    'Building Clearance' => 'Required for building permit applications',
    'Certificate of Good Moral Character' => 'Character reference for employment or school',
    'Certificate of Guardianship' => 'Proof of guardianship for minors',
    'Certificate of Solo Parent' => 'For solo parent benefits and privileges'
];
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

        /* Certificate Request Styles */
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

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .certificate-types {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .certificate-type-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .certificate-type-card:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }

        .certificate-type-card.selected {
            border-color: #3498db;
            background: #e3f2fd;
        }

        .certificate-type-card h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }

        .certificate-type-card p {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
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

        .btn-primary:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
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

        /* Request History Table */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .history-table tr:hover {
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

        .info-box {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }

        .info-box i {
            color: #1976d2;
            margin-top: 0.25rem;
        }

        .info-box p {
            margin: 0;
            color: #1565c0;
            font-size: 0.9rem;
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

            .history-table {
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
                    Announcements
                </a>
                <a href="complaints.php" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints
                    <?php if ($stats['pending_complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                    <?php endif; ?>
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
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="certificate-container">
                <div class="certificate-header">
                    <h2 class="certificate-title">
                        <i class="fas fa-certificate" style="color: #3498db;"></i>
                        Barangay Certificate Request
                    </h2>
                </div>

                <div class="tabs">
                    <div class="tab active" onclick="switchTab(event, 'new-request')">
                        <i class="fas fa-plus-circle"></i> New Request
                    </div>
                    <div class="tab" onclick="switchTab(event, 'request-history')">
                        <i class="fas fa-history"></i> Request History
                    </div>
                </div>

                <!-- New Request Tab -->
                <div id="new-request-tab" class="tab-content active">
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>Select the type of certificate you need and provide the required information. Your request will be reviewed by the barangay office.</p>
                    </div>

                    <form method="POST" id="certificateRequestForm">
                        <div class="form-group">
                            <label>Select Certificate Type *</label>
                            <div class="certificate-types">
                                <?php foreach ($certificate_types as $type => $description): ?>
                                    <div class="certificate-type-card" onclick="selectCertificateType(this, '<?php echo $type; ?>')">
                                        <h4><?php echo $type; ?></h4>
                                        <p><?php echo $description; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="certificate_type" id="certificate_type" required>
                        </div>

                        <div class="form-group">
                            <label for="purpose">Purpose of Request *</label>
                            <textarea name="purpose" id="purpose" class="form-control" 
                                      placeholder="Please specify the purpose of this certificate request (e.g., for employment, school requirements, etc.)" 
                                      required></textarea>
                        </div>

                        <div class="form-group">
                            <label for="delivery_option">Delivery Option *</label>
                            <select name="delivery_option" id="delivery_option" class="form-control" required>
                                <option value="">Select delivery option</option>
                                <option value="pickup">Pick-up at Barangay Office</option>
                                <option value="delivery">Home Delivery (Additional fee may apply)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <h4>Your Information</h4>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($resident_info['full_name']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($resident_info['address']); ?></p>
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($resident_info['phone']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($resident_info['email']); ?></p>
                            </div>
                        </div>

                        <button type="submit" name="submit_request" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-paper-plane"></i> Submit Certificate Request
                        </button>
                    </form>
                </div>

                <!-- Request History Tab -->
                <div id="request-history-tab" class="tab-content">
                    <h3 style="margin-bottom: 20px;">Your Certificate Request History</h3>
                    
                    <?php if (mysqli_num_rows($request_history) > 0): ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Request Date</th>
                                    <th>Certificate Type</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Delivery</th>
                                    <th>Processed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = mysqli_fetch_assoc($request_history)): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['certificate_type']); ?></td>
                                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($request['delivery_option']); ?></td>
                                        <td>
                                            <?php if ($request['processed_by_name']): ?>
                                                <?php echo htmlspecialchars($request['processed_by_name']); ?>
                                                <br>
                                                <small><?php echo date('M d, Y', strtotime($request['processed_date'])); ?></small>
                                            <?php else: ?>
                                                <span style="color: #999;">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($request['status'] == 'rejected' && $request['rejection_reason']): ?>
                                        <tr>
                                            <td colspan="6" style="background: #fff3cd; padding: 0.75rem 1rem;">
                                                <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($request['status'] == 'claimed' && $request['or_number']): ?>
                                        <tr>
                                            <td colspan="6" style="background: #d4edda; padding: 0.75rem 1rem;">
                                                <strong>Claimed on:</strong> <?php echo date('M d, Y', strtotime($request['claim_date'])); ?> | 
                                                <strong>O.R. Number:</strong> <?php echo htmlspecialchars($request['or_number']); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>You haven't made any certificate requests yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedCertificateType = null;

        function selectCertificateType(card, type) {
            // Remove selected class from all cards
            document.querySelectorAll('.certificate-type-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selected class to clicked card
            card.classList.add('selected');
            
            // Set the hidden input value
            document.getElementById('certificate_type').value = type;
            selectedCertificateType = type;
            
            // Enable submit button if all required fields are filled
            checkFormValidity();
        }

        function checkFormValidity() {
            const purpose = document.getElementById('purpose').value.trim();
            const deliveryOption = document.getElementById('delivery_option').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (selectedCertificateType && purpose && deliveryOption) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Add event listeners to form fields
        document.getElementById('purpose').addEventListener('input', checkFormValidity);
        document.getElementById('delivery_option').addEventListener('change', checkFormValidity);

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

        // Form submission validation
        document.getElementById('certificateRequestForm').addEventListener('submit', function(e) {
            if (!selectedCertificateType) {
                e.preventDefault();
                alert('Please select a certificate type.');
                return false;
            }
        });
    </script>
</body>
</html>