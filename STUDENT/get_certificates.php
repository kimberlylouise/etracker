<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Get student email from users table
$stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($email);
$stmt->fetch();
$stmt->close();

$program_id = $_GET['program_id'] ?? '';
$where = "p.student_email = ? AND p.certificate_issued = 1";
$params = [$email];
$types = 's';

if ($program_id) {
    $where .= " AND p.program_id = ?";
    $params[] = $program_id;
    $types .= 'i';
}

$sql = "SELECT p.id, p.program_id, p.student_name, p.issued_on AS certificate_date, pr.program_name, p.certificate_file
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE $where
        ORDER BY p.issued_on DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$certificates = [];
while ($row = $result->fetch_assoc()) {
    $certificates[] = [
        'program_name' => $row['program_name'],
        'certificate_date' => $row['certificate_date'],
        'status' => 'Generated',
        'certificate_url' => $row['certificate_file'] ? $row['certificate_file'] : null
    ];
}
echo json_encode([
    'total' => count($certificates),
    'certificates' => $certificates
]);
$conn->close();
?>