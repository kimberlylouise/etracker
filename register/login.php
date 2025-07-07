<?php
session_start();
require_once '../FACULTY/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        exit;
    }

    // Check for admin credentials first
    if ($email === 'admin' && $password === 'admin') {
        $_SESSION['user_id'] = 'admin';
        $_SESSION['role'] = 'admin';
        error_log("Admin login successful");
        echo json_encode(['status' => 'success', 'redirect_url' => '../ADMIN/Dashboard.html']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        error_log("Login successful: user_id={$user['id']}, role={$user['role']}");
        $redirect_url = $user['role'] === 'faculty' ? '../FACULTY/Dashboard.php' : '../STUDENT/index.php';
        echo json_encode(['status' => 'success', 'redirect_url' => $redirect_url]);
    } else {
        error_log("Login failed for email: $email");
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>