<?php

session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_POST['full_name'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$course = $_POST['course'] ?? '';
$contact_email = $_POST['contact_email'] ?? '';
$contact_phone = $_POST['contact_phone'] ?? '';
$emergency_contact = $_POST['emergency_contact'] ?? '';

if (!$full_name || !$student_id || !$course || !$contact_email || !$contact_phone || !$emergency_contact) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

// Prevent duplicate registration
$stmt = $conn->prepare("SELECT id FROM student_profiles WHERE student_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Profile already exists.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$stmt = $conn->prepare("INSERT INTO student_profiles (student_id, full_name, course, contact_email, contact_phone, emergency_contact) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('isssss', $user_id, $full_name, $course, $contact_email, $contact_phone, $emergency_contact);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile registered successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Registration failed.']);
}
$stmt->close();
$conn->close();
?>