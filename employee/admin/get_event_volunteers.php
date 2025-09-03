<?php
session_start();
require_once '../../config.php';

// Check if user is logged in as admin or secretary
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'secretary'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get event_id from query params (use integer)
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Event ID is required']);
    exit();
}

// Get event details
$e_stmt = $connection->prepare("SELECT title FROM announcements WHERE id = ?");
$e_stmt->bind_param('i', $event_id);
$e_stmt->execute();
$e_res = $e_stmt->get_result();
$event = $e_res->fetch_assoc();
$event_title = $event['title'] ?? 'Event Volunteers';

// Get all volunteers (pending, approved, rejected) for this event with resident info
$q = "SELECT cv.id as volunteer_id, cv.resident_id, cv.status, cv.attendance_status,
             COALESCE(r.full_name, '') AS full_name, COALESCE(r.contact_number, '') AS contact_number
      FROM community_volunteers cv
      LEFT JOIN residents r ON cv.resident_id = r.id
      WHERE cv.announcement_id = ?
      ORDER BY FIELD(cv.status, 'pending','approved','rejected') ASC, r.full_name ASC";
$stmt = $connection->prepare($q);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$res = $stmt->get_result();

$volunteers = [];
while ($row = $res->fetch_assoc()) {
    $volunteers[] = [
        'id' => (int)$row['volunteer_id'],
        'resident_id' => (int)$row['resident_id'],
        'name' => $row['full_name'],
        'contact' => $row['contact_number'],
        'status' => $row['status'],
        'attendance_status' => $row['attendance_status']
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'event_title' => $event_title,
    'volunteers' => $volunteers
]);
exit();
?>
