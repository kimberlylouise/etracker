<?php
header('Content-Type: application/json');
include 'db.php';

$id = intval($_GET['id']);
$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("UPDATE program_sessions SET session_title=?, session_date=?, session_start=?, session_end=?, location=? WHERE id=?");
$stmt->bind_param("sssssi",
    $data['session_title'],
    $data['session_date'],
    $data['session_start'],
    $data['session_end'],
    $data['location'],
    $id
);
$stmt->execute();
echo json_encode(['success' => true]);
$stmt->close();
?>