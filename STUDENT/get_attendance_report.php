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

$sql = "SELECT * FROM attendance WHERE $where ORDER BY date";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
$total = 0;
$attended = 0;
while ($row = $result->fetch_assoc()) {
    $sessions[] = [
        'date' => $row['date'],
        'status' => $row['status'],
        'time_in' => $row['time_in'],
        'time_out' => $row['time_out']
    ];
    $total++;
    if ($row['status'] === 'Present' || $row['status'] === 'Late') $attended++;
}
$stmt->close();

$attendance_rate = $total ? round(($attended / $total) * 100, 1) : 0;

echo json_encode([
    'attended' => $attended,
    'total_sessions' => $total,
    'attendance_rate' => $attendance_rate,
    'sessions' => $sessions
]);
$conn->close();
?>