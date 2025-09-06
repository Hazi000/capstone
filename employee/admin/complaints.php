<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// Get dashboard statistics for nav badges
$stats = [];

// Count pending complaints
$pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_complaint_query);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];

// Count pending appointments
$pending_appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $pending_appointment_query);
$stats['pending_appointments'] = mysqli_fetch_assoc($result)['pending'];

// Handle form submissions
if ($_POST) {
    // Handle status update
    if (isset($_POST['update_status'])) {
        $complaint_id = intval($_POST['complaint_id']);
        $new_status = mysqli_real_escape_string($connection, $_POST['status']);
        $resolution = mysqli_real_escape_string($connection, $_POST['resolution'] ?? '');
        $mediation_date = !empty($_POST['mediation_date']) ? mysqli_real_escape_string($connection, $_POST['mediation_date']) : NULL;
        
        $update_query = "UPDATE complaints SET 
                        status = '$new_status', 
                        resolution = '$resolution'";
        
        if ($mediation_date) {
            $update_query .= ", mediation_date = '$mediation_date'";
        }
        
        $update_query .= " WHERE id = $complaint_id";
        
        if (mysqli_query($connection, $update_query)) {
            $success_message = "Complaint status updated successfully!";
        } else {
            $error_message = "Error updating complaint status: " . mysqli_error($connection);
        }
    }
    
    // Handle create complaint
    if (isset($_POST['create_complaint'])) {
        $nature_of_complaint = mysqli_real_escape_string($connection, $_POST['nature_of_complaint']);
        $description = mysqli_real_escape_string($connection, $_POST['description']);
        $priority = mysqli_real_escape_string($connection, $_POST['priority']);
        
        // Handle complainant
        $complainant_type = mysqli_real_escape_string($connection, $_POST['complainant_type']);
        if ($complainant_type === 'resident') {
            $resident_id = intval($_POST['resident_id']);
            $complainant_name = '';
            $complainant_contact = '';
        } else {
            $resident_id = NULL;
            $complainant_name = mysqli_real_escape_string($connection, $_POST['complainant_name']);
            $complainant_contact = mysqli_real_escape_string($connection, $_POST['complainant_contact']);
        }
        
        // Handle defendant
        $defendant_type = mysqli_real_escape_string($connection, $_POST['defendant_type']);
        if ($defendant_type === 'resident') {
            $defendant_resident_id = intval($_POST['defendant_resident_id']);
            $defendant_name = '';
            $defendant_contact = '';
        } else {
            $defendant_resident_id = NULL;
            $defendant_name = mysqli_real_escape_string($connection, $_POST['defendant_name']);
            $defendant_contact = mysqli_real_escape_string($connection, $_POST['defendant_contact']);
        }
        
        $insert_query = "INSERT INTO complaints (
            nature_of_complaint, 
            description, 
            priority, 
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
            '$priority', 
            " . ($resident_id ? $resident_id : 'NULL') . ", 
            '$complainant_name', 
            '$complainant_contact', 
            
            " . ($defendant_resident_id ? $defendant_resident_id : 'NULL') . ", 
            '$defendant_name', 
            '$defendant_contact', 
            'pending', 
            NOW()
        )";
        
        if (mysqli_query($connection, $insert_query)) {
            $success_message = "Complaint created successfully!";
        } else {
            $error_message = "Error creating complaint: " . mysqli_error($connection);
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';
$filter_priority = isset($_GET['priority']) ? mysqli_real_escape_string($connection, $_GET['priority']) : 'all';
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($connection, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($connection, $_GET['date_to']) : '';

// Build WHERE clause for filtering
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(c.nature_of_complaint LIKE '%$search%' 
                          OR c.description LIKE '%$search%' 
                          OR c.complainant_name LIKE '%$search%' 
                          OR c.defendant_name LIKE '%$search%'
                          OR r.full_name LIKE '%$search%'
                          OR dr.full_name LIKE '%$search%')";
}

if ($filter_status !== 'all') {
    $where_conditions[] = "c.status = '$filter_status'";
}

if ($filter_priority !== 'all') {
    $where_conditions[] = "c.priority = '$filter_priority'";
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(c.created_at) >= '$filter_date_from'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(c.created_at) <= '$filter_date_to'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all residents for dropdown
$residents_query = "SELECT id, full_name, contact_number FROM residents ORDER BY full_name ASC";
$residents_result = mysqli_query($connection, $residents_query);

// Get recent complaints (last 7 days)
$recent_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.priority, c.created_at, 
                           c.complainant_name, c.complainant_contact, c.defendant_name, c.defendant_contact,
                           c.resolution, c.mediation_date,
                           r.full_name as resident_name, r.contact_number as resident_contact,
                           dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                           FROM complaints c 
                           LEFT JOIN residents r ON c.resident_id = r.id 
                           LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                           WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) $where_clause
                           ORDER BY c.created_at DESC";
$recent_complaints_result = mysqli_query($connection, $recent_complaints_query);

// Get pending complaints
$pending_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.priority, c.created_at, 
                            c.complainant_name, c.complainant_contact, c.defendant_name, c.defendant_contact,
                            c.resolution, c.mediation_date,
                            r.full_name as resident_name, r.contact_number as resident_contact,
                            dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                            FROM complaints c 
                            LEFT JOIN residents r ON c.resident_id = r.id 
                            LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                            WHERE c.status IN ('pending', 'in-progress') " . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "") . "
                            ORDER BY c.created_at DESC";
$pending_complaints_result = mysqli_query($connection, $pending_complaints_query);

// Get resolved complaints
$resolved_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.priority, c.created_at, 
                             c.complainant_name, c.complainant_contact, c.defendant_name, c.defendant_contact,
                             c.resolution, c.mediation_date,
                             r.full_name as resident_name, r.contact_number as resident_contact,
                             dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                             FROM complaints c 
                             LEFT JOIN residents r ON c.resident_id = r.id 
                             LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                             WHERE c.status IN ('resolved', 'closed') " . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "") . "
                             ORDER BY c.created_at DESC";
$resolved_complaints_result = mysqli_query($connection, $resolved_complaints_query);

// Get all complaints with filters
$all_complaints_query = "SELECT c.id, c.nature_of_complaint, c.description, c.status, c.priority, c.created_at, 
                        c.complainant_name, c.complainant_contact, c.defendant_name, c.defendant_contact,
                        c.resolution, c.mediation_date,
                        r.full_name as resident_name, r.contact_number as resident_contact,
                        dr.full_name as defendant_resident_name, dr.contact_number as defendant_resident_contact
                        FROM complaints c 
                        LEFT JOIN residents r ON c.resident_id = r.id 
                        LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                        $where_clause
                        ORDER BY c.created_at DESC";
$all_complaints_result = mysqli_query($connection, $all_complaints_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Complaints Management - Barangay Management System</title>
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

		.page-header {
			margin-bottom: 2rem;
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

		/* Filter Section */
		.filter-section {
			background: white;
			padding: 1.5rem;
			border-radius: 12px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.05);
			margin-bottom: 2rem;
		}

		.filter-grid {
			display: grid;
			grid-template-columns: 1fr 1fr 1fr 1fr auto;
			gap: 1rem;
			align-items: end;
		}

		.filter-group {
			display: flex;
			flex-direction: column;
		}

		.filter-label {
			font-size: 0.85rem;
			color: #666;
			margin-bottom: 0.5rem;
			font-weight: 600;
		}

		.filter-select {
			width: 100%;
			padding: 0.75rem;
			border: 1px solid #ddd;
			border-radius: 8px;
			font-size: 1rem;
			background: white;
			cursor: pointer;
		}

		.filter-input {
			width: 100%;
			padding: 0.75rem;
			border: 1px solid #ddd;
			border-radius: 8px;
			font-size: 1rem;
		}

		.filter-btn {
			padding: 0.75rem 1.5rem;
			background: #3498db;
			color: white;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			font-size: 1rem;
			font-weight: 500;
			transition: all 0.3s ease;
		}

		.filter-btn:hover {
			background: #2980b9;
			transform: translateY(-1px);
		}

		.reset-btn {
			padding: 0.75rem 1.5rem;
			background: #6c757d;
			color: white;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			font-size: 1rem;
			font-weight: 500;
			transition: all 0.3s ease;
			text-decoration: none;
			display: inline-block;
		}

		.reset-btn:hover {
			background: #5a6268;
			transform: translateY(-1px);
		}

		/* Header Actions */
		.header-actions {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 2rem;
		}

		.header-left {
			flex: 1;
		}

		.header-right {
			display: flex;
			gap: 1rem;
		}

		/* Tabs */
		.tabs-container {
			margin-bottom: 2rem;
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
			background: #3498db;
			color: white;
			box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
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

		/* Table Styles */
		.table-container {
			background: white;
			border-radius: 12px;
			box-shadow: 0 4px 15px rgba(0,0,0,0.08);
			overflow: hidden;
		}

		.table-header {
			padding: 1.5rem 2rem;
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

		/* Table Search Styles */
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
			border-color: #3498db;
			box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
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
			background: #3498db;
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
			background: #2980b9;
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
		}

		.complaints-table {
			width: 100%;
			border-collapse: collapse;
		}

		.complaints-table th,
		.complaints-table td {
			padding: 1rem;
			text-align: left;
			border-bottom: 1px solid #f5f5f5;
		}

		.complaints-table th {
			background: #f8f9fa;
			font-weight: 600;
			color: #495057;
			font-size: 0.9rem;
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

		/* Priority Badges */
		.priority-badge {
			padding: 0.25rem 0.5rem;
			border-radius: 4px;
			font-size: 0.7rem;
			font-weight: bold;
			text-transform: uppercase;
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

		/* Action Buttons */
		.action-btn {
			padding: 0.5rem 1rem;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			font-size: 0.85rem;
			font-weight: 500;
			transition: all 0.3s ease;
			margin-right: 0.5rem;
			margin-bottom: 0.5rem;
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

		.btn-danger {
			background: #e74c3c;
			color: white;
		}

		.btn-danger:hover {
			background: #c0392b;
			transform: translateY(-1px);
		}

		.btn-add {
			background: #27ae60;
			color: white;
			padding: 0.75rem 1.5rem;
			font-size: 1rem;
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.btn-add:hover {
			background: #219a52;
			transform: translateY(-2px);
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
			border-color: #3498db;
			box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
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

		.search-result-contact {
			font-size: 0.85rem;
			color: #666;
		}

		.no-results {
			padding: 20px;
			text-align: center;
			color: #666;
		}

		.use-anyway-btn {
			margin-top: 10px;
			padding: 8px 16px;
			background: #3498db;
			color: white;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			font-size: 0.9rem;
			transition: background 0.3s ease;
		}

		.use-anyway-btn:hover {
			background: #2980b9;
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
				min-width: 900px;
			}

			.header-actions {
				flex-direction: column;
				gap: 1rem;
				align-items: stretch;
			}

			.header-right {
				justify-content: center;
			}

			.filter-grid {
				grid-template-columns: 1fr;
			}

			.tabs {
				flex-direction: column;
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

		.tab-content {
			display: none;
		}

		.tab-content.active {
			display: block;
		}

		/* Small z-index/pointer-events fixes so overlays don't block interactive buttons */
		.sidebar-overlay { z-index: 3000; }
		.loading-overlay { z-index: 3500; pointer-events: none; }
		.loading-overlay.show { display: flex; pointer-events: auto; }
		.modal { z-index: 4000; }
	</style>
	<!-- Add SweetAlert CDN -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
				<a href="complaints.php" class="nav-item active">
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
			<h1 class="page-title">Complaints Management</h1>
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
						<h1>Complaints Management</h1>
						<p>Manage and track all resident complaints</p>
					</div>
				</div>
				<div class="header-right">
					<button class="action-btn btn-add" onclick="openCreateModal()">
						<i class="fas fa-plus"></i>
						New Complaint
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
					<i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
				</div>
			<?php endif; ?>

			<!-- Tabs -->
			<div class="tabs-container">
				<div class="tabs">
					<button class="tab active" onclick="switchTab('recent')">
						<i class="fas fa-clock"></i> Recent Complaints
						<?php 
						$recent_count = mysqli_num_rows($recent_complaints_result);
						if ($recent_count > 0): 
						?>
							<span class="tab-badge"><?php echo $recent_count; ?></span>
						<?php endif; ?>
					</button>
					<button class="tab" onclick="switchTab('all')">
						<i class="fas fa-list"></i> All Complaints
						<?php 
						$all_count = mysqli_num_rows($all_complaints_result);
						if ($all_count > 0): 
						?>
							<span class="tab-badge"><?php echo $all_count; ?></span>
						<?php endif; ?>
					</button>
					<button class="tab" onclick="switchTab('pending')">
						<i class="fas fa-hourglass-half"></i> Pending
						<?php 
						$pending_count = mysqli_num_rows($pending_complaints_result);
						if ($pending_count > 0): 
						?>
							<span class="tab-badge"><?php echo $pending_count; ?></span>
						<?php endif; ?>
					</button>
					<button class="tab" onclick="switchTab('resolved')">
						<i class="fas fa-check-circle"></i> Resolved
						<?php 
						$resolved_count = mysqli_num_rows($resolved_complaints_result);
						if ($resolved_count > 0): 
						?>
							<span class="tab-badge"><?php echo $resolved_count; ?></span>
						<?php endif; ?>
					</button>
				</div>
			</div>

			<!-- Recent Complaints Tab (Default) -->
			<div id="recent-tab" class="tab-content active">
				<div class="table-container">
					<div class="table-header">
						<div class="table-title">
							<i class="fas fa-clock"></i>
							Recent Complaints (Last 7 Days)
						</div>
						<div class="table-search">
							<form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
								<input type="hidden" name="status" value="<?php echo $filter_status; ?>">
								<input type="hidden" name="priority" value="<?php echo $filter_priority; ?>">
								<input type="hidden" name="date_from" value="<?php echo $filter_date_from; ?>">
								<input type="hidden" name="date_to" value="<?php echo $filter_date_to; ?>">
								<div class="compact-search">
									<i class="fas fa-search compact-search-icon"></i>
									<input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
								</div>
								<div class="compact-filter">
									<button type="submit" class="compact-btn">
										<i class="fas fa-search"></i> Search
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
								<th>Complainant</th>
								<th>Defendant</th>
								<th>Priority</th>
								<th>Status</th>
								<th>Date Filed</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							mysqli_data_seek($recent_complaints_result, 0);
							if (mysqli_num_rows($recent_complaints_result) > 0): 
							?>
								<?php while ($complaint = mysqli_fetch_assoc($recent_complaints_result)): ?>
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
											if ($complaint['resident_name']) {
												echo htmlspecialchars($complaint['resident_name']);
											} else {
												echo htmlspecialchars($complaint['complainant_name'] ?? 'Unknown');
											}
											?></strong>
											<br>
											<small style="color: #666;">
												<?php 
												if ($complaint['resident_contact']) {
													echo htmlspecialchars($complaint['resident_contact']);
												} else {
													echo htmlspecialchars($complaint['complainant_contact'] ?? 'N/A');
												}
												?>
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
											<span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
												<?php echo ucfirst($complaint['priority']); ?>
											</span>
										</td>
										<td>
											<span class="status-badge status-<?php echo $complaint['status']; ?>">
												<?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
											</span>
										</td>
										<td><?php echo date('M j, Y g:i A', strtotime($complaint['created_at'])); ?></td>
										<td>
											<button class="action-btn btn-primary" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
												<i class="fas fa-eye"></i> View
											</button>
											<button class="action-btn btn-success" onclick="updateStatus(<?php echo $complaint['id']; ?>, '<?php echo $complaint['status']; ?>')">
												<i class="fas fa-edit"></i> Update
											</button>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr>
									<td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
										<i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; color: #3498db;"></i>
										<br>
										No recent complaints (last 7 days)
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- All Complaints Tab -->
			<div id="all-tab" class="tab-content">
				<div class="table-container">
					<div class="table-header">
						<div class="table-title">
							<i class="fas fa-list"></i>
							All Complaints
						</div>
						<div class="table-search">
							<form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
								<input type="hidden" name="status" value="<?php echo $filter_status; ?>">
								<input type="hidden" name="priority" value="<?php echo $filter_priority; ?>">
								<input type="hidden" name="date_from" value="<?php echo $filter_date_from; ?>">
								<input type="hidden" name="date_to" value="<?php echo $filter_date_to; ?>">
								<div class="compact-search">
									<i class="fas fa-search compact-search-icon"></i>
									<input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
								</div>
								<div class="compact-filter">
									<button type="submit" class="compact-btn">
										<i class="fas fa-search"></i> Search
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
								<th>Complainant</th>
								<th>Defendant</th>
								<th>Priority</th>
								<th>Status</th>
								<th>Date Filed</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							mysqli_data_seek($all_complaints_result, 0);
							if (mysqli_num_rows($all_complaints_result) > 0): 
							?>
								<?php while ($complaint = mysqli_fetch_assoc($all_complaints_result)): ?>
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
											if ($complaint['resident_name']) {
												echo htmlspecialchars($complaint['resident_name']);
											} else {
												echo htmlspecialchars($complaint['complainant_name'] ?? 'Unknown');
											}
											?></strong>
											<br>
											<small style="color: #666;">
												<?php 
												if ($complaint['resident_contact']) {
													echo htmlspecialchars($complaint['resident_contact']);
												} else {
													echo htmlspecialchars($complaint['complainant_contact'] ?? 'N/A');
												}
												?>
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
											<span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
												<?php echo ucfirst($complaint['priority']); ?>
											</span>
										</td>
										<td>
											<span class="status-badge status-<?php echo $complaint['status']; ?>">
												<?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
											</span>
										</td>
										<td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
										<td>
											<button class="action-btn btn-primary" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
												<i class="fas fa-eye"></i> View
											</button>
											<button class="action-btn btn-success" onclick="updateStatus(<?php echo $complaint['id']; ?>, '<?php echo $complaint['status']; ?>')">
												<i class="fas fa-edit"></i> Update
											</button>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr>
									<td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
										<i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
										<br>
										No complaints found
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Pending Complaints Tab -->
			<div id="pending-tab" class="tab-content">
				<div class="table-container">
					<div class="table-header">
						<div class="table-title">
							<i class="fas fa-hourglass-half"></i>
							Pending Complaints
						</div>
						<div class="table-search">
							<form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
								<input type="hidden" name="status" value="<?php echo $filter_status; ?>">
								<input type="hidden" name="priority" value="<?php echo $filter_priority; ?>">
								<input type="hidden" name="date_from" value="<?php echo $filter_date_from; ?>">
								<input type="hidden" name="date_to" value="<?php echo $filter_date_to; ?>">
								<div class="compact-search">
									<i class="fas fa-search compact-search-icon"></i>
									<input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
								</div>
								<div class="compact-filter">
									<button type="submit" class="compact-btn">
										<i class="fas fa-search"></i> Search
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
								<th>Complainant</th>
								<th>Defendant</th>
								<th>Priority</th>
								<th>Status</th>
								<th>Date Filed</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							mysqli_data_seek($pending_complaints_result, 0);
							if (mysqli_num_rows($pending_complaints_result) > 0): 
							?>
								<?php while ($complaint = mysqli_fetch_assoc($pending_complaints_result)): ?>
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
											if ($complaint['resident_name']) {
												echo htmlspecialchars($complaint['resident_name']);
											} else {
												echo htmlspecialchars($complaint['complainant_name'] ?? 'Unknown');
											}
											?></strong>
											<br>
											<small style="color: #666;">
												<?php 
												if ($complaint['resident_contact']) {
													echo htmlspecialchars($complaint['resident_contact']);
												} else {
													echo htmlspecialchars($complaint['complainant_contact'] ?? 'N/A');
												}
												?>
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
											<span class="priority-badge priority-<?php echo $complaint['priority']; ?>">
												<?php echo ucfirst($complaint['priority']); ?>
											</span>
										</td>
										<td>
											<span class="status-badge status-<?php echo $complaint['status']; ?>">
												<?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
											</span>
										</td>
										<td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
										<td>
											<button class="action-btn btn-primary" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
												<i class="fas fa-eye"></i> View
											</button>
											<button class="action-btn btn-success" onclick="updateStatus(<?php echo $complaint['id']; ?>, '<?php echo $complaint['status']; ?>')">
												<i class="fas fa-edit"></i> Update
											</button>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr>
									<td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
										<i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; color: #27ae60;"></i>
										<br>
										No pending complaints
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Resolved Complaints Tab -->
			<div id="resolved-tab" class="tab-content">
				<div class="table-container">
					<div class="table-header">
						<div class="table-title">
							<i class="fas fa-check-circle"></i>
							Resolved Complaints
						</div>
						<div class="table-search">
							<form method="GET" action="" style="display: flex; align-items: center; gap: 0.75rem;">
								<input type="hidden" name="status" value="<?php echo $filter_status; ?>">
								<input type="hidden" name="priority" value="<?php echo $filter_priority; ?>">
								<input type="hidden" name="date_from" value="<?php echo $filter_date_from; ?>">
								<input type="hidden" name="date_to" value="<?php echo $filter_date_to; ?>">
								<div class="compact-search">
									<i class="fas fa-search compact-search-icon"></i>
									<input type="text" name="search" class="compact-search-input" placeholder="Search complaints..." value="<?php echo htmlspecialchars($search); ?>">
								</div>
								<div class="compact-filter">
									<button type="submit" class="compact-btn">
										<i class="fas fa-search"></i> Search
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
								<th>Complainant</th>
								<th>Defendant</th>
								<th>Resolution</th>
								<th>Mediation Date</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php 
							mysqli_data_seek($resolved_complaints_result, 0);
							if (mysqli_num_rows($resolved_complaints_result) > 0): 
							?>
								<?php while ($complaint = mysqli_fetch_assoc($resolved_complaints_result)): ?>
									<tr>
										<td>#<?php echo $complaint['id']; ?></td>
										<td>
											<strong><?php echo htmlspecialchars($complaint['nature_of_complaint']); ?></strong>
										</td>
										<td>
											<strong><?php 
											if ($complaint['resident_name']) {
												echo htmlspecialchars($complaint['resident_name']);
											} else {
												echo htmlspecialchars($complaint['complainant_name'] ?? 'Unknown');
											}
											?></strong>
										</td>
										<td>
											<strong><?php 
											if ($complaint['defendant_resident_name']) {
												echo htmlspecialchars($complaint['defendant_resident_name']);
											} else {
												echo htmlspecialchars($complaint['defendant_name'] ?? 'Unknown');
											}
											?></strong>
										</td>
										<td>
											<?php if ($complaint['resolution']): ?>
												<small><?php echo htmlspecialchars(substr($complaint['resolution'], 0, 50)) . '...'; ?></small>
											<?php else: ?>
												<small style="color: #999;">No resolution recorded</small>
											<?php endif; ?>
										</td>
										<td>
											<?php if ($complaint['mediation_date']): ?>
												<?php echo date('M j, Y', strtotime($complaint['mediation_date'])); ?>
											<?php else: ?>
												<small style="color: #999;">N/A</small>
											<?php endif; ?>
										</td>
										<td>
											<span class="status-badge status-<?php echo $complaint['status']; ?>">
												<?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
											</span>
										</td>
										<td>
											<button class="action-btn btn-primary" onclick="viewComplaint(<?php echo $complaint['id']; ?>)">
												<i class="fas fa-eye"></i> View
											</button>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr>
									<td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
										<i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
										<br>
										No resolved complaints found
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Create Complaint Modal -->
	<div class="modal" id="createModal">
		<div class="modal-content">
			<button class="close-btn" onclick="closeModal('createModal')">&times;</button>
			<div class="modal-header">
				<h2 class="modal-title">Create New Complaint</h2>
			</div>
			<form method="POST" action="">
				<div class="form-group">
					<label class="form-label">Nature of Complaint *</label>
					<input type="text" class="form-input" name="nature_of_complaint" required placeholder="Enter nature/purpose of complaint">
				</div>
				
				<div class="form-group">
					<label class="form-label">Description *</label>
					<textarea class="form-textarea" name="description" required placeholder="Describe the complaint in detail"></textarea>
				</div>
				
				<div class="form-group">
					<label class="form-label">Priority *</label>
					<select class="form-select" name="priority" required>
						<option value="">Select Priority</option>
						<option value="low">Low</option>
						<option value="medium">Medium</option>
						<option value="high">High</option>
					</select>
				</div>
				
				<!-- COMPLAINANT SECTION -->
				<div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
					<h4 style="margin-bottom: 1rem; color: #333;">Complainant Information</h4>
					
					<div class="form-group">
						<label class="form-label">Complainant Name *</label>
						<div style="position: relative;">
							<input type="text" 
								   class="form-input search-input" 
								   id="complainantSearch" 
								   name="complainant_name"
								   placeholder="Type name to search or enter new name..."
								   autocomplete="off"
								   required>
							<div id="complainantSearchResults" class="search-results"></div>
						</div>
						<div id="complainantStatus" class="resident-status"></div>
					</div>
					
					<!-- Hidden fields for complainant -->
					<input type="hidden" name="complainant_type" id="complainantType" value="non-resident">
					<input type="hidden" name="resident_id" id="complainantResidentId" value="">
					
					<div class="form-group">
						<label class="form-label">Contact Number *</label>
						<input type="text" class="form-input" name="complainant_contact" id="complainantContact" required placeholder="Enter contact number">
					</div>
				</div>
				
				<!-- DEFENDANT SECTION -->
				<div style="background: #fff3cd; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
					<h4 style="margin-bottom: 1rem; color: #333;">Defendant Information</h4>
					
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
					
					<div class="form-group">
						<label class="form-label">Contact Number *</label>
						<input type="text" class="form-input" name="defendant_contact" id="defendantContact" required placeholder="Enter contact number">
					</div>
				</div>
				
				<div class="modal-actions">
					<button type="button" class="action-btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
					<button type="submit" name="create_complaint" class="action-btn btn-primary">Create Complaint</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Status Update Modal -->
	<div class="modal" id="statusModal">
		<div class="modal-content">
			<button class="close-btn" onclick="closeModal('statusModal')">&times;</button>
			<div class="modal-header">
				<h2 class="modal-title">Update Complaint Status</h2>
			</div>
			<form method="POST" action="">
				<input type="hidden" id="complaintId" name="complaint_id">
				<div class="form-group">
					<label class="form-label">Status *</label>
					<select class="form-select" name="status" id="statusSelect" required onchange="toggleResolutionFields()">
						<option value="pending">Pending</option>
						<option value="in-progress">In Progress</option>
						<option value="resolved">Resolved</option>
						<option value="closed">Closed</option>
					</select>
				</div>
				
				<div id="resolutionFields" style="display: none;">
					<div class="form-group">
						<label class="form-label">Resolution Details</label>
						<textarea class="form-textarea" name="resolution" placeholder="Describe how the complaint was resolved..."></textarea>
					</div>
					
					<div class="form-group">
						<label class="form-label">Date of Mediation</label>
						<input type="date" class="form-input" name="mediation_date">
					</div>
				</div>
				
				<div class="modal-actions">
					<button type="button" class="action-btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
					<button type="submit" name="update_status" class="action-btn btn-primary">Update Status</button>
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

	<script>
		// Resident data from PHP
		const residents = <?php 
			mysqli_data_seek($residents_result, 0);
			$residents_array = [];
			while ($resident = mysqli_fetch_assoc($residents_result)) {
				$residents_array[] = [
					'id' => $resident['id'],
					'full_name' => $resident['full_name'],
					'contact_number' => $resident['contact_number']
				];
			}
			echo json_encode($residents_array);
		?>;

		let searchTimeout;
		let isSelectingResident = false;

		// Initialize search functionality
		document.addEventListener('DOMContentLoaded', function() {
			// Complainant search
			const complainantSearch = document.getElementById('complainantSearch');
			const complainantResults = document.getElementById('complainantSearchResults');
			
			complainantSearch.addEventListener('input', function() {
				if (isSelectingResident) {
					isSelectingResident = false;
					return;
				}
				
				clearTimeout(searchTimeout);
				const query = this.value.trim();
				
				// Reset to non-resident if user is typing
				document.getElementById('complainantType').value = 'non-resident';
				document.getElementById('complainantResidentId').value = '';
				document.getElementById('complainantStatus').style.display = 'none';
				
				if (query.length < 2) {
					complainantResults.style.display = 'none';
					return;
				}
				
				searchTimeout = setTimeout(() => {
					searchResidents(query, 'complainant');
				}, 300);
			});
			
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
					searchResidents(query, 'defendant');
			
				}, 300);
			});
			
			// Click outside to close search results
			document.addEventListener('click', function(e) {
				if (!e.target.closest('.search-results') && !e.target.classList.contains('search-input')) {
					complainantResults.style.display = 'none';
					defendantResults.style.display = 'none';
				}
			});
		});

		function searchResidents(query, type) {
			const resultsContainer = document.getElementById(`${type}SearchResults`);
			
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
						<div class="search-result-contact">${resident.contact_number}</div>
					`;
					resultItem.onclick = () => selectResident(resident, type);
					resultsContainer.appendChild(resultItem);
				});
				
				// Add option to use as non-resident
				const nonResidentOption = document.createElement('div');
				nonResidentOption.style.cssText = 'padding: 15px; background: #f8f9fa; border-top: 1px solid #ddd; text-align: center;';
				nonResidentOption.innerHTML = `
					<p style="margin: 0 0 10px 0; color: #666; font-size: 0.9rem;">Not a registered resident?</p>
					<button type="button" class="use-anyway-btn" onclick="useNonResident('${query}', '${type}')">
						Use "${query}" as non-resident
					</button>
				`;
				resultsContainer.appendChild(nonResidentOption);
			} else {
				resultsContainer.innerHTML = `
					<div class="no-results">
						<p>No registered resident found</p>
						<button type="button" class="use-anyway-btn" onclick="useNonResident('${query}', '${type}')">
							Use "${query}" as non-resident
						</button>
					</div>
				`;
			}
			
			resultsContainer.style.display = 'block';
		}

		function selectResident(resident, type) {
			isSelectingResident = true;
			const searchInput = document.getElementById(`${type}Search`);
			const contactInput = document.getElementById(`${type}Contact`);
			const typeInput = document.getElementById(`${type}Type`);
			const residentIdInput = document.getElementById(`${type}ResidentId`);
			const statusDiv = document.getElementById(`${type}Status`);
			const resultsContainer = document.getElementById(`${type}SearchResults`);
			
			// Set values
			searchInput.value = resident.full_name;
			contactInput.value = resident.contact_number;
			typeInput.value = 'resident';
			residentIdInput.value = resident.id;
			
			// Show status
			statusDiv.className = 'resident-status registered';
			statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Registered Resident';
			statusDiv.style.display = 'block';
			
			// Hide search results
			resultsContainer.style.display = 'none';
		}

		function useNonResident(name, type) {
			isSelectingResident = true;
			const searchInput = document.getElementById(`${type}Search`);
			const contactInput = document.getElementById(`${type}Contact`);
			const typeInput = document.getElementById(`${type}Type`);
			const residentIdInput = document.getElementById(`${type}ResidentId`);
			const statusDiv = document.getElementById(`${type}Status`);
			const resultsContainer = document.getElementById(`${type}SearchResults`);
			
			// Set values
			searchInput.value = name;
			contactInput.value = '';
			typeInput.value = 'non-resident';
			residentIdInput.value = '';
			
			// Show status
			statusDiv.className = 'resident-status not-registered';
			statusDiv.innerHTML = '<i class="fas fa-info-circle"></i> Non-Resident (Manual Entry)';
			statusDiv.style.display = 'block';
			
			// Focus on contact field
			contactInput.focus();
			
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

		function openCreateModal() {
			document.getElementById('createModal').style.display = 'block';
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

		function toggleResolutionFields() {
			const statusSelect = document.getElementById('statusSelect');
			const resolutionFields = document.getElementById('resolutionFields');
			
			if (statusSelect.value === 'resolved' || statusSelect.value === 'closed') {
				resolutionFields.style.display = 'block';
			} else {
				resolutionFields.style.display = 'none';
			}
		}

		function viewComplaint(id) {
			// For this demo, we'll create a simple view
			// In production, you'd fetch this data via AJAX
			const modal = document.getElementById('viewModal');
			const detailsDiv = document.getElementById('complaintDetails');
			
			// This is a placeholder - in production, fetch actual data
			detailsDiv.innerHTML = `
				<div style="margin-bottom: 1rem;">
					<strong>Loading complaint details...</strong>
				</div>
			`;
			
			modal.style.display = 'block';
			
			// Simulate AJAX call
			setTimeout(() => {
				detailsDiv.innerHTML = `
					<div style="margin-bottom: 1rem;">
						<strong>Complaint ID:</strong> #${id}
					</div>
					<div style="margin-bottom: 1rem;">
						<strong>Status:</strong> <span class="status-badge status-pending">Pending</span>
					</div>
					<div style="margin-bottom: 1rem;">
						<strong>Nature of Complaint:</strong> Sample Complaint
					</div>
					<div style="margin-bottom: 1rem;">
						<strong>Description:</strong><br>
						<div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-top: 0.5rem;">
							This is a sample complaint description. In production, this would show the actual complaint details.
						</div>
					</div>
					<div style="margin-bottom: 1rem;">
						<strong>Date Filed:</strong> ${new Date().toLocaleDateString()}
					</div>
				`;
			}, 500);
		}

		function updateStatus(id, currentStatus) {
			document.getElementById('complaintId').value = id;
			document.getElementById('statusSelect').value = currentStatus;
			toggleResolutionFields();
			document.getElementById('statusModal').style.display = 'block';
		}

		function closeModal(modalId) {
			document.getElementById(modalId).style.display = 'none';
			
			if (modalId === 'createModal') {
				const form = document.querySelector('#createModal form');
				form.reset();
				
				// Reset complainant fields
				document.getElementById('complainantStatus').style.display = 'none';
				document.getElementById('complainantType').value = 'non-resident';
				document.getElementById('complainantResidentId').value = '';
				
				// Reset defendant fields
				document.getElementById('defendantStatus').style.display = 'none';
				document.getElementById('defendantType').value = 'non-resident';
				document.getElementById('defendantResidentId').value = '';
			}
		}

		// Close modal when clicking outside
		window.onclick = function(event) {
			const modals = ['createModal', 'statusModal', 'viewModal'];
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
		
		// Ensure buttons without explicit type don't act as form submit and break JS handlers.
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('button').forEach(function(btn) {
				if (!btn.hasAttribute('type')) btn.type = 'button';
			});
		});
	</script>
</body>
</html>