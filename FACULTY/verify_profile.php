<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'faculty') {
    $user_id = $_POST['user_id'];
    // Simulate sending verification email/SMS (implement actual email/SMS service here)
    $stmt = $conn->prepare("UPDATE users SET verification_status = 'verified' WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Verification email/SMS sent']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to initiate verification']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
}
?>