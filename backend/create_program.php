<?php
header('Content-Type: application/json');
include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("INSERT INTO programs (program_name, department, start_date, end_date, location, max_students, description, status, faculty_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("sssssisii",
    $data['program_name'],
    $data['department'],
    $data['start_date'],
    $data['end_date'],
    $data['location'],
    $data['max_students'],
    $data['description'],
    $data['status'],
    $data['faculty_id']
);
$stmt->execute();
echo json_encode(['id' => $stmt->insert_id]);
$stmt->close();
?>