<?php
ob_start(); // Start output buffering
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $program_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT student_name, student_email, enrollment_date FROM participants WHERE program_id = ? ORDER BY enrollment_date");
    if ($stmt === false) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
    
    ob_end_clean();
    echo json_encode(['status' => 'success', 'data' => $participants]);
    $stmt->close();
} else {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid program ID']);
}
ob_end_flush();
?>
