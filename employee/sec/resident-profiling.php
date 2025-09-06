<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/residents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_resident') {
            $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
            $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
            $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
            $age = intval($_POST['age']);
            $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
            $status = mysqli_real_escape_string($connection, $_POST['status']);
            
            // Create full name
            $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
            
            // Handle photo upload and face data
            $photo_path = null;
            $face_descriptor = null;
            
            if (!empty($_POST['photo_data'])) {
                $photo_data = $_POST['photo_data'];
                $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
                $photo_data = str_replace('data:image/png;base64,', '', $photo_data);
                $photo_data = str_replace(' ', '+', $photo_data);
                $photo_binary = base64_decode($photo_data);
                
                // Generate unique filename
                $photo_filename = uniqid('resident_') . '.jpg';
                $photo_path = $upload_dir . $photo_filename;
                
                // Save the image
                file_put_contents($photo_path, $photo_binary);
                $photo_path = 'uploads/residents/' . $photo_filename; // Store relative path in database
            }
            
            // Get face descriptor if provided
            if (!empty($_POST['face_descriptor'])) {
                $face_descriptor = mysqli_real_escape_string($connection, $_POST['face_descriptor']);
            }
            
            $insert_query = "INSERT INTO residents (first_name, middle_initial, last_name, full_name, age, contact_number, status, photo_path, face_descriptor) 
                           VALUES ('$first_name', '$middle_initial', '$last_name', '$full_name', $age, '$contact_number', '$status', " . 
                           ($photo_path ? "'$photo_path'" : "NULL") . ", " .
                           ($face_descriptor ? "'$face_descriptor'" : "NULL") . ")";
            
            if (mysqli_query($connection, $insert_query)) {
                $success_message = "Resident added successfully with face data!";
            } else {
                $error_message = "Error adding resident: " . mysqli_error($connection);
            }
        } elseif ($_POST['action'] === 'update_resident') {
            $id = intval($_POST['resident_id']);
            $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
            $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
            $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
            $age = intval($_POST['age']);
            $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
            $status = mysqli_real_escape_string($connection, $_POST['status']);
            
            // Create full name
            $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
            
            // Handle photo update
            $photo_update = "";
            $face_update = "";
            
            if (!empty($_POST['photo_data'])) {
                // Delete old photo if exists
                $old_photo_query = "SELECT photo_path FROM residents WHERE id = $id";
                $old_photo_result = mysqli_query($connection, $old_photo_query);
                if ($old_photo_row = mysqli_fetch_assoc($old_photo_result)) {
                    if ($old_photo_row['photo_path'] && file_exists('../../' . $old_photo_row['photo_path'])) {
                        unlink('../../' . $old_photo_row['photo_path']);
                    }
                }
                
                $photo_data = $_POST['photo_data'];
                $photo_data = str_replace('data:image/jpeg;base64,', '', $photo_data);
                $photo_data = str_replace('data:image/png;base64,', '', $photo_data);
                $photo_data = str_replace(' ', '+', $photo_data);
                $photo_binary = base64_decode($photo_data);
                
                // Generate unique filename
                $photo_filename = uniqid('resident_') . '.jpg';
                $photo_path = $upload_dir . $photo_filename;
                
                // Save the image
                file_put_contents($photo_path, $photo_binary);
                $photo_path = 'uploads/residents/' . $photo_filename;
                $photo_update = ", photo_path = '$photo_path'";
            }
            
            // Update face descriptor if provided
            if (!empty($_POST['face_descriptor'])) {
                $face_descriptor = mysqli_real_escape_string($connection, $_POST['face_descriptor']);
                $face_update = ", face_descriptor = '$face_descriptor'";
            }
            
            $update_query = "UPDATE residents SET 
                           first_name = '$first_name', 
                           middle_initial = '$middle_initial', 
                           last_name = '$last_name', 
                           full_name = '$full_name',
                           age = $age, 
                           contact_number = '$contact_number', 
                           status = '$status'
                           $photo_update
                           $face_update,
                           updated_at = CURRENT_TIMESTAMP
                           WHERE id = $id";
            
            if (mysqli_query($connection, $update_query)) {
                $success_message = "Resident updated successfully!";
            } else {
                $error_message = "Error updating resident: " . mysqli_error($connection);
            }
        } elseif ($_POST['action'] === 'delete_resident') {
            $id = intval($_POST['resident_id']);
            
            // Delete photo if exists
            $photo_query = "SELECT photo_path FROM residents WHERE id = $id";
            $photo_result = mysqli_query($connection, $photo_query);
            if ($photo_row = mysqli_fetch_assoc($photo_result)) {
                if ($photo_row['photo_path'] && file_exists('../../' . $photo_row['photo_path'])) {
                    unlink('../../' . $photo_row['photo_path']);
                }
            }
            
            $delete_query = "DELETE FROM residents WHERE id = $id";
            
            if (mysqli_query($connection, $delete_query)) {
                $success_message = "Resident deleted successfully!";
            } else {
                $error_message = "Error deleting resident: " . mysqli_error($connection);
            }
        } elseif ($_POST['action'] === 'search_face') {
            // Handle face search
            $response = array('found' => false);
            
            if (!empty($_POST['search_descriptor'])) {
                $search_descriptor = $_POST['search_descriptor'];
                
                // Get all residents with face descriptors
                $face_query = "SELECT id, full_name, photo_path, face_descriptor FROM residents WHERE face_descriptor IS NOT NULL";
                $face_result = mysqli_query($connection, $face_query);
                
                if ($face_result) {
                    $response['residents'] = array();
                    while ($row = mysqli_fetch_assoc($face_result)) {
                        $response['residents'][] = array(
                            'id' => $row['id'],
                            'full_name' => $row['full_name'],
                            'photo_path' => $row['photo_path'],
                            'face_descriptor' => $row['face_descriptor']
                        );
                    }
                    $response['found'] = true;
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } 
        // Add new account management handling
        if ($_POST['action'] === 'get_account') {
            $rid = intval($_POST['resident_id'] ?? 0);
            $resp = ['found' => false, 'user' => null, 'error' => null];

            // Get resident full name for fallback
            $res_q = mysqli_query($connection, "SELECT full_name FROM residents WHERE id = $rid LIMIT 1");
            $resident_full = '';
            if ($res_q && mysqli_num_rows($res_q) > 0) {
                $resident_full = mysqli_fetch_assoc($res_q)['full_name'];
            }

            // Try to find user by resident_id (preferred)
            $user_q = mysqli_query($connection, "SELECT id, username, resident_id FROM users WHERE resident_id = $rid LIMIT 1");
            if ($user_q && mysqli_num_rows($user_q) > 0) {
                $row = mysqli_fetch_assoc($user_q);
                $resp['found'] = true;
                $resp['user'] = $row;
            } else {
                // If query failed or no result, try fallback by full_name (if available)
                if ($resident_full) {
                    $safe_name = mysqli_real_escape_string($connection, $resident_full);
                    $fb_q = mysqli_query($connection, "SELECT id, username, resident_id FROM users WHERE full_name = '$safe_name' LIMIT 1");
                    if ($fb_q && mysqli_num_rows($fb_q) > 0) {
                        $row = mysqli_fetch_assoc($fb_q);
                        $resp['found'] = true;
                        $resp['user'] = $row;
                    }
                }
            }

            header('Content-Type: application/json');
            echo json_encode($resp);
            exit();
        }

        if ($_POST['action'] === 'manage_account') {
            $rid = intval($_POST['resident_id'] ?? 0);
            $type = $_POST['type'] ?? 'create'; // 'create' or 'change_password'
            $username = mysqli_real_escape_string($connection, $_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($type === 'create') {
                if (empty($username) || empty($password)) {
                    $_SESSION['error_message'] = "Username and password are required to create an account.";
                } else {
                    $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                    // Get resident full_name for users.full_name field
                    $res_q = mysqli_query($connection, "SELECT full_name FROM residents WHERE id = $rid LIMIT 1");
                    $full_name = '';
                    if ($res_q && mysqli_num_rows($res_q) > 0) {
                        $full_name = mysqli_fetch_assoc($res_q)['full_name'];
                        $full_name = mysqli_real_escape_string($connection, $full_name);
                    }

                    // Include resident_id when creating user so the user is directly linked,
                    // then update the residents table to save user_id and username (if column exists).
                    $insert = "INSERT INTO users (username, password, full_name, role, created_at, resident_id) 
                               VALUES ('$username', '$pass_hash', '$full_name', 'resident', NOW(), $rid)";
                    if (mysqli_query($connection, $insert)) {
                        $new_user_id = mysqli_insert_id($connection);
                        // Link to resident: update user_id and try to save username on resident if column exists
                        @mysqli_query($connection, "UPDATE residents SET user_id = $new_user_id, username = '$username' WHERE id = $rid");
                        // Also ensure users.resident_id is set (already set in INSERT) — fallback if DB doesn't support the column
                        @mysqli_query($connection, "UPDATE users SET resident_id = $rid WHERE id = $new_user_id");
                        $_SESSION['success_message'] = "Account created successfully. Username saved to resident record.";
                    } else {
                        $_SESSION['error_message'] = "Error creating account: " . mysqli_error($connection);
                    }
                }
            } elseif ($type === 'change_password') {
                if (empty($password)) {
                    $_SESSION['error_message'] = "Password cannot be empty.";
                } else {
                    // Find user by resident_id, fallback to username
                    $user_id = 0;
                    $u_q = mysqli_query($connection, "SELECT id FROM users WHERE resident_id = $rid LIMIT 1");
                    if ($u_q && mysqli_num_rows($u_q) > 0) {
                        $user_id = mysqli_fetch_assoc($u_q)['id'];
                    } else if (!empty($username)) {
                        $safe_user = mysqli_real_escape_string($connection, $username);
                        $u_q2 = mysqli_query($connection, "SELECT id FROM users WHERE username = '$safe_user' LIMIT 1");
                        if ($u_q2 && mysqli_num_rows($u_q2) > 0) {
                            $user_id = mysqli_fetch_assoc($u_q2)['id'];
                        }
                    }

                    if ($user_id) {
                        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                        if (mysqli_query($connection, "UPDATE users SET password = '$pass_hash', updated_at = NOW() WHERE id = $user_id")) {
                            $_SESSION['success_message'] = "Password updated successfully.";
                        } else {
                            $_SESSION['error_message'] = "Error updating password: " . mysqli_error($connection);
                        }
                    } else {
                        $_SESSION['error_message'] = "Associated user account not found for this resident.";
                    }
                }
            }

            header("Location: resident-profiling.php");
            exit();
        }
    }
}

// Get all residents with search and pagination
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE '%$search%' OR contact_number LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM residents $where_clause";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get residents with pagination
$residents_query = "SELECT * FROM residents $where_clause ORDER BY created_at DESC LIMIT $records_per_page OFFSET $offset";
$residents_result = mysqli_query($connection, $residents_query);

// Get dashboard statistics for sidebar badges
$complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
$result = mysqli_query($connection, $complaint_query);
$pending_complaints = mysqli_fetch_assoc($result)['pending'];

$appointment_query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending'";
$result = mysqli_query($connection, $appointment_query);
$pending_appointments = mysqli_fetch_assoc($result)['pending'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Profiling with Advanced Face Recognition - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Face API -->
    <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
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
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Photo Capture Styles */
        .photo-capture-container {
            grid-column: 1 / -1;
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .camera-preview {
            width: 320px;
            height: 240px;
            margin: 0 auto 1rem;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        #videoElement, #editVideoElement, #searchVideoElement {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #photoCanvas, #editPhotoCanvas {
            display: none;
        }

        .face-detection-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .captured-photo {
            width: 200px;
            height: 200px;
            margin: 0 auto 1rem;
            border-radius: 8px;
            overflow: hidden;
            border: 3px solid #3498db;
        }

        .captured-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .face-status {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .face-detected {
            background: #d4edda;
            color: #155724;
        }

        .no-face {
            background: #f8d7da;
            color: #721c24;
        }

        .smile-detected {
            background: #fff3cd;
            color: #856404;
        }

        /* Face Detection Indicator */
        .face-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .face-indicator.detected {
            background: rgba(76, 175, 80, 0.9);
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .face-indicator.not-detected {
            background: rgba(244, 67, 54, 0.9);
            color: white;
            box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
        }

        .face-indicator.checking {
            background: rgba(255, 152, 0, 0.9);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
        }

        .face-indicator.smile-ready {
            background: rgba(156, 39, 176, 0.9);
            color: white;
            box-shadow: 0 2px 8px rgba(156, 39, 176, 0.3);
        }

        .face-indicator i {
            font-size: 1rem;
        }

        /* Countdown Styles */
        .countdown-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 2rem;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.1); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        /* Face Detection Box */
        .face-detection-box {
            position: absolute;
            border: 3px solid #4caf50;
            border-radius: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.5);
        }

        .face-detection-box.smile-waiting {
            border-color: #9c27b0;
            box-shadow: 0 0 20px rgba(156, 39, 176, 0.5);
        }

        /* Face Guide Overlay */
        .face-guide-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 180px;
            height: 240px;
            border: 2px dashed rgba(255, 255, 255, 0.5);
            border-radius: 50% / 60%;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .face-guide-text {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            background: rgba(0, 0, 0, 0.7);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .resident-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .no-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
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
            transform: translateY(-1px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229f56;
            transform: translateY(-1px);
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
            background: #8e44ad;
            color: white;
        }

        .btn-info:hover {
            background: #7d3c98;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Table Styles */
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-filters {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            padding: 0.5rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 0.9rem;
            min-width: 200px;
        }

        .search-input:focus {
            outline: none;
            border-color: #3498db;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .face-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .face-registered {
            background: #cce5ff;
            color: #004085;
        }

        /* Action Buttons Section */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-add-resident {
            background: linear-gradient(135deg, #27ae60 0%, #229f56 100%);
            color: white;
            padding: 0.9rem 1.8rem;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-add-resident:hover {
            background: linear-gradient(135deg, #229f56 0%, #1e8449 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-face-search {
            background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);
            color: white;
            padding: 0.9rem 1.8rem;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
        }

        .btn-face-search:hover {
            background: linear-gradient(135deg, #7d3c98 0%, #663399 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(142, 68, 173, 0.4);
        }

        /* Search Results */
        .search-results {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .match-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .match-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #27ae60;
        }

        .match-info {
            flex: 1;
        }

        .match-score {
            background: #27ae60;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            gap: 0.5rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
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
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            padding: 0.25rem;
            line-height: 1;
            opacity: 0.8;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: auto;
            }

            .table-header {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-add-resident,
            .btn-face-search {
                width: 100%;
                justify-content: center;
            }

            .camera-preview {
                width: 100%;
                max-width: 320px;
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

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
        }

        .loading-content i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading Face Recognition Models...</p>
        </div>
    </div>

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
                <a href="resident-profiling.php" class="nav-item active">
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
                    <?php if ($pending_complaints > 0): ?>
                        <span class="nav-badge"><?php echo $pending_complaints; ?></span>
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
            <h1 class="page-title">Resident Profiling with Face Recognition</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-add-resident" onclick="showModal('createModal')">
                        <i class="fas fa-user-plus"></i> Add New Resident
                    </button>
                    <button class="btn btn-face-search" onclick="showModal('searchModal')">
                        <i class="fas fa-search"></i> Search by Face
                    </button>
                </div>
                <div style="color: #666; font-size: 0.9rem;">
                    Total Residents: <strong><?php echo $total_records; ?></strong>
                </div>
            </div>

            <!-- Residents Table -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-users"></i>
                        Residents List
                    </div>
                    <div class="search-filters">
                        <form method="GET" style="display: flex; gap: 1rem; align-items: center;">
                            <input type="text" name="search" placeholder="Search residents..." 
                                   class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </form>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Age</th>
                                <th>Contact Number</th>
                                <th>Status</th>
                                <th>Face Data</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($residents_result) > 0): ?>
                                <?php while ($resident = mysqli_fetch_assoc($residents_result)): ?>
                                    <tr>
                                        
                                        <td><?php echo $resident['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($resident['full_name']); ?></strong>
                                        </td>
                                        <td><?php echo $resident['age']; ?></td>
                                        <td><?php echo htmlspecialchars($resident['contact_number']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $resident['status']; ?>">
                                                <?php echo ucfirst($resident['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($resident['face_descriptor']): ?>
                                                <span class="face-badge face-registered">
                                                    <i class="fas fa-check-circle"></i> Registered
                                                </span>
                                            <?php else: ?>
                                                <span class="face-badge">
                                                    <i class="fas fa-times-circle"></i> Not Set
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($resident['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm" onclick="editResident(<?php echo htmlspecialchars(json_encode($resident)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick='showAccountModal(<?php echo json_encode(['id'=>$resident['id'],'full_name'=>$resident['full_name']]); ?>)'>
                                                <i class="fas fa-user-lock"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 2rem; color: #666;">
                                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                        <br>
                                        <?php if (!empty($search) || !empty($status_filter)): ?>
                                            No residents found matching your criteria.
                                        <?php else: ?>
                                            No residents added yet.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Resident Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus"></i> Add New Resident
                </h3>
                <button class="close-btn" onclick="closeModal('createModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="createForm">
                    <input type="hidden" name="action" value="add_resident">
                    <input type="hidden" name="photo_data" id="photo_data">
                    <input type="hidden" name="face_descriptor" id="face_descriptor">
                    
                    <!-- Photo Capture Section -->
                    <div class="photo-capture-container">
                        <h4 style="margin-bottom: 1rem; color: #333;">Capture Resident Photo with Face Recognition</h4>
                        
                        <div id="face-status" class="face-status no-face" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> Position your face in the camera
                        </div>
                        
                        <div id="camera-container" style="display: none;">
                            <div class="camera-preview">
                                <video id="videoElement" autoplay></video>
                                <canvas class="face-detection-overlay" id="faceCanvas"></canvas>
                                <div class="face-indicator not-detected" id="faceIndicator">
                                    <i class="fas fa-times-circle"></i>
                                    <span>No Face</span>
                                </div>
                                <div class="face-guide-overlay">
                                    <span class="face-guide-text">Position face here</span>
                                </div>
                                <div id="countdownOverlay" class="countdown-overlay" style="display: none;">3</div>
                            </div>
                            <div class="camera-controls">
                                <button type="button" class="btn btn-danger" onclick="stopCamera()">
                                    <i class="fas fa-stop"></i> Stop Camera
                                </button>
                            </div>
                        </div>
                        <div id="photo-preview" style="display: none;">
                            <div class="captured-photo">
                                <img id="capturedImage" src="" alt="Captured photo">
                            </div>
                            <div class="camera-controls">
                                <button type="button" class="btn btn-warning" onclick="retakePhoto()">
                                    <i class="fas fa-redo"></i> Retake Photo
                                </button>
                                <button type="button" class="btn btn-success" onclick="confirmPhoto()">
                                    <i class="fas fa-check"></i> Use This Photo
                                </button>
                            </div>
                        </div>
                        <div id="camera-start">
                            <button type="button" class="btn btn-primary" onclick="startCamera()">
                                <i class="fas fa-camera"></i> Start Camera
                            </button>
                        </div>
                        <canvas id="photoCanvas"></canvas>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_initial">Middle Initial</label>
                            <input type="text" id="middle_initial" name="middle_initial" class="form-control" maxlength="1">
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="age">Age *</label>
                            <input type="number" id="age" name="age" class="form-control" min="1" max="120" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number *</label>
                            <input type="text" id="contact_number" name="contact_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="button" class="btn" onclick="closeModal('createModal')" style="background: #6c757d; color: white;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Add Resident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Resident
                </h3>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update_resident">
                    <input type="hidden" name="resident_id" id="edit_resident_id">
                    <input type="hidden" name="photo_data" id="edit_photo_data">
                    <input type="hidden" name="face_descriptor" id="edit_face_descriptor">
                    
                    <!-- Photo Update Section -->
                    <div class="photo-capture-container">
                        <h4 style="margin-bottom: 1rem; color: #333;">Update Resident Photo</h4>
                        
                        <div id="edit-face-status" class="face-status no-face" style="display: none;">
                            <i class="fas fa-exclamation-circle"></i> Position your face in the camera
                        </div>
                        
                        <div id="edit-current-photo" style="margin-bottom: 1rem;">
                            <div class="captured-photo">
                                <img id="currentPhoto" src="" alt="Current photo" style="display: none;">
                                <div id="noPhotoPlaceholder" class="no-photo" style="width: 200px; height: 200px; font-size: 3rem; margin: 0 auto;">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div id="edit-camera-container" style="display: none;">
                            <div class="camera-preview">
                                <video id="editVideoElement" autoplay></video>
                                <canvas class="face-detection-overlay" id="editFaceCanvas"></canvas>
                                <div class="face-indicator not-detected" id="editFaceIndicator">
                                    <i class="fas fa-times-circle"></i>
                                    <span>No Face</span>
                                </div>
                                <div class="face-guide-overlay">
                                    <span class="face-guide-text">Position face here</span>
                                </div>
                                <div id="editCountdownOverlay" class="countdown-overlay" style="display: none;">3</div>
                            </div>
                            <div class="camera-controls">
                                <button type="button" class="btn btn-danger" onclick="stopEditCamera()">
                                    <i class="fas fa-stop"></i> Stop Camera
                                </button>
                            </div>
                        </div>
                        <div id="edit-photo-preview" style="display: none;">
                            <div class="captured-photo">
                                <img id="editCapturedImage" src="" alt="Captured photo">
                            </div>
                            <div class="camera-controls">
                                <button type="button" class="btn btn-warning" onclick="retakeEditPhoto()">
                                    <i class="fas fa-redo"></i> Retake Photo
                                </button>
                                <button type="button" class="btn btn-success" onclick="confirmEditPhoto()">
                                    <i class="fas fa-check"></i> Use This Photo
                                </button>
                            </div>
                        </div>
                        <div id="edit-camera-start">
                            <button type="button" class="btn btn-primary" onclick="startEditCamera()">
                                <i class="fas fa-camera"></i> Update Photo
                            </button>
                        </div>
                        <canvas id="editPhotoCanvas"></canvas>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_first_name">First Name *</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_middle_initial">Middle Initial</label>
                            <input type="text" id="edit_middle_initial" name="middle_initial" class="form-control" maxlength="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name">Last Name *</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_age">Age *</label>
                            <input type="number" id="edit_age" name="age" class="form-control" min="1" max="120" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_contact_number">Contact Number *</label>
                            <input type="text" id="edit_contact_number" name="contact_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status *</label>
                            <select id="edit_status" name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="button" class="btn" onclick="closeModal('editModal')" style="background: #6c757d; color: white;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Resident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Face Search Modal -->
    <div class="modal" id="searchModal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #8e44ad 0%, #7d3c98 100%);">
                <h3 class="modal-title">
                    <i class="fas fa-search"></i> Search Resident by Face
                </h3>
                <button class="close-btn" onclick="closeModal('searchModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="photo-capture-container">
                    <h4 style="margin-bottom: 1rem; color: #333;">Capture Face to Search</h4>
                    
                    <div id="search-face-status" class="face-status no-face" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i> Position your face in the camera
                    </div>
                    
                    <div id="search-camera-container" style="display: none;">
                        <div class="camera-preview">
                            <video id="searchVideoElement" autoplay></video>
                            <canvas class="face-detection-overlay" id="searchFaceCanvas"></canvas>
                            <div class="face-indicator not-detected" id="searchFaceIndicator">
                                <i class="fas fa-times-circle"></i>
                                <span>No Face</span>
                            </div>
                            <div class="face-guide-overlay">
                                <span class="face-guide-text">Position face here</span>
                            </div>
                        </div>
                        <div class="camera-controls">
                            <button type="button" class="btn btn-info" onclick="searchFace()" id="searchBtn" disabled>
                                <i class="fas fa-search"></i> Search Face
                            </button>
                            <button type="button" class="btn btn-danger" onclick="stopSearchCamera()">
                                <i class="fas fa-stop"></i> Stop Camera
                            </button>
                        </div>
                    </div>
                    
                    <div id="search-camera-start">
                        <button type="button" class="btn btn-primary" onclick="startSearchCamera()">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>
                    </div>
                </div>
                
                <div id="searchResults" class="search-results">
                    <h4 style="margin-bottom: 1rem;">Search Results</h4>
                    <div id="resultsContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                </h3>
                <button class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 1rem;">
                    <i class="fas fa-user-times" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem;"></i>
                    <p>Are you sure you want to delete <strong id="deleteResidentName"></strong>?</p>
                    <p style="color: #666; font-size: 0.9rem;">This action cannot be undone.</p>
                </div>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="action" value="delete_resident">
                    <input type="hidden" name="resident_id" id="delete_resident_id">
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button type="button" class="btn" onclick="closeModal('deleteModal')" style="background: #6c757d; color: white;">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Modal -->
    <div class="modal" id="accountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-lock"></i> Manage Resident Account</h3>
                <button class="close-btn" onclick="closeModal('accountModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="accountForm">
                    <input type="hidden" name="action" value="manage_account">
                    <input type="hidden" name="resident_id" id="acct_resident_id">
                    <div style="margin-bottom: 1rem;">
                        <strong id="acctResidentName"></strong>
                    </div>

                    <div id="acctInfo" style="margin-bottom:1rem; display:none;">
                        <label>Existing Account:</label>
                        <div id="existingAccountInfo"></div>
                    </div>

                    <div class="form-group">
                        <label for="acct_username">Username</label>
                        <input type="text" id="acct_username" name="username" class="form-control" placeholder="Username (for create or to identify user)">
                    </div>

                    <div class="form-group">
                        <label for="acct_password">New Password</label>
                        <input type="password" id="acct_password" name="password" class="form-control" placeholder="Enter new password">
                    </div>

                    <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:1rem;">
                        <button type="button" class="btn" onclick="closeModal('accountModal')" style="background:#6c757d; color:white;">Cancel</button>
                        <button type="submit" name="type" value="change_password" class="btn btn-warning">Change Password</button>
                        <button type="submit" name="type" value="create" class="btn btn-success">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let stream = null;
        let editStream = null;
        let searchStream = null;
        let faceDetectionInterval = null;
        let currentFaceDescriptor = null;
        let editFaceDescriptor = null;
        let modelsLoaded = false;
        let smileDetected = false;
        let faceDetected = false;
        let isCapturing = false;

        // Load face-api models (try local first so it can work offline, then fallback to CDN)
        async function loadModels() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const LOCAL_MODEL_URL = './models/'; // place model weights in this folder (relative to this PHP file)
            const CDN_MODEL_URL = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/weights/';
            const ALT_MODEL_URL = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights/';

            async function tryLoad(url) {
                console.log('Attempting to load models from', url);
                // load minimal set first (tiny detector + recognition + landmarks + expressions)
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(url),
                    faceapi.nets.faceLandmark68Net.loadFromUri(url),
                    faceapi.nets.faceRecognitionNet.loadFromUri(url),
                    faceapi.nets.faceExpressionNet.loadFromUri(url)
                ]);
                // ssdMobilenet is optional / fallback for detection accuracy
                try {
                    await faceapi.nets.ssdMobilenetv1.loadFromUri(url);
                } catch (e) {
                    console.warn('ssdMobilenetv1 not loaded from', url, e);
                }
            }

            loadingOverlay.classList.add('show');
            console.log('Starting to load models...');

            // Try local models first (no internet required if files present)
            try {
                await tryLoad(LOCAL_MODEL_URL);
                modelsLoaded = true;
                loadingOverlay.classList.remove('show');
                console.log('Models loaded from local folder:', LOCAL_MODEL_URL);
                return;
            } catch (localErr) {
                console.warn('Local models not found or failed to load:', localErr);
            }

            // If local failed, try CDN
            try {
                await tryLoad(CDN_MODEL_URL);
                modelsLoaded = true;
                loadingOverlay.classList.remove('show');
                console.log('Models loaded from CDN:', CDN_MODEL_URL);
                return;
            } catch (cdnErr) {
                console.warn('CDN model loading failed:', cdnErr);
            }

            // Try alternative CDN
            try {
                await tryLoad(ALT_MODEL_URL);
                modelsLoaded = true;
                loadingOverlay.classList.remove('show');
                console.log('Models loaded from alternative CDN:', ALT_MODEL_URL);
                return;
            } catch (altErr) {
                console.error('All model loading attempts failed:', altErr);
                loadingOverlay.classList.remove('show');
                // Show a clear message to the user
                const errMsg = 'Failed to load face recognition models. If you are offline, download the model files and place them in the folder: ./employee/sec/models/ (relative to the PHP file).';
                alert(errMsg);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadModels();
        });

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

        // Enhanced face detection
        async function detectFace(video, canvasId, statusId, buttonId, indicatorId = null, mode = 'create') {
            if (!modelsLoaded) {
                console.log('Models not loaded yet');
                return;
            }

            if (!video || video.readyState !== 4 || video.videoWidth === 0) {
                console.log('Video not ready');
                return;
            }
            
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                console.log('Canvas not found');
                return;
            }

            const displaySize = { width: video.videoWidth, height: video.videoHeight };
            faceapi.matchDimensions(canvas, displaySize);
            
            try {
                console.log('Detecting faces...');
                
                // Use TinyFaceDetector for better performance and compatibility
                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ 
                    inputSize: 224, 
                    scoreThreshold: 0.3 
                }))
                .withFaceLandmarks()
                .withFaceExpressions()
                .withFaceDescriptors();
                
                console.log('Detections found:', detections.length);
                
                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                if (resizedDetections.length > 0) {
                    const detection = resizedDetections[0];
                    console.log('Face detected, confidence:', detection.detection.score);
                    
                    // Draw detection box
                    const box = detection.detection.box;
                    ctx.strokeStyle = '#4caf50';
                    ctx.lineWidth = 3;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                    
                    // Draw corner indicators
                    const cornerLength = 20;
                    ctx.beginPath();
                    // Top-left corner
                    ctx.moveTo(box.x, box.y + cornerLength);
                    ctx.lineTo(box.x, box.y);
                    ctx.lineTo(box.x + cornerLength, box.y);
                    // Top-right corner
                    ctx.moveTo(box.x + box.width - cornerLength, box.y);
                    ctx.lineTo(box.x + box.width, box.y);
                    ctx.lineTo(box.x + box.width, box.y + cornerLength);
                    // Bottom-left corner
                    ctx.moveTo(box.x, box.y + box.height - cornerLength);
                    ctx.lineTo(box.x, box.y + box.height);
                    ctx.lineTo(box.x + cornerLength, box.y + box.height);
                    // Bottom-right corner
                    ctx.moveTo(box.x + box.width - cornerLength, box.y + box.height);
                    ctx.lineTo(box.x + box.width, box.y + box.height);
                    ctx.lineTo(box.x + box.width, box.y + box.height - cornerLength);
                    ctx.stroke();
                    
                    faceDetected = true;
                    
                    // Update face indicator
                    if (indicatorId) {
                        const indicator = document.getElementById(indicatorId);
                        
                        if (mode === 'create' && !smileDetected && !isCapturing) {
                            // Check for smile
                            const smileConfidence = detection.expressions.happy || 0;
                            console.log('Smile confidence:', smileConfidence);
                            
                            if (smileConfidence > 0.6) {
                                smileDetected = true;
                                indicator.className = 'face-indicator smile-ready';
                                indicator.innerHTML = '<i class="fas fa-smile"></i><span>Smile Detected!</span>';
                                
                                // Update status
                                document.getElementById(statusId).style.display = 'block';
                                document.getElementById(statusId).className = 'face-status smile-detected';
                                document.getElementById(statusId).innerHTML = '<i class="fas fa-smile"></i> Great! Starting countdown...';
                                
                                // Start countdown
                                startCountdown(video, mode);
                            } else {
                                indicator.className = 'face-indicator smile-ready';
                                indicator.innerHTML = '<i class="fas fa-smile"></i><span>Please Smile</span>';
                                
                                // Update status
                                document.getElementById(statusId).style.display = 'block';
                                document.getElementById(statusId).className = 'face-status face-detected';
                                document.getElementById(statusId).innerHTML = '<i class="fas fa-smile"></i> Face detected! Please smile to continue';
                            }
                        } else if (mode === 'search' || mode === 'edit') {
                            indicator.className = 'face-indicator detected';
                            indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Face Detected</span>';
                            
                            document.getElementById(statusId).style.display = 'block';
                            document.getElementById(statusId).className = 'face-status face-detected';
                            document.getElementById(statusId).innerHTML = '<i class="fas fa-check-circle"></i> Face detected';
                            
                            if (buttonId && document.getElementById(buttonId)) {
                                document.getElementById(buttonId).disabled = false;
                            }
                        } else {
                            indicator.className = 'face-indicator detected';
                            indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Face Detected</span>';
                        }
                    }
                    
                    return detection.descriptor;
                } else {
                    console.log('No faces detected');
                    faceDetected = false;
                    smileDetected = false;
                    
                    // No face detected
                    if (indicatorId) {
                        const indicator = document.getElementById(indicatorId);
                        indicator.className = 'face-indicator not-detected';
                        indicator.innerHTML = '<i class="fas fa-times-circle"></i><span>No Face</span>';
                    }
                    
                    document.getElementById(statusId).style.display = 'block';
                    document.getElementById(statusId).className = 'face-status no-face';
                    document.getElementById(statusId).innerHTML = '<i class="fas fa-exclamation-circle"></i> Position your face in the camera';
                    
                    if (buttonId && document.getElementById(buttonId)) {
                        document.getElementById(buttonId).disabled = true;
                    }
                    
                    return null;
                }
            } catch (error) {
                console.error('Face detection error:', error);
                
                // Update UI to show error
                if (indicatorId) {
                    const indicator = document.getElementById(indicatorId);
                    indicator.className = 'face-indicator checking';
                    indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Detection Error</span>';
                }
                
                document.getElementById(statusId).style.display = 'block';
                document.getElementById(statusId).className = 'face-status no-face';
                document.getElementById(statusId).innerHTML = '<i class="fas fa-exclamation-triangle"></i> Detection error, please try again';
                
                return null;
            }
        }

        // Countdown function
        function startCountdown(video, mode) {
            if (isCapturing) return;
            isCapturing = true;
            
            const countdownEl = document.getElementById(mode === 'edit' ? 'editCountdownOverlay' : 'countdownOverlay');
            countdownEl.style.display = 'flex';
            
            let count = 3;
            countdownEl.textContent = count;
            
            const countdownInterval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownEl.textContent = count;
                } else {
                    clearInterval(countdownInterval);
                    countdownEl.style.display = 'none';
                    
                    // Auto capture
                    if (mode === 'create') {
                        autoCapturePhoto();
                    } else if (mode === 'edit') {
                        autoCaptureEditPhoto();
                    }
                }
            }, 1000);
        }

        // Auto capture functions
        async function autoCapturePhoto() {
            if (!currentFaceDescriptor) {
                isCapturing = false;
                return;
            }
            
            const video = document.getElementById('videoElement');
            const canvas = document.getElementById('photoCanvas');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            const imageData = canvas.toDataURL('image/jpeg');
            document.getElementById('capturedImage').src = imageData;
            document.getElementById('photo_data').value = imageData;
            document.getElementById('face_descriptor').value = JSON.stringify(Array.from(currentFaceDescriptor));
            
            document.getElementById('camera-container').style.display = 'none';
            document.getElementById('photo-preview').style.display = 'block';
            
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            
            isCapturing = false;
        }

        async function autoCaptureEditPhoto() {
            if (!editFaceDescriptor) {
                isCapturing = false;
                return;
            }
            
            const video = document.getElementById('editVideoElement');
            const canvas = document.getElementById('editPhotoCanvas');
            const context = canvas.getContext('2d');
            
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0);
            
            const imageData = canvas.toDataURL('image/jpeg');
            document.getElementById('editCapturedImage').src = imageData;
            document.getElementById('edit_photo_data').value = imageData;
            document.getElementById('edit_face_descriptor').value = JSON.stringify(Array.from(editFaceDescriptor));
            
            document.getElementById('edit-camera-container').style.display = 'none';
            document.getElementById('edit-photo-preview').style.display = 'block';
            
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            
            isCapturing = false;
        }

        // Camera functions for Create Modal
        async function startCamera() {
            if (!modelsLoaded) {
                alert('Face recognition models are still loading. Please wait...');
                return;
            }
            
            const video = document.getElementById('videoElement');
            const constraints = {
                video: {
                    width: { ideal: 640, min: 320 },
                    height: { ideal: 480, min: 240 },
                    facingMode: 'user'
                }
            };

            try {
                console.log('Starting camera...');
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                
                // Wait for video to be ready
                await new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        console.log('Video metadata loaded, dimensions:', video.videoWidth, 'x', video.videoHeight);
                        resolve();
                    };
                });
                
                // Play the video
                await video.play();
                console.log('Video is playing');
                
                document.getElementById('camera-start').style.display = 'none';
                document.getElementById('camera-container').style.display = 'block';
                document.getElementById('photo-preview').style.display = 'none';
                
                // Reset states
                smileDetected = false;
                faceDetected = false;
                isCapturing = false;
                
                // Wait a bit for video to stabilize
                setTimeout(() => {
                    console.log('Starting face detection...');
                    // Start face detection
                    faceDetectionInterval = setInterval(async () => {
                        if (video.readyState === 4 && !isCapturing && video.videoWidth > 0) {
                            currentFaceDescriptor = await detectFace(video, 'faceCanvas', 'face-status', null, 'faceIndicator', 'create');
                        }
                    }, 500); // Slower interval for better performance
                }, 1000);
                
            } catch (err) {
                console.error('Error accessing camera:', err);
                alert('Could not access camera. Please ensure you have given permission and try again.');
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            document.getElementById('camera-start').style.display = 'block';
            document.getElementById('camera-container').style.display = 'none';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('face-status').style.display = 'none';
            document.getElementById('countdownOverlay').style.display = 'none';
            
            // Reset states
            smileDetected = false;
            faceDetected = false;
            isCapturing = false;
        }

        function retakePhoto() {
            document.getElementById('camera-container').style.display = 'block';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo_data').value = '';
            document.getElementById('face_descriptor').value = '';
            
            // Reset states
            smileDetected = false;
            faceDetected = false;
            isCapturing = false;
            
            // Restart face detection
            const video = document.getElementById('videoElement');
            faceDetectionInterval = setInterval(async () => {
                if (video.readyState === 4 && !isCapturing) {
                    currentFaceDescriptor = await detectFace(video, 'faceCanvas', 'face-status', null, 'faceIndicator', 'create');
                }
            }, 200);
        }

        function confirmPhoto() {
            stopCamera();
            alert('Photo captured successfully with face recognition data!');
        }

        // Camera functions for Edit Modal
        async function startEditCamera() {
            if (!modelsLoaded) {
                alert('Face recognition models are still loading. Please wait...');
                return;
            }
            
            const video = document.getElementById('editVideoElement');
            const constraints = {
                video: {
                    width: { ideal: 640, min: 320 },
                    height: { ideal: 480, min: 240 },
                    facingMode: 'user'
                }
            };

            try {
                console.log('Starting edit camera...');
                editStream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = editStream;
                
                // Wait for video to be ready
                await new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        console.log('Edit video metadata loaded, dimensions:', video.videoWidth, 'x', video.videoHeight);
                        resolve();
                    };
                });
                
                // Play the video
                await video.play();
                console.log('Edit video is playing');
                
                document.getElementById('edit-camera-start').style.display = 'none';
                document.getElementById('edit-camera-container').style.display = 'block';
                document.getElementById('edit-photo-preview').style.display = 'none';
                document.getElementById('edit-current-photo').style.display = 'none';
                
                // Reset states
                smileDetected = false;
                faceDetected = false;
                isCapturing = false;
                
                // Wait a bit for video to stabilize
                setTimeout(() => {
                    console.log('Starting edit face detection...');
                    // Start face detection
                    faceDetectionInterval = setInterval(async () => {
                        if (video.readyState === 4 && !isCapturing && video.videoWidth > 0) {
                            editFaceDescriptor = await detectFace(video, 'editFaceCanvas', 'edit-face-status', null, 'editFaceIndicator', 'edit');
                            
                            // Check for smile for auto capture
                            if (editFaceDescriptor && !smileDetected && !isCapturing) {
                                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                                    inputSize: 224,
                                    scoreThreshold: 0.3
                                }))
                                .withFaceExpressions();
                                
                                if (detections.length > 0 && detections[0].expressions.happy > 0.6) {
                                    smileDetected = true;
                                    startCountdown(video, 'edit');
                                }
                            }
                        }
                    }, 500);
                }, 1000);
                
            } catch (err) {
                console.error('Error accessing edit camera:', err);
                alert('Could not access camera. Please ensure you have given permission and try again.');
            }
        }

        function stopEditCamera() {
            if (editStream) {
                editStream.getTracks().forEach(track => track.stop());
                editStream = null;
            }
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            document.getElementById('edit-camera-start').style.display = 'block';
            document.getElementById('edit-camera-container').style.display = 'none';
            document.getElementById('edit-photo-preview').style.display = 'none';
            document.getElementById('edit-current-photo').style.display = 'block';
            document.getElementById('edit-face-status').style.display = 'none';
            document.getElementById('editCountdownOverlay').style.display = 'none';
            
            // Reset states
            smileDetected = false;
            faceDetected = false;
            isCapturing = false;
        }

        function retakeEditPhoto() {
            document.getElementById('edit-camera-container').style.display = 'block';
            document.getElementById('edit-photo-preview').style.display = 'none';
            document.getElementById('edit_photo_data').value = '';
            document.getElementById('edit_face_descriptor').value = '';
            
            // Reset states
            smileDetected = false;
            faceDetected = false;
            isCapturing = false;
            
            // Restart face detection
            const video = document.getElementById('editVideoElement');
            faceDetectionInterval = setInterval(async () => {
                if (video.readyState === 4 && !isCapturing) {
                    editFaceDescriptor = await detectFace(video, 'editFaceCanvas', 'edit-face-status', null, 'editFaceIndicator', 'edit');
                    
                    // Check for smile for auto capture
                    if (editFaceDescriptor && !smileDetected && !isCapturing) {
                        const detections = await faceapi.detectAllFaces(video, new faceapi.SsdMobilenetv1Options())
                            .withFaceExpressions();
                        
                        if (detections.length > 0 && detections[0].expressions.happy > 0.7) {
                            smileDetected = true;
                            startCountdown(video, 'edit');
                        }
                    }
                }
            }, 200);
        }

        function confirmEditPhoto() {
            stopEditCamera();
            alert('Photo updated successfully with face data!');
        }

        // Face Search Functions
        async function startSearchCamera() {
            if (!modelsLoaded) {
                alert('Face recognition models are still loading. Please wait...');
                return;
            }
            
            const video = document.getElementById('searchVideoElement');
            const constraints = {
                video: {
                    width: { ideal: 640, min: 320 },
                    height: { ideal: 480, min: 240 },
                    facingMode: 'user'
                }
            };

            try {
                console.log('Starting search camera...');
                searchStream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = searchStream;
                
                // Wait for video to be ready
                await new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        console.log('Search video metadata loaded, dimensions:', video.videoWidth, 'x', video.videoHeight);
                        resolve();
                    };
                });
                
                // Play the video
                await video.play();
                console.log('Search video is playing');
                
                document.getElementById('search-camera-start').style.display = 'none';
                document.getElementById('search-camera-container').style.display = 'block';
                document.getElementById('searchResults').classList.remove('show');
                
                // Wait a bit for video to stabilize
                setTimeout(() => {
                    console.log('Starting search face detection...');
                    // Start face detection
                    faceDetectionInterval = setInterval(async () => {
                        if (video.readyState === 4 && video.videoWidth > 0) {
                            await detectFace(video, 'searchFaceCanvas', 'search-face-status', 'searchBtn', 'searchFaceIndicator', 'search');
                        }
                    }, 500);
                }, 1000);
                
            } catch (err) {
                console.error('Error accessing search camera:', err);
                alert('Could not access camera. Please ensure you have given permission and try again.');
            }
        }

        function stopSearchCamera() {
            if (searchStream) {
                searchStream.getTracks().forEach(track => track.stop());
                searchStream = null;
            }
            if (faceDetectionInterval) {
                clearInterval(faceDetectionInterval);
                faceDetectionInterval = null;
            }
            document.getElementById('search-camera-start').style.display = 'block';
            document.getElementById('search-camera-container').style.display = 'none';
            document.getElementById('search-face-status').style.display = 'none';
        }

        async function searchFace() {
            const video = document.getElementById('searchVideoElement');
            
            try {
                console.log('Searching for faces...');
                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                    inputSize: 224,
                    scoreThreshold: 0.3
                }))
                .withFaceLandmarks()
                .withFaceDescriptors();
                
                if (detections.length === 0) {
                    alert('No face detected. Please position your face in the camera and try again.');
                    return;
                }
                
                const searchDescriptor = detections[0].descriptor;
                console.log('Face descriptor obtained, searching database...');
                
                // Show loading
                document.getElementById('resultsContainer').innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Searching for matching faces...</p>';
                document.getElementById('searchResults').classList.add('show');
                
                // Send search request
                const formData = new FormData();
                formData.append('action', 'search_face');
                formData.append('search_descriptor', JSON.stringify(Array.from(searchDescriptor)));
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.found && data.residents) {
                    const matches = [];
                    
                    // Compare with all residents
                    for (const resident of data.residents) {
                        if (resident.face_descriptor) {
                            const storedDescriptor = new Float32Array(JSON.parse(resident.face_descriptor));
                            const distance = faceapi.euclideanDistance(searchDescriptor, storedDescriptor);
                            
                            if (distance < 0.6) {
                                matches.push({
                                    ...resident,
                                    distance: distance,
                                    similarity: Math.round((1 - distance) * 100)
                                });
                            }
                        }
                    }
                    
                    // Sort by similarity
                    matches.sort((a, b) => b.similarity - a.similarity);
                    
                    // Display results
                    if (matches.length > 0) {
                        let html = '<div style="display: grid; gap: 1rem;">';
                        matches.forEach(match => {
                            html += `
                                <div class="match-card">
                                    <img src="../../${match.photo_path}" alt="${match.full_name}" class="match-photo">
                                    <div class="match-info">
                                        <h5 style="margin: 0 0 0.5rem 0;">${match.full_name}</h5>
                                        <p style="margin: 0; color: #666;">ID: ${match.id}</p>
                                        <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                            Distance: ${match.distance.toFixed(3)}
                                        </p>
                                    </div>
                                    <span class="match-score">${match.similarity}% Match</span>
                                </div>
                            `;
                        });
                        html += '</div>';
                        document.getElementById('resultsContainer').innerHTML = html;
                    } else {
                        document.getElementById('resultsContainer').innerHTML = `
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-user-slash" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                                <p>No matching residents found in the database.</p>
                                <p style="color: #666; font-size: 0.9rem;">The face was not recognized.</p>
                            </div>
                        `;
                    }
                } else {
                    document.getElementById('resultsContainer').innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <i class="fas fa-database" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                            <p>No residents with face data found in the system.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Search error:', error);
                document.getElementById('resultsContainer').innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #e74c3c; margin-bottom: 1rem;"></i>
                        <p style="color: #e74c3c;">Error searching for faces.</p>
                    </div>
                `;
            }
        }

        function editResident(resident) {
            document.getElementById('edit_resident_id').value = resident.id;
            document.getElementById('edit_first_name').value = resident.first_name;
            document.getElementById('edit_middle_initial').value = resident.middle_initial || '';
            document.getElementById('edit_last_name').value = resident.last_name;
            document.getElementById('edit_age').value = resident.age;
            document.getElementById('edit_contact_number').value = resident.contact_number;
            document.getElementById('edit_status').value = resident.status;
            
            // Handle photo display
            if (resident.photo_path) {
                document.getElementById('currentPhoto').src = '../../' + resident.photo_path;
                document.getElementById('currentPhoto').style.display = 'block';
                document.getElementById('noPhotoPlaceholder').style.display = 'none';
            } else {
                document.getElementById('currentPhoto').style.display = 'none';
                document.getElementById('noPhotoPlaceholder').style.display = 'flex';
            }
            
            showModal('editModal');
        }

        function deleteResident(id, name) {
            document.getElementById('delete_resident_id').value = id;
            document.getElementById('deleteResidentName').textContent = name;
            
            showModal('deleteModal');
        }

        // Account modal functions
        async function showAccountModal(resident) {
            document.getElementById('acct_resident_id').value = resident.id;
            document.getElementById('acctResidentName').textContent = resident.full_name;
            document.getElementById('acct_username').value = '';
            document.getElementById('acct_password').value = '';
            document.getElementById('existingAccountInfo').innerHTML = '';
            document.getElementById('acctInfo').style.display = 'none';

            try {
                const form = new FormData();
                form.append('action', 'get_account');
                form.append('resident_id', resident.id);

                const resp = await fetch('resident-profiling.php', { method: 'POST', body: form });
                const data = await resp.json();

                if (data.found && data.user) {
                    document.getElementById('acctInfo').style.display = 'block';
                    const u = data.user;
                    document.getElementById('existingAccountInfo').innerHTML = `
                        <div>Username: <strong>${u.username}</strong></div>
                        <div>User ID: <strong>${u.id}</strong></div>
                    `;
                    // Pre-fill username to help change password
                    document.getElementById('acct_username').value = u.username;
                } else {
                    document.getElementById('acctInfo').style.display = 'none';
                }

            } catch (e) {
                console.error(e);
            }

            // Show modal
            document.getElementById('accountModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Minimal JS to fetch account info and show modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Form validation
        document.getElementById('createForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const age = document.getElementById('age').value;
            const contactNumber = document.getElementById('contact_number').value.trim();
            const photoData = document.getElementById('photo_data').value;
            const faceDescriptor = document.getElementById('face_descriptor').value;

            if (!firstName || !lastName || !age || !contactNumber) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            if (!photoData || !faceDescriptor) {
                e.preventDefault();
                alert('Please capture a photo with face detection before submitting.');
                return false;
            }

            if (age < 1 || age > 120) {
                e.preventDefault();
                alert('Please enter a valid age between 1 and 120.');
                return false;
            }
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('edit_first_name').value.trim();
            const lastName = document.getElementById('edit_last_name').value.trim();
            const age = document.getElementById('edit_age').value;
            const contactNumber = document.getElementById('edit_contact_number').value.trim();

            if (!firstName || !lastName || !age || !contactNumber) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            if (age < 1 || age > 120) {
                e.preventDefault();
                alert('Please enter a valid age between 1 and 120.');
                return false;
            }
        });

        // Clean up resources when page is unloaded
        window.addEventListener('beforeunload', function() {
            stopCamera();
            stopEditCamera();
            stopSearchCamera();
        });

        // Debug function (call from console: debugFaceDetection())
        window.debugFaceDetection = function() {
            console.log('=== Face Detection Debug Info ===');
            console.log('Models loaded:', modelsLoaded);
            console.log('Face detected:', faceDetected);
            console.log('Smile detected:', smileDetected);
            console.log('Is capturing:', isCapturing);
            console.log('Current face descriptor:', currentFaceDescriptor ? 'Present' : 'None');
            console.log('Detection interval active:', faceDetectionInterval ? 'Yes' : 'No');
            
            const video = document.getElementById('videoElement');
            if (video) {
                console.log('Video ready state:', video.readyState);
                console.log('Video dimensions:', video.videoWidth, 'x', video.videoHeight);
                console.log('Video src object:', video.srcObject ? 'Present' : 'None');
                console.log('Video playing:', !video.paused);
            }
            
            // Test if face-api is available
            console.log('FaceAPI available:', typeof faceapi !== 'undefined');
            if (typeof faceapi !== 'undefined') {
                console.log('TinyFaceDetector loaded:', faceapi.nets.tinyFaceDetector.isLoaded);
                console.log('FaceLandmark68Net loaded:', faceapi.nets.faceLandmark68Net.isLoaded);
                console.log('FaceRecognitionNet loaded:', faceapi.nets.faceRecognitionNet.isLoaded);
                console.log('FaceExpressionNet loaded:', faceapi.nets.faceExpressionNet.isLoaded);
            }
            
            console.log('=== End Debug Info ===');
        };
        
        // Add a simple face detection test function
        window.testFaceDetection = async function() {
            const video = document.getElementById('videoElement');
            if (!video || !video.srcObject) {
                console.log('Video not ready. Start camera first.');
                return;
            }
            
            console.log('Testing face detection...');
            try {
                const result = await detectFace(video, 'faceCanvas', 'face-status', null, 'faceIndicator', 'create');
                console.log('Test result:', result ? 'Face detected' : 'No face detected');
            } catch (error) {
                console.error('Test failed:', error);
            }
        };
    </script>
</body>
</html>