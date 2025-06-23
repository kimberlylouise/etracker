<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['evaluations' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT de.*, p.program_name 
        FROM detailed_evaluations de
        JOIN programs p ON de.program_id = p.id
        WHERE de.student_id = ?
        ORDER BY de.eval_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$evaluations = [];
while ($row = $result->fetch_assoc()) {
    $evaluations[] = [
        'program_name' => $row['program_name'],
        'eval_date' => $row['eval_date'],
        'content' => $row['content'],
        'facilitators' => $row['facilitators'],
        'relevance' => $row['relevance'],
        'organization' => $row['organization'],
        'experience' => $row['experience'],
        'suggestion' => $row['suggestion'],
        'recommend' => $row['recommend']
    ];
}
echo json_encode(['evaluations' => $evaluations]);
?>