<?php

session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in as a student']);
    exit;
}

require_once '../FACULTY/db.php';
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Removed the date filter for reports
$sql = "SELECT 
            p.id, 
            p.program_name, 
            p.start_date, 
            p.end_date, 
            p.department,
            p.location,
            p.max_students,
            p.description,
            p.status,
            u.firstname AS faculty_firstname,
            u.lastname AS faculty_lastname
        FROM programs p
        LEFT JOIN users u ON p.faculty_id = u.id
        WHERE p.status = 'ongoing'
        ORDER BY p.start_date DESC";
$result = $conn->query($sql);
if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

$programs = [];
while ($row = $result->fetch_assoc()) {
    // Sessions
    $sessions = [];
    $sess_sql = "SELECT session_title, session_date, session_start, session_end, location FROM program_sessions WHERE program_id = ?";
    $sess_stmt = $conn->prepare($sess_sql);
    $sess_stmt->bind_param('i', $row['id']);
    $sess_stmt->execute();
    $sess_result = $sess_stmt->get_result();
    while ($sess_row = $sess_result->fetch_assoc()) {
        $sessions[] = $sess_row;
    }
    $sess_stmt->close();

    $row['faculty_name'] = trim($row['faculty_firstname'] . ' ' . $row['faculty_lastname']);
    $row['sessions'] = $sessions;
    unset($row['faculty_firstname'], $row['faculty_lastname']);
    $programs[] = $row;
}

echo json_encode(['status' => 'success', 'programs' => $programs]);
$conn->close();
?>