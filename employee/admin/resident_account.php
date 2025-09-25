<?php
session_start();
require_once '../../config.php';
require_once '../../config/mail_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
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
            
            // Handle photo upload
            $photo_path = null;
            
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
                $photo_path = 'uploads/residents/' . $photo_filename;
            }
            
            $insert_query = "INSERT INTO residents (first_name, middle_initial, last_name, full_name, age, contact_number, status, photo_path) 
                           VALUES ('$first_name', '$middle_initial', '$last_name', '$full_name', $age, '$contact_number', '$status', " . 
                           ($photo_path ? "'$photo_path'" : "NULL") . ")";
            
            if (mysqli_query($connection, $insert_query)) {
                $success_message = "Resident added successfully!";
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
            
            $update_query = "UPDATE residents SET 
                           first_name = '$first_name', 
                           middle_initial = '$middle_initial', 
                           last_name = '$last_name', 
                           full_name = '$full_name',
                           age = $age, 
                           contact_number = '$contact_number', 
                           status = '$status'
                           $photo_update,
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

                    $insert = "INSERT INTO users (username, password, full_name, role, created_at) VALUES ('$username', '$pass_hash', '$full_name', 'resident', NOW())";
                    if (mysqli_query($connection, $insert)) {
                        $new_user_id = mysqli_insert_id($connection);
                        // Attempt to link users -> residents if residents.user_id exists
                        @mysqli_query($connection, "UPDATE residents SET user_id = $new_user_id WHERE id = $rid");
                        $_SESSION['success_message'] = "Account created successfully.";
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
        } elseif ($_POST['action'] === 'update_account') {
            $account_id = intval($_POST['account_id']);
            $username = mysqli_real_escape_string($connection, $_POST['username']);
            $new_email = mysqli_real_escape_string($connection, $_POST['email']);
            $password = $_POST['password'];
            
            // Get current email
            $current_email_query = "SELECT email FROM resident_accounts WHERE id = $account_id";
            $current_email_result = mysqli_query($connection, $current_email_query);
            $current_email = mysqli_fetch_assoc($current_email_result)['email'];
            
            // Check if email is being changed
            if ($current_email !== $new_email) {
                // Store new email in session for later use
                $_SESSION['pending_email_update'] = [
                    'account_id' => $account_id,
                    'new_email' => $new_email,
                    'timestamp' => time()
                ];
                
                // Generate verification code
                $verification_code = mt_rand(100000, 999999);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store verification details
                $insert_verify = "INSERT INTO email_change_verifications (account_id, new_email, verification_code, code_expiry) 
                                 VALUES ($account_id, '$new_email', '$verification_code', '$expires')";
                
                if (mysqli_query($connection, $insert_verify)) {
                    // Send verification email
                    $subject = "Email Verification Code";
                    $message = "Your verification code is: $verification_code\n";
                    $message .= "This code will expire in 15 minutes.\n";
                    
                    if (sendEmail($new_email, $subject, $message)) {
                        $success_message = "Verification code sent to $new_email";
                    } else {
                        $error_message = "Failed to send verification email";
                    }
                }
            }
            
            // Update other account details
            $update_query = "UPDATE resident_accounts SET username = '$username'";
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $update_query .= ", password = '$password_hash'";
            }
            $update_query .= " WHERE id = $account_id";
            
            if (mysqli_query($connection, $update_query)) {
                if ($current_email === $new_email) {
                    $success_message = "Account updated successfully!";
                }
            } else {
                $error_message = "Error updating account: " . mysqli_error($connection);
            }
        } elseif ($_POST['action'] === 'verify_email') {
            header('Content-Type: application/json; charset=utf-8');

            $code = isset($_POST['code']) ? trim(mysqli_real_escape_string($connection, $_POST['code'])) : '';
            $posted_account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;

            // fallback to session
            $pending_update = $_SESSION['pending_email_update'] ?? null;
            $session_account_id = $pending_update['account_id'] ?? 0;

            $account_id = $posted_account_id ?: $session_account_id;

            if (!$account_id || $code === '') {
                echo json_encode(['status' => 'error', 'message' => 'Missing account or code.']);
                exit();
            }

            // Fetch the latest unused verification row for this account
            $q = "SELECT * FROM email_change_verifications
                  WHERE account_id = $account_id
                  AND used = 0
                  ORDER BY id DESC LIMIT 1";
            $res = mysqli_query($connection, $q);

            if ($res === false) {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($connection)]);
                exit();
            }

            if (mysqli_num_rows($res) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'No pending verification code found. Please request a new code.']);
                exit();
            }

            $row = mysqli_fetch_assoc($res);

            // compare codes (string-safe)
            $stored_code = (string)$row['verification_code'];
            if ($stored_code !== (string)$code) {
                // do not reveal the stored code; just report mismatch
                echo json_encode(['status' => 'error', 'message' => 'Verification code does not match.']);
                exit();
            }

            // check expiry in PHP to avoid timezone mismatch
            $expiry_ts = strtotime($row['code_expiry']);
            if ($expiry_ts === false || $expiry_ts < time()) {
                echo json_encode(['status' => 'error', 'message' => 'Verification code has expired. Request a new code.']);
                exit();
            }

            // use new_email from this verification row for consistency
            $new_email = mysqli_real_escape_string($connection, $row['new_email']);

            $update = "UPDATE resident_accounts SET email = '$new_email' WHERE id = $account_id";
            if (mysqli_query($connection, $update)) {
                // mark verification used
                mysqli_query($connection, "UPDATE email_change_verifications SET used = 1 WHERE id = {$row['id']}");

                // clear session pending if matches
                if ($pending_update && $pending_update['account_id'] == $account_id && $pending_update['new_email'] === $row['new_email']) {
                    unset($_SESSION['pending_email_update']);
                }

                echo json_encode(['status' => 'success', 'message' => 'Email verified and updated successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error updating email: ' . mysqli_error($connection)]);
            }
            exit();
        } elseif ($_POST['action'] === 'send_verification') {
            header('Content-Type: application/json');
            $account_id = intval($_POST['account_id']);
            $new_email = mysqli_real_escape_string($connection, $_POST['email']);
            
            // Generate verification code
            $verification_code = mt_rand(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store verification details
            $insert_verify = "INSERT INTO email_change_verifications (account_id, new_email, verification_code, code_expiry) 
                             VALUES ($account_id, '$new_email', '$verification_code', '$expires')";
            
            if (mysqli_query($connection, $insert_verify)) {
                // Store pending email update in session
                $_SESSION['pending_email_update'] = [
                    'account_id' => $account_id,
                    'new_email' => $new_email,
                    'timestamp' => time()
                ];
                
                // Send verification email
                $subject = "Email Verification Code";
                $message = "Your verification code is: $verification_code\n";
                $message .= "This code will expire in 15 minutes.\n";
                
                if (sendEmail($new_email, $subject, $message)) {
                    echo json_encode(['status' => 'success', 'message' => 'Verification code sent successfully']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send verification email']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error']);
            }
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
    $where_conditions[] = "(ra.username LIKE '%$search%' OR ra.email LIKE '%$search%' OR r.id LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_conditions[] = "ra.account_locked = " . ($status_filter === 'inactive' ? '1' : '0');
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM resident_accounts ra 
                LEFT JOIN residents r ON ra.resident_id = r.id 
                $where_clause";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get resident accounts
$residents_query = "SELECT ra.*, r.id as resident_number, r.full_name 
                   FROM resident_accounts ra 
                   LEFT JOIN residents r ON ra.resident_id = r.id 
                   $where_clause 
                   ORDER BY ra.created_at DESC 
                   LIMIT $records_per_page OFFSET $offset";
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
	<title>Resident Account Management - Barangay Management System</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
	
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

		#videoElement {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		#photoCanvas {
			display: none;
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

		/* Remove face recognition related styles */
		.face-detection-overlay,
		.face-indicator,
		.face-guide-overlay,
		.countdown-overlay,
		.face-detection-box,
		.face-badge {
			display: none;
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

		/* Small adjustments for verify button + cooldown */
.verify-row {
	display: flex;
	gap: 0.5rem;
	align-items: flex-start;
}
.verify-col {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
}

/* make disabled buttons visually distinct and non-interactive */
.btn[disabled] {
	opacity: 0.6;
	cursor: not-allowed;
	pointer-events: none;
}

/* cooldown timer style */
#cooldownTimer {
	display: none;
	color: #666;
	font-size: 0.85rem;
	margin-top: 6px;
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
				<a href="resident-profiling.php" class="nav-item">
					<i class="fas fa-users"></i>
					Resident Profiling
				</a>
				 <a href="resident_family.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Resident Family
                </a>
				<a href="resident_account.php" class="nav-item active">
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
			<h1 class="page-title">Resident Account</h1>
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
				<div style="color: #666; font-size: 0.9rem;">
					Total Accounts: <strong><?php echo $total_records; ?></strong>
				</div>
			</div>

			<!-- Residents Table -->
			<div class="table-card">
				<div class="table-header">
					<div class="table-title">
						<i class="fas fa-user-shield"></i>
						Account List
					</div>
					<div class="search-filters">
						<form method="GET" style="display: flex; gap: 1rem; align-items: center;">
							<input type="text" name="search" placeholder="Search accounts..." 
								   class="search-input" value="<?php echo htmlspecialchars($search); ?>">
							<select name="status" class="filter-select">
								<option value="">All Status</option>
								<option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
								<option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Locked</option>
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
								<th>Username</th>
								<th>Email</th>
								<th>Owned By</th>
								<th>Status</th>
								<th>Last Login</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php if (mysqli_num_rows($residents_result) > 0): ?>
								<?php while ($account = mysqli_fetch_assoc($residents_result)): ?>
									<tr>
										<td><?php echo htmlspecialchars($account['username']); ?></td>
										<td><?php echo htmlspecialchars($account['email']); ?></td>
										<td><?php echo htmlspecialchars($account['full_name'] ?? 'N/A'); ?></td>
										<td>
											<span class="status-badge status-<?php echo $account['account_locked'] ? 'inactive' : 'active'; ?>">
												<?php echo $account['account_locked'] ? 'Locked' : 'Active'; ?>
											</span>
										</td>
										<td>
											<?php echo $account['last_login'] ? date('M j, Y g:i A', strtotime($account['last_login'])) : 'Never'; ?>
										</td>
										<td>
											<button class="btn btn-warning btn-sm" onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>)">
												<i class="fas fa-edit"></i>
											</button>
											<?php if ($account['account_locked']): ?>
												<button class="btn btn-success btn-sm" onclick="unlockAccount(<?php echo $account['id']; ?>)">
													<i class="fas fa-unlock"></i>
												</button>
											<?php else: ?>
												<button class="btn btn-danger btn-sm" onclick="lockAccount(<?php echo $account['id']; ?>)">
													<i class="fas fa-lock"></i>
												</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php else: ?>
								<tr>
									<td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
										<i class="fas fa-user-shield" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
										<br>
										<?php if (!empty($search) || !empty($status_filter)): ?>
											No accounts found matching your criteria.
										<?php else: ?>
											No resident accounts created yet.
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
					
					<!-- Photo Capture Section -->
					<div class="photo-capture-container">
						<h4 style="margin-bottom: 1rem; color: #333;">Capture Resident Photo</h4>
						
						<div id="camera-container" style="display: none;">
							<div class="camera-preview">
								<video id="videoElement" autoplay></video>
							</div>
							<div class="camera-controls">
								<button type="button" class="btn btn-success" onclick="capturePhoto()">
									<i class="fas fa-camera"></i> Take Photo
								</button>
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

	<!-- Edit Account Modal -->
	<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-edit"></i> Edit Account
            </h3>
            <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="action" value="update_account">
                <input type="hidden" name="account_id" id="edit_account_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <div class="verify-row">
							<div style="flex: 1;">
								<input type="email" id="edit_email" name="email" class="form-control" required oninput="handleEmailChange(this.value)">
								<small id="emailVerificationStatus" class="verification-status" style="color: #721c24; display: none;">
									Email must be verified before updating. Click verify to continue.
								</small>
							</div>

							<div class="verify-col" style="min-width:120px;">
								<button type="button" class="btn btn-primary" onclick="verifyEmail()" id="verifyEmailBtn" style="display: none;">
									<i class="fas fa-envelope" id="verifyBtnIcon"></i>
									<span id="verifyBtnText">Verify</span>
								</button>
								<small id="cooldownTimer" aria-live="polite"></small>
							</div>
						</div>

						<div id="verificationSection" style="display: none; margin-top: 1rem;">
							<label for="verification_code">Enter Verification Code</label>
							<div style="display: flex; gap: 0.5rem;">
								<div style="flex: 1;">
									<input type="text" id="verification_code" name="verification_code" 
										   class="form-control" placeholder="Enter 6-digit code">
									<small id="verificationMessage" class="verification-message"></small>
								</div>
								<button type="button" class="btn btn-success" onclick="submitVerificationCode()">
									<i class="fas fa-check"></i> Verify Code
								</button>
							</div>
							<small style="color: #666;">Check your email for the verification code</small>
						</div>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password</label>
                        <input type="password" id="edit_password" name="password" class="form-control" 
                               placeholder="Leave blank to keep current password">
                    </div>
                    <div class="form-group">
                        <label for="edit_confirm_password">Confirm New Password</label>
                        <input type="password" id="edit_confirm_password" name="confirm_password" 
                               class="form-control" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn" onclick="closeModal('editModal')" 
                            style="background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-success" id="updateBtn">
                        <i class="fas fa-save"></i> Update Account
                    </button>
                </div>
            </form>
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

<!-- View Resident Modal -->
<div class="modal" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-user"></i> Resident Profile
            </h3>
            <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div class="modal-body" id="residentProfileContent">
            <div class="text-center" style="padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3498db;"></i>
                <p>Loading resident profile...</p>
            </div>
        </div>
    </div>
</div>

	<script>
		// Global variables
		let stream = null;
		let editStream = null;
		let cooldownTime = 120; // 120 seconds cooldown
		let cooldownTimer = null;
		let canSendVerification = true;

		// Initialize on page load (simplified)
		document.addEventListener('DOMContentLoaded', function() {
			const loadingOverlay = document.getElementById('loadingOverlay');
			loadingOverlay.classList.remove('show');
		});

		// Basic camera functions
		async function startCamera() {
			const video = document.getElementById('videoElement');
			const constraints = {
				video: {
					width: { ideal: 640 },
					height: { ideal: 480 },
					facingMode: 'user'
				}
			};

			try {
				stream = await navigator.mediaDevices.getUserMedia(constraints);
				video.srcObject = stream;
				document.getElementById('camera-start').style.display = 'none';
				document.getElementById('camera-container').style.display = 'block';
				document.getElementById('photo-preview').style.display = 'none';
			} catch (err) {
				console.error('Error accessing camera:', err);
				alert('Could not access camera. Please ensure you have given permission and try again.');
			}
		}

		function capturePhoto() {
			const video = document.getElementById('videoElement');
			const canvas = document.getElementById('photoCanvas');
			const context = canvas.getContext('2d');
			
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;
			context.drawImage(video, 0, 0);
			
			const imageData = canvas.toDataURL('image/jpeg');
			document.getElementById('capturedImage').src = imageData;
			document.getElementById('photo_data').value = imageData;
			
			document.getElementById('camera-container').style.display = 'none';
			document.getElementById('photo-preview').style.display = 'block';
		}

		function stopCamera() {
			if (stream) {
				stream.getTracks().forEach(track => track.stop());
				stream = null;
			}
			document.getElementById('camera-start').style.display = 'block';
			document.getElementById('camera-container').style.display = 'none';
			document.getElementById('photo-preview').style.display = 'none';
		}

		function retakePhoto() {
			document.getElementById('camera-container').style.display = 'block';
			document.getElementById('photo-preview').style.display = 'none';
			document.getElementById('photo_data').value = '';
			
			// Reset states
			smileDetected = false;
			faceDetected = false;
			isCapturing = false;
		}

		function confirmPhoto() {
			stopCamera();
			alert('Photo captured successfully!');
		}

		// Camera functions for Edit Modal
		async function startEditCamera() {
			const video = document.getElementById('editVideoElement');
			const constraints = {
				video: {
					width: { ideal: 640 },
					height: { ideal: 480 },
					facingMode: 'user'
				}
			};

			try {
				editStream = await navigator.mediaDevices.getUserMedia(constraints);
				video.srcObject = editStream;
				document.getElementById('edit-camera-start').style.display = 'none';
				document.getElementById('edit-camera-container').style.display = 'block';
				document.getElementById('edit-photo-preview').style.display = 'none';
				document.getElementById('edit-current-photo').style.display = 'none';
			} catch (err) {
				console.error('Error accessing edit camera:', err);
				alert('Could not access camera. Please ensure you have given permission and try again.');
			}
		}

		function captureEditPhoto() {
			const video = document.getElementById('editVideoElement');
			const canvas = document.getElementById('editPhotoCanvas');
			const context = canvas.getContext('2d');
			
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;
			context.drawImage(video, 0, 0);
			
			const imageData = canvas.toDataURL('image/jpeg');
			document.getElementById('editCapturedImage').src = imageData;
			document.getElementById('edit_photo_data').value = imageData;
			
			document.getElementById('edit-camera-container').style.display = 'none';
			document.getElementById('edit-photo-preview').style.display = 'block';
		}

		function stopEditCamera() {
			if (editStream) {
				editStream.getTracks().forEach(track => track.stop());
				editStream = null;
			}
			document.getElementById('edit-camera-start').style.display = 'block';
			document.getElementById('edit-camera-container').style.display = 'none';
			document.getElementById('edit-photo-preview').style.display = 'none';
			document.getElementById('edit-current_photo').style.display = 'block';
		}

		function retakeEditPhoto() {
			document.getElementById('edit-camera-container').style.display = 'block';
			document.getElementById('edit-photo-preview').style.display = 'none';
			document.getElementById('edit_photo_data').value = '';
			
			// Reset states
			smileDetected = false;
			faceDetected = false;
			isCapturing = false;
		}

		function confirmEditPhoto() {
			stopEditCamera();
			alert('Photo updated successfully!');
		}

		// Show a modal with the given id (adds the 'show' class which is handled by CSS in this file)
		function showModal(id) {
			const modal = document.getElementById(id);
			if (!modal) return;
			modal.classList.add('show');
			// prevent background scroll while modal is open
			document.body.style.overflow = 'hidden';
		}

		// Close a modal by id
		function closeModal(id) {
			const modal = document.getElementById(id);
			if (!modal) return;
			modal.classList.remove('show');
			document.body.style.overflow = '';
			
			// Reset cooldown when modal is closed
			if (cooldownTimer) {
				clearInterval(cooldownTimer);
			}
			canSendVerification = true;
			const timerElement = document.getElementById('cooldownTimer');
			if (timerElement) {
				timerElement.style.display = 'none';
			}
		}

		// Close modal when clicking on the overlay/background area (the element with class 'modal')
		document.addEventListener('click', function (e) {
			const target = e.target;
			if (target && target.classList && target.classList.contains('modal')) {
				target.classList.remove('show');
				document.body.style.overflow = '';
			}
		});

		// Minimal sidebar toggle helper (keeps behavior consistent if referenced)
		function toggleSidebar() {
			const sidebar = document.getElementById('sidebar');
			const overlay = document.getElementById('sidebarOverlay');
			if (!sidebar || !overlay) return;
			sidebar.classList.toggle('active');
			overlay.classList.toggle('active');
		}

		function viewResident(residentId) {
			showModal('viewModal');
			const contentDiv = document.getElementById('residentProfileContent');
			
			fetch(`get_resident_profile.php?id=${residentId}`)
				.then(response => response.text())
				.then(data => {
					contentDiv.innerHTML = data;
				})
				.catch(error => {
					contentDiv.innerHTML = `
						<div class="alert alert-error">
							<i class="fas fa-exclamation-circle"></i>
							Error loading resident profile: ${error.message}
						</div>`;
				});
		}

		let originalEmail = '';
		let emailVerified = false;

		function editAccount(account) {
    document.getElementById('edit_account_id').value = account.id;
    document.getElementById('edit_username').value = account.username;
    document.getElementById('edit_email').value = account.email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_confirm_password').value = '';
    originalEmail = account.email;
    emailVerified = true; // Set to true for existing email
    document.getElementById('verificationSection').style.display = 'none';
    
    // Disable the update button initially if email is changed
    const updateBtn = document.getElementById('updateBtn');
    updateBtn.disabled = false;
    
    showModal('editModal');
}

function verifyEmail() {
    if (!canSendVerification) {
        document.getElementById('cooldownTimer').style.display = 'block';
        return;
    }
    
    const verifyBtn = document.getElementById('verifyEmailBtn');
    const verifyBtnIcon = document.getElementById('verifyBtnIcon');
    const verifyBtnText = document.getElementById('verifyBtnText');
    const newEmail = document.getElementById('edit_email').value;
    const accountId = document.getElementById('edit_account_id').value;
    const verificationMessage = document.getElementById('verificationMessage');
    
    // Disable button and show loading state
    verifyBtn.disabled = true;
    verifyBtn.style.opacity = '0.6';
    verifyBtn.style.cursor = 'not-allowed';
    verifyBtnIcon.className = 'fas fa-spinner fa-spin';
    verifyBtnText.textContent = 'Sending...';
    
    // Show verification section immediately
    document.getElementById('verificationSection').style.display = 'block';
    verificationMessage.style.color = '#155724';
    verificationMessage.textContent = 'Sending verification code...';
    
    const formData = new FormData();
    formData.append('action', 'send_verification');
    formData.append('email', newEmail);
    formData.append('account_id', accountId);
    
    fetch('resident_account.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            verificationMessage.style.color = '#155724';
            verificationMessage.textContent = 'Verification code sent! Please check your email.';
            canSendVerification = false;
            startCooldown();
        } else {
            verifyBtn.disabled = false;
            verifyBtn.style.opacity = '1';
            verifyBtn.style.cursor = 'pointer';
            verificationMessage.style.color = '#721c24';
            verificationMessage.textContent = data.message || 'Failed to send verification code.';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        verifyBtn.disabled = false;
        verifyBtn.style.opacity = '1';
        verifyBtn.style.cursor = 'pointer';
        verificationMessage.style.color = '#721c24';
        verificationMessage.textContent = 'Error sending verification code. Please try again.';
    })
    .finally(() => {
        verifyBtnIcon.className = 'fas fa-envelope';
        verifyBtnText.textContent = 'Verify';
    });
}

function handleEmailChange(value) {
    const verifyBtn = document.getElementById('verifyEmailBtn');
    const updateBtn = document.getElementById('updateBtn');
    const emailStatus = document.getElementById('emailVerificationStatus');
    const isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    
    if (value && value !== originalEmail && isEmailValid) {
        verifyBtn.style.display = 'block';
        verifyBtn.disabled = false; // Enable the verify button
        verifyBtn.style.opacity = '1';
        verifyBtn.style.cursor = 'pointer';
        emailVerified = false;
        updateBtn.disabled = true;
        emailStatus.style.display = 'block';
    } else {
        verifyBtn.style.display = 'none';
        emailStatus.style.display = 'none';
        if (value === originalEmail) {
            emailVerified = true;
            updateBtn.disabled = false;
        }
    }
}

// Add the cooldown function
function startCooldown() {
    let timeLeft = cooldownTime;
    const timerElement = document.getElementById('cooldownTimer');
    const verifyBtn = document.getElementById('verifyEmailBtn');

    if (!timerElement || !verifyBtn) return;

    // show and disable
    timerElement.style.display = 'block';
    verifyBtn.disabled = true;
    verifyBtn.setAttribute('aria-disabled', 'true');

    // display initial text
    timerElement.textContent = `Can resend in ${timeLeft}s`;

    // clear any existing interval
    if (cooldownTimer) clearInterval(cooldownTimer);

    cooldownTimer = setInterval(() => {
        timeLeft--;
        if (timeLeft > 0) {
            timerElement.textContent = `Can resend in ${timeLeft}s`;
        } else {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
            canSendVerification = true;
            verifyBtn.disabled = false;
            verifyBtn.removeAttribute('aria-disabled');
            timerElement.style.display = 'none';
            // restore button icon/text (in case changed elsewhere)
            const verifyBtnIcon = document.getElementById('verifyBtnIcon');
            const verifyBtnText = document.getElementById('verifyBtnText');
            if (verifyBtnIcon) verifyBtnIcon.className = 'fas fa-envelope';
            if (verifyBtnText) verifyBtnText.textContent = 'Verify';
        }
    }, 1000);
}

function submitVerificationCode() {
	const codeEl = document.getElementById('verification_code');
	const accountEl = document.getElementById('edit_account_id');
	const verificationMessage = document.getElementById('verificationMessage');
	const updateBtn = document.getElementById('updateBtn');
	const emailStatus = document.getElementById('emailVerificationStatus');
	const verifyEmailBtn = document.getElementById('verifyEmailBtn');

	const code = codeEl ? codeEl.value.trim() : '';
	const accountId = accountEl ? accountEl.value : '';

	if (!code) {
		verificationMessage.style.color = '#721c24';
		verificationMessage.textContent = 'Please enter the verification code';
		return;
	}
	if (!accountId) {
		verificationMessage.style.color = '#721c24';
		verificationMessage.textContent = 'Missing account id. Re-open the edit modal and try again.';
		return;
	}

	verificationMessage.style.color = '#155724';
	verificationMessage.textContent = 'Verifying code...';

	const formData = new FormData();
	formData.append('action', 'verify_email');
	formData.append('code', code);
	formData.append('account_id', accountId);

	fetch('resident_account.php', {
		method: 'POST',
		body: formData,
		headers: { 'X-Requested-With': 'XMLHttpRequest' }
	})
	.then(response => {
		if (!response.ok) throw new Error('Network response was not ok');
		return response.json();
	})
	.then(data => {
		// show server message exactly to help debug
		if (data.status === 'success') {
			emailVerified = true;
			updateBtn.disabled = false;
			verifyEmailBtn.style.display = 'none';
			emailStatus.style.display = 'block';
			emailStatus.style.color = '#155724';
			emailStatus.textContent = 'Email successfully verified!';
			verificationMessage.style.color = '#155724';
			verificationMessage.textContent = data.message || 'Email verified successfully!';
			originalEmail = document.getElementById('edit_email').value || originalEmail;
			Swal.fire({ icon: 'success', title: 'Verified', text: data.message || 'Email verified', confirmButtonColor: '#3498db' })
				.then(()=> document.getElementById('verificationSection').style.display = 'none');
		} else {
			verificationMessage.style.color = '#721c24';
			verificationMessage.textContent = data.message || 'Invalid verification code. Please try again.';
			updateBtn.disabled = true;
			Swal.fire({ icon: 'error', title: 'Verification Failed', text: data.message || 'Invalid or expired code', confirmButtonColor: '#e74c3c' });
		}
	})
	.catch(err => {
		console.error('Verification error:', err);
		verificationMessage.style.color = '#721c24';
		verificationMessage.textContent = 'Error verifying code. Please try again.';
		updateBtn.disabled = true;
	});
}

// add logout confirmation using SweetAlert2
function handleLogout() {
	// Use SweetAlert2 to confirm logout
	Swal.fire({
		title: 'Are you sure you want to logout?',
		text: "You will be logged out of the admin panel.",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#d33',
		cancelButtonColor: '#6c757d',
		confirmButtonText: 'Yes, logout',
		cancelButtonText: 'Cancel',
		reverseButtons: true
	}).then((result) => {
		if (result.isConfirmed) {
			// Optionally show a small loading toast while submitting
			Swal.fire({
				title: 'Logging out...',
				allowOutsideClick: false,
				allowEscapeKey: false,
				showConfirmButton: false,
				didOpen: () => {
					Swal.showLoading();
					// submit the existing logout form
					const form = document.getElementById('logoutForm');
					if (form) {
						form.submit();
					} else {
						// fallback: navigate to logout URL
						window.location.href = '../../employee/logout.php';
					}
				}
			});
		}
	});
}
	</script>
</body>
</html>