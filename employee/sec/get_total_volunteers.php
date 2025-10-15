<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    die("Unauthorized access");
}

// Get unique volunteers with their latest status
$sql = "SELECT DISTINCT 
            r.id as resident_id,
            r.full_name,
            r.contact_number,
            r.email,
            COUNT(vr.id) as total_events,
            MAX(CASE WHEN vr.status = 'attended' THEN vr.attended_at ELSE vr.registration_date END) as last_activity
        FROM volunteer_registrations vr
        LEFT JOIN residents r ON vr.resident_id = r.id
        WHERE vr.status IN ('approved', 'attended')
        GROUP BY r.id, r.full_name, r.contact_number, r.email
        ORDER BY r.full_name ASC";

$result = mysqli_query($connection, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<div class='table-container'>
            <table class='table'>
                <thead>
                    <tr>
                        <th>Resident Name</th>
                        <th>Contact Info</th>
                        <th>Total Events</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>";

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>
                <td>" . htmlspecialchars($row['full_name']) . "</td>
                <td>
                    " . htmlspecialchars($row['contact_number']) . "<br>
                    <small>" . htmlspecialchars($row['email']) . "</small>
                </td>
                <td>" . $row['total_events'] . " events</td>
                <td>" . date('M d, Y', strtotime($row['last_activity'])) . "</td>
                <td>
                    <button class='btn btn-info btn-sm view-history-btn' data-resident-id='" . $row['resident_id'] . "' data-resident-name=\"" . htmlspecialchars($row['full_name'], ENT_QUOTES) . "\">
                        <i class='fas fa-history'></i> View History
                    </button>
                </td>
              </tr>";
    }

    echo "</tbody></table></div>";
} else {
    echo "<div class='empty-state' style='text-align:center; padding:2rem;'>
            <i class='fas fa-users' style='font-size:3rem; color:#ddd;'></i>
            <h3>No Volunteers Found</h3>
            <p>There are no approved or attended volunteers yet.</p>
          </div>";
}
?>
