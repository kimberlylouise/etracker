<?php
session_start();
header('Content-Type: application/json');

// Debugging: show errors in response (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Use your shared DB connection file
require_once 'db_connect.php'; // Adjust the path if needed

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$sql = "SELECT id, program_name FROM programs WHERE end_date >= CURDATE()";
$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
    $conn->close();
    exit;
}

$programs = [];
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}

echo json_encode(['status' => 'success', 'programs' => $programs]);
$conn->close();
?>