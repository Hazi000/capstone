<?php
session_start();
require_once '../../config.php';

// Check if user is logged in as admin or secretary
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'secretary'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get event_id from query params
$event_id = isset($_GET['event_id']) ? mysqli_real_escape_string($connection, $_GET['event_id']) : null;

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Event ID is required']);
    exit();
}

// Get pending applications for this event
$applications_query = "SELECT cv.*, r.full_name as name, r.contact_number as contact 
                      FROM community_volunteers cv
                      LEFT JOIN residents r ON cv.resident_id = r.id
                      WHERE cv.announcement_id = '$event_id'
                      AND cv.status = 'pending'
                      ORDER BY cv.created_at DESC";

$applications_result = mysqli_query($connection, $applications_query);

$applications = [];
while ($app = mysqli_fetch_assoc($applications_result)) {
    $applications[] = [
        'id' => $app['id'],
        'name' => $app['name'],
        'contact' => $app['contact']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'applications' => $applications
]);
?>
]);
?>
