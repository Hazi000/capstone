<?php
session_start();
require_once '../../config.php';

// Check if we have verification parameters
if (isset($_GET['code']) && isset($_GET['email'])) {
    $code = $_GET['code'];
    $email = $_GET['email'];
    
    // Check verification in session
    $is_valid = isset($_SESSION['email_verification']) &&
                $_SESSION['email_verification']['code'] === $code &&
                $_SESSION['email_verification']['email'] === $email &&
                time() <= $_SESSION['email_verification']['expires'];

    if ($is_valid) {
        $_SESSION['email_verified'] = true;
        $_SESSION['email_verification']['verified'] = true;
    }
} else {
    header("Location: account_management.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_valid ? 'Email Verified' : 'Verification Failed'; ?> - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        h1 { color: #333; margin-bottom: 1rem; }
        p { color: #666; margin-bottom: 2rem; }
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_valid): ?>
            <i class="fas fa-check-circle icon success"></i>
            <h1>Email Verified!</h1>
            <p>Your email has been successfully verified. You can close this window and continue with your account creation.</p>
            <script>
                // Update parent window if available
                if (window.opener && !window.opener.closed) {
                    try {
                        const verificationStatus = window.opener.document.getElementById('addVerificationStatus');
                        const verifyBtn = window.opener.document.getElementById('add_verify_email_btn');
                        if (verificationStatus) {
                            verificationStatus.innerHTML = '<span style="color: green;"><i class="fas fa-check-circle"></i> Email verified successfully!</span>';
                        }
                        if (verifyBtn) {
                            verifyBtn.disabled = true;
                            verifyBtn.style.opacity = '0.5';
                            verifyBtn.innerHTML = '<i class="fas fa-check"></i> Verified';
                        }
                        // Set hidden input
                        const hiddenInput = window.opener.document.getElementById('add_is_email_verified');
                        if (hiddenInput) {
                            hiddenInput.value = '1';
                        }
                    } catch (e) {
                        console.log('Parent window update failed:', e);
                    }
                }
                // Auto close after 3 seconds
                setTimeout(() => window.close(), 3000);
            </script>
        <?php else: ?>
            <i class="fas fa-times-circle icon error"></i>
            <h1>Verification Failed</h1>
            <p>The verification link is invalid or has expired. Please try requesting a new verification email.</p>
        <?php endif; ?>
        <button onclick="window.close()" class="btn">Close Window</button>
    </div>
</body>
</html>