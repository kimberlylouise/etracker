<?php
ob_start(); // Start output buffering
require_once 'db.php';

header('Content-Type: application/json');

$program_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$program_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID', 'data' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT student_name, student_email, enrollment_date FROM participants WHERE program_id = ? AND status = 'accepted'");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();
$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $participants]);
ob_end_flush();
?>
