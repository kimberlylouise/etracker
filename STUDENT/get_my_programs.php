<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT 
            p.id, 
            p.program_name,
            e.status,
            p.status AS program_status,
            u.firstname AS faculty_firstname,
            u.lastname AS faculty_lastname
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        JOIN users u ON p.faculty_id = u.id
        WHERE e.user_id = ? AND e.status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$programs = [];
while ($row = $result->fetch_assoc()) {
    $row['faculty_name'] = $row['faculty_firstname'] . ' ' . $row['faculty_lastname'];
    $programs[] = $row;
}

echo json_encode(['status' => 'success', 'programs' => $programs]);
?>