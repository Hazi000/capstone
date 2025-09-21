<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
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
        
        // Only get resolution and mediation date if status is being set to resolved
        $resolution = ($new_status === 'resolved') ? mysqli_real_escape_string($connection, $_POST['resolution'] ?? '') : '';
        $mediation_date = ($new_status === 'resolved' && !empty($_POST['mediation_date'])) ? 
                         mysqli_real_escape_string($connection, $_POST['mediation_date']) : NULL;
        
        $update_query = "UPDATE complaints SET status = '$new_status'";
        
        // Add resolution and mediation date only if status is resolved
        if ($new_status === 'resolved') {
            $update_query .= ", resolution = '$resolution', mediation_date = '$mediation_date'";
        }
        
        $update_query .= " WHERE id = $complaint_id";
        
        if (mysqli_query($connection, $update_query)) {
            $_SESSION['success_message'] = "Complaint status updated to " . ucfirst($new_status) . " successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Error updating complaint status: " . mysqli_error($connection);
        }
    }
    
    // Handle create complaint
    if (isset($_POST['create_complaint'])) {
        $nature_of_complaint = mysqli_real_escape_string($connection, $_POST['nature_of_complaint']);
        $description = mysqli_real_escape_string($connection, $_POST['description']);
        
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
        
        // Validate that complainant and defendant are not the same person
        $is_same_person = false;
        if ($complainant_type === 'resident' && $defendant_type === 'resident') {
            if ($resident_id === $defendant_resident_id) {
                $is_same_person = true;
            }
        } else if ($complainant_type === 'non-resident' && $defendant_type === 'non-resident') {
            if (strtolower(trim($complainant_name)) === strtolower(trim($defendant_name))) {
                $is_same_person = true;
            }
        } else if ($complainant_type === 'resident' && $defendant_type === 'non-resident') {
            $resident_query = "SELECT full_name FROM residents WHERE id = $resident_id";
            $resident_result = mysqli_query($connection, $resident_query);
            if ($resident = mysqli_fetch_assoc($resident_result)) {
                if (strtolower(trim($resident['full_name'])) === strtolower(trim($defendant_name))) {
                    $is_same_person = true;
                }
            }
        } else if ($complainant_type === 'non-resident' && $defendant_type === 'resident') {
            $resident_query = "SELECT full_name FROM residents WHERE id = $defendant_resident_id";
            $resident_result = mysqli_query($connection, $resident_query);
            if ($resident = mysqli_fetch_assoc($resident_result)) {
                if (strtolower(trim($complainant_name)) === strtolower(trim($resident['full_name']))) {
                    $is_same_person = true;
                }
            }
        }

        if ($is_same_person) {
            // Remove the error_message assignment and just let the form resubmit fail silently
            // The JavaScript validation will handle showing the error in the modal
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
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
                $_SESSION['success_message'] = "Complaint created successfully!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error_message = "Error creating complaint: " . mysqli_error($connection);
            }
        }
    }
}

// Move success message handling here, after redirects
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
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

// Change the default filter from 'recent' to 'pending'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';
$current_result = null;

switch($filter) {
    case 'all':
        $base_query = $all_complaints_query;
        $count_query = "SELECT COUNT(*) as total FROM complaints c 
                       LEFT JOIN residents r ON c.resident_id = r.id 
                       LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                       $where_clause";
        $table_title = 'All Complaints';
        break;
    case 'resolved':
        $base_query = $resolved_complaints_query;
        $count_query = "SELECT COUNT(*) as total FROM complaints c 
                       LEFT JOIN residents r ON c.resident_id = r.id 
                       LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                       WHERE c.status IN ('resolved', 'closed') 
                       " . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "");
        $table_title = 'Resolved Complaints';
        break;
    default: // Default is 'pending'
        $base_query = $pending_complaints_query;
        $count_query = "SELECT COUNT(*) as total FROM complaints c 
                       LEFT JOIN residents r ON c.resident_id = r.id 
                       LEFT JOIN residents dr ON c.defendant_resident_id = dr.id
                       WHERE c.status IN ('pending', 'in-progress') 
                       " . (!empty($where_conditions) ? " AND " . implode(" AND ", $where_conditions) : "");
        $table_title = 'Pending Complaints';
        break;
}

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total count for pagination
$count_result = mysqli_query($connection, $count_query);
$total_items = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Add pagination to the query
$paginated_query = $base_query . " LIMIT $items_per_page OFFSET $offset";
$current_result = mysqli_query($connection, $paginated_query);
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
            text-decoration: none;
            color: white;
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
            position: relative;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
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
            position: relative;
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
            top: calc(100% + 2px);
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 2100;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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

        /* Add these styles to your existing CSS */
        .complaint-details {
            padding: 20px;
        }
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
        }
        
        .detail-section p {
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .detail-section strong {
            color: #34495e;
            min-width: 120px;
            display: inline-block;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #fff;
            border-top: 1px solid #eee;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #3498db;
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: #e9ecef;
            text-decoration: none;
            color: #2980b9;
        }

        .page-link.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-dots {
            padding: 0.5rem;
            color: #6c757d;
            font-weight: bold;
        }

        /* Add this specific style for create modal */
        #createModal .modal-content {
            max-width: 450px;  /* Reduced from 600px */
            margin: 5vh auto;  /* Position higher on screen - 5% from top */
            padding: 1.25rem;   /* Slightly reduced padding */
            max-height: 85vh;  /* Limit height to 85% of viewport height */
            overflow-y: auto;  /* Enable scrolling for content */
        }

        /* Update modal styles */
        #createModal .modal-content {
            max-width: 450px;
            max-height: 85vh;  /* Limit height to 85% of viewport height */
            margin: 5vh auto;  /* Position higher on screen - 5% from top */
            padding: 1.25rem;
            overflow-y: auto;  /* Enable scrolling for content */
        }

        #createModal .form-group {
            margin-bottom: 0.75rem;  /* Reduce spacing between form groups */
        }

        #createModal .form-input,
        #createModal .form-textarea {
            padding: 0.5rem;  /* Smaller input padding */
        }

        #createModal .form-textarea {
            min-height: 60px;  /* Shorter textarea */
        }

        #createModal .modal-title {
            font-size: 1.25rem;  /* Slightly smaller title */
            margin-bottom: 0.75rem;
        }

        #createModal .modal-actions {
            margin-top: 1rem;  /* Less space above buttons */
            padding-top: 0.75rem;
            border-top: 1px solid #eee;
        }

        /* Adjust name input grid to be more compact */
        #createModal .form-group div[style*="grid-template-columns"] {
            gap: 0.5rem;
        }
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
                 <a href="disaster_management.php" class="nav-item">
                    <i class="fas fa-house-damage"></i>
                    Disaster Management
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
            <h1 class="page-title">Complaints Management</h1>
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
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Update the New Complaint button style -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list"></i>
                        <?php echo $table_title; ?>
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
                                <select name="filter" class="compact-select" onchange="this.form.submit()">
                                    <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="resolved" <?php echo $filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                                <button type="button" class="compact-btn" onclick="openCreateModal()">
                                    <i class="fas fa-plus"></i> New Complaint
                                </button>
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
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($current_result) > 0):
                            while ($complaint = mysqli_fetch_assoc($current_result)): 
                                // Prepare sanitized data for JS
                                $complaint_safe = array_map('htmlspecialchars', [
                                    'nature_of_complaint' => $complaint['nature_of_complaint'],
                                    'description' => $complaint['description'],
                                    'status' => $complaint['status'],
                                    'created_at' => $complaint['created_at'],
                                    'complainant' => $complaint['resident_name'] ?: $complaint['complainant_name'],
                                    'complainant_contact' => $complaint['resident_contact'] ?: $complaint['complainant_contact'],
                                    'defendant' => $complaint['defendant_resident_name'] ?: $complaint['defendant_name'],
                                    'defendant_contact' => $complaint['defendant_resident_contact'] ?: $complaint['defendant_contact'],
                                    'resolution' => $complaint['resolution'],
                                    'mediation_date' => $complaint['mediation_date']
                                ]);
                        ?>
                            <tr>
                                <td>#<?php echo $complaint['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($complaint['nature_of_complaint']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo $complaint['resident_name'] ?: htmlspecialchars($complaint['complainant_name']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo $complaint['defendant_resident_name'] ?: htmlspecialchars($complaint['defendant_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $complaint['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <button class="action-btn btn-primary" onclick='viewComplaint(<?php echo json_encode($complaint_safe); ?>)'>
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($complaint['status'] === 'pending'): ?>
                            <button class="action-btn btn-success" onclick="updateStatus(<?php echo $complaint['id']; ?>, 'in-progress')">
                                <i class="fas fa-play"></i> Set In Progress
                            </button>
                        <?php elseif ($complaint['status'] === 'in-progress'): ?>
                            <button class="action-btn btn-success" onclick="updateStatus(<?php echo $complaint['id']; ?>, 'resolved')">
                                <i class="fas fa-check"></i> Set Resolved
                            </button>
                        <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                    </tbody>
                </table>
                
                <!-- Pagination Controls -->
                <div class="pagination">
                    <?php if ($total_pages > 1): ?>
                        <!-- First page and prev -->
                        <?php if ($current_page > 1): ?>
                            <a href="?page=1&filter=<?php echo $filter; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="page-link">&laquo;</a>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        
                        if ($start_page > 1) {
                            echo '<span class="page-dots">...</span>';
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="page-link <?php echo $current_page === $i ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor;
                        
                        if ($end_page < $total_pages) {
                            echo '<span class="page-dots">...</span>';
                        }
                        ?>

                        <!-- Last page and next -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $total_pages; ?>&filter=<?php echo $filter; ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="page-link">&raquo;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

<!-- Add this JavaScript before the closing </body> tag -->
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

        // NEW: global helper — build full name from parts and set hidden inputs
        function setHiddenFullName(type) {
            const firstEl = document.getElementById(`${type}FirstName`);
            const middleEl = document.getElementById(`${type}Middle`);
            const lastEl = document.getElementById(`${type}LastName`);
            const first = firstEl ? firstEl.value.trim() : '';
            const middle = middleEl ? middleEl.value.trim() : '';
            const last = lastEl ? lastEl.value.trim() : '';
            const full = `${first}${middle ? ' ' + (middle.length === 1 ? middle + '.' : middle) : ''}${last ? ' ' + last : ''}`.trim();
            const hidden = document.getElementById(`${type}Search`);
            if (hidden) hidden.value = full;
            const serverName = document.querySelector(`input[name="${type}_name"]`);
            if (serverName) serverName.value = full;
            return full;
        }

        // Clear any error styling and messages (required by closeModal)
        function clearErrors() {
            ['complainant', 'defendant'].forEach(type => {
                ['FirstName', 'Middle', 'LastName'].forEach(part => {
                    const el = document.getElementById(`${type}${part}`);
                    if (el) el.style.borderColor = '';
                });
                const err = document.getElementById(`${type}Error`);
                if (err) err.remove();
            });
        }

        // UPDATED validateForm: close modal and show SweetAlert with exact message when same person
        function validateForm() {
            // Ensure hidden server name fields are updated from visible parts
            setHiddenFullName('complainant');
            setHiddenFullName('defendant');

            const cFirst = document.getElementById('complainantFirstName').value || '';
            const cMiddle = document.getElementById('complainantMiddle').value || '';
            const cLast = document.getElementById('complainantLastName').value || '';
            const dFirst = document.getElementById('defendantFirstName').value || '';
            const dMiddle = document.getElementById('defendantMiddle').value || '';
            const dLast = document.getElementById('defendantLastName').value || '';

            // normalize: lowercase, collapse whitespace
            const normalize = s => s.toLowerCase().replace(/\s+/g, ' ').trim();
            const complainantName = normalize(`${cFirst} ${cMiddle} ${cLast}`);
            const defendantName = normalize(`${dFirst} ${dMiddle} ${dLast}`);

            if (complainantName && defendantName && complainantName === defendantName) {
                // remove any prior errors, close modal (closeModal calls clearErrors)
                try {
                    closeModal('createModal');
                } catch (e) {
                    const m = document.getElementById('createModal');
                    if (m) m.style.display = 'none';
                }

                Swal.fire({
                    title: 'Invalid Submission',
                    text: 'invalid the same person',
                    icon: 'error',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });

                return false; // prevent submit
            }

            // ok to submit
            return true;
        }

        // Initialize search functionality
        document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const complainantFirst = document.getElementById('complainantFirstName');
    const complainantResults = document.getElementById('complainantSearchResults');
    const defendantFirst = document.getElementById('defendantFirstName');
    const defendantResults = document.getElementById('defendantSearchResults');

    // Typing in first name triggers resident search (default to non-resident)
    [ ['complainant', complainantFirst, complainantResults], ['defendant', defendantFirst, defendantResults] ].forEach(([type, firstInput, resultsContainer]) => {
        if (!firstInput) return;
        firstInput.addEventListener('input', function() {
            if (isSelectingResident) {
                isSelectingResident = false;
                return;
            }
            
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            // Default to non-resident while typing
            const typeInput = document.getElementById(`${type}Type`);
            const residentIdInput = document.getElementById(`${type}ResidentId`);
            if (typeInput) typeInput.value = 'non-resident';
            if (residentIdInput) residentIdInput.value = '';
            const statusDiv = document.getElementById(`${type}Status`);
            if (statusDiv) statusDiv.style.display = 'none';

            // update hidden full-name immediately
            setHiddenFullName(type);

            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }

            searchResidents(query, type);
        });
    });

    // Click outside to close search results (works with search-input class)
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-results') && !e.target.classList.contains('search-input')) {
            if (complainantResults) complainantResults.style.display = 'none';
            if (defendantResults) defendantResults.style.display = 'none';
        }
    });

    // Name field sanitizers: update hidden name when any name part changes
    const nameParts = ['FirstName', 'Middle', 'LastName'];
    ['complainant','defendant'].forEach(type => {
        nameParts.forEach(part => {
            const el = document.getElementById(`${type}${part}`);
            if (!el) return;
            el.addEventListener('input', function() {
                // allow only letters, spaces and dot for middle
                this.value = this.value.replace(/[^A-Za-z\s.]/g, '');
                if (this.id.includes('Middle') && this.value.length > 1) {
                    this.value = this.value[0];
                }
                setHiddenFullName(type);
            });
            // on blur also sanitize
            el.addEventListener('blur', function() {
                setHiddenFullName(type);
            });
        });
    });
});

        function searchResidents(query, type) {
            const resultsContainer = document.getElementById(`${type}SearchResults`);
            
            // Set as non-resident by default when typing
            const typeInput = document.getElementById(`${type}Type`);
            const residentIdInput = document.getElementById(`${type}ResidentId`);
            typeInput.value = 'non-resident';
            residentIdInput.value = '';
            
            // Filter residents based on query
            const filteredResidents = residents.filter(resident => 
                resident.full_name.toLowerCase().includes(query.toLowerCase())
            );
            
            resultsContainer.innerHTML = '';
            
            if (filteredResidents.length > 0) {
                // Show only registered residents in dropdown
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
            }
            
            resultsContainer.style.display = filteredResidents.length > 0 ? 'block' : 'none';
        }

        function selectResident(resident, type) {
            const otherType = type === 'complainant' ? 'defendant' : 'complainant';
            const otherFirst = document.getElementById(`${otherType}FirstName`);
            
            if (otherFirst && otherFirst.value.toLowerCase() === resident.full_name.toLowerCase()) {
                // Show error styling
                const inputs = ['FirstName', 'Middle', 'LastName'].map(part => 
                    document.getElementById(`${type}${part}`)
                );
                
                // Add red border to all name inputs
                inputs.forEach(input => {
                    if (input) {
                        input.style.borderColor = '#dc3545';
                    }
                });

                // Create or update error message
                let errorDiv = document.getElementById(`${type}Error`);
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.id = `${type}Error`;
                    document.getElementById(`${type}Status`).parentNode.insertBefore(
                        errorDiv, 
                        document.getElementById(`${type}Status`)
                    );
                }
                errorDiv.textContent = 'Cannot select the same person as both complainant and defendant';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '0.875rem';
                errorDiv.style.marginTop = '0.25rem';
                return;
            }

            // Clear any existing error styling
            ['FirstName', 'Middle', 'LastName'].forEach(part => {
                const input = document.getElementById(`${type}${part}`);
                if (input) {
                    input.style.borderColor = '';
                }
            });
            
            const errorDiv = document.getElementById(`${type}Error`);
            if (errorDiv) {
                errorDiv.remove();
            }

            isSelectingResident = true;
            const contactInput = document.getElementById(`${type}Contact`);
            const typeInput = document.getElementById(`${type}Type`);
            const residentIdInput = document.getElementById(`${type}ResidentId`);
            const statusDiv = document.getElementById(`${type}Status`);
            const resultsContainer = document.getElementById(`${type}SearchResults`);

            // Split resident.full_name into parts
            const names = resident.full_name.split(/\s+/);
            const firstName = names[0] || '';
            const lastName = names.length > 1 ? names[names.length - 1] : '';
            const middleName = names.length > 2 ? names.slice(1, names.length-1).join(' ') : (names[1] && names.length===2 ? '' : '');

            // Set visible name parts and make readonly
            const firstEl = document.getElementById(`${type}FirstName`);
            const middleEl = document.getElementById(`${type}Middle`);
            const lastEl = document.getElementById(`${type}LastName`);
            if (firstEl) {
                firstEl.value = firstName;
                firstEl.readOnly = true;
            }
            if (middleEl) {
                middleEl.value = middleName;
                middleEl.readOnly = true;
            }
            if (lastEl) {
                lastEl.value = lastName;
                lastEl.readOnly = true;
            }

            // Set contact input readonly
            if (contactInput) {
                contactInput.value = resident.contact_number;
                contactInput.readOnly = true;
            }

            if (typeInput) typeInput.value = 'resident';
            if (residentIdInput) residentIdInput.value = resident.id;

            if (statusDiv) {
                statusDiv.className = 'resident-status registered';
                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Registered Resident';
                statusDiv.style.display = 'block';
            }

            if (resultsContainer) resultsContainer.style.display = 'none';
        }

        function clearResident(type) {
            // Remove readonly attributes
            document.getElementById(`${type}FirstName`).readOnly = false;
            document.getElementById(`${type}Middle`).readOnly = false; 
            document.getElementById(`${type}LastName`).readOnly = false;
            document.getElementById(`${type}Contact`).readOnly = false;

            // Reset values
            document.getElementById(`${type}FirstName`).value = '';
            document.getElementById(`${type}Middle`).value = '';
            document.getElementById(`${type}LastName`).value = '';
            document.getElementById(`${type}Contact`).value = '';
            document.getElementById(`${type}Type`).value = 'non-resident';
            document.getElementById(`${type}ResidentId`).value = '';
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

        function toggleResolutionFields() {
            const statusSelect = document.getElementById('statusSelect');
            const resolutionFields = document.getElementById('resolutionFields');
            
            if (statusSelect.value === 'resolved' || statusSelect.value === 'closed') {
                resolutionFields.style.display = 'block';
            } else {
                resolutionFields.style.display = 'none';
            }
        }

        function updateStatus(id, newStatus) {
            let title = newStatus === 'in-progress' ? 'Set to In Progress?' : 'Set to Resolved?';
            let text = newStatus === 'in-progress' ? 
                       'Are you sure you want to set this complaint to In Progress?' :
                       'Are you sure you want to resolve this complaint?';

            if (newStatus === 'resolved') {
                // Show modal with resolution form
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'question',
                    html: `
                        <div class="form-group">
                            <label class="form-label">Resolution Details</label>
                            <textarea id="swal-resolution" class="form-textarea" style="width: 100%; margin: 10px 0;" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mediation Date</label>
                            <input type="date" id="swal-mediation-date" class="form-input" style="width: 100%;" required>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#27ae60',
                    cancelButtonColor: '#95a5a6',
                    preConfirm: () => {
                        const resolution = document.getElementById('swal-resolution').value;
                        const mediationDate = document.getElementById('swal-mediation-date').value;
                        
                        if (!resolution || !mediationDate) {
                            Swal.showValidationMessage('Please fill in all fields');
                            return false;
                        }
                        return { resolution, mediationDate };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create and submit form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const fields = {
                            'complaint_id': id,
                            'status': newStatus,
                            'resolution': result.value.resolution,
                            'mediation_date': result.value.mediationDate,
                            'update_status': '1'
                        };

                        for (const [key, value] of Object.entries(fields)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            } else {
                // Show simple confirmation for in-progress
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#27ae60',
                    cancelButtonColor: '#95a5a6'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create and submit form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const fields = {
                            'complaint_id': id,
                            'status': newStatus,
                            'update_status': '1'
                        };

                        for (const [key, value] of Object.entries(fields)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            
            if (modalId === 'createModal') {
                clearErrors();
                const form = document.querySelector('#createModal form');
                form.reset();
                
                // Reset complainant fields and remove readonly
                document.getElementById('complainantFirstName').readOnly = false;
                document.getElementById('complainantMiddle').readOnly = false;
                document.getElementById('complainantLastName').readOnly = false;
                document.getElementById('complainantContact').readOnly = false;
                document.getElementById('complainantStatus').style.display = 'none';
                document.getElementById('complainantType').value = 'non-resident';
                document.getElementById('complainantResidentId').value = '';
                
                // Reset defendant fields and remove readonly  
                document.getElementById('defendantFirstName').readOnly = false;
                document.getElementById('defendantMiddle').readOnly = false;
                document.getElementById('defendantLastName').readOnly = false;
                document.getElementById('defendantContact').readOnly = false;
                document.getElementById('defendantStatus').style.display = 'none';
                document.getElementById('defendantType').value = 'non-resident';
                document.getElementById('defendantResidentId').value = '';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['createModal', 'viewModal'];
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

        function viewComplaint(complaint) {
    // Basic details
    document.getElementById('viewNature').textContent = complaint.nature_of_complaint;
    document.getElementById('viewDescription').textContent = complaint.description;
    document.getElementById('viewStatus').textContent = complaint.status;
    
    // Format date
    const date = new Date(complaint.created_at);
    document.getElementById('viewDate').textContent = date.toLocaleDateString();
    
    // Complainant details
    document.getElementById('viewComplainant').textContent = complaint.complainant;
    document.getElementById('viewComplainantContact').textContent = complaint.complainant_contact || 'Not provided';
    
    // Defendant details
    document.getElementById('viewDefendant').textContent = complaint.defendant;
    document.getElementById('viewDefendantContact').textContent = complaint.defendant_contact || 'Not provided';
    
    // Resolution section
    const resolutionSection = document.getElementById('resolutionSection');
    if (complaint.status === 'resolved' || complaint.status === 'closed') {
        document.getElementById('viewResolution').textContent = complaint.resolution || 'No resolution provided';
        document.getElementById('viewMediationDate').textContent = complaint.mediation_date || 'No date set';
        resolutionSection.style.display = 'block';
    } else {
        resolutionSection.style.display = 'none';
    }
    
    // Show modal
    const modal = document.getElementById('viewModal');
    modal.style.display = 'block';
}
    </script>        

<!-- Create Complaint Modal -->
<div class="modal" id="createModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('createModal')">&times;</button>
        <div class="modal-header">
            <h2 class="modal-title">Create New Complaint</h2>
        </div>
        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-group">
                <label class="form-label">Nature of Complaint</label>
                <input type="text" name="nature_of_complaint" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" required></textarea>
            </div>
            
            <!-- Complainant Details with fixed positioning for search results -->
            <div class="form-group">
                <label class="form-label">Complainant Name</label>
                <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 1rem;">
                    <div>
                        <input type="text" 
                               id="complainantFirstName" 
                               class="form-input search-input" 
                               placeholder="First Name" 
                               required
                               pattern="^[A-Za-z\s]{2,}$"
                               title="First name should only contain letters"
                               autocomplete="off">
                    </div>
                    <div>
                        <input type="text" 
                               id="complainantMiddle" 
                               class="form-input" 
                               placeholder="M.I." 
                               maxlength="2"
                               pattern="^[A-Zaz]\.?$"
                               title="Middle initial should be a single letter with optional period"
                               autocomplete="off">
                    </div>
                    <div>
                        <input type="text" 
                               id="complainantLastName" 
                               class="form-input" 
                               placeholder="Last Name" 
                               required
                               pattern="^[A-Za-z\s]{2,}$"
                               title="Last name should only contain letters"
                               autocomplete="off">
                    </div>
                </div>
                <input type="hidden" id="complainantSearch" class="form-input">
                <div id="complainantSearchResults" class="search-results"></div>
                <div id="complainantStatus" class="resident-status"></div>
                <input type="hidden" id="complainantType" name="complainant_type" value="non-resident">
                <input type="hidden" id="complainantResidentId" name="resident_id" value="">
            </div>
            
            <div class="form-group">
                <label class="form-label">Complainant Contact</label>
                <input type="text" 
                       id="complainantContact" 
                       name="complainant_contact" 
                       class="form-input" 
                       maxlength="11" 
                       pattern="^09\d{9}$"
                       placeholder="09XXXXXXXXX"
                       required
                       title="Please enter a valid phone number starting with 09">
            </div>

            <!-- Add Clear Complainant button -->
            <div class="form-group">
                <button type="button" class="action-btn btn-secondary" onclick="clearResident('complainant')">
                    Clear Complainant
                </button>
            </div>

            <!-- Defendant Details with fixed positioning for search results -->
            <div class="form-group">
                <label class="form-label">Defendant Name</label>
                <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 1rem;">
                    <div>
                        <input type="text" 
                               id="defendantFirstName" 
                               class="form-input search-input" 
                               placeholder="First Name" 
                               required
                               pattern="^[A-Za-z\s]{2,}$"
                               title="First name should only contain letters"
                               autocomplete="off">
                    </div>
                    <div>
                        <input type="text" 
                               id="defendantMiddle" 
                               class="form-input" 
                               placeholder="M.I." 
                               maxlength="2"
                               pattern="^[A-Za-z]\.?$"
                               title="Middle initial should be a single letter with optional period"
                               autocomplete="off">
                    </div>
                    <div>
                        <input type="text" 
                               id="defendantLastName" 
                               class="form-input" 
                               placeholder="Last Name" 
                               required
                               pattern="^[A-Za-z\s]{2,}$"
                               title="Last name should only contain letters"
                               autocomplete="off">
                    </div>
                </div>
                <input type="hidden" id="defendantSearch" class="form-input">
                <div id="defendantSearchResults" class="search-results"></div>
                <div id="defendantStatus" class="resident-status"></div>
                <input type="hidden" id="defendantType" name="defendant_type" value="non-resident">
                <input type="hidden" id="defendantResidentId" name="defendant_resident_id" value="">
            </div>
            
            <div class="form-group">
                <label class="form-label">Defendant Contact</label>
                <input type="text" 
                       id="defendantContact" 
                       name="defendant_contact" 
                       class="form-input" 
                       maxlength="11" 
                       pattern="^09\d{9}$"
                       placeholder="09XXXXXXXXX"
                       required
                       title="Please enter a valid phone number starting with 09">
            </div>

            <!-- Add Clear Defendant button -->
            <div class="form-group">
                <button type="button" class="action-btn btn-secondary" onclick="clearResident('defendant')">
                    Clear Defendant  
                </button>
            </div>

            <!-- Hidden fields for names -->
            <input type="hidden" name="complainant_name" value="">
            <input type="hidden" name="defendant_name" value="">

            <div class="modal-actions">
                <button type="button" class="action-btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" name="create_complaint" class="action-btn btn-success">Create Complaint</button>
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
            <input type="hidden" id="statusSelect" name="status">
            <div id="resolutionFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Resolution Details</label>
                    <textarea name="resolution" class="form-textarea" id="resolutionInput"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Mediation Date</label>
                    <input type="date" name="mediation_date" class="form-input" id="mediationInput">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="action-btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" name="update_status" class="action-btn btn-success">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- View Complaint Modal (single instance) -->
<div class="modal" id="viewModal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
        <div class="modal-header">
            <h2 class="modal-title">Complaint Details</h2>
        </div>
        <div class="complaint-details">
            <div class="detail-section">
                <h3>Complaint Information</h3>
                <p><strong>Nature:</strong> <span id="viewNature"></span></p>
                <p><strong>Description:</strong> <span id="viewDescription"></span></p>
                <p><strong>Status:</strong> <span id="viewStatus"></span></p>
                <p><strong>Date Filed:</strong> <span id="viewDate"></span></p>
            </div>
            <div class="detail-section">
                <h3>Complainant Information</h3>
                <p><strong>Name:</strong> <span id="viewComplainant"></span></p>
                <p><strong>Contact:</strong> <span id="viewComplainantContact"></span></p>
            </div>
            <div class="detail-section">
                <h3>Defendant Information</h3>
                <p><strong>Name:</strong> <span id="viewDefendant"></span></p>
                <p><strong>Contact:</strong> <span id="viewDefendantContact"></span></p>
            </div>
            <div class="detail-section" id="resolutionSection">
                <h3>Resolution Information</h3>
                <p><strong>Resolution:</strong> <span id="viewResolution"></span></p>
                <p><strong>Mediation Date:</strong> <span id="viewMediationDate"></span></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
