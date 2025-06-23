<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

$user_id = $_SESSION['user_id'];
$sql = "SELECT program_id, status FROM enrollments WHERE user_id = ? AND (status = 'pending' OR status = 'approved')";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$enrolled = [];
while ($row = $result->fetch_assoc()) {
    $enrolled[$row['program_id']] = $row['status'];
}
echo json_encode(['status' => 'success', 'enrolled' => $enrolled]);
?>