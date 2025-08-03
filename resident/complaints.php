<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['resident_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$resident_id = $_SESSION['resident_id'];

// Get dashboard statistics for nav badges
$stats = [];

// Count total complaints by resident
$complaint_query = "SELECT COUNT(*) as total FROM complaints WHERE resident_id = ?";
$stmt = mysqli_prepare($connection, $complaint_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['my_complaints'] = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Count pending complaints by resident
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_complaint_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
mysqli_stmt_close($stmt);

// Count total certificate requests by resident
$certificate_query = "SELECT COUNT(*) as total FROM certificate_requests WHERE resident_id = ?";
$stmt = mysqli_prepare($connection, $certificate_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['my_certificates'] = mysqli_fetch_assoc($result)['total'];
mysqli_stmt_close($stmt);

// Count pending certificate requests by resident
$pending_certificate_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE resident_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($connection, $pending_certificate_query);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
mysqli_stmt_close($stmt);

// Get all residents for search functionality (excluding current resident)
$residents_query = "SELECT id, full_name FROM residents WHERE id != $resident_id ORDER BY full_name ASC";
$residents_result = mysqli_query($connection, $residents_query);

// Handle form submission for new complaint
if ($_POST && isset($_POST['file_complaint'])) {
    $nature_of_complaint = mysqli_real_escape_string($connection, $_POST['nature_of_complaint']);
    $description = mysqli_real_escape_string($connection, $_POST['description']);
    
    // Handle defendant
    $defendant_type = mysqli_real_escape_string($connection, $_POST['defendant_type']);
    if ($defendant_type === 'resident') {
        $defendant_resident_id = intval($_POST['defendant_resident_id']);
        $defendant_name = '';
        $defendant_contact = '';
    } else {
        $defendant_resident_id = NULL;
        $defendant_name = mysqli_real_escape_string($connection, $_POST['defendant_name']);
        $defendant_contact = '';
    }
    
    $insert_query = "INSERT INTO complaints (
        nature_of_complaint, 
        description, 
        resident_id,
        complainant_name,
        complainant_contact,
        defendant_resident_id,
        defendant_name, 
        defendant_contact, 
        status, 
        created_at
    ) VALUES (
        '$nature_of_complaint', 
        '$description', 
        $resident_id,
        '',
        '',
        " . ($defendant_resident_id ? $defendant_resident_id : 'NULL') . ",
        '$defendant_name', 
        '$defendant_contact', 
        'pending', 
        NOW()
    )";
    
    if (mysqli_query($connection, $insert_query)) {
        $success_message = "Complaint filed successfully!";
    } else {
        $error_message = "Error filing complaint: " . mysqli_error($connection);
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';

// Build WHERE clause for filtering
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(c.nature_of_complaint LIKE '%$search%' 
                          OR c.description LIKE '%$search%' 
                          OR c.defendant_name LIKE '%$search%'
                          OR dr.full_name LIKE '%$search%')";
}

if ($filter_status !== 'all') {
    $where_conditions[] = "c.status = '$filter_status'";
}

$where_clause = !empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "";

// Get complaints filed by this resident (as complainant)
$my_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.created_at, 
                       c.defendant_name, c.defendant_contact, c.resolution, c.mediation_date,
                       dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                       FROM complaints c 
                       LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                       WHERE c.resident_id = $resident_id $where_clause
                       ORDER BY c.created_at DESC";
$my_complaints_result = mysqli_query($connection, $my_complaints_query);

// Get complaints where this resident is the defendant
$against_me_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.created_at, 
                               c.complainant_name, c.complainant_contact, c.resolution, c.mediation_date,
                               r.full_name as complainant_resident_name, r.contact_number as complainant_resident_contact
                               FROM complaints c 
                               LEFT JOIN residents r ON c.resident_id = r.id
                               WHERE c.defendant_resident_id = $resident_id $where_clause
                               ORDER BY c.created_at DESC";
$against_me_complaints_result = mysqli_query($connection, $against_me_complaints_query);

// Get individual complaint details for modal viewing
$complaint_details = null;
if (isset($_GET['view_id'])) {
    $view_id = intval($_GET['view_id']);
    $details_query = "SELECT c.*, 
                     r.full_name as complainant_resident_name, r.contact_number as complainant_resident_contact,
                     dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                     FROM complaints c 
                     LEFT JOIN residents r ON c.resident_id = r.id
                     LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                     WHERE c.id = $view_id AND (c.resident_id = $resident_id OR c.defendant_resident_id = $resident_id)";
    $details_result = mysqli_query($connection, $details_query);
    if ($details_result && mysqli_num_rows($details_result) > 0) {
        $complaint_details = mysqli_fetch_assoc($details_result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - Barangay Cawit</title>
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
            padding: 1rem 2rem;
        }

        .page-header {
            margin-bottom: 0.5rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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

        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .header-left {
            flex: 1;
        }

        .header-right {
            display: flex;
            gap: 1rem;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
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
            background: #219a52;
            transform: translateY(-1px);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Filter Section - Compact */
        .table-search {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .compact-search {
            position: relative;
            width: 300px;
        }

        .compact-search-input {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 2rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .compact-search-input:focus {
            outline: none;
            border-color: #4a47a3;
            box-shadow: 0 0 0 2px rgba(74, 71, 163, 0.2);
        }

        .compact-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 0.85rem;
        }

        .compact-filter {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .compact-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
            min-width: 120px;
        }

        .compact-btn {
            padding: 0.5rem 1rem;
            background: #4a47a3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .compact-btn:hover {
            background: #3a3782;
            transform: translateY(-1px);
        }

        .reset-link {
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .reset-link:hover {
            background: #5a6268;
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        /* Tabs */
        .tabs-container {
            margin-bottom: 1.5rem;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            gap: 0.5rem;
        }

        .tab {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            text-align: center;
        }

        .tab.active {
            background: #4a47a3;
            color: white;
            box-shadow: 0 4px 15px rgba(74, 71, 163, 0.3);
        }

        .tab:hover:not(.active) {
            background: #f8f9fa;
        }

        .tab-badge {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .tab:not(.active) .tab-badge {
            background: #e74c3c;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .complaints-table {
            width: 100%;
            border-collapse: collapse;
        }

        .complaints-table th,
        .complaints-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #f5f5f5;
        }

        .complaints-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .complaints-table tr:hover {
            background: #f8f9fa;
        }

        .complaints-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-resolved {
            background: #d4edda;
            color: #155724;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #4a47a3;
            box-shadow: 0 0 0 2px rgba(74, 71, 163, 0.2);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .close-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #333;
        }

        /* Search functionality styles */
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .use-anyway-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: #4a47a3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .use-anyway-btn:hover {
            background: #3a3782;
        }

        .resident-status {
            margin-top: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            display: none;
        }

        .resident-status.registered {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .resident-status.not-registered {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Details Section */
        .details-section {
            margin-bottom: 1.5rem;
        }

        .details-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .details-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            color: #333;
            line-height: 1.6;
        }

        .details-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .meta-value {
            color: #333;
            font-weight: 500;
        }

        /* Resolution info */
        .resolution-info {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            border: 1px solid #b8e0ff;
        }

        .resolution-label {
            font-weight: 600;
            color: #0c5460;
            margin-bottom: 0.25rem;
        }

        .mediation-date {
            display: inline-block;
            background: #fff;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #495057;
            margin-top: 0.5rem;
        }

        /* Empty State */
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

            .top-bar {
                padding: 1rem;
            }

            .table-container {
                overflow-x: auto;
            }

            .complaints-table {
                min-width: 800px;
            }

            .header-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .header-right {
                justify-content: center;
            }

            .table-search {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }

            .compact-search {
                width: 100%;
            }

            .compact-filter {
                width: 100%;
                justify-content: center;
            }

            .tabs {
                flex-direction: column;
            }
        }

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
                <a href="complaints.php" class="nav-item active">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints
                    <?php if ($stats['pending_complaints'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="request-certificate.php" class="nav-item">
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
            <h1 class="page-title">My Complaints</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <div class="header-actions">
                <div class="header-left">
                    <div class="page-header">
                        <h1>My Complaints</h1>
                        <p>View and manage your complaints and disputes</p>
                    </div>
                </div>
                <div class="header-right">
                    <button class="action-btn btn-success" onclick="openFileModal()">
                        <i class="fas fa-plus"></i>
                        File New Complaint
                    </button>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-times-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Tabs Container -->
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('my-complaints')">
                        <i class="fas fa-file-alt"></i> My Complaints
                        <?php 
                        $my_count = mysqli_num_rows($my_complaints_result);
                        if ($my_count > 0): 
                        ?>
                            <span class="tab-badge"><?php echo $my_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab" onclick="switchTab('against-me')">
                        <i class="fas fa-exclamation-triangle"></i> Complaints Against Me
                        <?php 
                        $against_count = mysqli_num_rows($against_me_complaints_result);
                        if ($against_count > 0): 
                        ?>
                            <span class="tab-badge"><?php echo $against_count; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>

            <!-- My Complaints Tab -->
            <div id="my-complaints-tab" class="tab-content active">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-file-alt"></i>
                            Complaints I Filed
                        </div>
                        <div class="table-search">
                            <form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="compact-search">
                                    <i class="fas fa-search compact-search-icon"></i>
                                    <input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="compact-filter">
                                    <select name="status" class="compact-select">
                                        <option value="all">All Status</option>
                                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in-progress" <?php echo $filter_status === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit" class="compact-btn">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="complaints.php" class="reset-link">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nature of Complaint</th>
                                <th>Against</th>
                                <th>Status</th>
                                <th>Date Filed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($my_complaints_result, 0);
                            if (mysqli_num_rows($my_complaints_result) > 0): 
                            ?>
                                <?php while ($complaint = mysqli_fetch_assoc($my_complaints_result)): ?>
                                    <tr>
                                        <td>#<?php echo $complaint['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['nature_of_complaint']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars(substr($complaint['description'], 0, 50)) . '...'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php 
                                            if ($complaint['defendant_resident_name']) {
                                                echo htmlspecialchars($complaint['defendant_resident_name']);
                                            } else {
                                                echo htmlspecialchars($complaint['defendant_name'] ?? 'Unknown');
                                            }
                                            ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php 
                                                if ($complaint['defendant_resident_contact']) {
                                                    echo htmlspecialchars($complaint['defendant_resident_contact']);
                                                } else {
                                                    echo htmlspecialchars($complaint['defendant_contact'] ?? 'N/A');
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                        <td>
                                            <button class="action-btn btn-primary btn-small" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                                        <div class="empty-state">
                                            <i class="fas fa-file-alt"></i>
                                            <p>You haven't filed any complaints yet</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Complaints Against Me Tab -->
            <div id="against-me-tab" class="tab-content">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Complaints Against Me
                        </div>
                        <div class="table-search">
                            <form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="compact-search">
                                    <i class="fas fa-search compact-search-icon"></i>
                                    <input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="compact-filter">
                                    <select name="status" class="compact-select">
                                        <option value="all">All Status</option>
                                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in-progress" <?php echo $filter_status === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                    <button type="submit" class="compact-btn">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <a href="complaints.php" class="reset-link">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nature of Complaint</th>
                                <th>Filed By</th>
                                <th>Status</th>
                                <th>Date Filed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($against_me_complaints_result, 0);
                            if (mysqli_num_rows($against_me_complaints_result) > 0): 
                            ?>
                                <?php while ($complaint = mysqli_fetch_assoc($against_me_complaints_result)): ?>
                                    <tr>
                                        <td>#<?php echo $complaint['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($complaint['nature_of_complaint']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars(substr($complaint['description'], 0, 50)) . '...'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php 
                                            if ($complaint['complainant_resident_name']) {
                                                echo htmlspecialchars($complaint['complainant_resident_name']);
                                            } else {
                                                echo htmlspecialchars($complaint['complainant_name'] ?? 'Unknown');
                                            }
                                            ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php 
                                                if ($complaint['complainant_resident_contact']) {
                                                    echo htmlspecialchars($complaint['complainant_resident_contact']);
                                                } else {
                                                    echo htmlspecialchars($complaint['complainant_contact'] ?? 'N/A');
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                        <td>
                                            <button class="action-btn btn-primary btn-small" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <p>No complaints have been filed against you</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- File Complaint Modal -->
    <div class="modal" id="fileModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('fileModal')">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">File New Complaint</h2>
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nature of Complaint *</label>
                    <input type="text" class="form-input" name="nature_of_complaint" required placeholder="Enter the nature/purpose of complaint">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <textarea class="form-textarea" name="description" required placeholder="Describe the complaint in detail"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Defendant Name *</label>
                    <div style="position: relative;">
                        <input type="text" 
                               class="form-input search-input" 
                               id="defendantSearch" 
                               name="defendant_name"
                               placeholder="Type name to search or enter new name..."
                               autocomplete="off"
                               required>
                        <div id="defendantSearchResults" class="search-results"></div>
                    </div>
                    <div id="defendantStatus" class="resident-status"></div>
                </div>
                
                <!-- Hidden fields for defendant -->
                <input type="hidden" name="defendant_type" id="defendantType" value="non-resident">
                <input type="hidden" name="defendant_resident_id" id="defendantResidentId" value="">
                
                <div class="modal-actions">
                    <button type="button" class="action-btn btn-secondary" onclick="closeModal('fileModal')">Cancel</button>
                    <button type="submit" name="file_complaint" class="action-btn btn-success">File Complaint</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">Complaint Details</h2>
            </div>
            <div id="complaintDetails">
                <!-- Complaint details will be loaded here -->
            </div>
            <div class="modal-actions">
                <button type="button" class="action-btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <?php if ($complaint_details): ?>
    <script>
        // Auto-open modal if viewing specific complaint
        document.addEventListener('DOMContentLoaded', function() {
            viewComplaintDetails(<?php echo json_encode($complaint_details); ?>);
        });
    </script>
    <?php endif; ?>

    <script>
        // Resident data from PHP (excluding current resident)
        const residents = <?php 
            mysqli_data_seek($residents_result, 0);
            $residents_array = [];
            while ($resident = mysqli_fetch_assoc($residents_result)) {
                $residents_array[] = [
                    'id' => $resident['id'],
                    'full_name' => $resident['full_name']
                ];
            }
            echo json_encode($residents_array);
        ?>;

        let searchTimeout;
        let isSelectingResident = false;

        // Initialize search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Defendant search
            const defendantSearch = document.getElementById('defendantSearch');
            const defendantResults = document.getElementById('defendantSearchResults');
            
            defendantSearch.addEventListener('input', function() {
                if (isSelectingResident) {
                    isSelectingResident = false;
                    return;
                }
                
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                // Reset to non-resident if user is typing
                document.getElementById('defendantType').value = 'non-resident';
                document.getElementById('defendantResidentId').value = '';
                document.getElementById('defendantStatus').style.display = 'none';
                
                if (query.length < 2) {
                    defendantResults.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchResidents(query);
                }, 300);
            });
            
            // Click outside to close search results
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-results') && !e.target.classList.contains('search-input')) {
                    defendantResults.style.display = 'none';
                }
            });
        });

        function searchResidents(query) {
            const resultsContainer = document.getElementById('defendantSearchResults');
            
            // Filter residents based on query
            const filteredResidents = residents.filter(resident => 
                resident.full_name.toLowerCase().includes(query.toLowerCase())
            );
            
            resultsContainer.innerHTML = '';
            
            if (filteredResidents.length > 0) {
                // Add a header
                const header = document.createElement('div');
                header.style.cssText = 'padding: 10px 15px; background: #f0f0f0; font-size: 0.85rem; color: #666; font-weight: 600;';
                header.textContent = 'Registered Residents';
                resultsContainer.appendChild(header);
                
                filteredResidents.forEach(resident => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    resultItem.innerHTML = `
                        <div class="search-result-name">${resident.full_name}</div>
                    `;
                    resultItem.onclick = () => selectResident(resident);
                    resultsContainer.appendChild(resultItem);
                });
                
                // Add option to use as non-resident
                const nonResidentOption = document.createElement('div');
                nonResidentOption.style.cssText = 'padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd; text-align: center;';
                nonResidentOption.innerHTML = `
                    <p style="margin: 0 0 10px 0; color: #666; font-size: 0.9rem;">Not a registered resident?</p>
                    <button type="button" class="use-anyway-btn" onclick="useNonResident('${query}')">
                        Use "${query}" as non-resident
                    </button>
                `;
                resultsContainer.appendChild(nonResidentOption);
            } else {
                resultsContainer.innerHTML = `
                    <div class="no-results">
                        <p>No registered resident found</p>
                        <button type="button" class="use-anyway-btn" onclick="useNonResident('${query}')">
                            Use "${query}" as non-resident
                        </button>
                    </div>
                `;
            }
            
            resultsContainer.style.display = 'block';
        }

        function selectResident(resident) {
            isSelectingResident = true;
            const searchInput = document.getElementById('defendantSearch');
            const typeInput = document.getElementById('defendantType');
            const residentIdInput = document.getElementById('defendantResidentId');
            const statusDiv = document.getElementById('defendantStatus');
            const resultsContainer = document.getElementById('defendantSearchResults');
            
            // Set values
            searchInput.value = resident.full_name;
            typeInput.value = 'resident';
            residentIdInput.value = resident.id;
            
            // Show status
            statusDiv.className = 'resident-status registered';
            statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Registered Resident';
            statusDiv.style.display = 'block';
            
            // Hide search results
            resultsContainer.style.display = 'none';
        }

        function useNonResident(name) {
            isSelectingResident = true;
            const searchInput = document.getElementById('defendantSearch');
            const typeInput = document.getElementById('defendantType');
            const residentIdInput = document.getElementById('defendantResidentId');
            const statusDiv = document.getElementById('defendantStatus');
            const resultsContainer = document.getElementById('defendantSearchResults');
            
            // Set values
            searchInput.value = name;
            typeInput.value = 'non-resident';
            residentIdInput.value = '';
            
            // Show status
            statusDiv.className = 'resident-status not-registered';
            statusDiv.innerHTML = '<i class="fas fa-info-circle"></i> Non-Resident (Manual Entry)';
            statusDiv.style.display = 'block';
            
            // Hide search results
            resultsContainer.style.display = 'none';
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

        function openFileModal() {
            document.getElementById('fileModal').style.display = 'block';
        }

        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(`${tabName}-tab`).classList.add('active');
            
            // Add active class to clicked tab
            event.target.closest('.tab').classList.add('active');
        }

        function viewComplaint(id) {
            // Redirect to view the complaint details
            window.location.href = `complaints.php?view_id=${id}`;
        }

        function viewComplaintDetails(complaint) {
            const modal = document.getElementById('viewModal');
            const detailsDiv = document.getElementById('complaintDetails');
            
            const complainantName = complaint.complainant_resident_name || complaint.complainant_name || 'Unknown';
            const complainantContact = complaint.complainant_resident_contact || complaint.complainant_contact || 'N/A';
            const defendantName = complaint.defendant_resident_name || complaint.defendant_name || 'Unknown';
            const defendantContact = complaint.defendant_resident_contact || complaint.defendant_contact || 'N/A';
            
            let resolutionSection = '';
            if (complaint.resolution || complaint.mediation_date) {
                resolutionSection = `
                    <div class="resolution-info">
                        ${complaint.resolution ? `
                            <div class="resolution-label">Resolution:</div>
                            <div>${complaint.resolution}</div>
                        ` : ''}
                        ${complaint.mediation_date ? `
                            <div class="mediation-date">
                                <i class="fas fa-calendar"></i> Mediation Date: ${new Date(complaint.mediation_date).toLocaleDateString()}
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            detailsDiv.innerHTML = `
                <div class="details-meta">
                    <div class="meta-item">
                        <div class="meta-label">Complaint ID</div>
                        <div class="meta-value">#${complaint.id}</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Date Filed</div>
                        <div class="meta-value">${new Date(complaint.created_at).toLocaleDateString()}</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Status</div>
                        <div class="meta-value">
                            <span class="status-badge status-${complaint.status}">
                                ${complaint.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="details-section">
                    <div class="details-label">Nature of Complaint</div>
                    <div class="details-content">${complaint.nature_of_complaint}</div>
                </div>
                
                <div class="details-section">
                    <div class="details-label">Description</div>
                    <div class="details-content">${complaint.description}</div>
                </div>
                
                <div class="details-section">
                    <div class="details-label">Complainant</div>
                    <div class="details-content">
                        <strong>${complainantName}</strong><br>
                        Contact: ${complainantContact}
                    </div>
                </div>
                
                <div class="details-section">
                    <div class="details-label">Defendant</div>
                    <div class="details-content">
                        <strong>${defendantName}</strong><br>
                        Contact: ${defendantContact}
                    </div>
                </div>
                
                ${resolutionSection}
            `;
            
            modal.style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            if (modalId === 'fileModal') {
                const form = document.querySelector('#fileModal form');
                form.reset();
                
                // Reset defendant fields
                document.getElementById('defendantStatus').style.display = 'none';
                document.getElementById('defendantType').value = 'non-resident';
                document.getElementById('defendantResidentId').value = '';
                document.getElementById('defendantSearchResults').style.display = 'none';
            }
            
            // Remove view_id from URL when closing view modal
            if (modalId === 'viewModal') {
                const url = new URL(window.location);
                url.searchParams.delete('view_id');
                window.history.replaceState({}, document.title, url);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['fileModal', 'viewModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

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