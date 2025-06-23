<?php
header('Content-Type: application/json');
include 'db.php';

$program_id = intval($_GET['program_id']);
$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("INSERT INTO program_sessions (program_id, session_title, session_date, session_start, session_end, location) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssss",
    $program_id,
    $data['session_title'],
    $data['session_date'],
    $data['session_start'],
    $data['session_end'],
    $data['location']
);
$stmt->execute();
echo json_encode(['id' => $stmt->insert_id]);
$stmt->close();
?>