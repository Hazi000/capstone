<?php
session_start();
require_once '../../config.php';

// If a success message was set in session (after redirect), capture it for SweetAlert then clear it
$swal_message = null;
if (isset($_SESSION['success_message'])) {
    $swal_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    header("Location: ../index.php");
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../uploads/residents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Initialize pagination variables
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
// Use a single variable for page size (10 items per page)
$records_per_page = 10;
// ensure integers and avoid TypeError when multiplying
$offset = max(0, ($page - 1) * $records_per_page);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // add_resident handling: after reading inputs
        if ($_POST['action'] === 'add_resident') {
            $existing_id = intval($_POST['existing_resident_id'] ?? 0);
            $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
            $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
            $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
            $age = intval($_POST['age']);
            $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
            // address column removed from DB; do not read $_POST['address']
            $contact_number = preg_replace('/\D/', '', $contact_number);
            if (!preg_match('/^09\d{9}$/', $contact_number)) {
                $error_message = "Contact number must be 11 digits and start with 09.";
            } else {
                $suffix = isset($_POST['suffix']) ? mysqli_real_escape_string($connection, trim($_POST['suffix'])) : null;
                $zone = !empty($_POST['zone']) ? mysqli_real_escape_string($connection, $_POST['zone']) : 'Zone 1A';
                $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
                if (!empty($suffix)) {
                    $full_name .= ' ' . $suffix;
                }

                if ($existing_id > 0) {
                    $update_parts = [];
                    $update_parts[] = "first_name = '$first_name'";
                    $update_parts[] = "middle_initial = '$middle_initial'";
                    $update_parts[] = "last_name = '$last_name'";
                    $update_parts[] = "full_name = '$full_name'";
                    $update_parts[] = "age = $age";
                    $update_parts[] = "contact_number = '$contact_number'";
                    $update_parts[] = "zone = '$zone'";
                    // address removed — do not include in update_parts

                    // include suffix column properly (allow NULL)
                    if ($suffix !== null && $suffix !== '') {
                        $update_parts[] = "suffix = '$suffix'";
                    } else {
                        $update_parts[] = "suffix = NULL";
                    }
                    $update_parts[] = "status = NULL";

                    $update_query = "UPDATE residents SET " . implode(', ', $update_parts) . ", updated_at = CURRENT_TIMESTAMP WHERE id = $existing_id";

                    if (mysqli_query($connection, $update_query)) {
                        $success_message = "Existing resident updated successfully.";
                    } else {
                        $error_message = "Error updating existing resident: " . mysqli_error($connection);
                    }
                } else {
                    // address column removed from residents table — do not insert address
                    $insert_query = "INSERT INTO residents (first_name, middle_initial, last_name, full_name, suffix, age, contact_number, zone) 
                                   VALUES ('$first_name', '$middle_initial', '$last_name', '$full_name', " . 
                                   ($suffix ? "'$suffix'" : "NULL") . ", $age, '$contact_number', '$zone')";
                    if (mysqli_query($connection, $insert_query)) {
                        $success_message = "Resident added successfully!";
                    } else {
                        $error_message = "Error adding resident: " . mysqli_error($connection);
                    }
                }
            }
        } elseif ($_POST['action'] === 'update_resident') {
            $id = intval($_POST['resident_id']);
            $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
            $middle_initial = mysqli_real_escape_string($connection, $_POST['middle_initial']);
            $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
            $age = intval($_POST['age']);
            $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
            // address removed — do not read from POST
            $contact_number = preg_replace('/\D/', '', $contact_number);
            if (!preg_match('/^09\d{9}$/', $contact_number)) {
                $error_message = "Contact number must be 11 digits and start with 09.";
            } else {
                $suffix = isset($_POST['suffix']) ? mysqli_real_escape_string($connection, trim($_POST['suffix'])) : null;
                $zone = !empty($_POST['zone']) ? mysqli_real_escape_string($connection, $_POST['zone']) : 'Zone 1A';
                $full_name = $first_name . ' ' . ($middle_initial ? $middle_initial . '. ' : '') . $last_name;
                if (!empty($suffix)) {
                    $full_name .= ' ' . $suffix;
                }
                $suffix_update = ($suffix !== null && $suffix !== '') ? ", suffix = '$suffix'" : ", suffix = NULL";
                // address removed — do not set address in update
                $update_query = "UPDATE residents SET 
                               first_name = '$first_name', 
                               middle_initial = '$middle_initial', 
                               last_name = '$last_name', 
                               full_name = '$full_name',
                               age = $age, 
                               contact_number = '$contact_number', 
                               zone = '$zone'
                               $suffix_update,
                               updated_at = CURRENT_TIMESTAMP
                               WHERE id = $id";
                if (mysqli_query($connection, $update_query)) {
                    $success_message = "Resident updated successfully!";
                } else {
                    $error_message = "Error updating resident: " . mysqli_error($connection);
                }
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
        // handle adding a member to a family
        elseif ($_POST['action'] === 'add_family_member') {
            $family_id = intval($_POST['family_id'] ?? 0);
            $resident_id = intval($_POST['resident_id'] ?? 0);
            $relationship = isset($_POST['relationship']) ? mysqli_real_escape_string($connection, $_POST['relationship']) : 'other';

            if ($family_id <= 0 || $resident_id <= 0) {
                $error_message = "Invalid family or resident selected.";
            } else {
                // prevent duplicate
                $check = mysqli_query($connection, "SELECT id FROM family_members WHERE family_id = $family_id AND resident_id = $resident_id");
                if ($check && mysqli_num_rows($check) > 0) {
                    $error_message = "Selected resident is already a member of this family.";
                } else {
                    $insert_member = "INSERT INTO family_members (family_id, resident_id, relationship) VALUES ($family_id, $resident_id, '$relationship')";
                    if (mysqli_query($connection, $insert_member)) {
                        $_SESSION['success_message'] = "Family member added successfully.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $error_message = "Error adding family member: " . mysqli_error($connection);
                    }
                }
            }
        }
    }
}

// Add family member management handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_family') {
            $household_number = mysqli_real_escape_string($connection, $_POST['household_number']);
            $zone = mysqli_real_escape_string($connection, $_POST['zone']);
            
            $insert_query = "INSERT INTO families (household_number, zone) 
                           VALUES ('$household_number', '$zone')";
            // Attempt insert safely and show SweetAlert on duplicate/key errors instead of a fatal error
            $insert_ok = false;
            try {
                // If mysqli is configured to throw exceptions this will catch them
                $insert_ok = mysqli_query($connection, $insert_query);
                if ($insert_ok === false) {
                    // fallback when mysqli_query returns false instead of throwing
                    $errno = mysqli_errno($connection);
                    $err = mysqli_error($connection);
                    throw new mysqli_sql_exception($err, $errno);
                }
            } catch (mysqli_sql_exception $ex) {
                // MySQL duplicate entry error code
                if ((int)$ex->getCode() === 1062) {
                    $msg = 'Household number already exists.';
                } else {
                    $msg = 'Error adding family: ' . $ex->getMessage();
                }
                // Render a SweetAlert error on the page (no redirect)
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '" . addslashes($msg) . "',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    });
                </script>";
            }

            if ($insert_ok) {
                // set exact message requested and redirect to avoid resubmission
                $_SESSION['success_message'] = "creating household successfully";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
        // handle adding a member to a family
        elseif ($_POST['action'] === 'add_family_member') {
            $family_id = intval($_POST['family_id'] ?? 0);
            $resident_id = intval($_POST['resident_id'] ?? 0);
            $relationship = isset($_POST['relationship']) ? mysqli_real_escape_string($connection, $_POST['relationship']) : 'other';

            if ($family_id <= 0 || $resident_id <= 0) {
                $error_message = "Invalid family or resident selected.";
            } else {
                // prevent duplicate
                $check = mysqli_query($connection, "SELECT id FROM family_members WHERE family_id = $family_id AND resident_id = $resident_id");
                if ($check && mysqli_num_rows($check) > 0) {
                    $error_message = "Selected resident is already a member of this family.";
                } else {
                    $insert_member = "INSERT INTO family_members (family_id, resident_id, relationship) VALUES ($family_id, $resident_id, '$relationship')";
                    if (mysqli_query($connection, $insert_member)) {
                        $_SESSION['success_message'] = "Family member added successfully.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $error_message = "Error adding family member: " . mysqli_error($connection);
                    }
                }
            }
        }
    }
}

// Get all families with their members
$families_query = "
    SELECT 
        f.id, f.household_number, f.zone,
        -- exclude members whose relationship = 'head' from the concatenated list
        GROUP_CONCAT(
            CASE WHEN fm.relationship <> 'head' THEN CONCAT(r.full_name, ' (', fm.relationship, ')') ELSE NULL END
            ORDER BY FIELD(fm.relationship, 'father', 'mother', 'spouse', 'child', 'other')
            SEPARATOR ', '
        ) as family_members,
        MAX(CASE WHEN fm.relationship = 'father' THEN r.full_name END) as father_name,
        MAX(CASE WHEN fm.relationship = 'mother' THEN r.full_name END) as mother_name
    FROM families f
    LEFT JOIN family_members fm ON f.id = fm.family_id
    LEFT JOIN residents r ON fm.resident_id = r.id
    GROUP BY f.id
    ORDER BY f.household_number
    LIMIT $records_per_page OFFSET $offset";
$families_result = mysqli_query($connection, $families_query);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT f.id) as total FROM families f";
$count_result = mysqli_query($connection, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
// Use the same $records_per_page when computing total pages
$total_pages = ($records_per_page > 0) ? ceil($total_records / $records_per_page) : 1;

// Get all residents for member selection (exclude those who are already family members)
$residents_query = "
    SELECT r.id, r.full_name 
    FROM residents r
    WHERE r.id NOT IN (SELECT resident_id FROM family_members)
    ORDER BY r.full_name";
$residents_result = mysqli_query($connection, $residents_query);
$residents = mysqli_fetch_all($residents_result, MYSQLI_ASSOC);

// Add: initialize row counter so numbering respects pagination
$row_number = $offset + 1;

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
    <title>Resident Family Cawit Barangay Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        /* Add this to your existing styles */
        .btn-block {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0.5rem 1rem;
            white-space: nowrap;
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

        /* Modal footer button alignment */
        .modal-footer {
            padding: 1rem 2rem 2rem;
            display: flex;
            justify-content: flex-end; /* right-align buttons */
            gap: 0.5rem;
        }

        /* Ensure primary button styling remains */
        .modal-footer .btn-primary {
            margin: 0;
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

        .zone-badge {
            background: #3498db;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .family-members-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .member-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .member-item i {
            color: #3498db;
            font-size: 1.2rem;
        }

        .member-item span {
            font-size: 1rem;
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
                <a href="resident_family.php" class="nav-item active">
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
            <h1 class="page-title">Resident Family</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>

        <div class="content-area">
            <?php if (!empty($swal_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo ($swal_message === "creating household successfully") ? "Creating Household Successfully" : addslashes($swal_message); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            </script>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="action-buttons">
                <button class="btn btn-primary" onclick="showAddFamilyModal()">
                    <i class="fas fa-plus"></i> Set New Family Unit
                </button>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h2 class="table-title"><i class="fas fa-users"></i> Family Units</h2>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Household #</th>
                                <th>Father</th>
                                <th>Mother</th>
                                <th>Zone</th>
                                <th>Family Members</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($family = mysqli_fetch_assoc($families_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($family['household_number']); ?></td>
                                <td><?php echo htmlspecialchars($family['father_name'] ?? 'Not Set'); ?></td>
                                <td><?php echo htmlspecialchars($family['mother_name'] ?? 'Not Set'); ?></td>
                                <td><?php echo htmlspecialchars($family['zone']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick='showViewMembersModal(<?php echo json_encode($family['family_members']); ?>)'>
                                        <i class="fas fa-users"></i> View Family Members
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="showAddMemberModal(<?php echo (int)$family['id']; ?>)">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php
            // Build base URL preserving other query params
            $qs = $_GET;
            unset($qs['page']);
            $baseQuery = http_build_query($qs);
            $base = $baseQuery !== '' ? '?' . $baseQuery . '&page=' : '?page=';

            // Simple numeric pagination (Previous, pages, Next) — same format as resident_account
            if ($total_pages > 1) {
                echo '<div class="pagination">';
                if ($page > 1) {
                    echo '<a href="' . htmlspecialchars($base . ($page - 1)) . '">&laquo; Previous</a>';
                }
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == $page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        echo '<a href="' . htmlspecialchars($base . $i) . '">' . $i . '</a>';
                    }
                }
                if ($page < $total_pages) {
                    echo '<a href="' . htmlspecialchars($base . ($page + 1)) . '">Next &raquo;</a>';
                }
                echo '</div>';
            }
            ?>
        </div>

        <!-- Add Family Modal -->
        <div id="addFamilyModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-home"></i> Set New Family Unit</h3>
                    <span class="close" onclick="closeModal('addFamilyModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addFamilyForm" onsubmit="return validateAddFamily()">
                        <input type="hidden" name="action" value="add_family">
                        <div class="form-group">
                            <label>Household Number</label>
                            <input type="text" name="household_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Zone</label>
                            <select name="zone" class="form-control" required>
                                <option value="Zone 1A">Zone 1A</option>
                                <option value="Zone 1B">Zone 1B</option>
                                <option value="Zone 2A">Zone 2A</option>
                                <option value="Zone 2B">Zone 2B</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="addFamilyForm" class="btn btn-primary">Set Family</button>
                </div>
            </div>
        </div>

        <!-- Add Member Modal -->
        <div id="addMemberModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-user-plus"></i> Add Family Member</h3>
                    <span class="close" onclick="closeModal('addMemberModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addMemberForm" onsubmit="return validateAddMember()">
                        <input type="hidden" name="action" value="add_family_member">
                        <input type="hidden" name="family_id" id="familyIdInput">
                        <div class="form-group">
                            <label>Search Resident</label>
                            <select name="resident_id" class="form-control select2-resident" required>
                                <option value="">Search for a resident...</option>
                                <?php foreach ($residents as $resident): ?>
                                    <option value="<?php echo $resident['id']; ?>">
                                        <?php echo htmlspecialchars($resident['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Relationship</label>
                            <select name="relationship" id="relationshipSelect" class="form-control" required>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="spouse">Spouse</option>
                                <option value="child">Child</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="addMemberForm" class="btn btn-primary">Add Member</button>
                </div>
            </div>
        </div>

        <!-- View Family Members Modal -->
        <div id="viewMembersModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-users"></i> Family Members</h3>
                    <span class="close" onclick="closeModal('viewMembersModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="membersList" class="family-members-list">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="closeModal('viewMembersModal')">Close</button>
                </div>
            </div>
        </div>

        <script>
        function showAddFamilyModal() {
            document.getElementById('addFamilyModal').classList.add('show');
        }

        async function showAddMemberModal(familyId) {
            document.getElementById('familyIdInput').value = familyId;

            // fetch existing relationships for this family and disable conflicting options
            try {
                const formData = new FormData();
                formData.append('action', 'get_family_relationships');
                formData.append('family_id', familyId);
                const resp = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await resp.json();
                const relSelect = document.getElementById('relationshipSelect');
                if (relSelect) {
                    // enable all by default
                    Array.from(relSelect.options).forEach(opt => {
                        opt.disabled = false;
                    });

                    if (data && data.success && Array.isArray(data.relationships)) {
                        // if father exists, disable father; same for mother
                        if (data.relationships.includes('father')) {
                            const opt = relSelect.querySelector('option[value="father"]');
                            if (opt) opt.disabled = true;
                        }
                        if (data.relationships.includes('mother')) {
                            const opt = relSelect.querySelector('option[value="mother"]');
                            if (opt) opt.disabled = true;
                        }
                        // if currently selected option becomes disabled, reset to 'other'
                        if (relSelect.value && relSelect.options[relSelect.selectedIndex].disabled) {
                            relSelect.value = 'other';
                        }
                    }
                }
            } catch (e) {
                console.error('Failed to retrieve family relationships', e);
            }

            document.getElementById('addMemberModal').classList.add('show');
        }

        function showViewMembersModal(members) {
            const membersList = document.getElementById('membersList');
            if (!members) {
                membersList.innerHTML = '<p>No family members found.</p>';
            } else {
                const memberArray = members.split(',').map(member => member.trim());
                const listHTML = memberArray.map(member => `
                    <div class="member-item">
                        <i class="fas fa-user"></i>
                        <span>${member}</span>
                    </div>
                `).join('');
                membersList.innerHTML = listHTML;
            }
            document.getElementById('viewMembersModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside (works when modal has "modal show")
        window.onclick = function(event) {
            if (event.target.classList && event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Attach id to the add family form so footer button can reference it
        (function(){
            var modal = document.getElementById('addFamilyModal');
            if(modal){
                var form = modal.querySelector('form');
                if(form) form.id = 'addFamilyForm';
            }
        })();
        </script>
    </div>
    
    <!-- All modals removed as requested -->
    
    <script>
        // Keep only sidebar toggle and logout to preserve navbar behavior
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
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-resident').select2({
                dropdownParent: $('#addMemberModal'),
                placeholder: "Search for a resident...",
                allowClear: true,
                width: '100%'
            });
        });
    </script>
    <script>
        function validateAddFamily() {
            const householdNumber = document.querySelector('input[name="household_number"]').value.trim();
            if (!householdNumber) {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please enter a household number',
                    timer: 2000,
                    showConfirmButton: false
                });
                return false;
            }
            return true;
        }
        
        // Disable back button on forms
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>