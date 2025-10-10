<?php
session_start();
require_once '../../config.php';

// Check if user is logged in and is a secretary
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'secretary') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if complaint ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

$complaint_id = intval($_GET['id']);

// Add pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get total complaints count
$count_query = "SELECT COUNT(*) as total FROM complaints";
$count_result = mysqli_query($connection, $count_query);
$total_complaints = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_complaints / $items_per_page);

// Get complaint details with resident information
$query = "SELECT c.id, c.subject, c.description, c.status, c.priority, c.created_at, 
                 c.complainant_name, c.complainant_contact,
                 r.full_name as resident_name, r.contact_number as resident_contact 
          FROM complaints c 
          LEFT JOIN residents r ON c.resident_id = r.id 
          WHERE c.id = $complaint_id
          LIMIT $items_per_page OFFSET $offset";

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

// Update the JSON response to include pagination info
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'complaint' => $complaint,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'items_per_page' => $items_per_page,
        'total_items' => $total_complaints
    ]
]);
?>