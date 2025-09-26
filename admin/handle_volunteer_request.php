<?php
session_start();
require_once '../config.php';

// Check if user is authorized
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;

    if (!$volunteer_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid volunteer ID']);
        exit();
    }

    // Get the volunteer registration details
    $get_volunteer = "SELECT vr.*, e.max_volunteers, 
        (SELECT COUNT(*) FROM volunteer_registrations 
         WHERE event_id = vr.event_id AND status = 'approved') as current_volunteers
        FROM volunteer_registrations vr
        JOIN events e ON vr.event_id = e.id
        WHERE vr.id = ?";
    
    $stmt = mysqli_prepare($connection, $get_volunteer);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $volunteer = mysqli_fetch_assoc($result);

    if (!$volunteer) {
        echo json_encode(['success' => false, 'message' => 'Volunteer registration not found']);
        exit();
    }

    switch ($action) {
        case 'approve':
            // Check if event is already full
            if ($volunteer['current_volunteers'] >= $volunteer['max_volunteers']) {
                echo json_encode(['success' => false, 'message' => 'Event has reached maximum volunteers']);
                exit();
            }

            $update_sql = "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ?";
            $stmt = mysqli_prepare($connection, $update_sql);
            mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Create notification for resident
                $notification_sql = "INSERT INTO notifications (user_id, type, message, related_id) 
                                   VALUES (?, 'volunteer_approved', 'Your volunteer application has been approved', ?)";
                $stmt = mysqli_prepare($connection, $notification_sql);
                mysqli_stmt_bind_param($stmt, "ii", $volunteer['resident_id'], $volunteer['event_id']);
                mysqli_stmt_execute($stmt);
                
                echo json_encode(['success' => true, 'message' => 'Volunteer application approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error approving application']);
            }
            break;

        case 'reject':
            $reason = $_POST['rejection_reason'] ?? '';
            
            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
                exit();
            }

            $update_sql = "UPDATE volunteer_registrations SET status = 'rejected', rejection_reason = ? WHERE id = ?";
            $stmt = mysqli_prepare($connection, $update_sql);
            mysqli_stmt_bind_param($stmt, "si", $reason, $volunteer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Create notification for resident
                $notification_sql = "INSERT INTO notifications (user_id, type, message, related_id) 
                                   VALUES (?, 'volunteer_rejected', ?, ?)";
                $message = "Your volunteer application has been rejected. Reason: " . $reason;
                $stmt = mysqli_prepare($connection, $notification_sql);
                mysqli_stmt_bind_param($stmt, "isi", $volunteer['resident_id'], $message, $volunteer['event_id']);
                mysqli_stmt_execute($stmt);
                
                echo json_encode(['success' => true, 'message' => 'Volunteer application rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error rejecting application']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
