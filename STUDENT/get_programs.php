<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
file_put_contents('debug.log', "get_programs.php called at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

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

$sql = "SELECT id, program_name, start_date, end_date, department
        FROM programs
        WHERE end_date >= CURDATE()";
$result = $conn->query($sql);
if (!$result) {
    file_put_contents('debug.log', "Query failed: " . $conn->error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$programs = [];
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}
file_put_contents('debug.log', "Programs fetched: " . count($programs) . "\n", FILE_APPEND);

echo json_encode(['status' => 'success', 'programs' => $programs]);
$conn->close();
?>