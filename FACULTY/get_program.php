<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

$program_id = $_GET['id'] ?? null;
if ($program_id && is_numeric($program_id)) {
    $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch sessions for this program
    $sessions = [];
    $session_stmt = $conn->prepare("SELECT id, session_date, session_start, session_end, session_title FROM program_sessions WHERE program_id = ?");
    $session_stmt->bind_param("i", $program_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    while ($row = $session_result->fetch_assoc()) {
        $sessions[] = $row;
    }
    $session_stmt->close();

    if ($program) {
        $program['sessions'] = $sessions;
        echo json_encode(['status' => 'success', 'data' => $program]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Program not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
}

// Example: Fetch active programs for the logged-in faculty
$faculty_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM programs WHERE faculty_id = ? AND start_date <= ? AND end_date >= ?");
$stmt->bind_param("iss", $faculty_id, $today, $today);
$stmt->execute();
$result = $stmt->get_result();
$active_programs = [];
while ($row = $result->fetch_assoc()) {
    $active_programs[] = $row;
}
$stmt->close();

if (empty($program['sessions'])) {
    echo "<p>No sessions scheduled for this program.</p>";
} else {
    // display sessions table
}
?>
