<?php

session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['total_evaluations' => 0, 'programs' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];
// Get student_name
$user_query = $conn->prepare("SELECT firstname, lastname, mi FROM users WHERE id = ?");
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
$student_name = $user['firstname'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['lastname'];

// Get all programs the student is enrolled in
$sql = "SELECT p.id as program_id, p.program_name, e.status
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        WHERE e.user_id = ? AND e.status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$programs = [];
$total_evals = 0;
while ($row = $result->fetch_assoc()) {
    // Check if evaluated in detailed_evaluations
    $eval = $conn->prepare("SELECT id, eval_date FROM detailed_evaluations WHERE student_id=? AND program_id=?");
    $eval->bind_param('ii', $user_id, $row['program_id']);
    $eval->execute();
    $eval_result = $eval->get_result();
    $evaluated = $eval_result->num_rows > 0;
    $submitted_date = '';
    if ($evaluated) {
        $eval_row = $eval_result->fetch_assoc();
        $submitted_date = $eval_row['eval_date'];
    }
    $programs[] = [
        'program_id' => $row['program_id'],
        'program_name' => $row['program_name'],
        'status' => $row['status'],
        'can_evaluate' => !$evaluated,
        'submitted_date' => $submitted_date
    ];
    if ($evaluated) $total_evals++;
}
echo json_encode(['total_evaluations' => $total_evals, 'programs' => $programs]);
$conn->close();
?>