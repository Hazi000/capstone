<?php
session_start();
require_once '../config.php';
require_once '../config/mail_config.php'; // added to send 2FA emails

// Redirect logged-in users to their dashboard if accessing via GET and already authenticated
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    $redirect = 'captain/dashboard.php';
    if ($role === 'super_admin') $redirect = 'admin/dashboard.php';
    if ($role === 'secretary') $redirect = 'sec/dashboard.php';
    if ($role === 'treasurer') $redirect = 'treasurer/dashboard.php';
    header("Location: {$redirect}");
    exit;
}

$error_message = '';
$success_message = '';

// New AJAX endpoints for verifying / resending 2FA codes
if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
    header('Content-Type: application/json');
    $code = trim((string)($_POST['code'] ?? ''));
    if (!isset($_SESSION['twofa']) || !isset($_SESSION['pre_2fa'])) {
        echo json_encode(['status' => 'error', 'message' => 'No two-factor request found.']);
        exit;
    }
    $twofa = $_SESSION['twofa'];
    if (time() > ($twofa['expires'] ?? 0)) {
        echo json_encode(['status' => 'error', 'message' => 'Verification code expired. Please resend.']);
        exit;
    }
    if ((string)$twofa['code'] !== (string)$code) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid verification code.']);
        exit;
    }

    // Code valid — complete login
    $pre = $_SESSION['pre_2fa'];
    $_SESSION['user_id'] = $pre['id'];
    $_SESSION['full_name'] = $pre['full_name'];
    $_SESSION['email'] = $pre['email'];
    $_SESSION['role'] = $pre['role'];

    // Clear temp two-factor session entries
    unset($_SESSION['pre_2fa'], $_SESSION['twofa']);

    // Update last login
    $uid = (int)$_SESSION['user_id'];
    @mysqli_query($connection, "UPDATE users SET last_login = NOW() WHERE id = {$uid}");

    // Return redirect based on role
    $role = $_SESSION['role'] ?? '';
    $redirect = 'captain/dashboard.php';
    if ($role === 'super_admin') $redirect = 'admin/dashboard.php';
    if ($role === 'secretary') $redirect = 'sec/dashboard.php';
    if ($role === 'treasurer') $redirect = 'treasurer/dashboard.php';

    echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'resend_2fa') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['pre_2fa'])) {
        echo json_encode(['status' => 'error', 'message' => 'No two-factor session available. Please login again.']);
        exit;
    }

    // If twofa not present (expired or missing), create a new code
    if (!isset($_SESSION['twofa']) || !isset($_SESSION['twofa']['code'])) {
        $verification_code = mt_rand(100000, 999999);
        $_SESSION['twofa'] = [
            'code' => $verification_code,
            'expires' => time() + 600,
            'last_sent' => time()
        ];
    } else {
        // Use existing code and enforce cooldown
        $now = time();
        $last = (int)($_SESSION['twofa']['last_sent'] ?? 0);
        $cooldown = 60; // seconds
        $elapsed = $now - $last;
        if ($elapsed < $cooldown) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please wait before resending the verification code.',
                'retry_after' => $cooldown - $elapsed
            ]);
            exit;
        }
        $verification_code = $_SESSION['twofa']['code'];
        // update last_sent to now
        $_SESSION['twofa']['last_sent'] = $now;
    }

    $email = $_SESSION['pre_2fa']['email'];
    $subject = "Your Two-Factor Verification Code";
    $message_html = "<html><body>
        <p>Hello " . htmlspecialchars($_SESSION['pre_2fa']['full_name']) . ",</p>
        <p>You recently requested to sign in to the Barangay Management System. To complete sign-in, please enter the verification code below on the sign-in page. This code expires in 10 minutes.</p>
        <div style='margin:18px 0; padding:12px; background:#f6f8fa; border-radius:6px; display:inline-block;'>
            <span style='font-size:22px; font-weight:700; letter-spacing:2px; color:#2c3e50;'>"
            . htmlspecialchars($verification_code) .
        "</span>
        </div>
        <p>If you did not attempt to sign in, ignore this message and contact your administrator.</p>
        <p>Regards,<br>Barangay Management System Team</p>
        </body></html>";
    $send_body = $message_html;
    $sent = sendEmail($email, $subject, $send_body);
    if ($sent) {
        // include retry_after so client can start countdown immediately (60s)
        echo json_encode(['status' => 'success', 'message' => 'Verification code resent', 'retry_after' => 60]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send verification code']);
    }
    exit;
}

// Handle Sign Up
if (isset($_POST['signup'])) {
    $full_name = mysqli_real_escape_string($connection, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($connection, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = mysqli_real_escape_string($connection, $_POST['role']);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error_message'] = "All fields are required.";
        header("Location: index.php"); exit();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Please enter a valid email address.";
        header("Location: index.php"); exit();
    } elseif (strlen($password) < 6) {
        $_SESSION['error_message'] = "Password must be at least 6 characters long.";
        header("Location: index.php"); exit();
    } elseif ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: index.php"); exit();
    } else {
        // Check if email already exists
        $check_email = "SELECT id FROM users WHERE email = '$email'";
        $result = mysqli_query($connection, $check_email);
        
        if (mysqli_num_rows($result) > 0) {
            $_SESSION['error_message'] = "Email already exists. Please use a different email.";
            header("Location: index.php"); exit();
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            
            $insert_query = "INSERT INTO users (full_name, email, password, role, created_at, status) 
                           VALUES ('$full_name', '$email', '$hashed_password', '$role', '$created_at', 'active')";
            
            if (mysqli_query($connection, $insert_query)) {
                $_SESSION['success_message'] = "Account created successfully! You can now sign in.";
            } else {
                $_SESSION['error_message'] = "Error creating account. Please try again.";
            }
            header("Location: index.php"); exit();
        }
    }
}

// Handle Sign In
if (isset($_POST['signin'])) {
    $email = mysqli_real_escape_string($connection, trim($_POST['login_email']));
    $password = $_POST['login_password'];
    
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // include two_factor_enabled in the SELECT
        $query = "SELECT id, full_name, email, password, role, two_factor_enabled FROM users WHERE email = '$email' AND status = 'active'";
        $result = mysqli_query($connection, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                // If two-factor is enabled, start pre-2FA flow instead of completing login
                if (!empty($user['two_factor_enabled']) && (int)$user['two_factor_enabled'] === 1) {
                    // store pre-2fa info
                    $_SESSION['pre_2fa'] = [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];
                    // generate and store code
                    $verification_code = mt_rand(100000, 999999);
                    $_SESSION['twofa'] = [
                        'code' => $verification_code,
                        'expires' => time() + 600,
                        'last_sent' => time()
                    ];
    
                    // Send email with long descriptive message and highlighted code
                    $subject = "Two-Factor Authentication Code - Barangay Management System";
                    $message = "Hello " . $user['full_name'] . ",\n\n"
                        . "You have attempted to sign in to the Barangay Management System. To protect your account, we have generated a one-time verification code. "
                        . "Enter the code below on the sign-in page to complete your login. The code expires in 10 minutes.\n\n"
                        . "========================================\n"
                        . "          VERIFICATION CODE\n"
                        . "========================================\n"
                        . "               >>  " . $verification_code . "  <<\n"
                        . "========================================\n\n"
                        . "If you did not try to sign in, please ignore this message and contact your system administrator.\n\n"
                        . "Regards,\nBarangay Management System Team\n";
    
                    $message_html = "<html><body>"
                        . "<p>Hello " . htmlspecialchars($user['full_name']) . ",</p>"
                        . "<p>You attempted to sign in. To complete sign-in, enter the verification code shown below. This code is valid for <strong>10 minutes</strong>.</p>"
                        . "<div style='margin:18px 0; padding:14px; background:#f6f8fa; border-radius:8px; display:inline-block;'>"
                        . "<span style='font-size:26px; font-weight:800; letter-spacing:3px; color:#2c3e50;'>"
                        . htmlspecialchars($verification_code)
                        . "</span>"
                        . "</div>"
                        . "<p>If you did not attempt to sign in, ignore this message and contact support immediately.</p>"
                        . "<p>Regards,<br>Barangay Management System Team</p>"
                        . "</body></html>";
    
                    sendEmail($user['email'], $subject, $message_html . "\n\n" . $message);
                    // Use PRG: we saved necessary info into $_SESSION already; redirect to clear POST and let JS modal open on GET
                    header("Location: index.php");
                    exit();
                } else {
                    // No two-factor — complete login as before
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $update_login = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
                    mysqli_query($connection, $update_login);
                    
                    // Redirect based on role
                    if ($user['role'] == 'super_admin') {
                        header("Location: admin/dashboard.php"); exit();
                    } elseif ($user['role'] == 'secretary') {
                        header("Location: sec/dashboard.php"); exit();
                    } elseif ($user['role'] == 'treasurer') {
                        header("Location: treasurer/dashboard.php"); exit();
                    } else {
                        header("Location: captain/dashboard.php"); exit();
                    }
                }
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    }
}

// New AJAX endpoint: send password reset code to user's email
if (isset($_POST['action']) && $_POST['action'] === 'forgot_send_code') {
    header('Content-Type: application/json');
    $raw_email = trim((string)($_POST['email'] ?? ''));
    if (empty($raw_email) || !filter_var($raw_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid email address.']);
        exit;
    }

    $email = mysqli_real_escape_string($connection, $raw_email);
    $q = "SELECT id, full_name FROM users WHERE email = '{$email}' AND status = 'active' LIMIT 1";
    $res = mysqli_query($connection, $q);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No active account found for that email.']);
        exit;
    }

    $user = mysqli_fetch_assoc($res);
    $uid = (int)$user['id'];

    if (!isset($_SESSION['pwd_reset'])) $_SESSION['pwd_reset'] = [];
    $entry = &$_SESSION['pwd_reset'][$uid];

    $now = time();
    $cooldown = 60;
    if (!isset($entry) || !isset($entry['code'])) {
        $code = mt_rand(100000, 999999);
        $entry = [
            'email' => $email,
            'code' => $code,
            'expires' => $now + 900,
            'last_sent' => $now
        ];
    } else {
        $last = (int)($entry['last_sent'] ?? 0);
        $elapsed = $now - $last;
        if ($elapsed < $cooldown) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Please wait before resending the reset code.',
                'retry_after' => $cooldown - $elapsed
            ]);
            exit;
        }
        $entry['last_sent'] = $now;
    }

    $verification_code = $entry['code'];

    $subject = "Password Reset Code - Barangay Management System";
    $message_html = "<html><body>
        <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
        <p>We received a request to reset your password. Use the verification code below to proceed. This code expires in 15 minutes.</p>
        <div style='margin:18px 0; padding:12px; background:#f6f8fa; border-radius:6px; display:inline-block;'>
            <span style='font-size:22px; font-weight:700; letter-spacing:2px; color:#2c3e50;'>"
            . htmlspecialchars($verification_code) .
        "</span>
        </div>
        <p>If you did not request a password reset, please ignore this email or contact your administrator.</p>
        <p>Regards,<br>Barangay Management System Team</p>
        </body></html>";

    $sent = sendEmail($email, $subject, $message_html);
    if ($sent) {
        echo json_encode(['status' => 'success', 'message' => 'Reset code sent to your email.', 'retry_after' => $cooldown]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to send reset code. Please try again later.']);
    }
    exit;
}

// New AJAX endpoint: verify reset code and set new password
if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    header('Content-Type: application/json');
    $raw_email = trim((string)($_POST['email'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));
    $new_password = (string)($_POST['new_password'] ?? '');

    if (empty($raw_email) || !filter_var($raw_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email.']);
        exit;
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid verification code.']);
        exit;
    }
    if (strlen($new_password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.' ]);
        exit;
    }

    $email = mysqli_real_escape_string($connection, $raw_email);
    $q = "SELECT id FROM users WHERE email = '{$email}' AND status = 'active' LIMIT 1";
    $res = mysqli_query($connection, $q);
    if (!$res || mysqli_num_rows($res) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No active account found for that email.']);
        exit;
    }
    $user = mysqli_fetch_assoc($res);
    $uid = (int)$user['id'];

    if (!isset($_SESSION['pwd_reset'][$uid]) || !isset($_SESSION['pwd_reset'][$uid]['code'])) {
        echo json_encode(['status' => 'error', 'message' => 'No reset request found. Please request a code first.']);
        exit;
    }

    $entry = $_SESSION['pwd_reset'][$uid];
    if (time() > ($entry['expires'] ?? 0)) {
        unset($_SESSION['pwd_reset'][$uid]);
        echo json_encode(['status' => 'error', 'message' => 'Reset code expired. Please request a new one.']);
        exit;
    }

    if ((string)$entry['code'] !== (string)$code) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid reset code.']);
        exit;
    }

    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = '{$hashed}' WHERE id = {$uid}";
    if (mysqli_query($connection, $update)) {
        unset($_SESSION['pwd_reset'][$uid]);
        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Try again later.']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Management System - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- ADDED: SweetAlert2 -->
    <style>
        :root {
            --primary: #00426D;  /* Changed to match navbar color */
            --primary-light: #005691;
            --primary-dark: #003154;
            --secondary: #0088cc;
            --success: #4caf50;
            --error: #f44336;
            --text-primary: #212121;
            --text-secondary: #757575;
            --bg-primary: #ffffff;
            --bg-secondary: #f5f5f5;
            --border: #e0e0e0;
            --shadow: 0 8px 30px rgba(0,0,0,0.12);
            --border-radius: 20px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            line-height: 1.6;
        }

        .main-container {
            width: 100%;
            max-width: 420px;
            min-height: 500px;
            background: rgba(255, 255, 255, 0.98);
            border: none;
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo i {
            font-size: 4rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
        }

        .welcome-text h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .form-container {
            width: 100%;
            max-width: 300px; /* smaller, compact form */
            margin: 0 auto;
            padding: 0;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        /* Slightly smaller, tighter inputs */
        .form-control {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--text-primary);
            transition: var(--transition);
            background: var(--bg-secondary);
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            background: var(--bg-primary);
            box-shadow: 0 0 0 4px rgba(83, 75, 174, 0.1);
        }

        /* Match submit height to inputs and make button cleaner */
        .submit-button {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-password a {
            color: var(--primary-light);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .forgot-password a:hover {
            color: var(--primary);
        }

        .footer {
            text-align: center;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-error {
            background-color: #ffebee;
            color: var(--error);
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: var(--success);
            border: 1px solid #c8e6c9;
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 32px 24px;
                margin: 16px;
            }
            
            .logo h1 {
                font-size: 28px;
            }
        }

        /* Rest of existing styles... */
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header">
            <div class="logo">
                <i class="fas fa-building"></i>
                <h1>Barangay Portal</h1>
                <p>Community Management System</p>
            </div>
        </div>

        <div class="form-container">
            <div class="welcome-text">
                <!-- Welcome text removed as requested -->
            </div>

            <div id="alerts"></div>

            <!-- Sign In Form -->
            <form id="signinForm" method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="login_email">Email Address</label>
                    <input 
                        type="email" 
                        id="login_email" 
                        name="login_email" 
                        class="form-control"
                        placeholder="Enter your email address"
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="login_password">Password</label>
                    <!-- removed eye button and wrapper to keep markup simple -->
                    <input 
                        type="password" 
                        id="login_password" 
                        name="login_password" 
                        class="form-control"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <div class="forgot-password">
                        <a href="#" onclick="showForgotPassword()">Forgot your password?</a>
                    </div>
                </div>

                <button type="submit" name="signin" class="submit-button" id="signin-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>
        </div>

        <div class="footer">
            <p>&copy; 2025 Barangay Management System. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Show alerts function
        function showAlert(message, type) {
            const alertsContainer = document.getElementById('alerts');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
            alert.innerHTML = `
                <i class="${icon}"></i>
                <span>${message}</span>
            `;
            
            alertsContainer.innerHTML = '';
            alertsContainer.appendChild(alert);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        }

        // Forgot password placeholder function
        function showForgotPassword() {
            const prefillEmail = document.getElementById('login_email') ? document.getElementById('login_email').value.trim() : '';

            Swal.fire({
                title: 'Forgot your password?',
                html: `
                    <p>Please choose an option below. You can contact the system administrator or receive a one-time reset code by email.</p>
                    <input id="swal-forgot-email" class="swal2-input" placeholder="Enter your email" />`,
                showCancelButton: true,
                showDenyButton: true,
                denyButtonText: 'Contact Admin',
                confirmButtonText: 'Send Reset Code',
                cancelButtonText: 'Close',
                focusConfirm: false,
                didOpen: () => {
                    const input = document.getElementById('swal-forgot-email');
                    if (input) input.value = prefillEmail;
                },
                preConfirm: () => {
                    const email = document.getElementById('swal-forgot-email').value.trim();
                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        Swal.showValidationMessage('Please enter a valid email address');
                        return false;
                    }
                    return fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=forgot_send_code&email=${encodeURIComponent(email)}`
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Network error');
                        return res.json();
                    })
                    .then(json => {
                        if (json.status === 'error') throw new Error(json.message || 'Failed to send code');
                        return { email, retry_after: json.retry_after || 60 };
                    })
                    .catch(err => {
                        Swal.showValidationMessage(err.message || 'Failed to send reset code');
                        return false;
                    });
                }
            }).then((result) => {
                if (result.isDenied) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Contact System Administrator',
                        html: '<p>Please contact your system administrator to reset your password.</p>',
                        confirmButtonText: 'OK'
                    });
                } else if (result.isConfirmed && result.value) {
                    // Open reset modal immediately
                    openResetModal(result.value.email, result.value.retry_after || 60);
                }
            });
        }

        function openResetModal(email, cooldownSeconds = 0) {
            let countdown = Math.max(0, Math.floor(cooldownSeconds));

            Swal.fire({
                title: 'Enter Reset Code & New Password',
                html: `
                    <p>A reset code was sent to <strong id="swal-email"></strong>. Enter it below, then provide a new password.</p>
                    <input id="swal-reset-code" class="swal2-input" placeholder="6-digit code" maxlength="6" />
                    <input id="swal-new-password" class="swal2-input" type="password" placeholder="New password (min 6 chars)" />
                    <input id="swal-confirm-password" class="swal2-input" type="password" placeholder="Confirm new password" />`,
                showCancelButton: true,
                showDenyButton: true,
                denyButtonText: countdown > 0 ? `Resend (${countdown}s)` : 'Resend Code',
                confirmButtonText: 'Reset Password',
                cancelButtonText: 'Close',
                didOpen: () => {
                    const emailEl = document.getElementById('swal-email');
                    if (emailEl) emailEl.textContent = email;
                    const denyBtn = Swal.getDenyButton();
                    if (!denyBtn) return;
                    if (countdown > 0) {
                        denyBtn.disabled = true;
                        const iv = setInterval(() => {
                            countdown--;
                            denyBtn.textContent = countdown > 0 ? `Resend (${countdown}s)` : 'Resend Code';
                            if (countdown <= 0) {
                                clearInterval(iv);
                                denyBtn.disabled = false;
                            }
                        }, 1000);
                    }
                },
                preConfirm: () => {
                    const code = document.getElementById('swal-reset-code').value.trim();
                    const pw = document.getElementById('swal-new-password').value;
                    const pw2 = document.getElementById('swal-confirm-password').value;
                    if (!/^\d{6}$/.test(code)) {
                        Swal.showValidationMessage('Enter a valid 6-digit code');
                        return false;
                    }
                    if (pw.length < 6) {
                        Swal.showValidationMessage('Password must be at least 6 characters');
                        return false;
                    }
                    if (pw !== pw2) {
                        Swal.showValidationMessage('Passwords do not match');
                        return false;
                    }

                    return fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=reset_password&email=${encodeURIComponent(email)}&code=${encodeURIComponent(code)}&new_password=${encodeURIComponent(pw)}`
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Network error');
                        return res.json();
                    })
                    .then(json => {
                        if (json.status === 'error') throw new Error(json.message || 'Failed to reset password');
                        return json;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(err.message || 'Failed to reset password');
                        return false;
                    });
                }
            }).then((result) => {
                if (result.isDenied) {
                    // Resend code flow
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=forgot_send_code&email=${encodeURIComponent(email)}`
                    })
                    .then(r => r.json())
                    .then(j => {
                        if (j.status === 'success') {
                            const retry = (typeof j.retry_after === 'number') ? j.retry_after : 60;
                            Swal.fire({ icon: 'success', title: 'Code Sent', text: j.message || 'A new reset code was sent to your email.' })
                                .then(() => openResetModal(email, retry));
                        } else if (j.retry_after) {
                            Swal.fire({ icon: 'error', title: 'Please wait', text: j.message || 'Please wait before resending' })
                                .then(() => openResetModal(email, j.retry_after));
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: j.message || 'Failed to resend code' });
                        }
                    })
                    .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to resend code' }));
                } else if (result.isConfirmed && result.value) {
                    Swal.fire({ icon: 'success', title: 'Password Updated', text: 'You can now sign in with your new password.' });
                }
            });
        }

        // Real-time validation feedback
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity() && this.value) {
                    this.style.borderColor = 'var(--success)';
                } else if (this.value && !this.checkValidity()) {
                    this.style.borderColor = 'var(--error)';
                } else {
                    this.style.borderColor = 'var(--border)';
                }
            });
            
            input.addEventListener('input', function() {
                if (this.style.borderColor && this.checkValidity()) {
                    this.style.borderColor = 'var(--success)';
                }
            });
        });

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.type !== 'submit') {
                const form = e.target.closest('form');
                if (form) {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.click();
                    }
                }
            }
        });

        // PHP error/success message handling (for integration)
        <?php if (!empty($error_message)): ?>
            showAlert('<?php echo addslashes($error_message); ?>', 'error');
        <?php endif; ?>
        
        <?php 
        // Only show the inline success message when NOT in the pre-2FA flow
        if (!empty($success_message) && !(isset($_SESSION['pre_2fa']) && isset($_SESSION['twofa']))): 
        ?>
            showAlert('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>
    </script>

    <?php
    // Insert: compute initial cooldown for resend button so modal JS has the value it expects
    if (isset($_SESSION['pre_2fa']) && isset($_SESSION['twofa'])) {
        $now = time();
        $last_sent = (int)($_SESSION['twofa']['last_sent'] ?? 0);
        $initialCooldown = max(0, 60 - ($now - $last_sent));
    } else {
        $initialCooldown = 0;
    }
    ?>
    <script>
        // Expose server-calculated initial cooldown to client; prevents ReferenceError
        const __initialResendCooldown = <?php echo (int)$initialCooldown; ?>;
    </script>

    <?php if (isset($_SESSION['pre_2fa']) && isset($_SESSION['twofa'])): ?>
    <script>
        (function () {
            // codeHint removed intentionally — do not display numeric code in the modal
            const emailHint = <?php echo json_encode((string)($_SESSION['pre_2fa']['email'] ?? '')); ?>;
            const fullName  = <?php echo json_encode((string)($_SESSION['pre_2fa']['full_name'] ?? 'User')); ?>;

            function openTwoFactorModal(cooldownSeconds = 0) {
                let countdown = Math.max(0, Math.floor(cooldownSeconds));

                Swal.fire({
                    title: 'Two-Factor Authentication Required',
                    html:
                        `<p>We sent a one-time verification code to <strong>${emailHint}</strong> for account <strong>${fullName}</strong>.</p>` +
                        `<p style="margin-top:12px; color:#333; font-weight:600;">please input the 6 digits code</p>`,
                    input: 'text',
                    inputLabel: 'Verification Code',
                    inputPlaceholder: 'Enter 6-digit code',
                    inputAttributes: { maxlength: 6, autocapitalize: 'off', autocorrect: 'off' },
                    showCancelButton: true,
                    showDenyButton: true,
                    denyButtonText: 'Resend Code',
                    confirmButtonText: 'Verify',
                    cancelButtonText: 'Cancel',
                    allowOutsideClick: () => !Swal.isLoading(),
                    didOpen: () => {
                        const denyBtn = Swal.getDenyButton();
                        if (!denyBtn) return;

                        // Initialize deny button state and label with countdown if needed
                        function setDenyLabel(sec) {
                            denyBtn.textContent = sec > 0 ? `Resend (${sec}s)` : 'Resend Code';
                        }

                        if (countdown > 0) {
                            denyBtn.disabled = true;
                            setDenyLabel(countdown);
                            const iv = setInterval(() => {
                                countdown--;
                                if (countdown <= 0) {
                                    clearInterval(iv);
                                    denyBtn.disabled = false;
                                    setDenyLabel(0);
                                } else {
                                    setDenyLabel(countdown);
                                }
                            }, 1000);
                        } else {
                            denyBtn.disabled = false;
                            setDenyLabel(0);
                        }
                    },
                    preConfirm: (value) => {
                        if (!value || !/^\d{6}$/.test(value)) {
                            Swal.showValidationMessage('Please enter a valid 6-digit code');
                            return false;
                        }
                        return fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=verify_2fa&code=${encodeURIComponent(value)}`
                        })
                        .then(res => {
                            if (!res.ok) throw new Error('Network error');
                            return res.json();
                        })
                        .then(json => {
                            if (json.status === 'error') throw new Error(json.message || 'Invalid code');
                            return json;
                        })
                        .catch(err => {
                            Swal.showValidationMessage(err.message || 'Verification failed');
                            return false;
                        });
                    }
                }).then((result) => {
                    if (result.isDenied) {
                        // Send resend request; on success server returns retry_after
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=resend_2fa'
                        })
                        .then(r => r.json())
                        .then(j => {
                            if (j.status === 'success') {
                                const retry = (typeof j.retry_after === 'number') ? j.retry_after : 60;
                                Swal.fire({ icon: 'success', title: 'Code Sent', text: j.message || 'A new verification code was sent to your email.', timer: 1400, showConfirmButton: false })
                                    .then(() => openTwoFactorModal(retry));
                            } else if (j.retry_after) {
                                // server blocked resend — show error and reopen with remaining retry_after
                                Swal.fire({ icon: 'error', title: 'Please wait', text: j.message || 'Please wait before resending', timer: 1400, showConfirmButton: false })
                                    .then(() => openTwoFactorModal(j.retry_after));
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: j.message || 'Failed to resend code' });
                            }
                        })
                        .catch(() => {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to resend code' });
                        });
                    } else if (result.isConfirmed && result.value) {
                        const redirectUrl = result.value.redirect || '';
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        Swal.fire({ icon: 'info', title: 'Two-Factor Required', text: 'Two-factor authentication is required to complete sign-in.', timer: 2200, showConfirmButton: false });
                    }
                });
            }

            // open modal after load, using server-provided initial cooldown
            window.addEventListener('load', function() {
                setTimeout(() => openTwoFactorModal(__initialResendCooldown || 0), 200);
            });
        })();
    </script>
    <?php endif; ?>

</body>
</html>