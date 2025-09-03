<?php
require_once __DIR__ . '/config.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$response = ['event_title' => '', 'volunteers' => []];

if ($event_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// get event title
$e_stmt = $connection->prepare("SELECT title FROM announcements WHERE id = ?");
$e_stmt->bind_param('i', $event_id);
$e_stmt->execute();
$e_res = $e_stmt->get_result();
$event = $e_res->fetch_assoc();
$response['event_title'] = $event['title'] ?? 'Event Volunteers';

// get approved volunteers
$q = "SELECT r.full_name, COALESCE(r.contact_number, '') as contact_number, COALESCE(cv.attendance_status, '') as attendance_status
      FROM community_volunteers cv
      JOIN residents r ON cv.resident_id = r.id
      WHERE cv.announcement_id = ? AND cv.status = 'approved'
      ORDER BY r.full_name ASC";
$stmt = $connection->prepare($q);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $response['volunteers'][] = [
        'name' => $row['full_name'],
        'contact' => $row['contact_number'],
        'attendance_status' => $row['attendance_status']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
