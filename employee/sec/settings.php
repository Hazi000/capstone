<?php
session_start();
require_once '../../config.php';
require_once '../../config/mail_config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Ensure two_factor_enabled exists (attempt to create it if missing)
function ensureTwoFactorColumnExists($conn) {
	// get current database name
	$dbRow = mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"));
	$dbName = $dbRow[0] ?? null;
	if (!$dbName) {
		error_log("ensureTwoFactorColumnExists: unable to determine database name");
		return false;
	}

	$checkSql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_enabled'";
	$stmt = @mysqli_prepare($conn, $checkSql);
	if (!$stmt) {
		error_log("ensureTwoFactorColumnExists: prepare failed: " . mysqli_error($conn));
		return false;
	}
	mysqli_stmt_bind_param($stmt, "s", $dbName);
	mysqli_stmt_execute($stmt);
	$res = mysqli_stmt_get_result($stmt);
	$row = mysqli_fetch_assoc($res);
	mysqli_stmt_close($stmt);

	if (!$row || (int)$row['cnt'] === 0) {
		// column missing — attempt to add it
		$alter = "ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0";
		if (mysqli_query($conn, $alter)) {
			error_log("ensureTwoFactorColumnExists: added column two_factor_enabled");
			return true;
		} else {
			error_log("ensureTwoFactorColumnExists: failed to add column: " . mysqli_error($conn));
			return false;
		}
	}

	return true;
}

// call helper before any code that references two_factor_enabled
ensureTwoFactorColumnExists($connection);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $message = '';
    $message_type = ''; // added to indicate success/error for client JS
    
    // Password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Prevent using the same value for current and new password
        if ($current_password === $new_password) {
            $message = "New password cannot be the same as the current password.";
            $message_type = 'error';
            // Skip further processing; flash + redirect handled later
        } else {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $update_query);
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = "Password updated successfully!";
                        $message_type = 'success';
                    } else {
                        $message = "Error updating password.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "New passwords do not match.";
                    $message_type = 'error';
                }
            } else {
                $message = "Current password is incorrect.";
                $message_type = 'error';
            }
        }
    }
    
    // Email change
    if (isset($_POST['change_email'])) {
        header('Content-Type: application/json');
        $new_email = trim(strtolower($_POST['new_email']));
        $user_id = $_SESSION['user_id'];

        // Validate email format
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            exit;
        }

        // Check if email was verified
        if (!isset($_SESSION['email_verification']) ||
            $_SESSION['email_verification']['email'] !== $new_email ||
            !isset($_SESSION['email_verification']['verified']) ||
            !$_SESSION['email_verification']['verified']) {
            echo json_encode(['status' => 'error', 'message' => 'Email must be verified first']);
            exit;
        }

        // Ensure the new email is not already used by another account
        $check_query = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($connection, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $new_email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);

        if ($existing && (int)$existing['id'] !== (int)$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Email is already in use by another account']);
            exit;
        }

        $update_query = "UPDATE users SET email = ? WHERE id = ?";
        $stmt = mysqli_prepare($connection, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $new_email, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['email'] = $new_email;
            unset($_SESSION['email_verification']); // Clear verification data
            echo json_encode(['status' => 'success', 'message' => 'Email updated successfully!', 'reload' => true]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating email']);
        }
        exit;
    }
    
    // Name change
    if (isset($_POST['change_name'])) {
        header('Content-Type: application/json');
        $first_name = trim($_POST['first_name']);
        $middle_initial = trim($_POST['middle_initial']);
        $last_name = trim($_POST['last_name']);
        $current_password = $_POST['current_password'];
        
        // First verify password
        $verify_query = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $verify_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($current_password, $user['password'])) {
            // prepare values for DB and display
            $mi_db = $middle_initial === '' ? '' : strtoupper(substr($middle_initial, 0, 1)); // single char for DB
            $mi_display = $mi_db !== '' ? $mi_db . '.' : ''; // display with dot
            $full_name = trim(ucwords(strtolower($first_name)) . ' ' . ($mi_display ? $mi_display . ' ' : '') . ucwords(strtolower($last_name)));
            
            $update_query = "UPDATE users SET first_name = ?, middle_initial = ?, last_name = ?, full_name = ? WHERE id = ?";
            $stmt = mysqli_prepare($connection, $update_query);
            mysqli_stmt_bind_param($stmt, "ssssi", $first_name, $mi_db, $last_name, $full_name, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $full_name;
                echo json_encode(['status' => 'success', 'message' => 'Name updated successfully!', 'full_name' => $full_name]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error updating name']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
        }
        exit;
    }

    // If a password change was attempted, store flash message and redirect to avoid form resubmission
    if (isset($_POST['change_password'])) {
        $_SESSION['flash'] = [
            'text' => $message,
            'type' => $message_type ?: (strpos($message, 'successfully') !== false ? 'success' : 'error')
        ];
        header("Location: settings.php");
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'send_verification') {
    header('Content-Type: application/json');
    $email = trim(strtolower($_POST['email']));
    $user_id = $_SESSION['user_id'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        exit;
    }

    // Check if email already exists for another user
    $check_query = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($connection, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);

    if ($existing) {
        if ((int)$existing['id'] === (int)$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'This is already your current email address']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Email is already in use']);
        }
        exit;
    }

    $verification_code = mt_rand(100000, 999999);

    $_SESSION['email_verification'] = [
        'code' => $verification_code,
        'email' => $email,
        'expires' => time() + 600 // 10 minutes
    ];

    $subject = "Email Verification Code for Barangay Management System - Change Email Request";

    // Long descriptive message with highlighted code (plain + simple HTML emphasis)
    $message = "Hello " . ($_SESSION['full_name'] ?? 'User') . ",\n\n"
        . "You recently requested to change the email address associated with your Barangay Management System account to:\n\n"
        . "    " . $email . "\n\n"
        . "To confirm this change and protect your account, please enter the verification code shown below on the Settings page. "
        . "This code authorizes the system to replace your existing account email with the new address above. "
        . "For your security, the code expires in 10 minutes and may only be used once.\n\n"
        . "========================================\n"
        . "          VERIFICATION CODE\n"
        . "========================================\n"
        . "               >>  " . $verification_code . "  <<\n"
        . "========================================\n\n"
        . "Important notes:\n"
        . "- Do NOT share this code with anyone. Treat it like a password.\n"
        . "- If you did NOT request this change, please ignore this email and contact your system administrator immediately.\n"
        . "- This code can only be used to change your account email to the address shown at the top of this message.\n\n"
        . "If you have questions or need assistance, reply to this email or contact support.\n\n"
        . "Regards,\nBarangay Management System Team\n";

    // Additionally include a simple HTML snippet (many mailers accept HTML bodies)
    $message_html = "<html><body>"
        . "<p>Hello " . htmlspecialchars($_SESSION['full_name'] ?? 'User') . ",</p>"
        . "<p>You requested to change your account email to <strong>" . htmlspecialchars($email) . "</strong>.</p>"
        . "<p style='margin-top:12px;'>To confirm this change, enter the verification code below on the Settings page. This code is valid for <strong>10 minutes</strong> and may only be used once.</p>"
        . "<div style='margin:18px 0; padding:12px; background:#f6f8fa; border-radius:6px; display:inline-block;'>"
        . "<span style='font-size:18px; font-weight:700; letter-spacing:2px; color:#2c3e50;'>"
        . htmlspecialchars($verification_code)
        . "</span>"
        . "</div>"
        . "<p><em>Important:</em></p>"
        . "<ul>"
        . "<li>Do NOT share this code with anyone.</li>"
        . "<li>If you did not request this change, ignore this message and contact support.</li>"
        . "<li>The code will be used only to update your account email to the address shown above.</li>"
        . "</ul>"
        . "<p>Regards,<br>Barangay Management System Team</p>"
        . "</body></html>";

    // Prefer HTML message if the mailer supports it, otherwise fallback to plain text.
    // Many sendEmail implementations accept HTML; pass the HTML string.
    $send_body = $message_html . "\n\n" . $message;

    if (sendEmail($email, $subject, $send_body)) {
        echo json_encode(['status' => 'success', 'message' => 'Verification code sent']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send verification code']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    header('Content-Type: application/json');
    $code = (string)$_POST['code']; // Convert to string for proper comparison
    
    if (isset($_SESSION['email_verification']) &&
        (string)$_SESSION['email_verification']['code'] === $code && // Convert stored code to string
        time() <= $_SESSION['email_verification']['expires']) {
        $_SESSION['email_verification']['verified'] = true;
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
    }
    exit;
}

// NEW: Toggle Two-Factor Authentication (requires current password)
if (isset($_POST['action']) && $_POST['action'] === 'toggle_2fa') {
    header('Content-Type: application/json');
    $enable = (isset($_POST['enable']) && $_POST['enable'] === '1') ? 1 : 0;
    $current_password = $_POST['current_password'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Fetch user's hashed password
    $pw_query = "SELECT password FROM users WHERE id = ? LIMIT 1";
    $stmt_pw = @mysqli_prepare($connection, $pw_query);
    if (!$stmt_pw) {
        error_log("Password query prepare error: " . mysqli_error($connection));
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        exit;
    }
    mysqli_stmt_bind_param($stmt_pw, "i", $user_id);
    mysqli_stmt_execute($stmt_pw);
    $res_pw = mysqli_stmt_get_result($stmt_pw);
    $user_pw_row = mysqli_fetch_assoc($res_pw);
    mysqli_stmt_close($stmt_pw);

    if (!$user_pw_row || !password_verify($current_password, $user_pw_row['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit;
    }

    // Update two_factor_enabled flag
    $update_tf = "UPDATE users SET two_factor_enabled = ? WHERE id = ?";
    $stmt_update = @mysqli_prepare($connection, $update_tf);
    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, "ii", $enable, $user_id);
        if (mysqli_stmt_execute($stmt_update)) {
            $_SESSION['two_factor_enabled'] = $enable;
            $msg = $enable ? 'Two-factor authentication has been enabled.' : 'Two-factor authentication has been disabled.';
            echo json_encode(['status' => 'success', 'message' => $msg, 'enabled' => (bool)$enable]);
        } else {
            error_log("Failed to execute two-factor update: " . mysqli_error($connection));
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update two-factor setting: ' . mysqli_error($connection)
            ]);
        }
        mysqli_stmt_close($stmt_update);
    } else {
        error_log("Failed to prepare two-factor update: " . mysqli_error($connection));
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error while updating setting: ' . mysqli_error($connection)
        ]);
    }
    exit;
}

// At the start, after session checks, add this to fetch current user details
$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_initial, last_name FROM users WHERE id = ?";
$user_data = [];
$stmt_user = mysqli_prepare($connection, $query);
if ($stmt_user) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    if (mysqli_stmt_execute($stmt_user)) {
        $result = mysqli_stmt_get_result($stmt_user);
        if ($result) {
            $user_data = mysqli_fetch_assoc($result) ?: [];
        }
    }
    mysqli_stmt_close($stmt_user);
} else {
    error_log("Failed to prepare user query: " . mysqli_error($connection));
}

// At the start, after fetching $user_data, fetch user sessions for privacy management
$user_sessions = [];
$current_session_id = session_id();
$sessions_error = false;
$sessions_fallback = false;

$sessions_query = "SELECT id, ip_address, user_agent, last_active, created_at, session_id FROM user_sessions WHERE user_id = ? ORDER BY last_active DESC";

// suppress warnings from mysqli_prepare with @ and handle failures explicitly
$stmt_sessions = @mysqli_prepare($connection, $sessions_query);
if ($stmt_sessions) {
    mysqli_stmt_bind_param($stmt_sessions, "i", $user_id);
    if (mysqli_stmt_execute($stmt_sessions)) {
        $sessions_result = mysqli_stmt_get_result($stmt_sessions);
        if ($sessions_result) {
            while ($row = mysqli_fetch_assoc($sessions_result)) {
                $user_sessions[] = $row;
            }
        } else {
            error_log("Failed to get result for sessions query: " . mysqli_error($connection));
            $sessions_error = true;
        }
    } else {
        error_log("Failed to execute sessions query: " . mysqli_error($connection));
        $sessions_error = true;
    }
    mysqli_stmt_close($stmt_sessions);
} else {
    error_log("Failed to prepare sessions query: " . mysqli_error($connection));
    $sessions_error = true;
}

// If DB failed, provide a non-fatal fallback showing the current session only
if ($sessions_error || empty($user_sessions)) {
    $sessions_fallback = true;
    $user_sessions = [
        [
            'id' => null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'last_active' => date('Y-m-d H:i:s', $_SESSION['last_active'] ?? time()),
            'created_at' => date('Y-m-d H:i:s', $_SESSION['created_at'] ?? time()),
            'session_id' => $current_session_id
        ]
    ];
    // Clear the error flag so UI won't show the blocking error banner; show a small fallback notice instead.
    $sessions_error = false;
}

// After fetching $user_data and sessions, fetch two_factor_enabled for UI
$two_factor_enabled = 0;
$tf_query = "SELECT two_factor_enabled FROM users WHERE id = ? LIMIT 1";
$stmt_tf = @mysqli_prepare($connection, $tf_query);
if ($stmt_tf) {
    mysqli_stmt_bind_param($stmt_tf, "i", $user_id);
    if (mysqli_stmt_execute($stmt_tf)) {
        $res_tf = mysqli_stmt_get_result($stmt_tf);
        if ($res_tf) {
            $row_tf = mysqli_fetch_assoc($res_tf);
            if ($row_tf !== null && isset($row_tf['two_factor_enabled'])) {
                $two_factor_enabled = (int)$row_tf['two_factor_enabled'];
            }
        }
    }
    mysqli_stmt_close($stmt_tf);
} else {
    error_log("Failed to prepare two-factor query: " . mysqli_error($connection));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Color Variables */
        :root {
            --primary-blue: #3498db;
            --primary-dark: #2980b9;
            --secondary-blue: #EBF5FB;
            --text-dark: #2c3e50;
            --text-medium: #34495e;
            --text-light: #7f8c8d;
            --text-muted: #95a5a6;
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --border-color: #e9ecef;
            --bg-light: #f8f9fa;
        }

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

        .nav-badge.orange {
            background: #f39c12;
        }

        .nav-badge.green {
            background: #27ae60;
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

        .logout-btn:active {
            transform: translateY(0);
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

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
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

        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .left-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .recent-activities {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .recent-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .recent-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .recent-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .recent-list {
            padding: 1rem;
        }

        .recent-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .recent-item:hover {
            background: #f8f9fa;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-info h4 {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .recent-info p {
            font-size: 0.8rem;
            color: #666;
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

        .status-resolved {
            background: #d4edda;
            color: #155724;
        }

        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
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

        /* Calendar Styles */
        .calendar-widget {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .calendar-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            text-align: center;
        }

        .calendar-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .calendar-nav button {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .calendar-nav button:hover {
            background: rgba(255,255,255,0.3);
        }

        .calendar-grid {
            padding: 0.5rem;
        }

        .calendar-days-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 2px;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 0.5rem;
            text-align: center;
            font-weight: bold;
            font-size: 0.85rem;
            color: #333;
            border: 1px solid #e0e0e0;
        }

        .calendar-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .calendar-day {
            background: white;
            border: 1px solid #e0e0e0;
            min-height: 80px;
            padding: 0.5rem;
            position: relative;
            transition: background 0.2s ease;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .calendar-day.other-month {
            background: #fafafa;
        }

        .calendar-day.other-month .calendar-day-number {
            color: #ccc;
        }

        .calendar-day-number {
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
        }

        .calendar-event {
            font-size: 0.7rem;
            color: #666;
            margin-bottom: 2px;
            line-height: 1.2;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .calendar-event:hover {
            overflow: visible;
            white-space: normal;
            z-index: 10;
            background: #fff;
            padding: 2px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .upcoming-events {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-top: 1rem;
        }

        .upcoming-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .upcoming-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upcoming-list {
    max-height: 300px; /* Reduced from 400px */
    overflow-y: auto;
}

        .upcoming-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .upcoming-item:last-child {
            border-bottom: none;
        }

        .upcoming-date {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 8px;
            text-align: center;
            min-width: 60px;
        }

        .upcoming-date-day {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .upcoming-date-month {
            font-size: 0.7rem;
            color: #666;
        }

        .upcoming-info {
            flex: 1;
        }

        .upcoming-info h4 {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .upcoming-info p {
            font-size: 0.8rem;
            color: #666;
        }

        .upcoming-type {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .upcoming-type.appointment {
            background: #e3f2fd;
            color: #1976d2;
        }

        .upcoming-type.announcement {
            background: #fff3e0;
            color: #f57c00;
        }

        /* Mobile Responsiveness */
        @media (max-width: 1200px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .recent-activities {
                grid-template-columns: 1fr;
            }
        }

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

            .top-bar {
                padding: 1rem;
            }

            .calendar-day {
                min-height: 50px;
                padding: 0.25rem;
            }

            .upcoming-item {
                padding: 0.75rem 1rem;
            }
         @media (max-width: 768px) {
    .calendar-day {
        min-height: 60px;
        padding: 2px;
    }
    
    .calendar-day-number {
        font-size: 0.8rem;
    }
    
    .calendar-event {
        font-size: 0.6rem;
    }
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

        /* Enhanced Settings Layout */
        .settings-layout {
            display: flex;
            gap: 2rem;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: linear-gradient(145deg, #f8f9fa, #ffffff);
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .settings-sidebar {
            width: 300px;
            flex-shrink: 0;
        }

        .settings-nav {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .settings-nav-title {
            padding: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 1px solid #eef2f7;
            background: linear-gradient(to right, #f8f9fa, white);
        }

        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: 1.2rem 1.5rem;
            color: #6c757d;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .settings-nav-item:hover {
            background: #f8f9fa;
            color: #3498db;
            transform: translateX(5px);
        }

        .settings-nav-item.active {
            background: #EBF5FB;
            color: #3498db;
            border-left-color: #3498db;
        }

        .settings-content {
            flex: 1;
            min-width: 0;
        }

        .section-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .settings-form {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            border: 1px solid rgba(52, 152, 219, 0.1);
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-light);
            color: var(--text-primary);
            height: 3.2rem; /* Fixed height for consistency */
        }

        .form-control:focus {
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        /* Button Styling */
        .form-actions {
            margin-top: 2.5rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }

        .btn-submit {
            background: linear-gradient(145deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            min-width: 180px;
            justify-content: center;
        }

        .btn-submit i {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.2);
        }

        .btn-submit:hover i {
            transform: translateX(3px);
        }

        /* Form Section Spacing */
        .settings-form h3 {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--secondary-color);
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .settings-form h3 i {
            color: var(--primary-color);
            font-size: 1.3rem;
        }

        /* Management Section Visibility */
        .management-section {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .management-section.active {
            display: block;
            opacity: 1;
        }

        /* Navigation Improvements */
        .settings-nav-item {
            color: var(--text-light);
            font-weight: 500;
        }

        .settings-nav-item.active {
            color: var(--primary-blue);
            background: var(--secondary-blue);
        }

        .settings-nav-item:hover {
            color: var(--primary-blue);
        }

        /* Button Enhancements */
        .btn-submit {
            background: linear-gradient(145deg, var(--primary-blue), var(--primary-dark));
            color: white;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* Alert Styling */
        .alert-success {
            background: linear-gradient(145deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(145deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .strength-bars {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .bar {
            height: 5px;
            flex: 1;
            background: #ddd;
            transition: background-color 0.3s;
        }
        
        .bar.weak { background-color: #e74c3c; }
        .bar.medium { background-color: #f1c40f; }
        .bar.strong { background-color: #2ecc71; }
        
        #password-strength-text {
            font-size: 0.85rem;
            color: #666;
        }
        
        #password-requirements {
            font-size: 0.85rem;
            margin-top: 8px;
        }
        
        #password-requirements i.fa-check {
            color: #2ecc71;
        }
        
        #password-requirements i.fa-times {
            color: #e74c3c;
        }

        /* Two-factor switch (light) */
        .switch {
            --w: 56px;
            --h: 30px;
            position: relative;
            width: var(--w);
            height: var(--h);
            background: #e6e6e6;
            border-radius: calc(var(--h) / 2);
            display: inline-block;
            vertical-align: middle;
            cursor: pointer;
            transition: background 180ms ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }
        .switch.on {
            background: linear-gradient(90deg,#4cd964,#30d158);
        }
        .switch .knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: calc(var(--h) - 6px);
            height: calc(var(--h) - 6px);
            background: white;
            border-radius: 50%;
            transition: transform 180ms ease, box-shadow 120ms;
            box-shadow: 0 2px 4px rgba(0,0,0,0.12);
        }
        .switch.on .knob {
            transform: translateX(calc(var(--w) - var(--h)));
            box-shadow: 0 6px 10px rgba(0,0,0,0.12);
        }
        .switch-label {
            margin-left: 12px;
            font-weight: 600;
            color: #333;
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
                <a href="resident_family.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Resident Family
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
                <a href="complaints.php" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    Complaints

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
                <a href="settings.php" class="nav-item active">
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
            <h1 class="page-title">Settings</h1>
        </div>

        <div class="settings-layout">
            <!-- Settings Navigation -->
            <div class="settings-sidebar">
                <div class="settings-nav">
                    <div class="settings-nav-title">Settings</div>
                    <a href="#account" class="settings-nav-item active" onclick="showSection('account', 'account-management')">
                        <i class="fas fa-user-circle"></i>
                        Account Management
                    </a>
                    <a href="#notifications" class="settings-nav-item" onclick="showSection('notifications', 'notification-management')">
                        <i class="fas fa-bell"></i>
                        Notification Management
                    </a>
                    <a href="#privacy" class="settings-nav-item" onclick="showSection('privacy', 'privacy-management')">
                        <i class="fas fa-lock"></i>
                        Privacy Management
                    </a>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <?php if (isset($_SESSION['flash'])):
                    $flash = $_SESSION['flash'];
                    unset($_SESSION['flash']);
                ?>
                    <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?>">
                        <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($flash['text']); ?>
                    </div>
                    <script>
                        // expose server flash for client-side SweetAlert (after redirect)
                        window.serverMessage = <?php echo json_encode(['text' => $flash['text'], 'type' => $flash['type']]); ?>;
                    </script>
                <?php endif; ?>

                <!-- Management Sections (siblings) -->
                <div id="account-management" class="management-section active">
                    <div id="account" class="settings-section active">
                        <div class="section-header">
                            <h2 class="section-title">Account Settings</h2>
                            <p class="section-description">Manage your account information</p>
                        </div>

                        <!-- Email Form -->
                        <form method="POST" action="" id="emailForm" class="settings-form">
                            <h3><i class="fas fa-envelope"></i> Email Address</h3>
                            <div class="form-group">
                                <label for="new_email">New Email</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="email" id="new_email" name="new_email" class="form-control" 
                                           value="<?php echo $_SESSION['email'] ?? ''; ?>" required 
                                           oninput="handleEmailChange(this.value, '<?php echo $_SESSION['email'] ?? ''; ?>')">
                                    <button type="button" id="verifyBtn" class="btn-submit" 
                                            style="min-width: auto; padding: 0 1.5rem; display: none;" 
                                            onclick="verifyEmail()">
                                        <i class="fas fa-check-circle"></i> Verify
                                    </button>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="change_email" class="btn-submit" id="updateEmailBtn" disabled>
                                    <i class="fas fa-paper-plane"></i>
                                    Update Email
                                </button>
                            </div>
                        </form>

                        <!-- Name Form -->
                        <form method="POST" action="" id="nameForm" class="settings-form" enctype="multipart/form-data">
                            <h3><i class="fas fa-user"></i> Full Name</h3>
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="middle_initial">Middle Initial</label>
                                <input type="text" id="middle_initial" name="middle_initial" class="form-control" maxlength="1"
                                       value="<?php echo htmlspecialchars($user_data['middle_initial'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="change_name" class="btn-submit">Update Name</button>
                            </div>
                        </form>

                        <!-- Password Form -->
                        <form method="POST" action="" id="passwordForm" class="settings-form">
                            <input type="hidden" name="change_password" value="1">
                            <h3><i class="fas fa-key"></i> Change Password</h3>
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required 
                                       oninput="checkPasswordStrength(this.value)">
                                <div id="password-strength-meter" class="mt-2">
                                    <div class="strength-bars">
                                        <div class="bar"></div>
                                        <div class="bar"></div>
                                        <div class="bar"></div>
                                        <div class="bar"></div>
                                    </div>
                                    <div id="password-strength-text" class="mt-1">Password Strength: Too Weak</div>
                                    <div id="password-requirements" class="mt-1 text-muted">
                                        <small>
                                            <i class="fas fa-times text-danger" id="req-length"></i> At least 8 characters<br>
                                            <i class="fas fa-times text-danger" id="req-capital"></i> At least one capital letter<br>
                                            <i class="fas fa-times text-danger" id="req-number"></i> At least one number<br>
                                            <i class="fas fa-times text-danger" id="req-special"></i> At least one special character
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-submit">Update Password</button>
                            </div>
                        </form>

                        <div style="margin-top:1rem; border-top:1px solid #eef2f7; padding-top:1rem;">
                            <h3 style="margin-bottom:8px;"><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</h3>
                            <p style="color:#666; margin-bottom:12px;">Add an extra layer of security to your account. Turning it on/off requires confirmation with your current password.</p>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div id="twoFactorSwitchAccount"
                                     class="switch <?php echo $two_factor_enabled ? 'on' : ''; ?>"
                                     role="switch"
                                     aria-checked="<?php echo $two_factor_enabled ? 'true' : 'false'; ?>"
                                     data-enabled="<?php echo $two_factor_enabled ? '1' : '0'; ?>">
                                    <div class="knob"></div>
                                </div>
                                <span id="twoFactorLabelAccount" class="switch-label"><?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?></span>
                            </div>
                        </div>

                    </div> <!-- end #account -->
                </div> <!-- end #account-management -->

                <!-- Notifications (placeholder sibling) -->
                <div id="notification-management" class="management-section">
                    <!-- Add notification settings here if needed -->
                </div>

                <!-- Privacy Management (now a sibling of account-management) -->
                <div id="privacy-management" class="management-section">
                    <div id="privacy" class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">Privacy Management</h2>
                            <p class="section-description">See which devices are logged in to your account. If you see unfamiliar devices, your account may be compromised.</p>
                        </div>
                        <div class="settings-form">
                            <h3><i class="fas fa-shield-alt"></i> Active Devices & Sessions</h3>

                            <?php if ($sessions_error && empty($user_sessions)): ?>
                                <div style="padding:12px; border-radius:8px; background:#fff3cd; color:#856404;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Unable to load active sessions right now. Please try again later.
                                </div>
                            <?php else: ?>
                                <?php if ($sessions_fallback): ?>
                                    <div style="padding:10px; border-radius:6px; background:#e9f7ef; color:#155724; margin-bottom:10px;">
                                        <i class="fas fa-info-circle"></i>
                                        Showing current session only (unable to load full session list).
                                    </div>
                                <?php endif; ?>

                                <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f8f9fa;">
                                            <th style="padding:8px; text-align:left;">Device / Browser</th>
                                            <th style="padding:8px; text-align:left;">IP Address</th>
                                            <th style="padding:8px; text-align:left;">Last Active</th>
                                            <th style="padding:8px; text-align:left;">Logged In</th>
                                            <th style="padding:8px; text-align:left;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_sessions as $session): ?>
                                            <tr style="border-bottom:1px solid #eee;<?php if ($session['session_id'] === $current_session_id) echo 'background:#e3f2fd;'; ?>">
                                                <td style="padding:8px;">
                                                    <?php
                                                        $ua = htmlspecialchars($session['user_agent']);
                                                        echo strlen($ua) > 60 ? substr($ua, 0, 57) . '...' : $ua;
                                                    ?>
                                                </td>
                                                <td style="padding:8px;"><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                                <td style="padding:8px;"><?php echo htmlspecialchars($session['last_active']); ?></td>
                                                <td style="padding:8px;"><?php echo htmlspecialchars($session['created_at']); ?></td>
                                                <td style="padding:8px;">
                                                    <?php if ($session['session_id'] === $current_session_id): ?>
                                                        <span class="status-badge status-approved">This Device</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-pending">Other Device</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>

                                <div style="margin-top:1rem; color:#888; font-size:0.95rem;">
                                    <i class="fas fa-info-circle"></i>
                                    If you see devices or locations you don't recognize, change your password immediately.
                                </div>
                            <?php endif; ?>

                            <div style="margin-top:1.25rem; border-top:1px solid #eef2f7; padding-top:1rem;">
                                <h3 style="margin-bottom:8px;"><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</h3>
                                <p style="color:#666; margin-bottom:12px;">Add an extra layer of security to your account. Turning it on/off requires confirmation with your current password.</p>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div id="twoFactorSwitchPrivacy"
                                         class="switch <?php echo $two_factor_enabled ? 'on' : ''; ?>"
                                         role="switch"
                                         aria-checked="<?php echo $two_factor_enabled ? 'true' : 'false'; ?>"
                                         data-enabled="<?php echo $two_factor_enabled ? '1' : '0'; ?>">
                                        <div class="knob"></div>
                                    </div>
                                    <span id="twoFactorLabelPrivacy" class="switch-label"><?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?></span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- At this point, no standalone Two-Factor block is needed -->
            </div>
        </div>
    </div>

    <script>
        // Calendar functionality
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        // Events data from PHP
        const eventsData = <?php echo json_encode($events_data ?? []); ?>;

        function renderCalendar() {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            
            document.getElementById('currentMonth').textContent = `${monthNames[currentMonth]} ${currentYear}`;
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            const daysInPrevMonth = new Date(currentYear, currentMonth, 0).getDate();
            
            let calendarHTML = '';
            
            // Previous month's trailing days
            for (let i = firstDay - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                calendarHTML += `<div class="calendar-day other-month">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
            }
            
            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(currentYear, currentMonth, day);
                const dateString = date.toISOString().split('T')[0];
                const isToday = date.toDateString() === new Date().toDateString();
                
                // Get events for this day
                const dayEvents = eventsData.filter(event => event.event_date === dateString);
                
                let eventsHTML = '';
                dayEvents.slice(0, 2).forEach(event => { // Limit to 2 events per day
                    eventsHTML += `<div class="calendar-event" title="${event.title}">${event.title}</div>`;
                });
                
                if (dayEvents.length > 2) {
                    eventsHTML += `<div class="calendar-event" style="font-style: italic; color: #999;">+${dayEvents.length - 2} more</div>`;
                }
                
                calendarHTML += `<div class="calendar-day ${isToday ? 'today' : ''}">
                    <div class="calendar-day-number">${day}</div>
                    ${eventsHTML}
                </div>`;
            }
            
            // Next month's leading days
            const totalCells = firstDay + daysInMonth;
            const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
            for (let day = 1; day <= remainingCells; day++) {
                calendarHTML += `<div class="calendar-day other-month">
                    <div class="calendar-day-number">${day}</div>
                </div>`;
            }
            
            document.getElementById('calendarDays').innerHTML = calendarHTML;
        }

        function previousMonth() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar();
        }

        function nextMonth() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
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

        // Initialize calendar on page load
        document.addEventListener('DOMContentLoaded', function() {
            renderCalendar();
            
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href === currentPage) {
                    item.classList.add('active');
                }
            });
        });

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            toggleSidebar();
        });

        function checkPasswordStrength(password) {
    const strengthMeter = document.getElementById('password-strength-meter');
    const strengthText = document.getElementById('password-strength-text');
    const bars = strengthMeter.getElementsByClassName('bar');
    
    // Requirements check
    const hasLength = password.length >= 8;
    const hasCapital = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    // Update requirement indicators
    updateRequirement('req-length', hasLength);
    updateRequirement('req-capital', hasCapital);
    updateRequirement('req-number', hasNumber);
    updateRequirement('req-special', hasSpecial);
    
    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasCapital) strength++;
    if (hasNumber) strength++;
    if (hasSpecial) strength++;
    
    // Reset all bars
    Array.from(bars).forEach(bar => {
        bar.className = 'bar';
    });
    
    // Update strength meter visuals and label
    let strengthClass = '';
    let strengthLabel = '';
    
    switch(strength) {
        case 0:
            strengthLabel = 'Too Weak';
            break;
        case 1:
            strengthClass = 'weak';
            strengthLabel = 'Weak';
            Array.from(bars).slice(0,1).forEach(bar => bar.classList.add(strengthClass));
            break;
        case 2:
            strengthClass = 'medium';
            strengthLabel = 'Medium';
            Array.from(bars).slice(0,2).forEach(bar => bar.classList.add(strengthClass));
            break;
        case 3:
            strengthClass = 'strong';
            strengthLabel = 'Strong';
            Array.from(bars).slice(0,3).forEach(bar => bar.classList.add(strengthClass));
            break;
        case 4:
            strengthClass = 'strong';
            strengthLabel = 'Very Strong';
            Array.from(bars).forEach(bar => bar.classList.add(strengthClass));
            break;
    }
    
    strengthText.textContent = `Password Strength: ${strengthLabel}`;
    // Return numeric strength (0..4)
    return strength;
}

function updateRequirement(elementId, isValid) {
    const element = document.getElementById(elementId);
    if (isValid) {
        element.className = 'fas fa-check text-success';
    } else {
        element.className = 'fas fa-times text-danger';
    }
}

// Remove old synchronous validatePasswordForm and replace with async handler
document.getElementById('passwordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Prevent same current and new password (client-side)
    if (currentPassword === newPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Password',
            text: 'New password cannot be the same as your current password.',
            timer: 2500,
            showConfirmButton: false
        });
        return;
    }

    // Enforce minimum length
    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Password Too Short',
            text: 'Password must be at least 8 characters long and cannot be updated otherwise.',
            timer: 2500,
            showConfirmButton: false
        });
        return;
    }
    
    // Get numeric strength
    const strength = checkPasswordStrength(newPassword);
    
    // If password is Weak or Medium, warn the user and ask confirmation
    if (strength === 1 || strength === 2) {
        const result = await Swal.fire({
            title: 'Weak Password',
            text: 'The password you entered is not very strong. It is recommended to use a stronger password with uppercase letters, numbers, and special characters. Do you want to proceed anyway?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Proceed',
            cancelButtonText: 'Cancel'
        });
        if (!result.isConfirmed) {
            return; // user chose to keep editing
        }
    }
    
    // Confirm passwords match
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Password Mismatch',
            text: 'New password and confirmation password do not match!',
            timer: 2500,
            showConfirmButton: false
        });
        return;
    }
    
    // Show processing SweetAlert then submit
    Swal.fire({
        title: 'Processing',
        html: 'Updating your password...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // small delay so user sees the processing modal
    setTimeout(() => {
        form.submit();
    }, 700);
});

// On page load, if server provided a message, show it with SweetAlert and hide the inline alert
document.addEventListener('DOMContentLoaded', function() {
    // ...existing DOMContentLoaded code...
    
    if (window.serverMessage && window.serverMessage.text) {
        const icon = window.serverMessage.type === 'success' ? 'success' : 'error';
        Swal.fire({
            icon: icon,
            title: (icon === 'success' ? 'Success' : 'Error'),
            text: window.serverMessage.text,
            timer: 2200,
            showConfirmButton: false
        });
        // hide the inline alert div if present
        const inlineAlert = document.querySelector('.alert');
        if (inlineAlert) inlineAlert.style.display = 'none';
    }
});

        function validateEmailForm() {
            const email = document.getElementById('new_email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address!'
                });
                return false;
            }
            return true;
        }

        function validateNameForm() {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            
            if (firstName.length < 2) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid First Name',
                    text: 'First name must be at least 2 characters long!'
                });
                return false;
            }
            
            if (lastName.length < 2) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Last Name',
                    text: 'Last name must be at least 2 characters long!'
                });
                return false;
            }
            
            return true;
        }

        function showSection(sectionId, managementId) {
            // Hide all sections with fade
            document.querySelectorAll('.management-section').forEach(section => {
                section.classList.remove('active');
                section.style.opacity = '0';
            });

            // Show selected section with fade
            const selectedSection = document.getElementById(managementId);
            selectedSection.classList.add('active');
            setTimeout(() => {
                selectedSection.style.opacity = '1';
            }, 50);

            // Update navigation
            document.querySelectorAll('.settings-nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[href="#${sectionId}"]`).classList.add('active');
        }

        // Initialize settings page
        document.addEventListener('DOMContentLoaded', function() {
            // Get section from URL hash or default to account
            const section = window.location.hash.substring(1) || 'account';
            const management = section + '-management';
            showSection(section, management);
        });

        // Add these new functions
        function handleEmailChange(newEmail, originalEmail) {
    const verifyBtn = document.getElementById('verifyBtn');
    const updateEmailBtn = document.getElementById('updateEmailBtn');
    
    if (newEmail !== originalEmail) {
        verifyBtn.style.display = 'block';
        updateEmailBtn.disabled = true;
    } else {
        verifyBtn.style.display = 'none';
        updateEmailBtn.disabled = false;
    }
}

function verifyEmail() {
    const email = document.getElementById('new_email').value;
    const verifyBtn = document.getElementById('verifyBtn');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Client-side format check before sending request
    if (!emailRegex.test(email)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Please enter a valid email address before requesting verification.'
        });
        return;
    }

    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch('settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=send_verification&email=${encodeURIComponent(email)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            verifyBtn.innerHTML = '<i class="fas fa-check"></i> Code Sent';
            handleVerificationCode(email);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify';
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Failed to send verification code'
        });
    });
}

function handleVerificationCode(email) {
    Swal.fire({
        title: 'Enter Verification Code',
        text: 'A verification code has been sent to your email',
        input: 'text',
        inputAttributes: {
            autocapitalize: 'off',
            maxlength: 6,
            placeholder: 'Enter 6-digit code'
        },
        showCancelButton: true,
        confirmButtonText: 'Verify',
        showLoaderOnConfirm: true,
        preConfirm: (code) => {
            if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
                Swal.showValidationMessage('Please enter a valid 6-digit code');
                return false;
            }
            
            return fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=verify_code&code=${encodeURIComponent(code)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(result => {
                if (result.status === 'error') {
                    throw new Error(result.message || 'Invalid code');
                }
                return result;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
                return false;
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('updateEmailBtn').disabled = false;
            document.getElementById('verifyBtn').style.display = 'none';
            Swal.fire({
                icon: 'success',
                title: 'Email Verified!',
                text: 'You can now update your email address'
            });
        } else {
            document.getElementById('verifyBtn').disabled = false;
            document.getElementById('verifyBtn').innerHTML = '<i class="fas fa-check-circle"></i> Verify';
        }
    });
}

// Update the email form submission
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('change_email', '1');
    
    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                if (data.reload) {
                    window.location.reload();
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong while updating email!'
        });
    });
});

document.getElementById('nameForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!validateNameForm()) {
        return;
    }

    Swal.fire({
        title: 'Verify Your Identity',
        text: 'Please enter your current password to update your name',
        input: 'password',
        inputAttributes: {
            autocapitalize: 'off',
            required: true
        },
        showCancelButton: true,
        confirmButtonText: 'Update',
        showLoaderOnConfirm: true,
        preConfirm: (password) => {
            const formData = new FormData(this);
            formData.append('current_password', password);
            formData.append('change_name', '1');
            
            return fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') {
                    throw new Error(data.message)
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(error.message);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            // Update the displayed name immediately
            if (result.value.full_name) {
                document.querySelector('.user-name').textContent = result.value.full_name;
            }
            
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.value.message,
                showConfirmButton: false,
                timer: 1500
            }).then(() => {
                window.location.reload();
            });
        }
    });
});

// Replace the old Two-Factor "light switch" behavior block with the unified handler below
(function(){
	// Elements (may be missing on some layouts)
	const accSwitch = document.getElementById('twoFactorSwitchAccount');
	const accLabel  = document.getElementById('twoFactorLabelAccount');
	const privSwitch = document.getElementById('twoFactorSwitchPrivacy');
	const privLabel  = document.getElementById('twoFactorLabelPrivacy');

	function setVisual(enabled) {
		[[accSwitch, accLabel],[privSwitch, privLabel]].forEach(([sw,label])=>{
			if (!sw || !label) return;
			if (enabled) {
				sw.classList.add('on');
				sw.setAttribute('aria-checked','true');
				sw.dataset.enabled = '1';
				label.textContent = 'Enabled';
			} else {
				sw.classList.remove('on');
				sw.setAttribute('aria-checked','false');
				sw.dataset.enabled = '0';
				label.textContent = 'Disabled';
			}
		});
	}

	async function sendToggleRequest(targetEnable, password) {
		const body = `action=toggle_2fa&enable=${targetEnable}&current_password=${encodeURIComponent(password)}`;
		const res = await fetch('settings.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body
		});
		if (!res.ok) throw new Error('Network error');
		const json = await res.json();
		if (json.status === 'error') throw new Error(json.message || 'Failed to toggle two-factor');
		return json;
	}

	function disablePointers(state) {
		[accSwitch, privSwitch].forEach(s => { if (s) s.style.pointerEvents = state ? 'none' : ''; });
	}

	function attachToggle(sw) {
		if (!sw) return;
		sw.addEventListener('click', function () {
			const currentlyOn = sw.classList.contains('on');
			const targetEnable = currentlyOn ? 0 : 1;
			disablePointers(true);

			Swal.fire({
				title: targetEnable ? 'Turn ON Two-Factor Authentication' : 'Turn OFF Two-Factor Authentication',
				html: targetEnable
					? 'You are about to ENABLE Two-Factor Authentication. It will add an extra verification step to future sign-ins. Please confirm by entering your <strong>current password</strong>.'
					: 'You are about to DISABLE Two-Factor Authentication. Please confirm by entering your <strong>current password</strong>.',
				input: 'password',
				inputAttributes: { autocapitalize: 'off', autocorrect: 'off', placeholder: 'Enter current password' },
				showCancelButton: true,
				confirmButtonText: targetEnable ? 'Turn On' : 'Turn Off',
				showLoaderOnConfirm: true,
				preConfirm: (pwd) => {
					if (!pwd) {
						Swal.showValidationMessage('Please enter your current password');
						return false;
					}
					return sendToggleRequest(targetEnable, pwd).catch(err => {
						Swal.showValidationMessage(err.message || 'Request failed');
						return false;
					});
				},
				allowOutsideClick: () => !Swal.isLoading()
			}).then(result => {
				disablePointers(false);
				if (result.isConfirmed && result.value) {
					// server returned success with .enabled
					const enabled = !!result.value.enabled;
					setVisual(enabled);
					Swal.fire({ icon: 'success', title: 'Success', text: result.value.message, timer: 1500, showConfirmButton: false });
				} else {
					// cancelled or failed: keep previous state
					setVisual(!targetEnable);
				}
			}).catch(() => {
				disablePointers(false);
				setVisual(!targetEnable);
			});
		});
	}

	// Attach to both switches
	attachToggle(accSwitch);
	attachToggle(privSwitch);

	// Initialize visuals from server state
	setVisual(<?php echo (int)$two_factor_enabled ? 'true' : 'false'; ?>);
})();
    
    </script>
</body>
</html>