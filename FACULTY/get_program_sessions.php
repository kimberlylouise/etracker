<?php

require_once 'db.php';
header('Content-Type: application/json');
$program_id = $_GET['program_id'] ?? null;
if (!$program_id || !is_numeric($program_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
    exit;
}
$stmt = $conn->prepare("SELECT id, session_date, session_start, session_end, session_title FROM program_sessions WHERE program_id = ?");
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
$stmt->close();
echo json_encode(['status' => 'success', 'sessions' => $sessions]);
?>