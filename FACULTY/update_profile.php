<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] === 'faculty') {
    $user_id = $_SESSION['user_id']; // Use session user_id for security
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $mi = $_POST['mi'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, mi = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $firstname, $lastname, $mi, $email, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update profile']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
}
?>