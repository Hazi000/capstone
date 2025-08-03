<?php
    session_start();
    require_once 'config.php'; // Your database configuration file

    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'verify_face':
                verifyFace($connection);
                exit;
            case 'complete_signup':
                completeSignup($connection);
                exit;
            case 'login':
                handleLogin($connection);
                exit;
        }
    }

    /**
     * Enhanced face verification with better accuracy
     */
    function verifyFace($connection) {
        try {
            if (!isset($_POST['face_descriptor'])) {
                throw new Exception('No face data provided');
            }

            $faceDescriptor = json_decode($_POST['face_descriptor'], true);
            if (!$faceDescriptor || !is_array($faceDescriptor)) {
                throw new Exception('Invalid face descriptor format');
            }

            // Get all residents with face descriptors
            $query = "SELECT id, first_name, last_name, full_name, email, photo_path, face_descriptor 
                    FROM residents 
                    WHERE face_descriptor IS NOT NULL AND status = 'active'";
            
            $result = mysqli_query($connection, $query);
            
            if (!$result) {
                throw new Exception('Database query failed');
            }

            $bestMatch = null;
            $lowestDistance = 1.0;
            $threshold = 0.5; // More strict threshold for better accuracy

            while ($resident = mysqli_fetch_assoc($result)) {
                if ($resident['face_descriptor']) {
                    $storedDescriptor = json_decode($resident['face_descriptor'], true);
                    
                    if ($storedDescriptor && is_array($storedDescriptor)) {
                        // Calculate Euclidean distance
                        $distance = calculateEuclideanDistance($faceDescriptor, $storedDescriptor);
                        
                        if ($distance < $threshold && $distance < $lowestDistance) {
                            $lowestDistance = $distance;
                            $bestMatch = $resident;
                        }
                    }
                }
            }

            if ($bestMatch) {
                // Check if resident already has an account
                $hasAccount = checkIfHasAccount($connection, $bestMatch['id']);
                
                echo json_encode([
                    'success' => true,
                    'found' => true,
                    'resident' => [
                        'id' => $bestMatch['id'],
                        'full_name' => $bestMatch['full_name'],
                        'photo_path' => $bestMatch['photo_path'],
                        'has_account' => $hasAccount,
                        'confidence' => round((1 - $lowestDistance) * 100, 1)
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'found' => false,
                    'message' => 'Face not recognized in our database'
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate Euclidean distance between two face descriptors
     */
    function calculateEuclideanDistance($desc1, $desc2) {
        if (count($desc1) !== count($desc2)) {
            return 1.0; // Maximum distance for mismatched descriptors
        }
        
        $sum = 0;
        for ($i = 0; $i < count($desc1); $i++) {
            $diff = $desc1[$i] - $desc2[$i];
            $sum += $diff * $diff;
        }
        
        return sqrt($sum);
    }

    function checkIfHasAccount($connection, $resident_id) {
        $query = "SELECT id FROM resident_accounts WHERE resident_id = ?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $resident_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $hasAccount = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $hasAccount;
    }

    /**
     * Complete the signup process after face verification
     */
    function completeSignup($connection) {
        try {
            // Validate input
            $resident_id = intval($_POST['resident_id'] ?? 0);
            $username = mysqli_real_escape_string($connection, $_POST['username'] ?? '');
            $email = mysqli_real_escape_string($connection, $_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validation
            if (!$resident_id || !$username || !$email || !$password) {
                throw new Exception('All fields are required');
            }

            if ($password !== $confirm_password) {
                throw new Exception('Passwords do not match');
            }

            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Check if resident already has an account
            $check_query = "SELECT id FROM resident_accounts WHERE resident_id = ?";
            $stmt = mysqli_prepare($connection, $check_query);
            mysqli_stmt_bind_param($stmt, "i", $resident_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                throw new Exception('This resident already has an account');
            }
            mysqli_stmt_close($stmt);

            // Check if username or email already exists
            $check_query = "SELECT id FROM resident_accounts WHERE username = ? OR email = ?";
            $stmt = mysqli_prepare($connection, $check_query);
            mysqli_stmt_bind_param($stmt, "ss", $username, $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                throw new Exception('Username or email already exists');
            }
            mysqli_stmt_close($stmt);

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));

            // Insert new account
            $insert_query = "INSERT INTO resident_accounts (resident_id, username, email, password, verification_token, is_verified) 
                            VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = mysqli_prepare($connection, $insert_query);
            mysqli_stmt_bind_param($stmt, "issss", $resident_id, $username, $email, $hashed_password, $verification_token);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create account');
            }

            $account_id = mysqli_insert_id($connection);
            mysqli_stmt_close($stmt);

            echo json_encode([
                'success' => true,
                'message' => 'Account created successfully! You can now sign in.',
                'account_id' => $account_id
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle login
     */
    function handleLogin($connection) {
        try {
            $username = mysqli_real_escape_string($connection, $_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$username || !$password) {
                throw new Exception('Username and password are required');
            }

            // Get user account with resident info
            $query = "SELECT ra.*, r.full_name, r.photo_path 
                    FROM resident_accounts ra 
                    JOIN residents r ON ra.resident_id = r.id 
                    WHERE ra.username = ? OR ra.email = ?";
            
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "ss", $username, $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user['password'])) {
                    // Check if account is locked
                    if ($user['account_locked']) {
                        throw new Exception('Account is locked. Please contact support.');
                    }

                    // Set session variables
                    $_SESSION['resident_id'] = $user['resident_id'];
                    $_SESSION['account_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['logged_in'] = true;

                    // Update last login
                    $update_query = "UPDATE resident_accounts SET last_login = NOW(), login_attempts = 0 WHERE id = ?";
                    $update_stmt = mysqli_prepare($connection, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'redirect' => 'resident/dashboard.php'
                    ]);
                } else {
                    // Update failed login attempts
                    $attempts = $user['login_attempts'] + 1;
                    $locked = $attempts >= 5 ? 1 : 0;
                    
                    $update_query = "UPDATE resident_accounts SET login_attempts = ?, account_locked = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($connection, $update_query);
                    mysqli_stmt_bind_param($update_stmt, "iii", $attempts, $locked, $user['id']);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    throw new Exception('Invalid username or password');
                }
            } else {
                throw new Exception('Invalid username or password');
            }
            
            mysqli_stmt_close($stmt);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barangay Cawit Resident Portal - Enhanced Face Recognition</title>
        <!-- Face API with fallback -->
        <script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js" 
                onerror="loadFaceAPIFallback()"></script>
        <script>
            // Fallback for Face API loading
            function loadFaceAPIFallback() {
                console.log('Loading Face API fallback...');
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/dist/face-api.min.js';
                script.onerror = function() {
                    console.error('All Face API sources failed');
                    alert('Face recognition library failed to load. Please refresh the page or check your internet connection.');
                };
                document.head.appendChild(script);
            }
        </script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Arial', sans-serif;
                background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
                min-height: 100vh;
                color: white;
            }

            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px 50px;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
            }

            .logo {
                font-size: 24px;
                font-weight: bold;
                color: white;
            }

            .nav-links {
                display: flex;
                gap: 30px;
                list-style: none;
            }

            .nav-links li {
                cursor: pointer;
                transition: color 0.3s ease;
            }

            .nav-links li:hover {
                color: #ffd700;
            }

            .auth-buttons {
                display: flex;
                gap: 15px;
            }

            .btn {
                padding: 10px 20px;
                border: none;
                border-radius: 25px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.3s ease;
            }

            .btn-signin {
                background: transparent;
                color: white;
                border: 2px solid white;
            }

            .btn-signin:hover {
                background: white;
                color: #4a47a3;
            }

            .btn-signup {
                background: #ffd700;
                color: #4a47a3;
            }

            .btn-signup:hover {
                background: #ffed4a;
                transform: translateY(-2px);
            }

            .hero-section {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 80px 50px;
                max-width: 1200px;
                margin: 0 auto;
            }

            .hero-content {
                flex: 1;
                padding-right: 50px;
            }

            .hero-logo {
                width: 150px;
                height: 150px;
                background: linear-gradient(45deg, #ffd700, #ff6b6b, #4ecdc4);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 30px;
                position: relative;
                overflow: hidden;
            }

            .hero-logo::before {
                content: '';
                position: absolute;
                width: 60px;
                height: 60px;
                background: #4a47a3;
                border-radius: 50%;
                top: 20px;
                left: 20px;
            }

            .hero-logo::after {
                content: '';
                position: absolute;
                width: 40px;
                height: 40px;
                background: #ff6b6b;
                border-radius: 50%;
                bottom: 30px;
                right: 30px;
            }

            .hero-title {
                font-size: 48px;
                font-weight: bold;
                margin-bottom: 20px;
                line-height: 1.2;
            }

            .hero-subtitle {
                font-size: 18px;
                margin-bottom: 10px;
                opacity: 0.9;
            }

            .hero-details {
                font-size: 16px;
                margin-bottom: 30px;
                opacity: 0.8;
            }

            .btn-about {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                padding: 15px 30px;
                border: none;
                border-radius: 30px;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .btn-about:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-2px);
            }

            .hero-image {
                flex: 1;
                text-align: center;
            }

            .building-icon {
                width: 300px;
                height: 300px;
                background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
                border-radius: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 120px;
                margin: 0 auto;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .services-preview {
                text-align: center;
                padding: 50px;
                background: rgba(255, 255, 255, 0.05);
                margin: 50px;
                border-radius: 20px;
                backdrop-filter: blur(10px);
            }

            .services-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 30px;
                margin-top: 30px;
            }

            .service-card {
                background: rgba(255, 255, 255, 0.1);
                padding: 30px;
                border-radius: 15px;
                text-align: center;
                transition: transform 0.3s ease;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            .service-card:hover {
                transform: translateY(-5px);
                background: rgba(255, 255, 255, 0.15);
            }

            .service-icon {
                font-size: 48px;
                margin-bottom: 15px;
            }

            /* Enhanced Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(5px);
            }

            .modal-content {
                background: linear-gradient(135deg, #4a47a3 0%, #3a3782 100%);
                margin: 2% auto;
                padding: 0;
                border-radius: 20px;
                width: 90%;
                max-width: 600px;
                position: relative;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(255, 255, 255, 0.1);
                max-height: 90vh;
                overflow-y: auto;
            }

            .modal-header {
                padding: 30px 30px 0;
                text-align: center;
            }

            .modal-title {
                font-size: 28px;
                font-weight: bold;
                margin-bottom: 10px;
                color: white;
            }

            .modal-subtitle {
                color: rgba(255, 255, 255, 0.8);
                margin-bottom: 30px;
            }

            .close {
                position: absolute;
                right: 20px;
                top: 20px;
                font-size: 28px;
                font-weight: bold;
                color: white;
                cursor: pointer;
                transition: color 0.3s ease;
                z-index: 1001;
            }

            .close:hover {
                color: #ffd700;
            }

            .modal-body {
                padding: 0 30px 30px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: white;
                font-weight: 500;
            }

            .form-group input {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.1);
                color: white;
                font-size: 16px;
                transition: border-color 0.3s ease;
            }

            .form-group input:focus {
                outline: none;
                border-color: #ffd700;
                background: rgba(255, 255, 255, 0.15);
            }

            .form-group input::placeholder {
                color: rgba(255, 255, 255, 0.6);
            }

            .btn-submit {
                width: 100%;
                padding: 15px;
                background: #ffd700;
                color: #4a47a3;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
                margin-bottom: 20px;
            }

            .btn-submit:hover {
                background: #ffed4a;
                transform: translateY(-2px);
            }

            .btn-submit:disabled {
                background: #999;
                cursor: not-allowed;
                transform: none;
            }

            .modal-footer {
                text-align: center;
                color: rgba(255, 255, 255, 0.8);
            }

            .modal-footer a {
                color: #ffd700;
                text-decoration: none;
                cursor: pointer;
            }

            .modal-footer a:hover {
                text-decoration: underline;
            }

            .row {
                display: flex;
                gap: 15px;
            }

            .col {
                flex: 1;
            }

            /* Enhanced Camera Section Styles */
            .camera-section {
                margin-bottom: 25px;
                text-align: center;
                position: relative;
            }

            .camera-container {
                position: relative;
                margin: 15px auto;
                border-radius: 15px;
                overflow: hidden;
                background: rgba(0, 0, 0, 0.3);
                border: 3px solid rgba(255, 255, 255, 0.2);
                width: 400px;
                height: 300px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #video, #signupVideo {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: none;
            }

            #canvas, #signupCanvas {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
            }

            .camera-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                color: rgba(255, 255, 255, 0.5);
                background: rgba(255, 255, 255, 0.05);
            }

            .camera-controls {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .btn-camera {
                padding: 10px 20px;
                border: none;
                border-radius: 25px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.3s ease;
                font-size: 14px;
            }

            .btn-camera.primary {
                background: #ffd700;
                color: #4a47a3;
            }

            .btn-camera.primary:hover {
                background: #ffed4a;
                transform: translateY(-2px);
            }

            .btn-camera.secondary {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                border: 1px solid rgba(255, 255, 255, 0.3);
            }

            .btn-camera.secondary:hover {
                background: rgba(255, 255, 255, 0.3);
            }

            .btn-camera.danger {
                background: #ff6b6b;
                color: white;
            }

            .btn-camera.danger:hover {
                background: #ff5252;
            }

            .btn-camera:disabled {
                background: #666;
                cursor: not-allowed;
                opacity: 0.5;
            }

            /* Enhanced Face Detection Indicator */
            .face-indicator {
                position: absolute;
                top: 15px;
                left: 15px;
                padding: 10px 15px;
                border-radius: 25px;
                font-size: 14px;
                font-weight: bold;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                z-index: 10;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            }

            .face-indicator.detected {
                background: rgba(76, 175, 80, 0.95);
                color: white;
                animation: pulse-green 2s infinite;
            }

            .face-indicator.not-detected {
                background: rgba(244, 67, 54, 0.95);
                color: white;
                animation: pulse-red 2s infinite;
            }

            .face-indicator.smile-waiting {
                background: rgba(255, 152, 0, 0.95);
                color: white;
                animation: pulse-orange 1s infinite;
            }

            .face-indicator.smile-detected {
                background: rgba(156, 39, 176, 0.95);
                color: white;
                animation: pulse-purple 0.5s infinite;
            }

            @keyframes pulse-green {
                0%, 100% { transform: scale(1); opacity: 0.9; }
                50% { transform: scale(1.05); opacity: 1; }
            }

            @keyframes pulse-red {
                0%, 100% { transform: scale(1); opacity: 0.9; }
                50% { transform: scale(1.05); opacity: 1; }
            }

            @keyframes pulse-orange {
                0%, 100% { transform: scale(1); opacity: 0.9; }
                50% { transform: scale(1.1); opacity: 1; }
            }

            @keyframes pulse-purple {
                0%, 100% { transform: scale(1); opacity: 0.9; }
                50% { transform: scale(1.15); opacity: 1; }
            }

            /* Enhanced Countdown Styles */
            .countdown-overlay {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 0, 0, 0.9);
                color: #ffd700;
                padding: 30px;
                border-radius: 50%;
                width: 150px;
                height: 150px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-size: 3rem;
                font-weight: bold;
                z-index: 20;
                border: 4px solid #ffd700;
                box-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
                animation: countdown-pulse 1s infinite;
            }

            .countdown-text {
                font-size: 0.8rem;
                margin-top: 5px;
            }

            @keyframes countdown-pulse {
                0% { transform: translate(-50%, -50%) scale(1); }
                50% { transform: translate(-50%, -50%) scale(1.1); }
                100% { transform: translate(-50%, -50%) scale(1); }
            }

            /* Face Guide Overlay */
            .face-guide-overlay {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 200px;
                height: 250px;
                border: 3px dashed rgba(255, 255, 255, 0.6);
                border-radius: 50% / 60%;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                animation: guide-pulse 3s infinite;
            }

            @keyframes guide-pulse {
                0%, 100% { opacity: 0.6; transform: translate(-50%, -50%) scale(1); }
                50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.05); }
            }

            .face-guide-text {
                position: absolute;
                bottom: -40px;
                left: 50%;
                transform: translateX(-50%);
                color: white;
                background: rgba(0, 0, 0, 0.8);
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 12px;
                white-space: nowrap;
                font-weight: bold;
            }

            /* Recognition Result Display */
            .recognition-result {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                display: none;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 25;
                border-radius: 15px;
            }

            .recognition-result.show {
                display: flex;
                animation: slideInUp 0.5s ease;
            }

            @keyframes slideInUp {
                from { transform: translateY(100%); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            .result-photo {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #ffd700;
                margin-bottom: 20px;
                box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            }

            .result-name {
                font-size: 24px;
                font-weight: bold;
                color: #ffd700;
                margin-bottom: 10px;
                text-align: center;
            }

            .result-confidence {
                font-size: 14px;
                color: rgba(255, 255, 255, 0.8);
                margin-bottom: 20px;
            }

            .result-status {
                padding: 10px 20px;
                border-radius: 25px;
                font-weight: bold;
                font-size: 14px;
            }

            .result-status.success {
                background: rgba(76, 175, 80, 0.2);
                color: #4caf50;
                border: 2px solid #4caf50;
            }

            .result-status.warning {
                background: rgba(255, 152, 0, 0.2);
                color: #ff9800;
                border: 2px solid #ff9800;
            }

            /* Enhanced Alert Styles */
            .alert {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 10px;
                text-align: center;
                font-weight: 500;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .alert-success {
                background: rgba(39, 174, 96, 0.2);
                border: 1px solid rgba(39, 174, 96, 0.4);
                color: #27ae60;
            }

            .alert-error {
                background: rgba(231, 76, 60, 0.2);
                border: 1px solid rgba(231, 76, 60, 0.4);
                color: #e74c3c;
            }

            .alert-info {
                background: rgba(52, 152, 219, 0.2);
                border: 1px solid rgba(52, 152, 219, 0.4);
                color: #3498db;
            }

            .alert-warning {
                background: rgba(243, 156, 18, 0.2);
                border: 1px solid rgba(243, 156, 18, 0.4);
                color: #f39c12;
            }

            #signupForm {
                display: none;
            }

            .password-requirements {
                font-size: 12px;
                color: rgba(255, 255, 255, 0.7);
                margin-top: 5px;
                text-align: left;
                padding-left: 5px;
            }

            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }

            .loading-overlay.active {
                display: flex;
            }

            .loading-content {
                background: white;
                padding: 30px;
                border-radius: 15px;
                text-align: center;
                color: #333;
            }

            .loading-content .loading-spinner {
                width: 50px;
                height: 50px;
                border-color: rgba(74, 71, 163, 0.3);
                border-top-color: #4a47a3;
                margin: 0 auto 20px;
            }

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

            /* Face Detection Box */
            .face-box {
                position: absolute;
                border: 3px solid #4caf50;
                border-radius: 8px;
                box-shadow: 0 0 20px rgba(76, 175, 80, 0.6);
                transition: all 0.3s ease;
            }

            .face-box.smile-waiting {
                border-color: #ff9800;
                box-shadow: 0 0 20px rgba(255, 152, 0, 0.6);
                animation: waiting-pulse 1s infinite;
            }

            @keyframes waiting-pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }

            @media (max-width: 768px) {
                .header {
                    padding: 15px 20px;
                    flex-direction: column;
                    gap: 20px;
                }

                .nav-links {
                    gap: 20px;
                }

                .hero-section {
                    flex-direction: column;
                    padding: 50px 20px;
                    text-align: center;
                }

                .hero-content {
                    padding-right: 0;
                    margin-bottom: 30px;
                }

                .hero-title {
                    font-size: 36px;
                }

                .services-preview {
                    margin: 20px;
                    padding: 30px 20px;
                }

                .row {
                    flex-direction: column;
                }

                .modal-content {
                    width: 95%;
                    margin: 1% auto;
                }

                .camera-container {
                    width: 100%;
                    max-width: 350px;
                }

                .camera-controls {
                    gap: 8px;
                }

                .btn-camera {
                    padding: 8px 16px;
                    font-size: 12px;
                }

                .countdown-overlay {
                    width: 120px;
                    height: 120px;
                    font-size: 2.5rem;
                }
            }
        </style>
    </head>
    <body>
        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <p>Loading Advanced Face Recognition Models...</p>
                <small>Please wait while we initialize the AI...</small>
            </div>
        </div>

        <!-- Header -->
        <header class="header">
            <div class="logo">BARANGAY CAWIT RESIDENT PORTAL</div>
            <nav>
                <ul class="nav-links">
                    <li onclick="scrollToSection('home')">HOME</li>
                    <li onclick="scrollToSection('about')">ABOUT</li>
                </ul>
            </nav>
            <div class="auth-buttons">
                <button class="btn btn-signin" onclick="openModal('signinModal')">SIGN IN</button>
                <button class="btn btn-signup" onclick="openModal('signupModal')">SIGN UP</button>
            </div>
        </header>

        <!-- Hero Section -->
        <section id="home" class="hero-section">
            <div class="hero-content">
                <div class="hero-logo"></div>
                <h1 class="hero-title">WELCOME TO<br>BARANGAY CAWIT PORTAL</h1>
                <p class="hero-subtitle">Barangay Cawit, Zone II, Zamboanga City, Zamboanga Del Sur, Region IX, Philippines</p>
                <p class="hero-details">Open Hours of Barangay: Monday to Friday (8AM - 5PM)<br>
                    <strong>Enhanced with AI Face Recognition Technology</strong>
                </p>
                <button class="btn-about" onclick="scrollToSection('about')">ABOUT US</button>
            </div>
            <div class="hero-image">
                <div class="building-icon">🏛️</div>
            </div>
        </section>

        <!-- Services Preview -->
        <section id="services" class="services-preview">
            <h2 style="font-size: 36px; margin-bottom: 20px;">Resident Services</h2>
            <p style="opacity: 0.8; margin-bottom: 30px;">Access barangay services online for your convenience</p>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">📄</div>
                    <h3>Document Requests</h3>
                    <p>Request barangay certificates, clearances, and permits online</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">📋</div>
                    <h3>Incident Reports</h3>
                    <p>Report incidents and track their resolution status</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">📢</div>
                    <h3>Announcements</h3>
                    <p>Stay updated with barangay news and announcements</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">💬</div>
                    <h3>Complaints</h3>
                    <p>Submit complaints and suggestions to barangay officials</p>
                </div>
            </div>
        </section>

        <!-- Sign In Modal -->
        <div id="signinModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('signinModal')">&times;</span>
                <div class="modal-header">
                    <h2 class="modal-title">Welcome Back</h2>
                    <p class="modal-subtitle">Sign in to your resident account</p>
                </div>
                <div class="modal-body">
                    <div id="signinAlert" style="display: none;"></div>
                    <form id="signinForm">
                        <div class="form-group">
                            <label for="signinEmail">Username or Email</label>
                            <input type="text" id="signinUsername" name="username" placeholder="Enter your username or email" required>
                        </div>
                        <div class="form-group">
                            <label for="signinPassword">Password</label>
                            <input type="password" id="signinPassword" name="password" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="btn-submit">SIGN IN</button>
                    </form>
                    <div class="modal-footer">
                        <p>Don't have an account? <a onclick="switchModal('signinModal', 'signupModal')">Sign up here</a></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sign Up Modal -->
        <div id="signupModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('signupModal')">&times;</span>
                <div class="modal-header">
                    <h2 class="modal-title">Create Account</h2>
                    <p class="modal-subtitle">Register with AI Face Recognition</p>
                </div>
                <div class="modal-body">
                    <div id="signupAlert" style="display: none;"></div>
                    
                    <!-- Enhanced Camera Section for Face Verification -->
                    <div id="faceVerificationSection" class="camera-section">
                        <label style="display: block; margin-bottom: 15px; font-weight: bold;">
                            🤖 AI Face Recognition Verification <span style="color: #ff6b6b;">*</span>
                        </label>
                        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 15px; font-size: 14px;">
                            Our advanced AI will detect your face, ask you to smile, then verify your identity in our database
                            <br><small id="modelStatus" style="color: #ffd700;">⏳ Loading AI models...</small>
                        </p>
                        
                        <div class="camera-container">
                            <video id="signupVideo" autoplay muted playsinline></video>
                            <canvas id="signupCanvas"></canvas>
                            <div id="signupCameraPlaceholder" class="camera-placeholder">
                                <div style="text-align: center;">
                                    <div style="font-size: 72px; margin-bottom: 10px;">📷</div>
                                    <div style="font-size: 16px;">AI Face Recognition Ready</div>
                                </div>
                            </div>
                            
                            <!-- Face Guide Overlay -->
                            <div id="faceGuideOverlay" class="face-guide-overlay" style="display: none;">
                                <span class="face-guide-text">Position your face here</span>
                            </div>
                            
                            <!-- Face Detection Indicator -->
                            <div id="faceIndicator" class="face-indicator not-detected" style="display: none;">
                                <i class="fas fa-times-circle"></i>
                                <span>Searching for face...</span>
                            </div>
                            
                            <!-- Countdown Overlay -->
                            <div id="countdownOverlay" class="countdown-overlay" style="display: none;">
                                <div id="countdownNumber">3</div>
                                <div class="countdown-text">Smile!</div>
                            </div>
                            
                            <!-- Recognition Result -->
                            <div id="recognitionResult" class="recognition-result">
                                <img id="resultPhoto" class="result-photo" src="" alt="Resident photo">
                                <div id="resultName" class="result-name"></div>
                                <div id="resultConfidence" class="result-confidence"></div>
                                <div id="resultStatus" class="result-status"></div>
                            </div>
                        </div>
                        
                        <div class="camera-controls">
                            <button type="button" id="startSignupCamera" class="btn-camera primary">
                                <i class="fas fa-play"></i> <span id="cameraButtonText">Start AI Recognition</span>
                            </button>
                            <button type="button" id="stopSignupCamera" class="btn-camera danger" style="display: none;">
                                <i class="fas fa-stop"></i> Stop Camera
                            </button>
                            <button type="button" id="retryRecognition" class="btn-camera secondary" style="display: none;">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    </div>

                    <!-- Registration Form (Hidden initially) -->
                    <form id="signupForm">
                        <input type="hidden" id="resident_id" name="resident_id">
                        
                        <div class="form-group">
                            <label for="username">Username <span style="color: #ff6b6b;">*</span></label>
                            <input type="text" id="username" name="username" placeholder="Choose a username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span style="color: #ff6b6b;">*</span></label>
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                        </div>
                        
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="signupPassword">Password <span style="color: #ff6b6b;">*</span></label>
                                    <input type="password" id="signupPassword" name="password" placeholder="Create password" required>
                                    <div class="password-requirements">
                                        • At least 8 characters long<br>
                                        • Include numbers and letters
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm Password <span style="color: #ff6b6b;">*</span></label>
                                    <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm password" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-submit">CREATE ACCOUNT</button>
                    </form>
                    
                    <div class="modal-footer">
                        <p>Already have an account? <a onclick="switchModal('signupModal', 'signinModal')">Sign in here</a></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Enhanced Global Variables
            let signupStream = null;
            let faceDetectionInterval = null;
            let modelsLoaded = false;
            let currentFaceDescriptor = null;
            let verifiedResident = null;
            let isProcessing = false;
            let faceDetected = false;
            let smileDetected = false;
            let countdownActive = false;

            // Enhanced Face API Model Loading with Multiple CDN Fallbacks
            async function loadModels() {
                const MODEL_URLS = [
                    'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights/',
                    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/',
                    'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/'
                ];
                
                document.getElementById('loadingOverlay').classList.add('active');
                updateModelStatus('⏳ Loading AI models...');
                console.log('🤖 Loading Face Recognition AI Models...');
                
                for (let i = 0; i < MODEL_URLS.length; i++) {
                    const MODEL_URL = MODEL_URLS[i];
                    console.log(`Trying model source ${i + 1}:`, MODEL_URL);
                    updateModelStatus(`⏳ Trying source ${i + 1}/${MODEL_URLS.length}...`);
                    
                    try {
                        // Load models one by one for better error handling
                        console.log('Loading TinyFaceDetector...');
                        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                        
                        console.log('Loading FaceLandmark68Net...');
                        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                        
                        console.log('Loading FaceRecognitionNet...');
                        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                        
                        console.log('Loading FaceExpressionNet...');
                        await faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL);
                        
                        // Verify all models are loaded
                        if (faceapi.nets.tinyFaceDetector.isLoaded && 
                            faceapi.nets.faceLandmark68Net.isLoaded && 
                            faceapi.nets.faceRecognitionNet.isLoaded && 
                            faceapi.nets.faceExpressionNet.isLoaded) {
                            
                            modelsLoaded = true;
                            console.log('✅ All AI models loaded successfully from:', MODEL_URL);
                            updateModelStatus('✅ AI Ready! Face recognition enabled');
                            document.getElementById('loadingOverlay').classList.remove('active');
                            return; // Success, exit the loop
                        }
                    } catch (error) {
                        console.warn(`❌ Failed to load from ${MODEL_URL}:`, error);
                        continue; // Try next URL
                    }
                }
                
                // If we get here, all URLs failed
                console.error('❌ All model loading attempts failed');
                document.getElementById('loadingOverlay').classList.remove('active');
                updateModelStatus('⚠️ AI models failed - Basic mode available');
                
                // Show error and provide manual option
                const loadingContent = document.querySelector('.loading-content');
                loadingContent.innerHTML = `
                    <div style="color: #e74c3c; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p style="margin-bottom: 1rem;"><strong>Failed to load AI models</strong></p>
                        <p style="font-size: 0.9rem; margin-bottom: 1.5rem;">This might be due to network issues or CDN problems.</p>
                        <button onclick="retryModelLoading()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-redo"></i> Retry Loading
                        </button>
                        <button onclick="useBasicMode()" style="background: #95a5a6; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                            Continue Without AI
                        </button>
                    </div>
                `;
                
                document.getElementById('loadingOverlay').classList.add('active');
            }
            
            // Update model status indicator
            function updateModelStatus(message) {
                const statusEl = document.getElementById('modelStatus');
                if (statusEl) {
                    statusEl.textContent = message;
                }
                
                // Update camera button text based on status
                const buttonTextEl = document.getElementById('cameraButtonText');
                if (buttonTextEl) {
                    if (message.includes('✅')) {
                        buttonTextEl.textContent = 'Start AI Recognition';
                    } else if (message.includes('⚠️')) {
                        buttonTextEl.textContent = 'Start Camera (Basic)';
                    } else {
                        buttonTextEl.textContent = 'Loading...';
                    }
                }
            }
            
            // Retry model loading
            window.retryModelLoading = function() {
                document.getElementById('loadingOverlay').classList.remove('active');
                setTimeout(() => {
                    loadModels();
                }, 500);
            };
            
            // Use basic mode without AI
            window.useBasicMode = function() {
                modelsLoaded = false; // Keep false but allow basic functionality
                document.getElementById('loadingOverlay').classList.remove('active');
                updateModelStatus('📷 Basic mode - Manual photo capture enabled');
                
                // Modify camera start function for basic mode
                window.basicMode = true;
                alert('⚠️ Running in basic mode without AI face recognition. You can still register normally.');
            };

            // Initialize on page load with better error handling
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🚀 Initializing Face Recognition System...');
                console.log('FaceAPI available:', typeof faceapi !== 'undefined');
                
                // Wait a bit for face-api to load if it's deferred
                setTimeout(() => {
                    if (typeof faceapi === 'undefined') {
                        console.error('❌ Face-API not loaded after timeout');
                        updateModelStatus('❌ Face API failed to load');
                        document.getElementById('loadingOverlay').classList.remove('active');
                        
                        // Offer basic mode immediately
                        setTimeout(() => {
                            if (confirm('Face recognition failed to load. Would you like to continue in basic mode without AI face recognition?')) {
                                useBasicMode();
                            }
                        }, 1000);
                        return;
                    }
                    
                    // Add debug info
                    window.debugInfo = function() {
                        console.log('=== FACE RECOGNITION DEBUG ===');
                        console.log('FaceAPI loaded:', typeof faceapi !== 'undefined');
                        console.log('Models loaded:', modelsLoaded);
                        if (typeof faceapi !== 'undefined') {
                            console.log('TinyFaceDetector:', faceapi.nets.tinyFaceDetector.isLoaded);
                            console.log('FaceLandmark68Net:', faceapi.nets.faceLandmark68Net.isLoaded);
                            console.log('FaceRecognitionNet:', faceapi.nets.faceRecognitionNet.isLoaded);
                            console.log('FaceExpressionNet:', faceapi.nets.faceExpressionNet.isLoaded);
                        }
                        console.log('Camera stream:', signupStream ? 'Active' : 'Inactive');
                        console.log('Basic mode:', window.basicMode ? 'Yes' : 'No');
                        console.log('=============================');
                    };
                    
                    loadModels();
                }, 2000); // Wait 2 seconds for face-api to load
            });

            // Enhanced Face Detection with Smile Recognition
            async function detectFaceAndExpression(video) {
                if (!modelsLoaded || !video || video.readyState !== 4) {
                    return null;
                }

                try {
                    // Detect faces with landmarks, descriptors, and expressions
                    const detections = await faceapi
                        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                            inputSize: 416,
                            scoreThreshold: 0.5
                        }))
                        .withFaceLandmarks()
                        .withFaceDescriptors()
                        .withFaceExpressions();

                    if (detections.length > 0) {
                        const detection = detections[0];
                        const expressions = detection.expressions;
                        
                        // Update face detection status
                        faceDetected = true;
                        
                        // Draw face detection box
                        drawFaceBox(video, detection);
                        
                        // Check for smile (happiness expression)
                        const happinessScore = expressions.happy || 0;
                        const isSmiling = happinessScore > 0.7; // Threshold for smile detection
                        
                        console.log('Face detected. Happiness score:', happinessScore.toFixed(3));
                        
                        if (isSmiling && !smileDetected && !countdownActive) {
                            console.log('😊 Smile detected! Starting countdown...');
                            smileDetected = true;
                            updateFaceIndicator('smile-detected', 'Smile Detected!', 'fas fa-smile');
                            startCountdown();
                        } else if (isSmiling) {
                            updateFaceIndicator('smile-detected', 'Keep Smiling!', 'fas fa-smile');
                        } else if (!isSmiling && !countdownActive) {
                            updateFaceIndicator('smile-waiting', 'Please Smile', 'fas fa-meh');
                            smileDetected = false;
                        }
                        
                        return detection.descriptor;
                    } else {
                        // No face detected
                        faceDetected = false;
                        smileDetected = false;
                        updateFaceIndicator('not-detected', 'No Face Detected', 'fas fa-times-circle');
                        clearFaceBox();
                        return null;
                    }
                } catch (error) {
                    console.error('Face detection error:', error);
                    updateFaceIndicator('not-detected', 'Detection Error', 'fas fa-exclamation-triangle');
                    return null;
                }
            }

            // Draw face detection box
            function drawFaceBox(video, detection) {
                const canvas = document.getElementById('signupCanvas');
                const displaySize = { width: video.videoWidth, height: video.videoHeight };
                faceapi.matchDimensions(canvas, displaySize);
                
                const resizedDetection = faceapi.resizeResults(detection, displaySize);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Draw face box
                const box = resizedDetection.detection.box;
                ctx.strokeStyle = smileDetected ? '#9c27b0' : '#4caf50';
                ctx.lineWidth = 3;
                ctx.strokeRect(box.x, box.y, box.width, box.height);
                
                // Draw corner indicators
                const cornerLength = 20;
                ctx.beginPath();
                // Top-left
                ctx.moveTo(box.x, box.y + cornerLength);
                ctx.lineTo(box.x, box.y);
                ctx.lineTo(box.x + cornerLength, box.y);
                // Top-right
                ctx.moveTo(box.x + box.width - cornerLength, box.y);
                ctx.lineTo(box.x + box.width, box.y);
                ctx.lineTo(box.x + box.width, box.y + cornerLength);
                // Bottom-left
                ctx.moveTo(box.x, box.y + box.height - cornerLength);
                ctx.lineTo(box.x, box.y + box.height);
                ctx.lineTo(box.x + cornerLength, box.y + box.height);
                // Bottom-right
                ctx.moveTo(box.x + box.width - cornerLength, box.y + box.height);
                ctx.lineTo(box.x + box.width, box.y + box.height);
                ctx.lineTo(box.x + box.width, box.y + box.height - cornerLength);
                ctx.stroke();
            }

            // Clear face detection box
            function clearFaceBox() {
                const canvas = document.getElementById('signupCanvas');
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }

            // Update face indicator
            function updateFaceIndicator(className, text, iconClass) {
                const indicator = document.getElementById('faceIndicator');
                indicator.className = `face-indicator ${className}`;
                indicator.innerHTML = `<i class="${iconClass}"></i><span>${text}</span>`;
                indicator.style.display = 'flex';
            }

            // Enhanced Countdown with better visual feedback
            function startCountdown() {
                if (countdownActive || isProcessing) return;
                
                countdownActive = true;
                const overlay = document.getElementById('countdownOverlay');
                const numberEl = document.getElementById('countdownNumber');
                
                overlay.style.display = 'flex';
                
                let count = 3;
                numberEl.textContent = count;
                
                const countdownInterval = setInterval(() => {
                    count--;
                    if (count > 0) {
                        numberEl.textContent = count;
                    } else {
                        clearInterval(countdownInterval);
                        overlay.style.display = 'none';
                        countdownActive = false;
                        
                        // Auto capture and verify
                        captureAndVerifyFace();
                    }
                }, 1000);
            }

            // Capture and verify face with the database
            async function captureAndVerifyFace() {
                if (isProcessing || !currentFaceDescriptor) {
                    console.log('Cannot capture: processing =', isProcessing, 'descriptor =', !!currentFaceDescriptor);
                    return;
                }
                
                isProcessing = true;
                updateFaceIndicator('smile-detected', 'Processing...', 'fas fa-cog fa-spin');
                
                try {
                    console.log('🔍 Verifying face with database...');
                    
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'verify_face');
                    formData.append('face_descriptor', JSON.stringify(Array.from(currentFaceDescriptor)));

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    console.log('Verification response:', data);

                    if (data.success) {
                        if (data.found && data.resident) {
                            const resident = data.resident;
                            
                            if (resident.has_account) {
                                showRecognitionResult(resident, 'warning', 'Account exists! Please sign in instead.');
                                setTimeout(() => {
                                    switchModal('signupModal', 'signinModal');
                                }, 3000);
                            } else {
                                showRecognitionResult(resident, 'success', 'Identity verified! Complete registration below.');
                                showVerificationSuccess(resident);
                            }
                        } else {
                            showRecognitionResult(null, 'error', 'Face not recognized in our database.');
                            setTimeout(resetCamera, 3000);
                        }
                    } else {
                        showAlert('signupAlert', data.message || 'Verification failed. Please try again.', 'error');
                        resetCamera();
                    }
                } catch (error) {
                    console.error('Verification error:', error);
                    showAlert('signupAlert', 'Network error. Please check your connection and try again.', 'error');
                    resetCamera();
                }
                
                isProcessing = false;
            }

            // Show recognition result
            function showRecognitionResult(resident, type, message) {
                const resultDiv = document.getElementById('recognitionResult');
                const photoEl = document.getElementById('resultPhoto');
                const nameEl = document.getElementById('resultName');
                const confidenceEl = document.getElementById('resultConfidence');
                const statusEl = document.getElementById('resultStatus');
                
                if (resident) {
                    photoEl.src = resident.photo_path || 'default-avatar.png';
                    nameEl.textContent = resident.full_name;
                    confidenceEl.textContent = `Confidence: ${resident.confidence}%`;
                } else {
                    photoEl.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
                    nameEl.textContent = 'Unknown Person';
                    confidenceEl.textContent = '';
                }
                
                statusEl.textContent = message;
                statusEl.className = `result-status ${type}`;
                
                resultDiv.classList.add('show');
                
                // Hide face indicator while showing result
                document.getElementById('faceIndicator').style.display = 'none';
            }

            // Show verification success and proceed to registration
            function showVerificationSuccess(resident) {
                setTimeout(() => {
                    // Hide camera section and show form
                    document.getElementById('faceVerificationSection').style.display = 'none';
                    document.getElementById('signupForm').style.display = 'block';
                    document.getElementById('resident_id').value = resident.id;
                    
                    showAlert('signupAlert', `Welcome ${resident.full_name}! Please complete your registration.`, 'success');
                    
                    verifiedResident = resident;
                }, 2000);
            }

            // Reset camera for retry
            function resetCamera() {
                const resultDiv = document.getElementById('recognitionResult');
                resultDiv.classList.remove('show');
                
                // Reset states
                faceDetected = false;
                smileDetected = false;
                countdownActive = false;
                isProcessing = false;
                currentFaceDescriptor = null;
                
                // Show retry button
                document.getElementById('retryRecognition').style.display = 'inline-block';
                updateFaceIndicator('not-detected', 'Click "Try Again" to retry', 'fas fa-redo');
            }

            // Start camera with enhanced features
            async function startSignupCamera() {
                // Check if we're in basic mode or models aren't loaded
                if (!modelsLoaded && !window.basicMode) {
                    showAlert('signupAlert', 'AI models are still loading. Please wait or try the retry button...', 'warning');
                    return;
                }

                try {
                    const video = document.getElementById('signupVideo');
                    const constraints = {
                        video: {
                            width: { ideal: 640 },
                            height: { ideal: 480 },
                            facingMode: 'user'
                        }
                    };

                    console.log('🎥 Starting camera...');
                    signupStream = await navigator.mediaDevices.getUserMedia(constraints);
                    video.srcObject = signupStream;
                    
                    video.onloadedmetadata = () => {
                        video.play();
                        console.log('✅ Camera started successfully');
                        
                        // Show video and hide placeholder
                        document.getElementById('signupVideo').style.display = 'block';
                        document.getElementById('signupCameraPlaceholder').style.display = 'none';
                        document.getElementById('faceGuideOverlay').style.display = 'block';
                        
                        // Update buttons
                        document.getElementById('startSignupCamera').style.display = 'none';
                        document.getElementById('stopSignupCamera').style.display = 'inline-block';
                        document.getElementById('retryRecognition').style.display = 'none';
                        
                        // Reset states
                        faceDetected = false;
                        smileDetected = false;
                        countdownActive = false;
                        isProcessing = false;
                        
                        if (modelsLoaded) {
                            // Start AI face detection loop
                            startFaceDetectionLoop();
                            updateFaceIndicator('not-detected', 'Searching for face...', 'fas fa-search');
                        } else if (window.basicMode) {
                            // Basic mode - manual capture
                            showBasicModeInterface();
                        }
                    };
                    
                } catch (error) {
                    console.error('❌ Camera access error:', error);
                    showAlert('signupAlert', 'Could not access camera. Please allow camera permissions and try again.', 'error');
                }
            }
            
            // Show basic mode interface (without AI)
            function showBasicModeInterface() {
                updateFaceIndicator('not-detected', 'Basic Mode - Manual Capture', 'fas fa-camera');
                
                // Add manual capture button
                const cameraControls = document.querySelector('.camera-controls');
                if (!document.getElementById('manualCapture')) {
                    const manualBtn = document.createElement('button');
                    manualBtn.type = 'button';
                    manualBtn.id = 'manualCapture';
                    manualBtn.className = 'btn-camera primary';
                    manualBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
                    manualBtn.onclick = manualCapture;
                    cameraControls.insertBefore(manualBtn, document.getElementById('stopSignupCamera'));
                }
                
                showAlert('signupAlert', 'Basic mode active. Position yourself and click "Capture Photo" when ready.', 'info');
            }
            
            // Manual capture for basic mode
            function manualCapture() {
                const video = document.getElementById('signupVideo');
                if (!video || video.readyState !== 4) {
                    showAlert('signupAlert', 'Video not ready. Please wait...', 'warning');
                    return;
                }
                
                // Show countdown
                updateFaceIndicator('smile-detected', 'Get Ready!', 'fas fa-camera');
                startBasicCountdown();
            }
            
            // Basic countdown without AI
            function startBasicCountdown() {
                if (countdownActive) return;
                
                countdownActive = true;
                const overlay = document.getElementById('countdownOverlay');
                const numberEl = document.getElementById('countdownNumber');
                
                overlay.style.display = 'flex';
                
                let count = 3;
                numberEl.textContent = count;
                
                const countdownInterval = setInterval(() => {
                    count--;
                    if (count > 0) {
                        numberEl.textContent = count;
                    } else {
                        clearInterval(countdownInterval);
                        overlay.style.display = 'none';
                        countdownActive = false;
                        
                        // Capture photo and proceed without verification
                        captureBasicPhoto();
                    }
                }, 1000);
            }
            
            // Capture photo in basic mode
            function captureBasicPhoto() {
                // Show processing
                updateFaceIndicator('smile-detected', 'Processing...', 'fas fa-cog fa-spin');
                
                // Simulate processing time
                setTimeout(() => {
                    showRecognitionResult(null, 'info', 'Photo captured successfully!');
                    
                    setTimeout(() => {
                        // For basic mode, skip verification and go directly to form
                        document.getElementById('faceVerificationSection').style.display = 'none';
                        document.getElementById('signupForm').style.display = 'block';
                        
                        // Set a dummy resident ID for basic registration
                        document.getElementById('resident_id').value = '0'; // 0 indicates basic mode
                        
                        showAlert('signupAlert', 'Please complete your registration. Admin will verify your identity later.', 'info');
                    }, 2000);
                }, 1500);
            }

            // Start face detection loop
            function startFaceDetectionLoop() {
                const video = document.getElementById('signupVideo');
                
                faceDetectionInterval = setInterval(async () => {
                    if (!isProcessing && !countdownActive && video.readyState === 4) {
                        currentFaceDescriptor = await detectFaceAndExpression(video);
                    }
                }, 300); // Check every 300ms for better performance
            }

            // Stop camera
            function stopSignupCamera() {
                if (signupStream) {
                    signupStream.getTracks().forEach(track => track.stop());
                    signupStream = null;
                }
                
                if (faceDetectionInterval) {
                    clearInterval(faceDetectionInterval);
                    faceDetectionInterval = null;
                }
                
                // Reset UI
                document.getElementById('signupVideo').style.display = 'none';
                document.getElementById('signupCameraPlaceholder').style.display = 'flex';
                document.getElementById('faceGuideOverlay').style.display = 'none';
                document.getElementById('faceIndicator').style.display = 'none';
                document.getElementById('countdownOverlay').style.display = 'none';
                document.getElementById('recognitionResult').classList.remove('show');
                
                // Update buttons
                document.getElementById('startSignupCamera').style.display = 'inline-block';
                document.getElementById('stopSignupCamera').style.display = 'none';
                document.getElementById('retryRecognition').style.display = 'none';
                
                // Clear canvas
                clearFaceBox();
                
                // Reset states
                faceDetected = false;
                smileDetected = false;
                countdownActive = false;
                isProcessing = false;
                currentFaceDescriptor = null;
            }

            // Retry recognition
            function retryRecognition() {
                const resultDiv = document.getElementById('recognitionResult');
                resultDiv.classList.remove('show');
                
                // Reset states
                faceDetected = false;
                smileDetected = false;
                countdownActive = false;
                isProcessing = false;
                currentFaceDescriptor = null;
                
                // Hide retry button
                document.getElementById('retryRecognition').style.display = 'none';
                
                // Restart detection
                startFaceDetectionLoop();
                updateFaceIndicator('not-detected', 'Searching for face...', 'fas fa-search');
            }

            // Modal functions
            function openModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
                document.body.style.overflow = 'auto';
                
                // Stop camera when closing signup modal
                if (modalId === 'signupModal' && signupStream) {
                    stopSignupCamera();
                    
                    // Reset form if needed
                    if (document.getElementById('signupForm').style.display === 'block') {
                        document.getElementById('faceVerificationSection').style.display = 'block';
                        document.getElementById('signupForm').style.display = 'none';
                        document.getElementById('signupForm').reset();
                    }
                }
                
                // Clear alerts
                document.getElementById('signinAlert').style.display = 'none';
                document.getElementById('signupAlert').style.display = 'none';
            }

            function switchModal(currentModal, targetModal) {
                closeModal(currentModal);
                openModal(targetModal);
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    if (event.target === modals[i]) {
                        closeModal(modals[i].id);
                    }
                }
            }

            // Smooth scrolling
            function scrollToSection(sectionId) {
                document.getElementById(sectionId).scrollIntoView({
                    behavior: 'smooth'
                });
            }

            // Show alert messages
            function showAlert(alertId, message, type) {
                const alertDiv = document.getElementById(alertId);
                alertDiv.className = `alert alert-${type}`;
                
                let icon = '';
                switch(type) {
                    case 'success': icon = 'fas fa-check-circle'; break;
                    case 'error': icon = 'fas fa-exclamation-circle'; break;
                    case 'warning': icon = 'fas fa-exclamation-triangle'; break;
                    case 'info': icon = 'fas fa-info-circle'; break;
                }
                
                alertDiv.innerHTML = `<i class="${icon}"></i>${message}`;
                alertDiv.style.display = 'flex';
            }

            // Event listeners for camera buttons
            document.getElementById('startSignupCamera').addEventListener('click', startSignupCamera);
            document.getElementById('stopSignupCamera').addEventListener('click', stopSignupCamera);
            document.getElementById('retryRecognition').addEventListener('click', retryRecognition);

            // Handle sign in form
            document.getElementById('signinForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'login');

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('signinAlert', data.message, 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect || 'dashboard.php';
                        }, 1500);
                    } else {
                        showAlert('signinAlert', data.message, 'error');
                    }
                } catch (error) {
                    showAlert('signinAlert', 'An error occurred. Please try again.', 'error');
                }
            });

            // Handle sign up form
            document.getElementById('signupForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const password = document.getElementById('signupPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                if (password !== confirmPassword) {
                    showAlert('signupAlert', 'Passwords do not match!', 'error');
                    return;
                }
                
                if (password.length < 8) {
                    showAlert('signupAlert', 'Password must be at least 8 characters long!', 'error');
                    return;
                }

                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'complete_signup');

                try {
                    showAlert('signupAlert', 'Creating your account...', 'info');
                    
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('signupAlert', data.message, 'success');
                        
                        // Reset form and UI
                        this.reset();
                        document.getElementById('faceVerificationSection').style.display = 'block';
                        document.getElementById('signupForm').style.display = 'none';
                        
                        setTimeout(() => {
                            switchModal('signupModal', 'signinModal');
                        }, 3000);
                    } else {
                        showAlert('signupAlert', data.message, 'error');
                    }
                } catch (error) {
                    showAlert('signupAlert', 'An error occurred. Please try again.', 'error');
                }
            });

            // Add interactive animations
            document.addEventListener('DOMContentLoaded', function() {
                const serviceCards = document.querySelectorAll('.service-card');
                
                serviceCards.forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-10px) scale(1.02)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0) scale(1)';
                    });
                });
            });

            // Clean up resources when page is unloaded
            window.addEventListener('beforeunload', function() {
                stopSignupCamera();
            });

            // Debug function for testing
            window.debugFaceRecognition = function() {
                console.log('=== Enhanced Face Recognition Debug ===');
                console.log('Models loaded:', modelsLoaded);
                console.log('Face detected:', faceDetected);
                console.log('Smile detected:', smileDetected);
                console.log('Countdown active:', countdownActive);
                console.log('Is processing:', isProcessing);
                console.log('Current descriptor:', currentFaceDescriptor ? 'Present' : 'None');
                console.log('Verified resident:', verifiedResident ? verifiedResident.full_name : 'None');
                console.log('=======================================');
            };
        </script>
    </body>
    </html>