<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id']) || !is_numeric($data['id']) || !isset($data['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = (int)$data['id'];
$status = $data['status'];
$reason = isset($data['reason']) ? $data['reason'] : '';

if ($status === 'rejected') {
    $query = "UPDATE enrollments SET status = 'rejected', reason = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $reason, $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Enrollment rejected.']);
} else if ($status === 'approved') {
    $query = "UPDATE enrollments SET status = 'approved', reason = NULL WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Enrollment approved.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unknown status']);
}
?>