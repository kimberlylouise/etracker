<?php
require_once 'db.php';

// Insert or update notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $message = $_POST['message'];
    $priority = $_POST['priority'];
    $expires_at = $_POST['expires_at'];
    $is_active = 1;

    if ($id) {
        $stmt = $conn->prepare("UPDATE notifications SET message=?, priority=?, expires_at=? WHERE id=?");
        $stmt->bind_param("sssi", $message, $priority, $expires_at, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (message, priority, created_at, expires_at, is_active) VALUES (?, ?, NOW(), ?, ?)");
        $stmt->bind_param("sssi", $message, $priority, $expires_at, $is_active);
    }
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// Fetch notifications for admin tab
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['for'] === 'admin') {
    $result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC");
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $notifications]);
    exit;
}

// Fetch notifications for faculty side
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['for'] === 'faculty') {
    $result = $conn->query("SELECT * FROM notifications WHERE is_active=1 AND expires_at >= CURDATE() ORDER BY created_at DESC");
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $notifications]);
    exit;
}
?>