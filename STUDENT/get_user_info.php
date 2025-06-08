<?php
session_start();
header('Content-Type: application/json');

// Enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
file_put_contents('debug.log', "get_user_info.php called at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    file_put_contents('debug.log', "Unauthorized: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", role=" . ($_SESSION['role'] ?? 'not set') . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in as a student']);
    exit;
}

require_once '../FACULTY/db.php'; // Ensure this path is correct

if ($conn->connect_error) {
    file_put_contents('debug.log', "Database connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$user_id = $_SESSION['user_id'];
file_put_contents('debug.log', "Querying for user_id=$user_id\n", FILE_APPEND);
$stmt = $conn->prepare("
    SELECT u.firstname, u.lastname, s.student_id
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE u.id = ?
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
$user = $result->fetch_assoc();
if (!$user) {
    file_put_contents('debug.log', "No user found for user_id=$user_id\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit;
}

file_put_contents('debug.log', "User found: " . json_encode($user) . "\n", FILE_APPEND);
echo json_encode(['status' => 'success', 'user' => $user]);
$stmt->close();
$conn->close();
?>