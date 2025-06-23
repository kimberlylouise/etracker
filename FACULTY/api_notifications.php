<?php
// ...existing code...
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['for'] === 'faculty') {
    $department = $_GET['department'] ?? '';
    $sql = "SELECT * FROM notifications WHERE is_active=1 AND expires_at >= CURDATE() AND (audience='all' OR audience=?) ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $notifications]);
    exit;
}