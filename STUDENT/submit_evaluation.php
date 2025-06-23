<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$program_id = $data['program_id'] ?? '';
$score = $data['score'] ?? '';
$comments = $data['comments'] ?? '';

if (!$program_id || !$score) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

// Get student_name
$user_id = $_SESSION['user_id'];
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

$eval_date = date('Y-m-d');
$stmt = $conn->prepare("INSERT INTO evaluations (student_name, score, comments, eval_date, program_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('ssssi', $student_name, $score, $comments, $eval_date, $program_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit feedback. Error: ' . $stmt->error]);
}
$conn->close();
?>