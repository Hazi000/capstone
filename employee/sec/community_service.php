<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a super_admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Handle volunteer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_volunteer':
                $volunteer_id = mysqli_real_escape_string($connection, $_POST['volunteer_id']);
                $query = "UPDATE community_volunteers SET status = 'approved', approved_by = '{$_SESSION['user_id']}', approved_at = NOW() WHERE id = '$volunteer_id'";
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success_message'] = "Volunteer approved successfully!";
                } else {
                    $_SESSION['error_message'] = "Error approving volunteer: " . mysqli_error($connection);
                }
                break;
                
            case 'reject_volunteer':
                $volunteer_id = mysqli_real_escape_string($connection, $_POST['volunteer_id']);
                $rejection_reason = mysqli_real_escape_string($connection, $_POST['rejection_reason']);
                $query = "UPDATE community_volunteers SET status = 'rejected', rejection_reason = '$rejection_reason', approved_by = '{$_SESSION['user_id']}', approved_at = NOW() WHERE id = '$volunteer_id'";
                if (mysqli_query($connection, $query)) {
                    $_SESSION['success_message'] = "Volunteer application rejected.";
                } else {
                    $_SESSION['error_message'] = "Error rejecting volunteer: " . mysqli_error($connection);
                }
                break;
                
            case 'mark_attended':
                $volunteer_id = mysqli_real_escape_string($connection, $_POST['volunteer_id']);
                $hours_served = mysqli_real_escape_string($connection, $_POST['hours_served']);
                
                // Start transaction
                mysqli_begin_transaction($connection);
                
                try {
                    // Update volunteer record
                    $query = "UPDATE community_volunteers SET attendance_status = 'attended', hours_served = '$hours_served', attendance_marked_at = NOW() WHERE id = '$volunteer_id'";
                    mysqli_query($connection, $query);
                    
                    // Get resident_id for updating statistics
                    $get_resident = "SELECT resident_id FROM community_volunteers WHERE id = '$volunteer_id'";
                    $result = mysqli_query($connection, $get_resident);
                    $resident_id = mysqli_fetch_assoc($result)['resident_id'];
                    
                    // Update resident statistics
                    $update_resident = "UPDATE residents 
                                      SET total_volunteer_hours = total_volunteer_hours + $hours_served,
                                          total_volunteer_events = total_volunteer_events + 1,
                                          last_volunteer_date = CURDATE(),
                                          volunteer_status = CASE 
                                              WHEN total_volunteer_hours + $hours_served >= 100 THEN 'outstanding'
                                              WHEN total_volunteer_hours + $hours_served >= 20 THEN 'active'
                                              ELSE 'inactive'
                                          END
                                      WHERE id = '$resident_id'";
                    mysqli_query($connection, $update_resident);
                    
                    mysqli_commit($connection);
                    $_SESSION['success_message'] = "Attendance marked successfully!";
                } catch (Exception $e) {
                    mysqli_rollback($connection);
                    $_SESSION['error_message'] = "Error marking attendance: " . $e->getMessage();
                }
                break;
                
            case 'generate_certificate':
                $resident_id = mysqli_real_escape_string($connection, $_POST['resident_id']);
                // Certificate generation will be handled by JavaScript
                break;
        }
        
        if ($_POST['action'] !== 'generate_certificate') {
            header("Location: community_service.php");
            exit();
        }
    }
}

// Get view mode
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'events';
$selected_resident = isset($_GET['resident_id']) ? mysqli_real_escape_string($connection, $_GET['resident_id']) : '';

// Get dashboard statistics for sidebar
$stats = [];
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];

// Get community service statistics
$volunteer_stats_query = "SELECT 
    COUNT(DISTINCT cv.resident_id) as total_volunteers,
    COUNT(CASE WHEN cv.status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN cv.status = 'approved' THEN 1 END) as approved_volunteers,
    SUM(CASE WHEN cv.attendance_status = 'attended' THEN cv.hours_served ELSE 0 END) as total_hours_served
FROM community_volunteers cv";
$volunteer_stats = mysqli_fetch_assoc(mysqli_query($connection, $volunteer_stats_query));

$active_events_query = "SELECT COUNT(*) as active_events FROM announcements 
                       WHERE status = 'active' 
                       AND event_date >= CURDATE() 
                       AND needs_volunteers = 1
                       AND announcement_type = 'event'
                       AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
$active_events_result = mysqli_query($connection, $active_events_query);
$volunteer_stats['active_events'] = mysqli_fetch_assoc($active_events_result)['active_events'];

// Get filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$filter_event = isset($_GET['event']) ? mysqli_real_escape_string($connection, $_GET['event']) : '';
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : '';
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($connection, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($connection, $_GET['date_to']) : '';

if ($view_mode === 'history') {
    // Fetch volunteer history
    if ($selected_resident) {
        // Get resident details
        $resident_query = "SELECT * FROM residents WHERE id = '$selected_resident'";
        $resident_result = mysqli_query($connection, $resident_query);
        $resident_details = mysqli_fetch_assoc($resident_result);
        
        // Get volunteer history for specific resident
        $history_query = "SELECT cv.*, a.title as event_title, a.event_date, a.location
                         FROM community_volunteers cv
                         LEFT JOIN announcements a ON cv.announcement_id = a.id
                         WHERE cv.resident_id = '$selected_resident'
                         AND cv.status = 'approved'
                         ORDER BY a.event_date DESC";
        $history_result = mysqli_query($connection, $history_query);
    } else {
        // Get top volunteers
        $top_volunteers_query = "SELECT r.*, 
                                COUNT(CASE WHEN cv.attendance_status = 'attended' THEN 1 END) as events_attended
                                FROM residents r
                                LEFT JOIN community_volunteers cv ON r.id = cv.resident_id
                                WHERE r.total_volunteer_hours > 0
                                GROUP BY r.id
                                ORDER BY r.total_volunteer_hours DESC
                                LIMIT 20";
        $top_volunteers_result = mysqli_query($connection, $top_volunteers_query);
    }
} else {
   $events_query = "SELECT a.*, 
                (SELECT COUNT(*) FROM community_volunteers WHERE announcement_id = a.id AND status = 'approved') as volunteer_count
                FROM announcements a 
                WHERE a.status = 'active' 
                AND a.event_date IS NOT NULL
                AND a.needs_volunteers = 1
                AND a.announcement_type = 'event'";

if ($filter_date_from) {
    $events_query .= " AND a.event_date >= '$filter_date_from'";
}
if ($filter_date_to) {
    $events_query .= " AND a.event_date <= '$filter_date_to'";
}

$events_query .= " ORDER BY a.event_date ASC";
$events_result = mysqli_query($connection, $events_query);

    // Fetch volunteers based on filters
    $volunteers_query = "SELECT cv.*, r.full_name, r.contact_number, r.email, a.title as event_title, a.event_date,
                        u.full_name as approved_by_name
                        FROM community_volunteers cv
                        LEFT JOIN residents r ON cv.resident_id = r.id
                        LEFT JOIN announcements a ON cv.announcement_id = a.id
                        LEFT JOIN users u ON cv.approved_by = u.id
                        WHERE 1=1";

    if ($search) {
        $volunteers_query .= " AND (r.full_name LIKE '%$search%' OR r.contact_number LIKE '%$search%' OR r.email LIKE '%$search%')";
    }

    if ($filter_event) {
        $volunteers_query .= " AND cv.announcement_id = '$filter_event'";
    }

    if ($filter_status) {
        $volunteers_query .= " AND cv.status = '$filter_status'";
    }

    $volunteers_query .= " ORDER BY cv.created_at DESC";
    $volunteers_result = mysqli_query($connection, $volunteers_query);
}

// Get all events for filter dropdown
$all_events_query = "SELECT id, title, event_date FROM announcements 
                    WHERE status = 'active' AND event_date IS NOT NULL 
                    ORDER BY event_date DESC";
$all_events_result = mysqli_query($connection, $all_events_query);

// Get all residents for dropdown
$all_residents_query = "SELECT id, full_name, total_volunteer_hours 
                       FROM residents 
                       WHERE total_volunteer_hours > 0 
                       ORDER BY full_name ASC";
$all_residents_result = mysqli_query($connection, $all_residents_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Service - Barangay Management System</title>
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

        /* View Tabs */
        .view-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }

        .view-tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-tab:hover {
            color: #333;
        }

        .view-tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
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

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: 600;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .event-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .event-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .event-body {
            padding: 1.5rem;
        }

        .event-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .event-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .event-info-item i {
            width: 20px;
            text-align: center;
            color: #3498db;
        }

        .volunteer-progress {
            margin-top: 1rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #666;
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            background: #27ae60;
            height: 100%;
            transition: width 0.3s ease;
        }

        /* Resident Profile Card */
        .resident-profile {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .profile-info h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .profile-stat {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .profile-stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }

        .profile-stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .volunteer-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .volunteer-inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .volunteer-active {
            background: #d4edda;
            color: #155724;
        }

        .volunteer-outstanding {
            background: #ffd700;
            color: #856404;
        }

        /* Volunteers Table */
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

        .volunteer-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .volunteer-name {
            font-weight: 600;
            color: #333;
        }

        .volunteer-contact {
            font-size: 0.85rem;
            color: #666;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .attendance-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .attendance-attended {
            background: #d1ecf1;
            color: #0c5460;
        }

        .attendance-absent {
            background: #e2e3e5;
            color: #383d41;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            color: #333;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* Certificate Preview */
        .certificate-preview {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 800px;
            height: 600px;
            background: white;
            border: 20px solid #f4e4c1;
            box-shadow: 0 0 50px rgba(0,0,0,0.3);
            padding: 60px;
            text-align: center;
            z-index: 3000;
        }

        .certificate-header {
            margin-bottom: 40px;
        }

        .certificate-logo {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }

        .certificate-title {
            font-size: 3rem;
            color: #2c3e50;
            font-family: 'Georgia', serif;
            margin-bottom: 10px;
        }

        .certificate-subtitle {
            font-size: 1.2rem;
            color: #666;
            font-style: italic;
        }

        .certificate-body {
            margin: 40px 0;
        }

        .certificate-recipient {
            font-size: 2.5rem;
            color: #3498db;
            font-weight: bold;
            margin: 20px 0;
            font-family: 'Georgia', serif;
        }

        .certificate-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333;
            margin: 20px 0;
        }

        .certificate-stats {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
        }

        .certificate-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }

        .certificate-signature {
            text-align: center;
            flex: 1;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }

        .certificate-date {
            position: absolute;
            bottom: 30px;
            right: 60px;
            font-size: 0.9rem;
            color: #666;
        }

        .certificate-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        /* Empty State */
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .events-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 600px;
            }

            .certificate-preview {
                width: 90%;
                height: auto;
                padding: 30px;
                border-width: 10px;
            }

            .certificate-title {
                font-size: 2rem;
            }

            .certificate-recipient {
                font-size: 1.8rem;
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

        @media print {
            .certificate-actions {
                display: none !important;
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
                <a href="community_service.php" class="nav-item active">
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
                <a href="certificates.php" class="nav-item">
                    <i class="fas fa-certificate"></i>
                    Certificates
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
            <h1 class="page-title">Community Service</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                    ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                    ?>
                    <button class="alert-close" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- View Tabs -->
            <div class="view-tabs">
                <a href="?view=events" class="view-tab <?php echo $view_mode === 'events' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    Events & Applications
                </a>
                <a href="?view=history" class="view-tab <?php echo $view_mode === 'history' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    Volunteer History
                </a>
            </div>

            <?php if ($view_mode === 'events'): ?>
                <!-- Statistics Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #3498db;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $volunteer_stats['active_events']; ?></h3>
                            <p>Active Events</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #27ae60;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $volunteer_stats['total_volunteers'] ?? 0; ?></h3>
                            <p>Total Volunteers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #f39c12;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $volunteer_stats['pending_applications'] ?? 0; ?></h3>
                            <p>Pending Applications</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: #e74c3c;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($volunteer_stats['total_hours_served'] ?? 0); ?></h3>
                            <p>Total Hours Served</p>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events Section -->
                <div class="section-header">
                    <h2 class="section-title">Upcoming Community Events</h2>
                </div>

                <div class="events-grid">
                    <?php 
                    mysqli_data_seek($events_result, 0);
                    if (mysqli_num_rows($events_result) > 0): 
                        while ($event = mysqli_fetch_assoc($events_result)): 
                            $event_date = new DateTime($event['event_date']);
                            $today = new DateTime();
                            $is_past = $event_date < $today;
                    ?>
                        <div class="event-card">
                            <div class="event-header">
                                <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $event_date->format('F j, Y'); ?>
                                    <?php if ($is_past): ?>
                                        <span style="background: rgba(255,255,255,0.3); padding: 2px 8px; border-radius: 12px; font-size: 0.75rem;">Past Event</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="event-body">
                                <div class="event-info">
                                    <?php if ($event['event_time']): ?>
                                        <div class="event-info-item">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($event['location']): ?>
                                        <div class="event-info-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="event-info-item">
                                        <i class="fas fa-users"></i>
                                        <?php echo $event['volunteer_count']; ?> volunteers registered
                                    </div>
                                </div>
                                
                                <div class="volunteer-progress">
                                    <div class="progress-label">
                                        <span>Volunteer Progress</span>
                                        <span><?php echo $event['volunteer_count']; ?> registered</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo min(100, ($event['volunteer_count'] / 20) * 100); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile; 
                    else: 
                    ?>
                        <div class="empty-state" style="grid-column: 1/-1;">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No upcoming events</h3>
                            <p>Check back later for community service opportunities.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Volunteers Management Section -->
                <div class="section-header">
                    <h2 class="section-title">Volunteer Applications</h2>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" action="community_service.php">
                        <input type="hidden" name="view" value="events">
                        <div class="filter-row">
                            <div class="filter-group" style="flex: 2;">
                                <label for="search">Search Volunteers</label>
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="Search by name, contact, or email..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="event">Event</label>
                                <select id="event" name="event" class="form-control">
                                    <option value="">All Events</option>
                                    <?php 
                                    mysqli_data_seek($all_events_result, 0);
                                    while ($event = mysqli_fetch_assoc($all_events_result)): 
                                    ?>
                                        <option value="<?php echo $event['id']; ?>" <?php echo $filter_event == $event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?> - <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                    Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Volunteers Table -->
                <div class="table-container">
                    <?php if (mysqli_num_rows($volunteers_result) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Event</th>
                                    <th>Application Date</th>
                                    <th>Status</th>
                                    <th>Attendance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($volunteer = mysqli_fetch_assoc($volunteers_result)): ?>
                                    <tr>
                                        <td>
                                            <div class="volunteer-info">
                                                <span class="volunteer-name"><?php echo htmlspecialchars($volunteer['full_name']); ?></span>
                                                <span class="volunteer-contact">
                                                    <?php echo htmlspecialchars($volunteer['contact_number']); ?> | 
                                                    <?php echo htmlspecialchars($volunteer['email']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($volunteer['event_title']); ?></strong><br>
                                            <small><?php echo date('M j, Y', strtotime($volunteer['event_date'])); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($volunteer['created_at'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $volunteer['status']; ?>">
                                                <?php echo ucfirst($volunteer['status']); ?>
                                            </span>
                                            <?php if ($volunteer['status'] == 'approved' && $volunteer['approved_by_name']): ?>
                                                <br><small>by <?php echo htmlspecialchars($volunteer['approved_by_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($volunteer['status'] == 'approved'): ?>
                                                <?php if ($volunteer['attendance_status'] == 'attended'): ?>
                                                    <span class="attendance-badge attendance-attended">
                                                        Attended (<?php echo $volunteer['hours_served']; ?> hrs)
                                                    </span>
                                                <?php else: ?>
                                                    <?php 
                                                    $event_date = new DateTime($volunteer['event_date']);
                                                    $today = new DateTime();
                                                    if ($event_date < $today): 
                                                    ?>
                                                        <button class="btn btn-sm btn-warning" onclick="openAttendanceModal(<?php echo $volunteer['id']; ?>, '<?php echo htmlspecialchars($volunteer['full_name']); ?>')">
                                                            <i class="fas fa-check"></i> Mark Attendance
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="attendance-badge">Upcoming</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <?php if ($volunteer['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveVolunteer(<?php echo $volunteer['id']; ?>)">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $volunteer['id']; ?>, '<?php echo htmlspecialchars($volunteer['full_name']); ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($volunteer['status'] == 'rejected' && $volunteer['rejection_reason']): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="showRejectionReason('<?php echo htmlspecialchars($volunteer['rejection_reason']); ?>')">
                                                        <i class="fas fa-info-circle"></i> View Reason
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <h3>No volunteer applications found</h3>
                            <p>No volunteers have registered for community service events yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: // History View ?>
                
                <?php if ($selected_resident && isset($resident_details)): ?>
                    <!-- Individual Resident Profile -->
                    <div class="resident-profile">
                        <div class="profile-header">
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($resident_details['full_name']); ?></h2>
                                <p style="color: #666; margin-bottom: 1rem;">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($resident_details['contact_number']); ?> |
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($resident_details['email']); ?>
                                </p>
                                <span class="volunteer-badge volunteer-<?php echo $resident_details['volunteer_status']; ?>">
                                    <?php echo ucfirst($resident_details['volunteer_status']); ?> Volunteer
                                </span>
                            </div>
                            <div>
                                <button class="btn btn-info" onclick="generateCertificate(<?php echo $resident_details['id']; ?>, '<?php echo htmlspecialchars($resident_details['full_name']); ?>', <?php echo $resident_details['total_volunteer_hours']; ?>, <?php echo $resident_details['total_volunteer_events']; ?>)">
                                    <i class="fas fa-certificate"></i> Generate Certificate
                                </button>
                            </div>
                        </div>

                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo number_format($resident_details['total_volunteer_hours'], 1); ?></div>
                                <div class="profile-stat-label">Total Hours</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?php echo $resident_details['total_volunteer_events']; ?></div>
                                <div class="profile-stat-label">Events Attended</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">
                                    <?php 
                                    if ($resident_details['total_volunteer_events'] > 0) {
                                        echo number_format($resident_details['total_volunteer_hours'] / $resident_details['total_volunteer_events'], 1);
                                    } else {
                                        echo "0";
                                    }
                                    ?>
                                </div>
                                <div class="profile-stat-label">Avg Hours/Event</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">
                                    <?php echo $resident_details['last_volunteer_date'] ? date('M j, Y', strtotime($resident_details['last_volunteer_date'])) : 'N/A'; ?>
                                </div>
                                <div class="profile-stat-label">Last Activity</div>
                            </div>
                        </div>
                    </div>

                    <!-- Volunteer History Table -->
                    <div class="section-header">
                        <h2 class="section-title">Volunteer History</h2>
                        <a href="?view=history" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to All Volunteers
                        </a>
                    </div>

                    <div class="table-container">
                        <?php if (mysqli_num_rows($history_result) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Hours Served</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($history['event_title']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($history['event_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($history['location'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($history['attendance_status'] == 'attended'): ?>
                                                    <span class="attendance-badge attendance-attended">Attended</span>
                                                <?php else: ?>
                                                    <span class="attendance-badge">Did Not Attend</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $history['hours_served'] ?? '-'; ?> hrs</td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No volunteer history</h3>
                                <p>This resident has not participated in any events yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <!-- Top Volunteers List -->
                    <div class="section-header">
                        <h2 class="section-title">Top Community Volunteers</h2>
                    </div>

                    <!-- Filter Section for History -->
                    <div class="filter-section">
                        <form method="GET" action="community_service.php">
                            <input type="hidden" name="view" value="history">
                            <div class="filter-row">
                                <div class="filter-group" style="flex: 2;">
                                    <label for="resident_id">Select Resident</label>
                                    <select id="resident_id" name="resident_id" class="form-control" onchange="this.form.submit()">
                                        <option value="">View Top Volunteers</option>
                                        <?php 
                                        mysqli_data_seek($all_residents_result, 0);
                                        while ($resident = mysqli_fetch_assoc($all_residents_result)): 
                                        ?>
                                            <option value="<?php echo $resident['id']; ?>">
                                                <?php echo htmlspecialchars($resident['full_name']); ?> (<?php echo $resident['total_volunteer_hours']; ?> hrs)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-container">
                        <?php if (mysqli_num_rows($top_volunteers_result) > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Volunteer</th>
                                        <th>Total Hours</th>
                                        <th>Events Attended</th>
                                        <th>Status</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    while ($volunteer = mysqli_fetch_assoc($top_volunteers_result)): 
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank <= 3): ?>
                                                    <span style="font-size: 1.5rem;">
                                                        <?php 
                                                        if ($rank == 1) echo '🥇';
                                                        elseif ($rank == 2) echo '🥈';
                                                        elseif ($rank == 3) echo '🥉';
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    #<?php echo $rank; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="volunteer-info">
                                                    <span class="volunteer-name"><?php echo htmlspecialchars($volunteer['full_name']); ?></span>
                                                    <span class="volunteer-contact">
                                                        <?php echo htmlspecialchars($volunteer['contact_number']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($volunteer['total_volunteer_hours'], 1); ?></strong> hours
                                            </td>
                                            <td><?php echo $volunteer['events_attended']; ?> events</td>
                                            <td>
                                                <span class="volunteer-badge volunteer-<?php echo $volunteer['volunteer_status']; ?>">
                                                    <?php echo ucfirst($volunteer['volunteer_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $volunteer['last_volunteer_date'] ? date('M j, Y', strtotime($volunteer['last_volunteer_date'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="?view=history&resident_id=<?php echo $volunteer['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View History
                                                    </a>
                                                    <button class="btn btn-sm btn-info" onclick="generateCertificate(<?php echo $volunteer['id']; ?>, '<?php echo htmlspecialchars($volunteer['full_name']); ?>', <?php echo $volunteer['total_volunteer_hours']; ?>, <?php echo $volunteer['total_volunteer_events']; ?>)">
                                                        <i class="fas fa-certificate"></i> Certificate
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                        $rank++;
                                        endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-trophy"></i>
                                <h3>No volunteer records yet</h3>
                                <p>Volunteer records will appear here once residents start participating in community events.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Reject Volunteer Application</h2>
                <button class="modal-close" onclick="closeRejectModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject_volunteer">
                    <input type="hidden" name="volunteer_id" id="rejectVolunteerId">
                    
                    <p style="margin-bottom: 1rem;">
                        Rejecting application for: <strong id="rejectVolunteerName"></strong>
                    </p>
                    
                    <div class="form-group">
                        <label for="rejection_reason">Reason for Rejection <span style="color: red;">*</span></label>
                        <textarea id="rejection_reason" 
                                  name="rejection_reason" 
                                  class="form-control" 
                                  placeholder="Please provide a reason for rejection..."
                                  required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div class="modal" id="attendanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Mark Attendance</h2>
                <button class="modal-close" onclick="closeAttendanceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="action" value="mark_attended">
                    <input type="hidden" name="volunteer_id" id="attendanceVolunteerId">
                    
                    <p style="margin-bottom: 1rem;">
                        Marking attendance for: <strong id="attendanceVolunteerName"></strong>
                    </p>
                    
                    <div class="form-group">
                        <label for="hours_served">Hours Served <span style="color: red;">*</span></label>
                        <input type="number" 
                               id="hours_served" 
                               name="hours_served" 
                               class="form-control" 
                               placeholder="Enter hours served"
                               min="0.5"
                               max="24"
                               step="0.5"
                               required>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAttendanceModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Mark as Attended
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Certificate Preview -->
    <div class="certificate-preview" id="certificatePreview">
        <div class="certificate-actions">
            <button class="btn btn-primary" onclick="printCertificate()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn btn-secondary" onclick="closeCertificate()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <div class="certificate-header">
            <div class="certificate-logo">
                <i class="fas fa-building"></i>
            </div>
            <h1 class="certificate-title">Certificate of Appreciation</h1>
            <p class="certificate-subtitle">For Outstanding Community Service</p>
        </div>
        
        <div class="certificate-body">
            <p class="certificate-text">This is to certify that</p>
            <h2 class="certificate-recipient" id="certRecipientName">John Doe</h2>
            <p class="certificate-text">
                has demonstrated exceptional dedication and commitment to community service
                through voluntary participation in barangay activities and events.
            </p>
            
            <div class="certificate-stats">
                <p><strong>Total Volunteer Hours:</strong> <span id="certHours">0</span> hours</p>
                <p><strong>Events Participated:</strong> <span id="certEvents">0</span> events</p>
            </div>
            
            <p class="certificate-text">
                We express our sincere gratitude for your selfless service and valuable contribution
                to the betterment of our community.
            </p>
        </div>
        
        <div class="certificate-footer">
            <div class="certificate-signature">
                <div class="signature-line">
                    <strong>Barangay Captain</strong>
                </div>
            </div>
            <div class="certificate-signature">
                <div class="signature-line">
                    <strong>Secretary</strong>
                </div>
            </div>
        </div>
        
        <div class="certificate-date">
            Issued on: <?php echo date('F j, Y'); ?>
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
            if (confirm('Are you sure you want to logout?')) {
                document.getElementById('logoutForm').submit();
            }
        }

        // Approve volunteer
        function approveVolunteer(volunteerId) {
            if (confirm('Are you sure you want to approve this volunteer application?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve_volunteer">
                    <input type="hidden" name="volunteer_id" value="${volunteerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reject modal functions
        function openRejectModal(volunteerId, volunteerName) {
            document.getElementById('rejectVolunteerId').value = volunteerId;
            document.getElementById('rejectVolunteerName').textContent = volunteerName;
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejectForm').reset();
        }

        // Attendance modal functions
        function openAttendanceModal(volunteerId, volunteerName) {
            document.getElementById('attendanceVolunteerId').value = volunteerId;
            document.getElementById('attendanceVolunteerName').textContent = volunteerName;
            document.getElementById('attendanceModal').classList.add('active');
        }

        function closeAttendanceModal() {
            document.getElementById('attendanceModal').classList.remove('active');
            document.getElementById('attendanceForm').reset();
        }

        // Show rejection reason
        function showRejectionReason(reason) {
            alert('Rejection Reason:\n\n' + reason);
        }

        // Certificate functions
        function generateCertificate(residentId, residentName, totalHours, totalEvents) {
            document.getElementById('certRecipientName').textContent = residentName;
            document.getElementById('certHours').textContent = totalHours;
            document.getElementById('certEvents').textContent = totalEvents;
            document.getElementById('certificatePreview').style.display = 'block';
        }

        function closeCertificate() {
            document.getElementById('certificatePreview').style.display = 'none';
        }

        function printCertificate() {
            window.print();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const rejectModal = document.getElementById('rejectModal');
            const attendanceModal = document.getElementById('attendanceModal');
            const certificatePreview = document.getElementById('certificatePreview');
            
            if (event.target === rejectModal) {
                closeRejectModal();
            }
            if (event.target === attendanceModal) {
                closeAttendanceModal();
            }
            if (event.target === certificatePreview) {
                closeCertificate();
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