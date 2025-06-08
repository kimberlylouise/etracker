<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
file_put_contents('debug.log', "get_my_programs.php called at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    file_put_contents('debug.log', "Unauthorized: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", role=" . ($_SESSION['role'] ?? 'not set') . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in as a student']);
    exit;
}

require_once '../FACULTY/db.php';
if ($conn->connect_error) {
    file_put_contents('debug.log', "Database connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT p.program_name, e.status
    FROM enrollments e
    JOIN programs p ON e.program_id = p.id
    WHERE e.user_id = ?
");
if (!$stmt) {
    file_put_contents('debug.log', "Prepare failed: " . $conn->error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) {
    file_put_contents('debug.log', "Execute failed: " . $stmt->error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Query execution failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
$programs = [];
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}
file_put_contents('debug.log', "Enrolled programs fetched: " . count($programs) . "\n", FILE_APPEND);

echo json_encode(['status' => 'success', 'programs' => $programs]);
$stmt->close();
$conn->close();
?>