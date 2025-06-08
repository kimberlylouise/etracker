<?php

session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Get user's full name
$user_query = $conn->prepare("SELECT firstname, lastname, mi FROM users WHERE id = ?");
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
if ($user_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}
$user = $user_result->fetch_assoc();
$student_name = $user['firstname'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['lastname'];

// Get attendance records for this student using student_name
$sql = "SELECT a.date, a.time_in, a.status, p.program_name
        FROM attendance a
        JOIN programs p ON a.program_id = p.id
        WHERE a.student_name = ?
        ORDER BY a.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $student_name);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}
echo json_encode(['status' => 'success', 'records' => $records]);
$stmt->close();
$conn->close();
?>