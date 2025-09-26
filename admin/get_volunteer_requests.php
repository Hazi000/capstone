<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

// Check if user is authorized
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit();
}

// Get pending volunteer requests for the event
$query = "SELECT 
    vr.id,
    vr.registration_date,
    vr.status,
    r.full_name as name,
    r.contact_number as contact,
    r.email,
    e.title as event_title
FROM volunteer_registrations vr
JOIN residents r ON vr.resident_id = r.id
JOIN events e ON vr.event_id = e.id
WHERE vr.event_id = ? AND vr.status = 'pending'
ORDER BY vr.registration_date ASC";

$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
    $requests[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'contact' => $row['contact'],
        'email' => $row['email'],
        'registration_date' => $row['registration_date'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'event_title' => mysqli_fetch_assoc(mysqli_query($connection, "SELECT title FROM events WHERE id = $event_id"))['title'] ?? 'Event',
    'requests' => $requests
]);
echo json_encode([
    'success' => true,
    'event_title' => $event['title'] ?? 'Event',
    'requests' => $requests
]);
