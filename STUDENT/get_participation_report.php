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
$where = "a.student_name = ?";
$params = [$student_name];
$types = 's';

if ($program_id) {
    $where .= " AND a.program_id = ?";
    $params[] = $program_id;
    $types .= 'i';
}

$sql = "SELECT p.program_name, p.start_date, p.end_date
        FROM attendance a
        JOIN programs p ON a.program_id = p.id
        WHERE $where
        GROUP BY a.program_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$programs = [];
$total = $active = $completed = $pending = 0;
$today = date('Y-m-d');
while ($row = $result->fetch_assoc()) {
    $status = 'Active';
    if ($row['end_date'] < $today) {
        $status = 'Completed';
        $completed++;
    } else {
        $active++;
    }
    $programs[] = [
        'program_name' => $row['program_name'],
        'status' => $status,
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date']
    ];
    $total++;
}
echo json_encode([
    'total' => $total,
    'active' => $active,
    'completed' => $completed,
    'pending' => $pending,
    'programs' => $programs
]);
$conn->close();
?>