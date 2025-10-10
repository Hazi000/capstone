<?php
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

// basic auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'captain') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$volunteer_id = isset($_POST['volunteer_id']) ? intval($_POST['volunteer_id']) : 0;

if ($volunteer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid volunteer id']);
    exit;
}

if ($action === 'approve') {
    $sql = "UPDATE volunteer_registrations SET status = 'approved' WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $volunteer_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Volunteer approved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while approving']);
    }
    exit;
}

if ($action === 'reject') {
    $reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
    if ($reason === '') {
        echo json_encode(['success' => false, 'message' => 'Rejection reason required']);
        exit;
    }
    $sql = "UPDATE volunteer_registrations SET status = 'rejected', rejection_reason = ? WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "si", $reason, $volunteer_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Volunteer rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error while rejecting']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
