    <?php
    session_start();
    require_once '../../config.php';

    // Add this after session_start() and before other code
    if (isset($_GET['action']) && $_GET['action'] === 'search_resident') {
        $query = mysqli_real_escape_string($connection, $_GET['query']);
        // Remove address and status from query
        $search_sql = "SELECT id, full_name, age, zone, contact_number 
                    FROM residents 
                    WHERE full_name LIKE '%$query%' 
                    ORDER BY full_name 
                    LIMIT 5";
        
        $result = mysqli_query($connection, $search_sql);
        $residents = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $residents[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode($residents);
        exit();
    }

    // Helper: get resident info by ID with multiple fallback queries
    function getResidentInfo($connection, $resident_id) {
        $resident_id = intval($resident_id);
        $queries_to_try = [
            "SELECT id, full_name, contact_number as phone, email, '' as address FROM residents WHERE id = $resident_id",
            "SELECT id, CONCAT(first_name, ' ', IFNULL(middle_initial, ''), ' ', last_name) as full_name, contact_number as phone, email, '' as address FROM residents WHERE id = $resident_id",
            "SELECT id, full_name, contact_number, email, '' as address FROM residents WHERE id = $resident_id",
            // fallback to users table if resident data stored there
            "SELECT id, full_name, phone, email, '' as address FROM users WHERE id = $resident_id"
        ];

        foreach ($queries_to_try as $q) {
            $res = mysqli_query($connection, $q);
            if ($res && mysqli_num_rows($res) > 0) {
                $row = mysqli_fetch_assoc($res);
                if (!empty($row['full_name'])) {
                    if (empty($row['phone']) && !empty($row['contact_number'])) {
                        $row['phone'] = $row['contact_number'];
                    }
                    return $row;
                }
            }
        }

        return [
            'id' => $resident_id,
            'full_name' => 'Unknown Resident #' . $resident_id,
            'phone' => 'No contact',
            'email' => 'No email',
            'address' => 'No address'
        ];
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Get certificate settings from database (if exists)
    $settings_query = "SELECT * FROM certificate_settings WHERE id = 1";
    $settings_result = mysqli_query($connection, $settings_query);
    $settings = mysqli_fetch_assoc($settings_result);

    // Default settings if not in database
    if (!$settings) {
        $settings = [
            'barangay_name' => 'Barangay Cawit',
            'municipality' => 'Zamboanga City',
            'province' => 'Province of Zamboanga Del Sur',
            'country' => 'Republic of the Philippines',
            'captain_name' => 'N/A',
            'logo_path' => '../sec/assets/images/barangay-logo.png'
        ];
    }

    // Get statistics for nav badges
    $stats = [];

    // Get pending complaints count (with error handling)
    $pending_complaint_query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
    $result = mysqli_query($connection, $pending_complaint_query);
    if ($result) {
        $stats['pending_complaints'] = mysqli_fetch_assoc($result)['pending'];
    } else {
        $stats['pending_complaints'] = 0;
    }

    // Get pending certificate requests count (with error handling)
    $pending_cert_query = "SELECT COUNT(*) as pending FROM certificate_requests WHERE status = 'pending'";
    $result = mysqli_query($connection, $pending_cert_query);
    if ($result) {
        $stats['pending_certificates'] = mysqli_fetch_assoc($result)['pending'];
    } else {
        $stats['pending_certificates'] = 0;
    }

    // Handle certificate request actions
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id']);
        
        if ($action == 'approve') {
            $update_query = "UPDATE certificate_requests SET 
                            status = 'approved',
                            processed_date = NOW(),
                            processed_by = {$_SESSION['user_id']}
                            WHERE id = $request_id";
            if (mysqli_query($connection, $update_query)) {
                // Log the action (if table exists)
                $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status) 
                            VALUES ($request_id, 'approved', {$_SESSION['user_id']}, 'pending', 'approved')";
                mysqli_query($connection, $log_query); // Don't check for errors on log table
            }
            
            header("Location: certificates.php?tab=requests&msg=approved");
            exit();
        } elseif ($action == 'reject') {
            $reason = mysqli_real_escape_string($connection, $_POST['rejection_reason']);
            $update_query = "UPDATE certificate_requests SET 
                            status = 'rejected',
                            processed_date = NOW(),
                            processed_by = {$_SESSION['user_id']},
                            rejection_reason = '$reason'
                            WHERE id = $request_id";
            if (mysqli_query($connection, $update_query)) {
                // Log the action (if table exists)
                $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status, remarks) 
                            VALUES ($request_id, 'rejected', {$_SESSION['user_id']}, 'pending', 'rejected', '$reason')";
                mysqli_query($connection, $log_query); // Don't check for errors on log table
            }
            
            header("Location: certificates.php?tab=requests&msg=rejected");
            exit();
        } elseif ($action == 'mark_claimed') {
            $or_number = mysqli_real_escape_string($connection, $_POST['or_number']);
            $update_query = "UPDATE certificate_requests SET 
                            status = 'claimed',
                            claim_date = NOW(),
                            or_number = '$or_number'
                            WHERE id = $request_id";
            if (mysqli_query($connection, $update_query)) {
                // Log the action (if table exists)
                $log_query = "INSERT INTO certificate_request_logs (request_id, action, performed_by, old_status, new_status) 
                            VALUES ($request_id, 'claimed', {$_SESSION['user_id']}, 'approved', 'claimed')";
                mysqli_query($connection, $log_query); // Don't check for errors on log table
            }
            
            header("Location: certificates.php?tab=requests&msg=claimed");
            exit();
        }
    }

    // Get certificate requests with resident information (with better error handling)
    $certificate_requests = null;

    // First, try the full query with JOIN
    // Use a LEFT JOIN to fetch resident info but avoid selecting non-existent columns like r.address
    $requests_query = "SELECT cr.*, r.full_name as resident_name, r.contact_number AS resident_phone, r.email as resident_email,
                    cr.certificate_type as certificate_name,
                    u.full_name as processed_by_name
                    FROM certificate_requests cr
                    LEFT JOIN residents r ON cr.resident_id = r.id
                    LEFT JOIN users u ON cr.processed_by = u.id
                    ORDER BY cr.created_at DESC";
    $certificate_requests = mysqli_query($connection, $requests_query);

    // Fallback: if query failed, attempt a simpler query
    if (!$certificate_requests) {
        $requests_query = "SELECT cr.*, cr.certificate_type as certificate_name FROM certificate_requests cr ORDER BY cr.created_at DESC";
        $certificate_requests = mysqli_query($connection, $requests_query);
    }

    // If still false, set to empty array and flag error
    if (!$certificate_requests) {
        $certificate_requests = [];
        $db_error = "Database tables are not properly set up. Please run the SQL setup script first.";
    }

    // Build array with resident info and safe fallbacks
    $certificate_requests_with_info = [];
    if ($certificate_requests && is_object($certificate_requests) && mysqli_num_rows($certificate_requests) > 0) {
        while ($req = mysqli_fetch_assoc($certificate_requests)) {
            // if join didn't give a resident name, try the helper
            if (empty($req['resident_name'])) {
                $resinfo = getResidentInfo($connection, $req['resident_id'] ?? 0);
                $req['resident_name'] = $resinfo['full_name'] ?? null;
                $req['resident_phone'] = $resinfo['phone'] ?? ($req['resident_phone'] ?? null);
                $req['resident_email'] = $resinfo['email'] ?? ($req['resident_email'] ?? null);
            }

            // final defaults
            if (empty($req['resident_name'])) {
                $req['resident_name'] = 'Resident #' . ($req['resident_id'] ?? 'N/A');
            }
            if (empty($req['resident_phone'])) {
                $req['resident_phone'] = 'No contact';
            }
            if (empty($req['resident_email'])) {
                $req['resident_email'] = 'No email';
            }

            $certificate_requests_with_info[] = $req;
        }
    }

    // Handle form submission for certificate generation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
        $resident_id = !empty($_POST['resident_id']) ? intval($_POST['resident_id']) : null;
        $resident_name = mysqli_real_escape_string($connection, $_POST['resident_name'] ?? '');
        $age = mysqli_real_escape_string($connection, $_POST['age'] ?? '');
        $purpose = mysqli_real_escape_string($connection, $_POST['purpose'] ?? '');
        $or_number = mysqli_real_escape_string($connection, $_POST['or_number'] ?? '');
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : 0;
        $paid_flag = $amount_paid > 0 ? 1 : 0;

        // Insert into certificates table
        $insert_query = "INSERT INTO certificates (resident_id, resident_name, age, purpose, or_number, amount_paid, paid, issued_date, issued_by) 
                        VALUES (" . ($resident_id ? $resident_id : "NULL") . ", '$resident_name', '$age', '$purpose', '$or_number', '$amount_paid', $paid_flag, NOW(), '{$_SESSION['user_id']}')";
        if (mysqli_query($connection, $insert_query)) {
            $certificate_id = mysqli_insert_id($connection);

            // If generation is for an existing request, optionally update that request (request_id passed)
            if (!empty($_POST['request_id'])) {
                $request_id = intval($_POST['request_id']);
                @mysqli_query($connection, "UPDATE certificate_requests SET status = 'approved', processed_date = NOW(), processed_by = {$_SESSION['user_id']}, or_number = '" . mysqli_real_escape_string($connection, $or_number) . "' WHERE id = $request_id");
            }

            header("Location: certificates.php?msg=certificate_generated");
            exit();
        } else {
            $db_err = mysqli_error($connection);
        }
    }

    // Handle settings update
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
        $barangay_name = mysqli_real_escape_string($connection, $_POST['barangay_name']);
        $municipality = mysqli_real_escape_string($connection, $_POST['municipality']);
        $province = mysqli_real_escape_string($connection, $_POST['province']);
        $country = mysqli_real_escape_string($connection, $_POST['country']);
        $captain_name = mysqli_real_escape_string($connection, $_POST['captain_name']);
        
        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $upload_dir = '../sec/assets/images/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = 'barangay-logo-' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_path = $upload_path;
            }
        } else {
            $logo_path = $settings['logo_path'];
        }
        
        // Check if settings exist
        $check_query = "SELECT id FROM certificate_settings WHERE id = 1";
        $check_result = mysqli_query($connection, $check_query);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $update_query = "UPDATE certificate_settings SET 
                            barangay_name = '$barangay_name',
                            municipality = '$municipality',
                            province = '$province',
                            country = '$country',
                            captain_name = '$captain_name',
                            logo_path = '$logo_path'
                            WHERE id = 1";
        } else {
            $update_query = "INSERT INTO certificate_settings (barangay_name, municipality, province, country, captain_name, logo_path) 
                            VALUES ('$barangay_name', '$municipality', '$province', '$country', '$captain_name', '$logo_path')";
        }
        
        mysqli_query($connection, $update_query);
        header("Location: certificates.php?settings=updated");
        exit();
    }

    // Determine active tab. Default to 'requests' when there are requests to show.
    if (isset($_GET['tab'])) {
        $active_tab = $_GET['tab'];
    } else {
        $active_tab = !empty($certificate_requests_with_info) ? 'requests' : 'generate';
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barangay Certificates - Cawit Barangay Management System</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

            /* Sidebar Styles - Copied from dashboard */
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

            /* Certificate Specific Styles */
            .certificate-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                overflow: hidden;
            }

            .certificate-header {
                padding: 1.5rem;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-bottom: 1px solid #eee;
            }

            .certificate-title {
                font-size: 1.5rem;
                font-weight: bold;
                color: #333;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #333;
            }

            .form-control {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 0.95rem;
                transition: border-color 0.3s;
            }

            .form-control:focus {
                outline: none;
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            }

            .form-row {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.95rem;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
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
                background: #229954;
                transform: translateY(-1px);
            }

            .btn-secondary {
                background: #95a5a6;
                color: white;
            }

            .btn-secondary:hover {
                background: #7f8c8d;
                transform: translateY(-1px);
            }

            .btn-danger {
                background: #e74c3c;
                color: white;
            }

            .btn-danger:hover {
                background: #c0392b;
                transform: translateY(-1px);
            }

            .btn-sm {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            /* Certificate Preview Styles */
            .certificate-preview {
                border: 3px double #000;
                padding: 40px;
                margin: 20px auto;
                max-width: 800px;
                background: white;
                font-family: 'Times New Roman', serif;
            }

            .cert-header {
                text-align: center;
                margin-bottom: 30px;
            }

            .cert-logo {
                width: 100px;
                height: 100px;
                margin: 0 auto 20px;
            }

            .cert-title {
                font-size: 28px;
                font-weight: bold;
                margin: 20px 0;
                letter-spacing: 2px;
            }

            .cert-body {
                margin: 30px 0;
                line-height: 2;
                text-align: justify;
            }

            .cert-footer {
                margin-top: 80px;
                text-align: right;
            }

            .signature-block {
                display: inline-block;
                text-align: center;
                margin-top: 60px;
            }

            .signature-line {
                border-bottom: 2px solid #000;
                width: 250px;
                margin-bottom: 5px;
            }

            .cert-details {
                margin-top: 40px;
            }

            .detail-row {
                margin: 10px 0;
            }

            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }

                .no-print {
                    display: none !important;
                }

                .certificate-preview {
                    border: none;
                    margin: 0;
                    padding: 20px;
                }
            }

            .settings-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }

            .logo-preview {
                width: 150px;
                height: 150px;
                border: 2px dashed #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 10px;
                border-radius: 8px;
                background: #f8f9fa;
            }

            .logo-preview img {
                max-width: 100%;
                max-height: 100%;
            }

            .alert {
                padding: 1rem 1.5rem;
                margin-bottom: 1.5rem;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .alert-success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-info {
                background: #d1ecf1;
                color: #0c5460;
                border: 1px solid #bee5eb;
            }

            .alert-warning {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeaa7;
            }

            .alert-danger {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            /* Requests Table Styles */
            .requests-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 1rem;
            }

            .requests-table th {
                background: #f8f9fa;
                padding: 1rem;
                text-align: left;
                font-weight: 600;
                color: #333;
                border-bottom: 2px solid #dee2e6;
            }

            .requests-table td {
                padding: 1rem;
                border-bottom: 1px solid #dee2e6;
            }

            .requests-table tr:hover {
                background: #f8f9fa;
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

            .status-processing {
                background: #cfe2ff;
                color: #084298;
            }

            .status-approved {
                background: #d1ecf1;
                color: #0c5460;
            }

            .status-rejected {
                background: #f8d7da;
                color: #721c24;
            }

            .status-claimed {
                background: #d4edda;
                color: #155724;
            }

            .action-buttons {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }

            .modal-content {
                position: relative;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .modal-centered {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 90%;
                max-width: 900px; /* adjust as needed */
                max-height: 90vh;
                overflow-y: auto;
                margin: 0;
                box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            }

            .modal-header {
                padding: 1rem 1.5rem;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
                border-radius: 8px 8px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .modal-header h2 {
                margin: 0;
                font-size: 1.25rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .close {
                background: none;
                border: none;
                padding: 0.5rem;
                margin: -0.5rem -0.5rem -0.5rem auto;
                font-size: 1.5rem;
                font-weight: bold;
                color: #666;
                cursor: pointer;
                transition: color 0.15s ease-in-out;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border-radius: 50%;
            }

            .close:hover {
                color: #000;
                background-color: rgba(0,0,0,0.1);
            }

            .modal-body {
                padding: 1.5rem;
            }

            .modal-footer {
                padding: 1rem 1.5rem;
                background: #f8f9fa;
                border-top: 1px solid #dee2e6;
                border-radius: 0 0 8px 8px;
                display: flex;
                justify-content: flex-end;
                gap: 0.5rem;
            }

            .request-details {
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 6px;
                margin-bottom: 1rem;
            }

            .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
            }

            .detail-label {
                font-weight: 600;
                color: #666;
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

                .form-row {
                    grid-template-columns: 1fr;
                }

                .requests-table {
                    display: block;
                    overflow-x: auto;
                    white-space: nowrap;
                }

                .tabs {
                    flex-wrap: wrap;
                    padding: 0 1rem;
                }

                .tab {
                    flex: 1;
                    text-align: center;
                    font-size: 0.9rem;
                    padding: 0.75rem 1rem;
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

            .certificate-types {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 2rem;
                padding: 2rem;
                background: #f8f9fa;
            }

            .certificate-type-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: all 0.3s ease;
                border: 1px solid rgba(0,0,0,0.05);
            }

            .certificate-type-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(52, 152, 219, 0.2);
            }

            .card-header {
                background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                padding: 2rem;
                border-bottom: none;
                display: flex;
                align-items: center;
                gap: 1.5rem;
            }

            .card-header i {
                font-size: 2.5rem;
                color: white;
                background: rgba(255,255,255,0.2);
                padding: 1rem;
                border-radius: 12px;
                transition: transform 0.3s ease;
            }

            .certificate-type-card:hover .card-header i {
                transform: scale(1.1);
            }

            .card-header h3 {
                margin: 0;
                font-size: 1.5rem;
                color: white;
                font-weight: 600;
            }

            .card-body {
                padding: 2rem;
                background: white;
            }

            .action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            .btn {
                padding: 1rem 1.5rem;
                border-radius: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
            }

            .btn-info {
                background: #e1f0ff;
                color: #3498db;
                border: 2px solid #3498db;
            }

            .btn-info:hover {
                background: #3498db;
                color: white;
                transform: translateY(-2px);
            }

            .btn-primary {
                background: #3498db;
                color: white;
                border: 2px solid transparent;
            }

            .btn-primary:hover {
                background: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            }

            .badge {
                background: #e74c3c;
                color: white;
                padding: 0.4rem 0.8rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-left: 0.5rem;
                box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
            }

            /* Certificate Container Enhancement */
            .certificate-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                overflow: hidden;
                margin-bottom: 2rem;
            }

            .certificate-header {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 2rem;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }

            .certificate-title {
                font-size: 1.8rem;
                font-weight: 700;
                color: #2c3e50;
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .certificate-title i {
                font-size: 2rem;
                background: #3498db;
                color: white;
                padding: 0.8rem;
                border-radius: 12px;
            }

            /* Add these styles to the existing styles section */
            .modal-content {
                max-width: 800px;
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            @media (max-width: 768px) {
                .form-row {
                    grid-template-columns: 1fr;
                }
            }

            .form-group {
                margin-bottom: 1rem;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #333;
            }

            .form-control {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 0.95rem;
                transition: all 0.3s;
            }

            .form-control:focus {
                border-color: #3498db;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
                outline: none;
            }

            select.form-control {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M0 2l4 4 4-4z' fill='%23333'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 1rem center;
                background-size: 8px;
                padding-right: 2.5rem;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }

            /* Add these styles for the certificate preview */
            .certificate-preview {
                background: white;
                padding: 60px;
                margin: 20px auto;
                max-width: 8.5in;
                min-height: 11in;
                position: relative;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                font-family: 'Times New Roman', Times, serif;
                line-height: 1.6;
            }

            @media print {
                .certificate-preview {
                    box-shadow: none;
                    margin: 0;
                    padding: 30px;
                }
                
                .btn, .no-print {
                    display: none !important;
                }
            }

            .logo-upload {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
                padding: 1rem;
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                background: #f8f9fa;
            }

            .logo-upload img {
                max-width: 150px;
                max-height: 150px;
                object-fit: contain;
            }

            .resident-search {
                position: relative;
            }

            .resident-results {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }

            .resident-result-item {
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
                transition: background 0.2s;
            }

            .resident-result-item:hover {
                background: #f8f9fa;
            }

            .resident-result-item:last-child {
                border-bottom: none;
            }

            .resident-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .resident-info img {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                object-fit: cover;
            }

            .resident-details {
                flex: 1;
            }

            .resident-name {
                font-weight: 600;
                color: #333;
            }

            .resident-meta {
                font-size: 0.85rem;
                color: #666;
            }

            #selectedResidentInfo .alert {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1rem;
            }

            #selectedResidentInfo .close {
                font-size: 1.2rem;
                padding: 0.2rem 0.5rem;
            }
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <i class="fas fa-building"></i>
                    Cawit Barangay Management
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
                        <?php if ($stats['pending_complaints'] > 0): ?>
                            <span class="nav-badge"><?php echo $stats['pending_complaints']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="certificates.php" class="nav-item active">
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
                <h1 class="page-title">Certificate Management</h1>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="color: #666; font-size: 0.9rem;">
                        <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                    </span>
                </div>
            </div>

            <div class="content-area">
                <?php if (isset($db_error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $db_error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['settings']) && $_GET['settings'] == 'updated'): ?>
                    <div class="alert alert-success no-print">
                        <i class="fas fa-check-circle"></i> Certificate settings updated successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] == 'approved'): ?>
                        <div class="alert alert-success no-print">
                            <i class="fas fa-check-circle"></i> Certificate request approved successfully!
                        </div>
                    <?php elseif ($_GET['msg'] == 'rejected'): ?>
                        <div class="alert alert-warning no-print">
                            <i class="fas fa-times-circle"></i> Certificate request rejected!
                        </div>
                    <?php elseif ($_GET['msg'] == 'claimed'): ?>
                        <div class="alert alert-info no-print">
                            <i class="fas fa-check-circle"></i> Certificate marked as claimed!
                        </div>
                    <?php elseif ($_GET['msg'] == 'certificate_generated'): ?>
                        <div class="alert alert-success no-print">
                            <i class="fas fa-check-circle"></i> Certificate generated successfully!
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="certificate-container">
                    <div class="certificate-header">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h2 class="certificate-title">
                                <i class="fas fa-certificate" style="color: #3498db;"></i>
                                Certificate Types
                            </h2>
                            <button onclick="showSettingsModal()" class="btn btn-secondary btn-sm">
                                <i class="fas fa-cog"></i> Settings
                            </button>
                        </div>
                    </div>

                    <div class="certificate-types">
                        <!-- Barangay Clearance -->
                        <div class="certificate-type-card">
                            <div class="card-header">
                                <i class="fas fa-file-alt"></i>
                                <h3>Barangay Clearance</h3>
                            </div>
                            <div class="card-body">
                                <div class="action-buttons">
                                    <a href="#" onclick="showRequestsModal('clearance', 'Barangay Clearance')" class="btn btn-info">
                                        <i class="fas fa-list"></i> Requests
                                        <?php if (isset($stats['pending_clearance']) && $stats['pending_clearance'] > 0): ?>
                                            <span class="badge"><?php echo $stats['pending_clearance']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <button onclick="showGenerateModal('clearance', 'Barangay Clearance')" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate of Indigency -->
                        <div class="certificate-type-card">
                            <div class="card-header">
                                <i class="fas fa-hand-holding-heart"></i>
                                <h3>Certificate of Indigency</h3>
                            </div>
                            <div class="card-body">
                                <div class="action-buttons">
                                    <a href="#" onclick="showRequestsModal('indigency', 'Certificate of Indigency')" class="btn btn-info">
                                        <i class="fas fa-list"></i> Requests
                                        <?php if (isset($stats['pending_indigency']) && $stats['pending_indigency'] > 0): ?>
                                            <span class="badge"><?php echo $stats['pending_indigency']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <button onclick="showGenerateModal('indigency', 'Certificate of Indigency')" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate of Residency -->
                        <div class="certificate-type-card">
                            <div class="card-header">
                                <i class="fas fa-home"></i>
                                <h3>Certificate of Residency</h3>
                            </div>
                            <div class="card-body">
                                <div class="action-buttons">
                                    <a href="#" onclick="showRequestsModal('residency', 'Certificate of Residency')" class="btn btn-info">
                                        <i class="fas fa-list"></i> Requests
                                        <?php if (isset($stats['pending_residency']) && $stats['pending_residency'] > 0): ?>
                                            <span class="badge"><?php echo $stats['pending_residency']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <button onclick="showGenerateModal('residency', 'Certificate of Residency')" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Handle different certificate types and actions
                    if (isset($_GET['type']) && isset($_GET['action'])):
                        $type = $_GET['type'];
                        $action = $_GET['action'];
                        
                        // Get the certificate title based on type
                        $certificate_titles = [
                            'clearance' => 'Barangay Clearance',
                            'indigency' => 'Certificate of Indigency',
                            'residency' => 'Certificate of Residency'
                        ];
                        
                        $title = $certificate_titles[$type] ?? 'Certificate';
                    ?>
                        <div class="certificate-action-container">
                            <div class="action-header">
                                <h3>
                                    <?php echo $title; ?> - 
                                    <?php echo ucfirst($action); ?>
                                </h3>
                                <a href="certificates.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>

                            <?php if ($action === 'requested'): ?>
                                <!-- Show requests table for this certificate type -->
                                <div class="requests-table-container">
                                    <!-- Table Header and Filters -->
                                    <div style="padding: 1.5rem; border-bottom: 1px solid #dee2e6;">
                                        <h3 style="margin-bottom: 1rem;">
                                            <i class="fas fa-scroll"></i> <?php echo $title; ?> Requests
                                        </h3>
                                        
                                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                                            <select id="statusFilter" class="form-control" style="max-width: 200px;" onchange="filterRequests()">
                                                <option value="">All Statuses</option>
                                                <option value="pending">Pending</option>
                                                <option value="approved">Approved</option>
                                                <option value="rejected">Rejected</option>
                                                <option value="claimed">Claimed</option>
                                            </select>
                                            
                                            <input type="text" id="searchInput" class="form-control" placeholder="Search resident..." 
                                                style="max-width: 300px;" oninput="filterRequests()">
                                        </div>
                                    </div>

                                    <!-- Requests Table -->
                                    <table class="requests-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Resident</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Request Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($certificate_requests_with_info as $request):
                                                if (strtolower($request['certificate_type']) === $type):
                                                    $status_class = "status-" . $request['status'];
                                            ?>
                                                <tr data-request='<?php echo htmlspecialchars(json_encode($request)); ?>'>
                                                    <td><?php echo $request['id']; ?></td>
                                                    <td>
                                                        <a href="#" onclick="viewRequestDetails(this.closest('tr'))">
                                                            <?php echo htmlspecialchars($request['resident_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                                    <td><span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <?php if ($request['status'] === 'pending'): ?>
                                                                <button class="btn btn-success btn-sm" onclick="showApproveModal(<?php echo $request['id']; ?>, '<?php echo addslashes($request['resident_name']); ?>', '<?php echo addslashes($title); ?>')">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $request['id']; ?>)">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                                <button class="btn btn-primary btn-sm" onclick="showClaimModal(<?php echo $request['id']; ?>)">
                                                                    <i class="fas fa-file-download"></i> Mark as Claimed
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Request Details Modal -->
                                <div id="requestDetailsModal" class="modal">
                                    <div class="modal-content modal-lg">
                                        <div class="modal-header">
                                            <h2><i class="fas fa-info-circle"></i> Request Details</h2>
                                            <button type="button" class="close" onclick="closeModal('requestDetailsModal')">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="request-info-grid">
                                                <div class="info-section resident-info">
                                                    <div class="section-header">
                                                        <i class="fas fa-user"></i> Resident Information
                                                    </div>
                                                    <div class="info-content">
                                                        <div class="info-item">
                                                            <span class="label">Full Name:</span>
                                                            <span id="modal-resident-name"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Contact:</span>
                                                            <span id="modal-contact"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Zone:</span>
                                                            <span id="modal-zone"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="info-section request-info">
                                                    <div class="section-header">
                                                        <i class="fas fa-file-alt"></i> Certificate Details
                                                    </div>
                                                    <div class="info-content">
                                                        <div class="info-item">
                                                            <span class="label">Type:</span>
                                                            <span id="modal-cert-type"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Purpose:</span>
                                                            <span id="modal-purpose"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Request Date:</span>
                                                            <span id="modal-request-date"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Status:</span>
                                                            <span id="modal-status"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div id="modal-processing-info" class="info-section processing-info" style="display: none;">
                                                    <div class="section-header">
                                                        <i class="fas fa-clock"></i> Processing Information
                                                    </div>
                                                    <div class="info-content">
                                                        <div class="info-item">
                                                            <span class="label">Processed By:</span>
                                                            <span id="modal-processed-by"></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <span class="label">Process Date:</span>
                                                            <span id="modal-process-date"></span>
                                                        </div>
                                                        <div id="modal-or-info" class="info-item" style="display: none;">
                                                            <span class="label">OR Number:</span>
                                                            <span id="modal-or-number"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <div id="modal-actions" class="modal-actions"></div>
                                            <button type="button" class="btn btn-secondary" onclick="closeModal('requestDetailsModal')">Close</button>
                                        </div>
                                    </div>
                                </div>

                                <style>
                                    .modal-lg {
                                        max-width: 800px !important;
                                    }

                                    .request-info-grid {
                                        display: grid;
                                        gap: 1.5rem;
                                    }

                                    .info-section {
                                        background: #f8f9fa;
                                        border-radius: 8px;
                                        overflow: hidden;
                                        border: 1px solid #e9ecef;
                                    }

                                    .section-header {
                                        background: #e9ecef;
                                        padding: 1rem;
                                        font-weight: 600;
                                        color: #2c3e50;
                                        display: flex;
                                        align-items: center;
                                        gap: 0.5rem;
                                    }

                                    .info-content {
                                        padding: 1rem;
                                    }

                                    .info-item {
                                        display: grid;
                                        grid-template-columns: 120px 1fr;
                                        gap: 1rem;
                                        margin-bottom: 0.5rem;
                                        align-items: center;
                                    }

                                    .info-item .label {
                                        font-weight: 500;
                                        color: #6c757d;
                                    }

                                    .modal-actions {
                                        display: flex;
                                        gap: 0.5rem;
                                    }

                                    .status-badge {
                                        display: inline-flex;
                                        align-items: center;
                                        gap: 0.3rem;
                                        padding: 0.25rem 0.75rem;
                                        border-radius: 20px;
                                        font-size: 0.85rem;
                                        font-weight: 500;
                                    }
                                </style>

                                <script>
                                    function viewRequestDetails(row) {
                                        const request = JSON.parse(row.dataset.request);
                                        
                                        // Fill modal with request data
                                        document.getElementById('modal-resident-name').textContent = request.resident_name;
                                        document.getElementById('modal-contact').textContent = request.resident_phone || 'Not provided';
                                        document.getElementById('modal-zone').textContent = request.zone || 'Not specified';
                                        document.getElementById('modal-cert-type').textContent = request.certificate_name;
                                        document.getElementById('modal-purpose').textContent = request.purpose;
                                        document.getElementById('modal-request-date').textContent = new Date(request.created_at).toLocaleString();
                                        
                                        // Status with badge
                                        const statusEl = document.getElementById('modal-status');
                                        statusEl.innerHTML = `<span class="status-badge status-${request.status}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span>`;
                                        
                                        // Show/hide processing info
                                        const processingSection = document.getElementById('modal-processing-info');
                                        if (request.processed_by) {
                                            processingSection.style.display = 'block';
                                            document.getElementById('modal-processed-by').textContent = request.processed_by_name || 'Unknown';
                                            document.getElementById('modal-process-date').textContent = new Date(request.processed_date).toLocaleString();
                                            
                                            const orInfo = document.getElementById('modal-or-info');
                                            if (request.status === 'claimed' && request.or_number) {
                                                orInfo.style.display = 'grid';
                                                document.getElementById('modal-or-number').textContent = request.or_number;
                                            } else {
                                                orInfo.style.display = 'none';
                                            }
                                        } else {
                                            processingSection.style.display = 'none';
                                        }
                                        
                                        // Update action buttons
                                        const actionsContainer = document.getElementById('modal-actions');
                                        actionsContainer.innerHTML = '';
                                        
                                        if (request.status === 'pending') {
                                            actionsContainer.innerHTML = `
                                                <button class="btn btn-success" onclick="showApproveModal(${request.id}, '${request.resident_name}', '${request.certificate_name}')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-danger" onclick="showRejectModal(${request.id})">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            `;
                                        } else if (request.status === 'approved') {
                                            actionsContainer.innerHTML = `
                                                <button class="btn btn-primary" onclick="showClaimModal(${request.id})">
                                                    <i class="fas fa-file-download"></i> Mark as Claimed
                                                </button>
                                            `;
                                        }
                                        
                                        // Show modal
                                        document.getElementById('requestDetailsModal').style.display = 'block';
                                    }

                                    // Keep existing filterRequests() function
                                    // ...existing code...
                                </script>
                            <?php else: ?>
                                <!-- Show generation form for this certificate type -->
                                <div class="generate-form-container">
                                    <!-- Add your generation form here -->

                                    <div class="certificate-preview">
                        <!-- Logo and Header -->
                        <div class="republic-header" style="text-align: center; margin-bottom: 20px;">
                            <p>Republic of the Philippines</p>
                            <p>Province of Zamboanga del Sur</p>
                            <p>Municipality of Zamboanga City</p>
                            <p style="font-size: 24px; font-weight: bold;">BARANGAY CAWIT</p>
                            <p>OFFICE OF THE PUNONG BARANGAY</p>
                        </div>

                        <!-- Logo Container -->
                        <div style="display: flex; justify-content: space-between; margin: 20px 0;">
                            <img src="<?php echo $settings['logo_path']; ?>" alt="Barangay Logo" style="width: 100px; height: 100px;">
                            <div style="width: 100px;"><!-- Spacer for alignment --></div>
                        </div>

                        <!-- Certificate Title -->
                        <div style="text-align: center; margin: 30px 0;">
                            <h1 style="font-size: 32px; border-bottom: 2px solid #000; display: inline-block; padding: 0 20px;">
                                BARANGAY CLEARANCE
                            </h1>
                        </div>

                        <!-- Certificate Content -->
                        <div style="margin: 30px 0; line-height: 1.8; text-align: justify;">
                            <p style="margin-bottom: 20px;">TO WHOM IT MAY CONCERN:</p>
                            
                            <p style="text-indent: 50px;">
                                This is to certify that <strong style="border-bottom: 1px solid #000; padding: 0 5px;">
                                {resident_name}</strong>, <strong>{age}</strong> years old, 
                                <strong>{nationality}</strong> citizen and a bonafide resident of 
                                Barangay Cawit, Zamboanga City is a person of good moral character 
                                and law-abiding citizen in the community.
                            </p>
                            
                            <p style="text-indent: 50px; margin-top: 20px;">
                                He/She has not been involved nor convicted of any violation of existing laws, rules and ordinances
                                of this Barangay up to the present.
                            </p>
                            
                            <p style="text-indent: 50px; margin-top: 20px;">
                                This certification is being issued upon the request of the above-named person for 
                                <strong>{purpose}</strong>.
                            </p>
                        </div>

                        <!-- Issued Date -->
                        <div style="margin: 30px 0;">
                            <p>Issued this <?php echo date('jS'); ?> day of <?php echo date('F Y'); ?>.</p>
                        </div>

                        <!-- Signature Section -->
                        <div style="margin-top: 50px; float: right; text-align: center;">
                            <div style="border-bottom: 1px solid #000; width: 200px; margin-bottom: 5px;">
                                &nbsp;
                            </div>
                            <p style="font-weight: bold; margin: 5px 0;"><?php echo $settings['captain_name']; ?></p>
                            <p>Punong Barangay</p>
                        </div>

                        <!-- Footer Section -->
                        <div style="clear: both; margin-top: 150px;">
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 10px;">
                                <p>CTC No:</p>
                                <p style="border-bottom: 1px solid #000;">{or_number}</p>
                                <p>Issued on:</p>
                                <p style="border-bottom: 1px solid #000;"><?php echo date('F j, Y'); ?></p>
                                <p>Issued at:</p>
                                <p style="border-bottom: 1px solid #000;">Barangay Cawit, Zamboanga City</p>
                                <p>Doc Stamp:</p>
                                <p style="border-bottom: 1px solid #000;">PAID</p>
                            </div>
                        </div>

                        <!-- Official Seal -->
                        <div style="position: absolute; bottom: 100px; left: 50px; opacity: 0.2;">
                            <img src="<?php echo $settings['logo_path']; ?>" alt="Official Seal" style="width: 150px;">
                        </div>
                    </div>

                    <!-- Print Button -->
                    <div class="text-center mt-4">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Certificate
                        </button>
                    </div>
                </div>

                <style>
                    /* Add these styles for the certificate preview */
                    .certificate-preview {
                        background: white;
                        padding: 60px;
                        margin: 20px auto;
                        max-width: 8.5in;
                        min-height: 11in;
                        position: relative;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        font-family: 'Times New Roman', Times, serif;
                        line-height: 1.6;
                    }

                    @media print {
                        .certificate-preview {
                            box-shadow: none;
                            margin: 0;
                            padding: 30px;
                        }
                        
                        .btn, .no-print {
                            display: none !important;
                        }
                    }
                </style>

                <script>
                    // Add this to your existing JavaScript
                    document.getElementById('generateForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        // Get form values
                        const formData = new FormData(this);
                        
                        // Replace placeholders in certificate preview
                        const preview = document.querySelector('.certificate-preview');
                        const content = preview.innerHTML;
                        
                        const updatedContent = content
                            .replace('{resident_name}', formData.get('resident_name'))
                            .replace('{age}', formData.get('age'))
                            .replace('{nationality}', formData.get('nationality'))
                            .replace('{purpose}', formData.get('purpose'))
                            .replace('{or_number}', formData.get('or_number'));
                        
                        preview.innerHTML = updatedContent;
                    });
                </script>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approve Modal -->
            <div id="approveModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Approve Certificate Request</h2>
                        <button type="button" class="close" onclick="closeModal('approveModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to approve this certificate request?</p>
                        <div class="request-details">
                            <div class="detail-item">
                                <span class="detail-label">Resident:</span>
                                <span id="approve-resident-name"></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Certificate Type:</span>
                                <span id="approve-cert-type"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="request_id" id="approve-request-id">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                            <button type="submit" class="btn btn-success">Approve Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div id="rejectModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Reject Certificate Request</h2>
                        <button type="button" class="close" onclick="closeModal('rejectModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="rejectForm">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" id="reject-request-id">
                            <div class="form-group">
                                <label for="rejection_reason">Reason for Rejection:</label>
                                <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                        <button type="submit" form="rejectForm" class="btn btn-danger">Reject Request</button>
                    </div>
                </div>
            </div>

            <!-- Claim Modal -->
            <div id="claimModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Mark Certificate as Claimed</h2>
                        <button type="button" class="close" onclick="closeModal('claimModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="claimForm">
                            <input type="hidden" name="action" value="mark_claimed">
                            <input type="hidden" name="request_id" id="claim-request-id">
                            <div class="form-group">
                                <label for="or_number">O.R. Number:</label>
                                <input type="text" name="or_number" id="or_number" class="form-control" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('claimModal')">Cancel</button>
                        <button type="submit" form="claimForm" class="btn btn-success">Mark as Claimed</button>
                    </div>
                </div>
            </div>

            <!-- Generate Certificate Modal -->
            <div id="generateModal" class="modal">
                <div class="modal-content modal-centered" style="max-width: 800px;">
                    <div class="modal-header">
                        <h2 id="generate-certificate-title">Generate Certificate</h2>
                        <button type="button" class="close" onclick="closeModal('generateModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="generateForm">
                            <input type="hidden" name="generate" value="1">
                            <div class="form-group">
                                <label>Search Resident</label>
                                <div class="resident-search">
                                    <input type="text" 
                                        class="form-control" 
                                        id="residentSearchInput" 
                                        placeholder="Type resident name..."
                                        autocomplete="off">
                                    <div id="residentSearchResults" class="resident-results"></div>
                                </div>
                            </div>
                            <div id="selectedResidentInfo" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="fas fa-user"></i> Selected Resident: <span id="selectedResidentName"></span>
                                    <button type="button" class="close" onclick="clearSelectedResident()">&times;</button>
                                </div>
                            </div>
                            <input type="hidden" name="resident_id" id="selectedResidentId">
                            <input type="hidden" name="resident_name" id="selectedResidentFullName">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Age</label>
                                    <input type="number" name="age" id="residentAge" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Zone</label>
                                    <input type="text" id="residentZone" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Purpose</label>
                                <textarea name="purpose" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>O.R. Number</label>
                                    <input type="text" name="or_number" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Amount Paid</label>
                                    <input type="number" name="amount_paid" class="form-control" required>
                                </div>
                            </div>

                            <!-- Hidden request_id field (added by JS if needed) -->
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('generateModal')">Cancel</button>
                        <button type="submit" form="generateForm" class="btn btn-primary" id="generateBtn" disabled>Generate Certificate</button                </div>
                </div>
            </div>

            <!-- Settings Modal -->
            <div id="settingsModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-cog"></i> Certificate Settings</h2>
                        <button type="button" class="close" onclick="closeModal('settingsModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="settingsForm" enctype="multipart/form-data">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div class="form-group">
                                <label>Barangay Logo</label>
                                <div class="logo-upload">
                                    <img src="<?php echo $settings['logo_path']; ?>" alt="Current Logo" style="max-width: 100px; margin-bottom: 10px;">
                                    <input type="file" name="logo" class="form-control" accept="image/*" onchange="previewImage(this)">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Punong Barangay Name</label>
                                <input type="text" name="captain_name" class="form-control" value="<?php echo $settings['captain_name']; ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Barangay Name</label>
                                <input type="text" name="barangay_name" class="form-control" value="<?php echo $settings['barangay_name']; ?>" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('settingsModal')">Cancel</button>
                        <button type="submit" form="settingsForm" class="btn btn-primary">Save Settings</button>
                    </div>
                </div>
            </div>

            <!-- Requests List Modal -->
            <div id="requestsListModal" class="modal">
                <div class="modal-content modal-centered">
                    <div class="modal-header">
                        <h2 id="requestsModalTitle">Certificate Requests</h2>
                        <button type="button" class="close" onclick="closeModal('requestsListModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                                <select id="statusFilter" class="form-control" style="max-width: 200px;" onchange="filterRequests()">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="claimed">Claimed</option>
                                </select>
                                
                                <input type="text" id="searchInput" class="form-control" 
                                    placeholder="Search resident..." style="max-width: 300px;" 
                                    oninput="filterRequests()">
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                            <table class="requests-table" id="requestsTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Resident</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Request Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function showRequestsModal(type, title) {
                    const modal = document.getElementById('requestsListModal');
                    document.getElementById('requestsModalTitle').textContent = title + ' Requests';
                    
                    // Filter requests for this certificate type
                    const filteredRequests = <?php echo json_encode($certificate_requests_with_info); ?>
                        .filter(req => req.certificate_type.toLowerCase() === type.toLowerCase());
                    
                    // Populate table
                    const tbody = document.querySelector('#requestsTable tbody');
                    tbody.innerHTML = '';
                    
                    if (filteredRequests.length > 0) {
                        filteredRequests.forEach(request => {
                            const tr = document.createElement('tr');
                            tr.dataset.request = JSON.stringify(request);
                            
                            const statusClass = "status-" + request.status;
                            const actions = request.status === 'pending' ? 
                                `<button class="btn btn-success btn-sm" onclick="showApproveModal(${request.id}, '${request.resident_name}', '${title}')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(${request.id})">
                                    <i class="fas fa-times"></i> Reject
                                </button>` :
                                request.status === 'approved' ?
                                `<button class="btn btn-primary btn-sm" onclick="showClaimModal(${request.id})">
                                    <i class="fas fa-file-download"></i> Mark as Claimed
                                </button>` : '';
                            
                            tr.innerHTML = `
                                <td>${request.id}</td>
                                <td>
                                    <a href="#" onclick="viewRequestDetails(this.closest('tr')); return false;">
                                        ${request.resident_name}
                                    </a>
                                </td>
                                <td>${request.purpose}</td>
                                <td><span class="status-badge ${statusClass}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span></td>
                                <td>${new Date(request.created_at).toLocaleString()}</td>
                                <td><div class="action-buttons">${actions}</div></td>
                            `;
                            
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-info-circle"></i> No requests found
                                </td>
                            </tr>
                        `;
                    }
                    
                    modal.style.display = 'block';
                }

                // Add to existing filterRequests function
                function filterRequests() {
                    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
                    const searchFilter = document.getElementById('searchInput').value.toLowerCase();
                    const rows = document.querySelectorAll('#requestsTable tbody tr');
                    
                    rows.forEach(row => {
                        if (!row.dataset.request) return;
                        
                        const request = JSON.parse(row.dataset.request);
                        const matchStatus = !statusFilter || request.status === statusFilter;
                        const matchSearch = !searchFilter || 
                                        request.resident_name.toLowerCase().includes(searchFilter);
                        
                        row.style.display = matchStatus && matchSearch ? '' : 'none';
                    });
                }
            </script>

            <style>
                /* Add these styles to your existing styles */
                .table-responsive {
                    border-radius: 8px;
                    border: 1px solid #dee2e6;
                    margin-top: 1rem;
                }
                
                .requests-table {
                    margin-bottom: 0;
                }
                
                .modal-content {
                    animation: modalSlideIn 0.3s ease;
                }
                
                @keyframes modalSlideIn {
                    from {
                        transform: translateY(-10%);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
            </style>

            <script>
                // Function to close any modal
                function closeModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                // Close modal when clicking outside of it
                window.onclick = function(event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }

                // Show modals
                function showApproveModal(requestId, residentName, certType) {
                    document.getElementById('approve-request-id').value = requestId;
                    document.getElementById('approve-resident-name').textContent = residentName;
                    document.getElementById('approve-cert-type').textContent = certType;
                    document.getElementById('approveModal').style.display = 'block';
                }

                function showRejectModal(requestId) {
                    document.getElementById('reject-request-id').value = requestId;
                    document.getElementById('rejectModal').style.display = 'block';
                }

                function showClaimModal(requestId) {
                    document.getElementById('claim-request-id').value = requestId;
                    document.getElementById('claimModal').style.display = 'block';
                }

                function showSettingsModal() {
                    document.getElementById('settingsModal').style.display = 'block';
                }

                function showGenerateModal(type, title, requestId = null) {
                    document.getElementById('generate-certificate-title').innerHTML = `<i class="fas fa-file-alt"></i> Generate ${title}`;

                    // Reset and clear previous selection
                    document.getElementById('generateForm').reset();
                    document.getElementById('selectedResidentInfo').style.display = 'none';
                    document.getElementById('selectedResidentId').value = '';
                    document.getElementById('selectedResidentFullName').value = '';
                    document.getElementById('residentAge').value = '';
                    document.getElementById('residentZone').value = '';
                    document.getElementById('generateBtn').disabled = true;

                    // Ensure hidden request_id field exists
                    if (!document.getElementById('requestIdField')) {
                        const rid = document.createElement('input');
                        rid.type = 'hidden';
                        rid.id = 'requestIdField';
                        rid.name = 'request_id';
                        document.getElementById('generateForm').appendChild(rid);
                    }
                    document.getElementById('requestIdField').value = '';

                    // set certificate_type hidden if needed (existing behavior)
                    if (!document.getElementById('certificateType')) {
                        const typeInput = document.createElement('input');
                        typeInput.type = 'hidden';
                        typeInput.id = 'certificateType';
                        typeInput.name = 'certificate_type';
                        document.getElementById('generateForm').appendChild(typeInput);
                    }
                    document.getElementById('certificateType').value = type;

                    // If requestId was provided, pre-fill from the requests array
                    if (requestId) {
                        const req = CERTIFICATE_REQUESTS.find(r => String(r.id) === String(requestId));
                        if (req) {
                            // Fill form fields using available request data
                            if (req.resident_id) {
                                document.getElementById('selectedResidentId').value = req.resident_id;
                            }
                            document.getElementById('selectedResidentFullName').value = req.resident_name || '';
                            document.getElementById('selectedResidentName').textContent = req.resident_name || '';
                            document.getElementById('residentAge').value = req.age || '';
                            document.getElementById('residentZone').value = req.zone || '';
                            // fill amount if present
                            const amountField = document.querySelector('input[name="amount_paid"]');
                            if (amountField && typeof req.amount_paid !== 'undefined') {
                                amountField.value = req.amount_paid;
                            }
                            // set request id hidden
                            document.getElementById('requestIdField').value = requestId;
                            // show selected resident info & enable generate
                            document.getElementById('selectedResidentInfo').style.display = 'block';
                            document.getElementById('generateBtn').disabled = false;
                        }
                    }

                    document.getElementById('generateModal').style.display = 'block';
                }

                // Close modal when pressing ESC key
                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        const modals = document.getElementsByClassName('modal');
                        for (let modal of modals) {
                            modal.style.display = 'none';
                        }
                    }
                });

                // Resident search functionality
                let searchTimeout = null;
                const residentSearchInput = document.getElementById('residentSearchInput');
                const residentSearchResults = document.getElementById('residentSearchResults');

                residentSearchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    
                    if (this.value.length < 2) {
                        residentSearchResults.style.display = 'none';
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        fetch(`certificates.php?action=search_resident&query=${encodeURIComponent(this.value)}`)
                            .then(response => response.json())
                            .then(data => {
                                residentSearchResults.innerHTML = '';
                                if (data.length > 0) {
                                    data.forEach(resident => {
                                        const div = document.createElement('div');
                                        div.className = 'resident-result-item';
                                        div.innerHTML = `
                                            <div class="resident-info">
                                                <div class="resident-details">
                                                    <div class="resident-name">${resident.full_name}</div>
                                                    <div class="resident-meta">
                                                        Age: ${resident.age} | Zone: ${resident.zone || 'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                        div.addEventListener('click', () => selectResident(resident));
                                        residentSearchResults.appendChild(div);
                                    });
                                    residentSearchResults.style.display = 'block';
                                } else {
                                    residentSearchResults.innerHTML = '<div class="resident-result-item">No residents found</div>';
                                    residentSearchResults.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                console.error('Error searching residents:', error);
                                residentSearchResults.innerHTML = '<div class="resident-result-item">Error searching residents</div>';
                                residentSearchResults.style.display = 'block';
                            });
                    }, 300);
                });

                function selectResident(resident) {
                    document.getElementById('selectedResidentId').value = resident.id;
                    document.getElementById('selectedResidentFullName').value = resident.full_name;
                    document.getElementById('selectedResidentName').textContent = resident.full_name;
                    document.getElementById('residentAge').value = resident.age;
                    document.getElementById('residentZone').value = resident.zone || 'N/A';
                    
                    residentSearchInput.value = '';
                    residentSearchResults.style.display = 'none';
                    document.getElementById('selectedResidentInfo').style.display = 'block';
                    document.getElementById('generateBtn').disabled = false;

                    // Clear any request id since user explicitly picked resident
                    if (document.getElementById('requestIdField')) {
                        document.getElementById('requestIdField').value = '';
                    }
                }

                function clearSelectedResident() {
                    document.getElementById('selectedResidentId').value = '';
                    document.getElementById('selectedResidentFullName').value = '';
                    document.getElementById('residentAge').value = '';
                    document.getElementById('residentZone').value = '';
                    document.getElementById('selectedResidentInfo').style.display = 'none';
                    document.getElementById('generateBtn').disabled = true;
                }

                // Close resident search results when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.resident-search')) {
                        residentSearchResults.style.display = 'none';
                    }
                });
            </script>
        </body>
    </html>