<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Join students and users table to get all needed info
$sql = "SELECT 
            students.student_id, 
            students.course, 
            students.contact_no, 
            students.emergency_contact,
            users.firstname, 
            users.lastname, 
            users.mi, 
            users.email
        FROM students
        JOIN users ON students.user_id = users.id
        WHERE students.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    // Build full name
    $mi = $row['mi'] ? $row['mi'] . '.' : '';
    $full_name = trim($row['firstname'] . ' ' . $mi . ' ' . $row['lastname']);
    $profile = [
        'full_name' => $full_name,
        'student_id' => $row['student_id'],
        'course' => $row['course'],
        'contact_email' => $row['email'],
        'contact_no' => $row['contact_no'],
        'emergency_contact' => $row['emergency_contact']
    ];
    echo json_encode(['status' => 'success', 'profile' => $profile]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Profile not found']);
}
$stmt->close();
$conn->close();
?>