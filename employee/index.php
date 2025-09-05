<?php
session_start();
require_once '../config.php';



$error_message = '';
$success_message = '';

// Handle Sign Up
if (isset($_POST['signup'])) {
    $full_name = mysqli_real_escape_string($connection, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($connection, trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = mysqli_real_escape_string($connection, $_POST['role']);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_email = "SELECT id FROM users WHERE email = '$email'";
        $result = mysqli_query($connection, $check_email);
        
        if (mysqli_num_rows($result) > 0) {
            $error_message = "Email already exists. Please use a different email.";
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');
            
            $insert_query = "INSERT INTO users (full_name, email, password, role, created_at, status) 
                           VALUES ('$full_name', '$email', '$hashed_password', '$role', '$created_at', 'active')";
            
            if (mysqli_query($connection, $insert_query)) {
                $success_message = "Account created successfully! You can now sign in.";
            } else {
                $error_message = "Error creating account. Please try again.";
            }
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
        $query = "SELECT id, full_name, email, password, role FROM users WHERE email = '$email' AND status = 'active'";
        $result = mysqli_query($connection, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $update_login = "UPDATE users SET last_login = NOW() WHERE id = " . $user['id'];
                mysqli_query($connection, $update_login);
                
                // Redirect based on role
                if ($user['role'] == 'super_admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] == 'secretary') {
                    header("Location: sec/dashboard.php");
                } else {
                    header("Location: treasurer/dashboard.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Management System - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --secondary: #06b6d4;
            --success: #10b981;
            --error: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --border: #e5e7eb;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            line-height: 1.6;
        }

        .main-container {
            width: 100%;
            max-width: 420px;
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 3rem 2rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .logo {
            position: relative;
            z-index: 1;
        }

        .logo i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .logo h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .logo p {
            font-size: 1rem;
            opacity: 0.8;
            font-weight: 500;
        }

        .form-container {
            padding: 2.5rem 2rem;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .welcome-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease-out;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-control:invalid:not(:placeholder-shown) {
            border-color: var(--error);
        }

        .form-control:valid:not(:placeholder-shown) {
            border-color: var(--success);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 1rem;
            padding: 0.5rem;
            border-radius: 6px;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--text-primary);
            background: var(--bg-secondary);
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .submit-button {
            width: 100%;
            padding: 1.125rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            margin-top: 2rem;
        }

        .submit-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .submit-button:hover::before {
            left: 100%;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(79, 70, 229, 0.4);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .system-info {
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .system-info h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .feature-item i {
            color: var(--primary);
            font-size: 1.125rem;
            width: 1.25rem;
            text-align: center;
        }

        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .footer {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            .main-container {
                max-width: 100%;
                margin: 0.5rem;
                border-radius: 16px;
            }
            
            .header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .form-container {
                padding: 2rem 1.5rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .logo i {
                font-size: 3rem;
            }
            
            .logo h1 {
                font-size: 1.75rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus styles for better accessibility */
        .submit-button:focus,
        .form-control:focus,
        .password-toggle:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
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
                <h2>Welcome Back</h2>
                <p>Sign in to access your barangay management dashboard</p>
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
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="login_password" 
                            name="login_password" 
                            class="form-control"
                            placeholder="Enter your password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('login_password')" title="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="forgot-password">
                        <a href="#" onclick="showForgotPassword()">Forgot your password?</a>
                    </div>
                </div>

                <button type="submit" name="signin" class="submit-button" id="signin-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In to Dashboard</span>
                </button>
            </form>

            <div class="system-info">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    System Features
                </h3>
                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Access</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Resident Records</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Document Processing</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics Dashboard</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Mobile Responsive</span>
                    </div>
                </div>
            </div>
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

        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
                button.title = 'Hide password';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
                button.title = 'Show password';
            }
        }

        // Forgot password placeholder function
        function showForgotPassword() {
            showAlert('Please contact your system administrator to reset your password.', 'error');
        }

        // Form submission with loading state
        document.getElementById('signinForm').addEventListener('submit', function() {
            const button = document.getElementById('signin-btn');
            button.classList.add('loading');
            button.querySelector('span').textContent = 'Signing In...';
        });

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
        
        <?php if (!empty($success_message)): ?>
            showAlert('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>

        // Auto-focus on first input when page loads
        window.addEventListener('load', function() {
            const firstInput = document.getElementById('login_email');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>