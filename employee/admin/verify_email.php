<?php
session_start();
require_once '../../config.php';

// Check if verification data exists in session
if (!isset($_SESSION['email_verification'])) {
    header("Location: account_management.php");
    exit();
}

$verification_data = $_SESSION['email_verification'];
$new_email = $verification_data['new_email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend_code'])) {
        // Generate new verification code
        $verification_code = generateVerificationCode();
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Update verification code in database
        $update_code_query = "UPDATE email_verifications 
                            SET verification_code = '$verification_code', code_expiry = '$expiry'
                            WHERE user_id = '{$verification_data['user_id']}' AND new_email = '$new_email'";
        
        if (mysqli_query($connection, $update_code_query)) {
            // Send verification email
            if (sendVerificationEmail($new_email, $verification_code)) {
                $_SESSION['success'] = "New verification code sent!";
            } else {
                $_SESSION['error'] = "Failed to send verification email!";
            }
        } else {
            $_SESSION['error'] = "Error generating new verification code: " . mysqli_error($connection);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Add your existing styles here */
        .verification-container {
            max-width: 500px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .verification-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .verification-icon {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 1rem;
        }
        
        .verification-form {
            margin-top: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Your sidebar and header structure here -->

    <div class="main-content">
        <div class="content-area">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="verification-container">
                <div class="verification-header">
                    <div class="verification-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h2>Verify Email Address</h2>
                    <p>We've sent a verification code to <strong><?php echo htmlspecialchars($new_email); ?></strong></p>
                </div>
                
                <form method="POST" action="account_management.php" class="verification-form">
                    <input type="hidden" name="verify_code" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $verification_data['user_id']; ?>">
                    <input type="hidden" name="new_email" value="<?php echo $new_email; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Verification Code</label>
                        <input type="text" name="verification_code" class="form-control" required placeholder="Enter 6-digit code">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-check"></i>
                            Verify Email
                        </button>
                    </div>
                    
                    <div class="form-group" style="text-align: center;">
                        <p>Didn't receive the code? <a href="#" onclick="document.getElementById('resendForm').submit(); return false;">Resend code</a></p>
                    </div>
                </form>
                
                <form method="POST" action="verify_email.php" id="resendForm" style="display: none;">
                    <input type="hidden" name="resend_code" value="1">
                </form>
            </div>
        </div>
    </div>
</body>
</html>