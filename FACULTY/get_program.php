<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
    exit;
}

$program_id = (int)$_GET['id'];

// Fetch program details
$program_query = "SELECT id, program_name, department, start_date, end_date, location, max_students, description FROM programs WHERE id = ?";
$program_stmt = $conn->prepare($program_query);
if ($program_stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$program_stmt->bind_param('i', $program_id);
$program_stmt->execute();
$program_result = $program_stmt->get_result();
$program = $program_result->fetch_assoc();
$program_stmt->close();

if (!$program) {
    echo json_encode(['status' => 'error', 'message' => 'Program not found']);
    exit;
}

// Fetch sessions for the program
$sessions_query = "SELECT id, session_title, session_date, session_start, session_end FROM program_sessions WHERE program_id = ?";
$sessions_stmt = $conn->prepare($sessions_query);
if ($sessions_stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$sessions_stmt->bind_param('i', $program_id);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();
$sessions = [];
while ($row = $sessions_result->fetch_assoc()) {
    $sessions[] = $row;
}
$sessions_stmt->close();

$program['sessions'] = $sessions;

echo json_encode([
    'status' => 'success',
    'data' => $program
]);
?>
