<?php
require_once 'config.php';

$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // OCR verification is done client-side, server just receives the verified text
        $clearance_text = isset($_POST['clearance_text']) ? $_POST['clearance_text'] : '';
        $clearance_image = isset($_POST['clearance_image']) ? $_POST['clearance_image'] : '';
        
        if (empty($clearance_text) || empty($clearance_image)) {
            $error_message = "Please capture your barangay clearance.";
        } else if (stripos($clearance_text, 'Barangay Cawit') === false) {
            $error_message = "This clearance does not appear to be from Barangay Cawit.";
        } else {
            // Save the clearance image
            $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $clearance_image));
            $filename = 'uploads/clearances/' . uniqid() . '.jpg';
            
            // Create directory if it doesn't exist
            if (!file_exists('uploads/clearances/')) {
                mkdir('uploads/clearances/', 0777, true);
            }
            
            if (file_put_contents($filename, $image_data)) {
                // Store clearance info in session for step 2
                $_SESSION['clearance_file'] = $filename;
                $_SESSION['clearance_text'] = $clearance_text;
                $step = 2;
            } else {
                $error_message = "Error saving clearance image.";
            }
        }
    } elseif ($step === 2) {
        // Save resident profiling data
        $clearance_no = mysqli_real_escape_string($connection, $_POST['clearance_no']);
        $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
        $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
        $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
        $age = intval($_POST['age']);
        $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
        $zone = mysqli_real_escape_string($connection, $_POST['zone']);
        $suffix = mysqli_real_escape_string($connection, $_POST['suffix']);

        // Check again for clearance validity before saving
        $clearance_query = "SELECT * FROM barangay_clearance WHERE clearance_no = '$clearance_no' AND status = 'valid' LIMIT 1";
        $result = mysqli_query($connection, $clearance_query);
        if ($result && mysqli_num_rows($result) > 0) {
            $insert_query = "INSERT INTO residents (first_name, middle_initial, last_name, age, contact_number, zone, suffix, status) 
                VALUES ('$first_name', '$middle_initial', '$last_name', $age, '$contact_number', '$zone', '$suffix', 'pending')";
            if (mysqli_query($connection, $insert_query)) {
                $success_message = "Your profiling has been submitted successfully. Please wait for approval.";
                $step = 3;
            } else {
                $error_message = "Error saving your data: " . mysqli_error($connection);
            }
        } else {
            $error_message = "Barangay clearance not found or not valid.";
            $step = 1;
        }
    } else if (isset($_POST['action'])) {
        if ($_POST['action'] === 'submit_resident') {
            $clearance_image = $_POST['clearance_image'] ?? '';
            $clearance_text = $_POST['clearance_text'] ?? '';
            
            if (empty($clearance_image) || empty($clearance_text)) {
                $error_message = "Please capture a valid barangay clearance.";
            } else if (stripos($clearance_text, 'Barangay Cawit') === false) {
                $error_message = "This clearance does not appear to be from Barangay Cawit.";
            } else {
                // Process and save the resident data
                $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
                $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
                $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
                $age = intval($_POST['age']);
                $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
                $zone = mysqli_real_escape_string($connection, $_POST['zone']);
                $suffix = mysqli_real_escape_string($connection, $_POST['suffix'] ?? '');

                // Save clearance image
                $clearance_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $clearance_image));
                $clearance_filename = 'uploads/clearances/' . uniqid() . '.jpg';
                
                if (!file_exists('uploads/clearances/')) {
                    mkdir('uploads/clearances/', 0777, true);
                }
                
                if (file_put_contents($clearance_filename, $clearance_data)) {
                    // Insert into online_profiling_requests instead of residents
                    $insert_query = "INSERT INTO online_profiling_requests (
                        first_name, middle_initial, last_name, age, 
                        contact_number, zone, suffix, clearance_path, 
                        clearance_text, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    
                    $stmt = mysqli_prepare($connection, $insert_query);
                    mysqli_stmt_bind_param($stmt, "sssisssss", 
                        $first_name, 
                        $middle_initial, 
                        $last_name, 
                        $age, 
                        $contact_number, 
                        $zone, 
                        $suffix, 
                        $clearance_filename,
                        $clearance_text
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Your profile request has been submitted successfully. The barangay office will review your application and contact you through your provided number.";
                        mysqli_stmt_close($stmt);
                    } else {
                        $error_message = "Error submitting your request: " . mysqli_error($connection);
                    }
                } else {
                    $error_message = "Error saving clearance image.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Resident Profiling</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add Tesseract.js for OCR -->
    <script src='https://unpkg.com/tesseract.js@v2.1.0/dist/tesseract.min.js'></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);}
        h2 { color: #3498db; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { font-weight: bold; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #ccc; }
        .btn { background: #3498db; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #2980b9; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        
        .camera-container {
            width: 100%;
            max-width: 640px;
            margin: 0 auto;
        }
        
        #video {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        #canvas {
            display: none;
        }
        
        #preview {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 1rem;
        }
        
        .loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .certificate-preview {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .cert-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .cert-logo {
            max-width: 100px;
            margin-bottom: 10px;
        }

        .cert-title {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }

        .cert-body {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .cert-footer {
            text-align: center;
            margin-top: 30px;
        }

        .signature-block {
            display: inline-block;
            text-align: center;
            margin-top: 40px;
        }

        .signature-line {
            width: 200px;
            height: 2px;
            background: #000;
            margin: 0 auto;
        }

        .cert-details {
            font-size: 14px;
            margin-top: 10px;
        }

        .detail-row {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-user-plus"></i> Online Resident Profiling</h2>
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" id="clearanceForm">
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="clearance_text" id="clearance_text">
                <input type="hidden" name="clearance_image" id="clearance_image">
                
                <div class="camera-container">
                    <video id="video" autoplay></video>
                    <canvas id="canvas"></canvas>
                    <img id="preview" alt="Captured clearance">
                    
                    <div class="loading" id="loading">
                        <i class="fas fa-spinner"></i> Processing clearance...
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn" id="captureBtn">
                            <i class="fas fa-camera"></i> Capture Clearance
                        </button>
                        <button type="button" class="btn" id="retakeBtn" style="display:none">
                            <i class="fas fa-redo"></i> Retake
                        </button>
                        <button type="submit" class="btn" id="submitBtn" style="display:none">
                            <i class="fas fa-check"></i> Submit
                        </button>
                    </div>
                </div>
            </form>
            
            <script>
                // Initialize camera
                let stream;
                const video = document.getElementById('video');
                const canvas = document.getElementById('canvas');
                const preview = document.getElementById('preview');
                const captureBtn = document.getElementById('captureBtn');
                const retakeBtn = document.getElementById('retakeBtn');
                const submitBtn = document.getElementById('submitBtn');
                const loadingDiv = document.getElementById('loading');
                const clearanceForm = document.getElementById('clearanceForm');
                
                async function startCamera() {
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ 
                            video: { 
                                width: { ideal: 1920 },
                                height: { ideal: 1080 }
                            } 
                        });
                        video.srcObject = stream;
                    } catch (err) {
                        alert('Error accessing camera: ' + err.message);
                    }
                }
                
                captureBtn.onclick = () => {
                    // Capture image
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    canvas.getContext('2d').drawImage(video, 0, 0);
                    
                    // Show preview
                    preview.src = canvas.toDataURL('image/jpeg');
                    preview.style.display = 'block';
                    video.style.display = 'none';
                    
                    // Update buttons
                    captureBtn.style.display = 'none';
                    retakeBtn.style.display = 'inline-block';
                    loadingDiv.style.display = 'block';
                    
                    // Perform OCR
                    Tesseract.recognize(
                        canvas.toDataURL('image/jpeg'),
                        'eng',
                        { logger: m => console.log(m) }
                    ).then(({ data: { text } }) => {
                        console.log('OCR Result:', text);
                        
                        // Check if text contains "Barangay Cawit"
                        if (text.toLowerCase().includes('barangay cawit')) {
                            document.getElementById('clearance_text').value = text;
                            document.getElementById('clearance_image').value = canvas.toDataURL('image/jpeg');
                            submitBtn.style.display = 'inline-block';
                            loadingDiv.style.display = 'none';
                        } else {
                            alert('This does not appear to be a Barangay Cawit clearance. Please try again.');
                            retakePhoto();
                        }
                    });
                };
                
                retakeBtn.onclick = retakePhoto;
                
                function retakePhoto() {
                    video.style.display = 'block';
                    preview.style.display = 'none';
                    captureBtn.style.display = 'inline-block';
                    retakeBtn.style.display = 'none';
                    submitBtn.style.display = 'none';
                    loadingDiv.style.display = 'none';
                }
                
                // Start camera when page loads
                startCamera();
                
                // Clean up when page is closed
                window.onbeforeunload = () => {
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                };
            </script>
        <?php elseif ($step === 2): ?>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="clearance_no" value="<?php echo htmlspecialchars($_POST['clearance_no']); ?>">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="middle_initial">Middle Initial</label>
                    <input type="text" name="middle_initial" id="middle_initial" class="form-control" maxlength="1">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="age">Age *</label>
                    <input type="number" name="age" id="age" class="form-control" min="1" max="120" required>
                </div>
                <div class="form-group">
                    <label for="contact_number">Contact Number *</label>
                    <input type="text" name="contact_number" id="contact_number" class="form-control" required maxlength="11" pattern="09\d{9}" placeholder="09XXXXXXXXX">
                </div>
                <div class="form-group">
                    <label for="zone">Zone *</label>
                    <select name="zone" id="zone" class="form-control" required>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option value="Zone <?php echo $i; ?>A">Zone <?php echo $i; ?>A</option>
                            <option value="Zone <?php echo $i; ?>B">Zone <?php echo $i; ?>B</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="suffix">Suffix (optional)</label>
                    <input type="text" name="suffix" id="suffix" class="form-control" placeholder="Jr., Sr., II, III">
                </div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Submit Profiling</button>
            </form>
        <?php elseif ($step === 3): ?>
            <div class="certificate-preview">
                <div class="cert-header">
                    <?php if (file_exists($settings['logo_path'])): ?>
                        <img src="<?php echo $settings['logo_path']; ?>" alt="Barangay Logo" class="cert-logo">
                    <?php else: ?>
                        <div class="cert-logo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building" style="font-size: 48px; color: #ccc;"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div><?php echo $settings['country']; ?></div>
                    <div><?php echo $settings['province']; ?></div>
                    <div><?php echo $settings['municipality']; ?></div>
                    <div style="font-size: 24px; font-weight: bold; margin-top: 10px;">
                        <?php echo $settings['barangay_name']; ?>
                    </div>
                    <div style="border-bottom: 3px solid #000; margin: 20px auto; width: 80%;"></div>
                    
                    <div style="margin-top: 30px; font-weight: bold;">
                        OFFICE OF THE BARANGAY CAPTAIN
                    </div>
                    
                    <h1 class="cert-title">BARANGAY CLEARANCE</h1>
                </div>

                <div class="cert-body">
                    <p style="font-weight: bold;">TO WHOM IT MAY CONCERN:</p>
                    <p style="text-indent: 50px;">
                        This is to certify that <strong style="border-bottom: 1px solid #000; padding: 0 8px;">
                        <?php echo htmlspecialchars($resident_name); ?></strong>, 
                        <strong style="border-bottom: 1px solid #000; padding: 0 8px;"><?php echo htmlspecialchars($resident_age); ?></strong> years old, 
                        and a resident of <?php echo $settings['barangay_name']; ?>, <?php echo $settings['municipality']; ?>, 
                        <?php echo str_replace('Province of ', '', $settings['province']); ?> is known to be of good moral 
                        character and law-abiding citizen in the community.
                    </p>
                    
                    <p style="text-indent: 50px; margin-top: 20px;">
                        To certify further, that he/she has no derogatory and/or criminal records filed in this barangay.
                    </p>

                    <p style="margin-top: 40px; text-align: center;">
                        <i>This clearance is being issued upon request of the above-named person for profiling purposes.</i>
                    </p>
                </div>

                <div class="cert-footer">
                    <div class="signature-block">
                        <div class="signature-line"></div>
                        <div style="font-weight: bold;"><?php echo $settings['captain_name']; ?></div>
                        <div>Barangay Captain</div>
                    </div>
                </div>

                <div class="cert-details">
                    <div class="detail-row">Contact No.: <strong><?php echo htmlspecialchars($resident_contact); ?></strong></div>
                    <div class="detail-row">Date: <strong><?php echo date('m/d/Y'); ?></strong></div>
                </div>
            </div>

            <div style="text-align:center; margin-top: 20px;">
                <a href="index.php" class="btn" style="margin-top: 1rem;">
                    <i class="fas fa-home"></i> Return to Home
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
