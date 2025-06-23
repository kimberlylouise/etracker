<?php
header('Content-Type: application/json');
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id']);
$status = $conn->real_escape_string($data['status']);
$remarks = $conn->real_escape_string($data['remarks'] ?? '');
$admin_id = 1; // TODO: Use session/admin login

$conn->query("UPDATE document_uploads SET status='$status', admin_remarks='$remarks', reviewed_by=$admin_id, reviewed_at=NOW() WHERE id=$id");
echo json_encode(['success' => true]);
?>