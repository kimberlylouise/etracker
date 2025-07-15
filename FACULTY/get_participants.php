<?php
ob_start(); // Start output buffering
require_once 'db.php';

header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
    exit;
}

$program_id = (int)$_GET['id'];

// Query to get approved participants for a program
$query = "SELECT e.id, e.enrollment_date, 
          CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
          u.email AS student_email
          FROM enrollments e
          JOIN users u ON e.user_id = u.id
          WHERE e.program_id = ? AND e.status = 'approved'
          ORDER BY e.enrollment_date DESC";

// Prepare statement with error handling
$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

// Bind parameters and execute
$stmt->bind_param('i', $program_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch participants
$participants = [];
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}

$stmt->close();

// Return the participants as JSON
echo json_encode([
    'status' => 'success',
    'message' => 'Participants retrieved successfully',
    'data' => $participants
]);
ob_end_flush();
?>
