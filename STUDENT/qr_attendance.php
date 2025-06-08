<?php
session_start();
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Please log in to mark attendance.');
}

$user_id = $_SESSION['user_id'];
$program_id = $_GET['program_id'] ?? '';
$date = $_GET['date'] ?? '';
$token = $_GET['token'] ?? '';

// 1. Validate token for this program and date
$stmt = $conn->prepare("SELECT * FROM qr_sessions WHERE program_id=? AND date=? AND token=? AND NOW() BETWEEN start_time AND end_time");
$stmt->bind_param('iss', $program_id, $date, $token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Invalid or expired QR code.');
}

// 2. Check if student is approved
$enroll = $conn->prepare("SELECT * FROM enrollments WHERE user_id=? AND program_id=? AND status='approved'");
$enroll->bind_param('ii', $user_id, $program_id);
$enroll->execute();
if ($enroll->get_result()->num_rows === 0) {
    die('You are not approved for this program.');
}

// 3. Prevent duplicate attendance
$check = $conn->prepare("SELECT id FROM attendance WHERE student_name=? AND program_id=? AND date=?");
// Fetch student name from users table
$user_stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows === 0) {
    die('User not found.');
}
$student_row = $user_result->fetch_assoc();
$student_name = $student_row['name'];
$check->bind_param('sis', $student_name, $program_id, $date);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    die('Attendance already marked for today.');
}

// 4. Mark attendance
$time_in = date('H:i:s');
$status = 'Present';
$insert = $conn->prepare("INSERT INTO attendance (student_name, program_id, status, time_in, date) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param('sisss', $student_name, $program_id, $status, $time_in, $date);
$insert->execute();

echo 'Attendance marked successfully!';
?>