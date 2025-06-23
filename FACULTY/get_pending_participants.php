<?php

require_once 'db.php';
header('Content-Type: application/json');

$program_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$program_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID', 'data' => []]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT e.id, u.firstname, u.lastname, u.email, e.enrollment_date, e.reason
     FROM enrollments e
     JOIN users u ON e.user_id = u.id
     WHERE e.program_id = ? AND e.status = 'pending'"
);
$stmt->bind_param("i", $program_id);
$stmt->execute();
$result = $stmt->get_result();
$pending = [];
while ($row = $result->fetch_assoc()) {
    $pending[] = [
        'id' => $row['id'],
        'student_name' => $row['firstname'] . ' ' . $row['lastname'],
        'student_email' => $row['email'],
        'enrollment_date' => $row['enrollment_date'],
        'reason' => $row['reason']
    ];
}
$stmt->close();

echo json_encode(['status' => 'success', 'data' => $pending]);