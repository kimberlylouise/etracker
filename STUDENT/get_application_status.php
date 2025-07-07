<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$program_id = $_GET['program_id'] ?? null;

if (!$program_id) {
    echo json_encode(['status' => 'error', 'message' => 'Program ID required']);
    exit;
}

$sql = "SELECT 
            p.program_name,
            e.status,
            e.enrollment_date,
            e.reason,
            e.id as enrollment_id
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        WHERE e.user_id = ? AND e.program_id = ?
        ORDER BY e.id DESC LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $program_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'status' => 'success', 
        'application' => [
            'program_name' => $row['program_name'],
            'status' => ucfirst($row['status']),
            'enrollment_date' => date('F j, Y', strtotime($row['enrollment_date'])),
            'reason' => $row['reason'],
            'enrollment_id' => $row['enrollment_id']
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Application not found']);
}

$stmt->close();
$conn->close();
?>
