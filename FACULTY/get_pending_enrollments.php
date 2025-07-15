<?php
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
    exit;
}

$program_id = (int)$_GET['id'];

// Correct SQL: use the right column names from your enrollments table
$query = "SELECT e.id, e.enrollment_date, 
          CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
          u.email AS student_email
          FROM enrollments e
          JOIN users u ON e.user_id = u.id
          WHERE e.program_id = ? AND e.status = 'pending'
          ORDER BY e.enrollment_date DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $conn->error,
        'query' => $query // helpful for debugging
    ]);
    exit;
}

$stmt->bind_param('i', $program_id);
$stmt->execute();
$result = $stmt->get_result();

$pending = [];
while ($row = $result->fetch_assoc()) {
    $row['reject_reasons'] = [
        'Incomplete requirements',
        'Not eligible',
        'Program full',
        'Other'
    ];
    $pending[] = $row;
}

$stmt->close();


echo json_encode([
    'status' => 'success',
    'message' => 'Pending enrollments retrieved successfully',
    'data' => $pending
]);
// DO NOT put anything after this line (no HTML, no whitespace, no closing PHP tag)