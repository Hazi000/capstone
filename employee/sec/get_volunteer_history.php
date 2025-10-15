<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

$resident_id = isset($_GET['resident_id']) ? intval($_GET['resident_id']) : 0;
if ($resident_id <= 0) {
    echo "<div class='empty-state' style='padding:2rem; text-align:center;'><h3>Invalid resident</h3></div>";
    exit;
}

$sql = "SELECT e.title AS event_title, e.description, e.event_start_date, e.event_time, e.location, vr.registration_date, vr.status, vr.attended_at
        FROM volunteer_registrations vr
        JOIN events e ON vr.event_id = e.id
        WHERE vr.resident_id = ? AND LOWER(COALESCE(vr.status,'')) IN ('approved','attended')
        ORDER BY e.event_start_date DESC";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $resident_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    echo "<div class='history-list'>";
    while ($row = mysqli_fetch_assoc($result)) {
        $status = $row['status'];
        $status_class = ($status === 'attended') ? 'attendance-attended' : 'status-approved';
        $activity_date = ($status === 'attended') ? ($row['attended_at'] ?? $row['registration_date']) : $row['registration_date'];
        $title = htmlspecialchars($row['event_title']);
        $desc = htmlspecialchars($row['description']);
        $date = !empty($row['event_start_date']) ? date('F j, Y', strtotime($row['event_start_date'])) : '-';
        $time = !empty($row['event_time']) ? ' at ' . date('g:i A', strtotime($row['event_time'])) : '';
        $loc = htmlspecialchars($row['location']);
        $activity = !empty($activity_date) ? date('M d, Y', strtotime($activity_date)) : '-';

        echo "<div style='background:#fff;padding:1rem;margin-bottom:1rem;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06);'>";
        echo "<div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;'>";
        echo "<h3 style='margin:0;font-size:1.05rem;color:#2c3e50;'>{$title}</h3>";
        echo "<span class='status-badge {$status_class}'>" . ucfirst($status) . "</span>";
        echo "</div>";
        echo "<div style='color:#666;font-size:0.9rem;margin-bottom:0.5rem;'>";
        echo "<div><i class='fas fa-calendar'></i> {$date}{$time}</div>";
        if ($loc) echo "<div><i class='fas fa-map-marker-alt'></i> {$loc}</div>";
        echo "</div>";
        if ($desc) echo "<div style='color:#555;font-size:0.9rem;margin-bottom:0.5rem;'>" . nl2br($desc) . "</div>";
        echo "<div style='font-size:0.85rem;color:#888;border-top:1px solid #eee;padding-top:0.5rem;'><i class='fas fa-clock'></i> {$activity}</div>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<div class='empty-state' style='padding:2rem; text-align:center;'><i class='fas fa-calendar-times' style='font-size:3rem;color:#ddd;'></i>
          <h3>No History Found</h3><p>This resident hasn't participated in any events yet.</p></div>";
}
?>
