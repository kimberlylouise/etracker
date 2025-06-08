<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$program_name = $data['program_name'] ?? '';
$time_in = $data['time_in'] ?? date('H:i:s'); // fallback to current time if not provided

if (!$program_name) {
    echo json_encode(['status' => 'error', 'message' => 'No program selected']);
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT firstname, lastname, mi FROM users WHERE id = ?");
if (!$user_query) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed (users): ' . $conn->error]);
    exit;
}
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
if ($user_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}
$user = $user_result->fetch_assoc();
$student_name = $user['firstname'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['lastname'];

// Get program_id from program_name
$prog_query = $conn->prepare("SELECT id FROM programs WHERE program_name = ?");
$prog_query->bind_param('s', $program_name);
$prog_query->execute();
$prog_result = $prog_query->get_result();
if ($prog_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Program not found']);
    exit;
}
$program = $prog_result->fetch_assoc();
$program_id = $program['id'];

// Prevent duplicate attendance for today
$today = date('Y-m-d');
$check_query = $conn->prepare("SELECT id FROM attendance WHERE student_name = ? AND program_id = ? AND date = ?");
$check_query->bind_param('sis', $student_name, $program_id, $today);
$check_query->execute();
$check_result = $check_query->get_result();
if ($check_result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Attendance already marked for today']);
    exit;
}

// Insert attendance
$status = 'Present';
$insert = $conn->prepare("INSERT INTO attendance (student_name, program_id, status, time_in, date) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param('sisss', $student_name, $program_id, $status, $time_in, $today);

if ($insert->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Attendance marked successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark attendance']);
}

$conn->close();
?>