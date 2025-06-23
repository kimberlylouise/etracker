<?php
header('Content-Type: application/json');
include 'db.php';

$id = intval($_GET['id']);
$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("UPDATE programs SET program_name=?, department=?, start_date=?, end_date=?, location=?, max_students=?, description=?, status=?, faculty_id=? WHERE id=?");
$stmt->bind_param("sssssisiii",
    $data['program_name'],
    $data['department'],
    $data['start_date'],
    $data['end_date'],
    $data['location'],
    $data['max_students'],
    $data['description'],
    $data['status'],
    $data['faculty_id'],
    $id
);
$stmt->execute();
echo json_encode(['success' => true]);
$stmt->close();
?>