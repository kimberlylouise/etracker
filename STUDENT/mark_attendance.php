<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$qr_data = $data['qr_data'] ?? null;
if ($qr_data) {
    $stmt = $conn->prepare("SELECT user_id, program_id FROM qr_codes WHERE code = ?");
    $stmt->bind_param('s', $qr_data);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code']);
        exit;
    }
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];
    $program_id = $row['program_id'];
} else {
    echo json_encode(['status' => 'error', 'message' => 'No code entered']);
    exit;
}

$time_in = $data['time_in'] ?? date('H:i:s');

if (!$program_id || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user or program info']);
    exit;
}

// Get user info
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