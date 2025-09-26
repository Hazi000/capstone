<?php
session_start();
require_once '../../config.php';
header('Content-Type: text/html; charset=UTF-8');

// Authorization: only secretary allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    echo '<div class="empty-state error">Unauthorized access</div>';
    exit();
}

if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    echo "<div class='error'>Invalid request</div>";
    exit;
}

$event_id = intval($_GET['event_id']);

// Get event details
$event_sql = "SELECT title FROM events WHERE id = ?";
$stmt = mysqli_prepare($connection, $event_sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$event_result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($event_result);

// Get pending volunteer requests
$sql = "SELECT vr.id, r.full_name 
        FROM volunteer_registrations vr
        JOIN residents r ON vr.resident_id = r.id 
        WHERE vr.event_id = ? AND vr.status = 'pending'
        ORDER BY vr.registration_date ASC";
        
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo "<h3>Pending Requests for: " . htmlspecialchars($event['title']) . "</h3>";
    echo "<div class='table-container' style='margin-top: 1rem;'>";
    echo "<table class='table'>";
    echo "<thead>
            <tr>
                <th>Name</th>
                <th>Actions</th>
            </tr>
          </thead><tbody>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td class='table-actions'>
                <button class='btn btn-success btn-sm' onclick='approveVolunteer(" . $row['id'] . ")'>
                    <i class='fas fa-check'></i> Approve
                </button>
                <button class='btn btn-danger btn-sm' onclick='openRejectModal(" . $row['id'] . ", \"" . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\")'>
                    <i class='fas fa-times'></i> Reject
                </button>
              </td>";
        echo "</tr>";
    }
    
    echo "</tbody></table></div>";
} else {
    echo "<div class='empty-state' style='text-align:center; padding:2rem;'>
            <i class='fas fa-clipboard-check' style='font-size:3rem; color:#ddd; margin-bottom:1rem;'></i>
            <h3>No Pending Requests</h3>
            <p>There are currently no pending volunteer applications for this event.</p>
          </div>";
}
?>


