<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit();
}

// Handle POST requests for approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if (isset($_POST['action']) && isset($_POST['volunteer_id'])) {
        $volunteer_id = intval($_POST['volunteer_id']);
        
        if ($_POST['action'] === 'approve') {
            $sql = "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'Volunteer approved successfully'];
            }
        } 
        elseif ($_POST['action'] === 'reject' && isset($_POST['rejection_reason'])) {
            $reason = trim($_POST['rejection_reason']);
            $sql = "UPDATE volunteer_registrations SET status = 'rejected', rejection_reason = ? WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "si", $reason, $volunteer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $response = ['success' => true, 'message' => 'Volunteer rejected successfully'];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

if (!isset($_GET['event_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Event ID is required';
    exit();
}

$event_id = intval($_GET['event_id']);

// Get event details
$event_sql = "SELECT title FROM events WHERE id = ?";
$stmt = mysqli_prepare($connection, $event_sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event_result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($event_result);

// Get volunteers for this event
$sql = "SELECT vr.id, vr.registration_date, vr.status, r.full_name, 
        COALESCE(vr.hours_served, 0) as hours_served
        FROM volunteer_registrations vr
        JOIN residents r ON vr.resident_id = r.id 
        WHERE vr.event_id = ?
        ORDER BY r.full_name ASC";
        
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo "<h3>Volunteers for: " . htmlspecialchars($event['title']) . "</h3>";
    echo "<div class='table-container' style='margin-top: 1rem;'>";
    echo "<table class='table'>";
    echo "<thead>
            <tr>
                <th>Name</th>
                <th>Status</th>
                <th>Hours Served</th>
                <th>Actions</th>
            </tr>
          </thead><tbody>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td><span class='status-badge status-" . strtolower($row['status']) . "'>" 
             . ucfirst($row['status']) . "</span></td>";
        echo "<td>" . ($row['hours_served'] > 0 ? htmlspecialchars($row['hours_served']) : '-') . "</td>";
        echo "<td class='table-actions'>";
        if ($row['status'] === 'pending') {
            echo "<button class='btn btn-success btn-sm' onclick='approveVolunteer(" . $row['id'] . ")'>"
                 . "<i class='fas fa-check'></i> Approve</button> ";
            echo "<button class='btn btn-danger btn-sm' onclick='openRejectModal(" . $row['id'] . ", \"" 
                 . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\")'>"
                 . "<i class='fas fa-times'></i> Reject</button>";
        } elseif ($row['status'] === 'approved' && $row['hours_served'] == 0) {
            echo "<button class='btn btn-primary btn-sm' onclick='markAttendance(" . $row['id'] . ", \"" 
                 . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\")'>"
                 . "<i class='fas fa-clock'></i> Mark Hours</button>";
        }
        echo "</td></tr>";
    }
    
    echo "</tbody></table></div>";
} else {
    echo "<div class='empty-state' style='text-align:center; padding:2rem;'>
            <i class='fas fa-users' style='font-size:3rem; color:#ddd; margin-bottom:1rem;'></i>
            <h3>No Volunteers Found</h3>
            <p>There are no volunteers registered for this event yet.</p>
          </div>";
}
?>
