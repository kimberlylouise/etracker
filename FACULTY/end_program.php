<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = $data['program_id'] ?? null;

    if (!$program_id || !is_numeric($program_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
        exit;
    }

    // Update the program's status to 'ended' and end_date to today to mark it as ended
    $stmt = $conn->prepare("UPDATE programs SET status = 'ended', end_date = CURDATE() WHERE id = ?");
    $stmt->bind_param("i", $program_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Program ended successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to end program: ' . $conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
