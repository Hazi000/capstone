<?php
session_start();
require_once '../../config.php';



// Check if complaint ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

$complaint_id = intval($_GET['id']);

// Get complaint details with resident information
$query = "SELECT c.id, c.subject, c.description, c.status, c.priority, c.created_at, 
                 c.complainant_name, c.complainant_contact,
                 r.full_name as resident_name, r.contact_number as resident_contact 
          FROM complaints c 
          LEFT JOIN residents r ON c.resident_id = r.id 
          WHERE c.id = $complaint_id";

$result = mysqli_query($connection, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

if (mysqli_num_rows($result) === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Complaint not found']);
    exit();
}

$complaint = mysqli_fetch_assoc($result);

// Return complaint details as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'complaint' => $complaint
]);
?>