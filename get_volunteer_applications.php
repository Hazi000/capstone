<?php
require_once __DIR__ . '/config.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$response = ['applications' => []];

if ($event_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$q = "SELECT cv.id, r.full_name, COALESCE(r.contact_number,'') as contact_number
      FROM community_volunteers cv
      JOIN residents r ON cv.resident_id = r.id
      WHERE cv.announcement_id = ? AND cv.status = 'pending'
      ORDER BY cv.created_at ASC";
$stmt = $connection->prepare($q);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $response['applications'][] = [
        'id' => (int)$row['id'],
        'name' => $row['full_name'],
        'contact' => $row['contact_number']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
