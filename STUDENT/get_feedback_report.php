<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Get student_name from users table
$stmt = $conn->prepare("SELECT firstname, mi, lastname FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($fn, $mi, $ln);
$stmt->fetch();
$stmt->close();
$student_name = trim($fn . ' ' . ($mi ? $mi . '. ' : '') . $ln);

$program_id = $_GET['program_id'] ?? '';
$where = "student_name = ?";
$params = [$student_name];
$types = 's';

if ($program_id) {
    $where .= " AND program_id = ?";
    $params[] = $program_id;
    $types .= 'i';
}

$sql = "SELECT * FROM detailed_evaluations WHERE $where ORDER BY eval_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$feedbacks = [];
$total = 0;
$sum = 0;
while ($row = $result->fetch_assoc()) {
    $rating = round((
        $row['content'] +
        $row['facilitators'] +
        $row['relevance'] +
        $row['organization'] +
        $row['experience']
    ) / 5, 1);
    $feedbacks[] = [
        'program_name' => $row['program_id'], // You may join with programs for name
        'eval_date' => $row['eval_date'],
        'rating' => $rating,
        'suggestion' => $row['suggestion']
    ];
    $sum += $rating;
    $total++;
}
$avg = $total ? round($sum / $total, 2) : 0;

echo json_encode([
    'total' => $total,
    'avg_satisfaction' => $avg,
    'feedbacks' => $feedbacks
]);
$conn->close();
?>