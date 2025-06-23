<?php

require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

if (!$id || !in_array($status, ['accepted', 'rejected'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$stmt = $conn->prepare("UPDATE participants SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Participant status updated']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update status']);
}
$stmt->close();