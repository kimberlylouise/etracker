<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

if (!$id || !in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get enrollment info
$stmt = $conn->prepare("SELECT user_id, program_id FROM enrollments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($user_id, $program_id);
$stmt->fetch();
$stmt->close();

if ($status === 'approved') {
    // Check if participant already exists to avoid duplicates
    $check_stmt = $conn->prepare("SELECT id FROM participants WHERE program_id = ? AND student_email = (SELECT email FROM users WHERE id = ?)");
    $check_stmt->bind_param("ii", $program_id, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows === 0) {
        // Insert into participants
        $user_stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_stmt->bind_result($firstname, $lastname, $email);
        $user_stmt->fetch();
        $user_stmt->close();

        $insert_stmt = $conn->prepare("INSERT INTO participants (program_id, student_name, student_email, enrollment_date, status) VALUES (?, ?, ?, NOW(), 'accepted')");
        $student_name = $firstname . ' ' . $lastname;
        $insert_stmt->bind_param("iss", $program_id, $student_name, $email);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $check_stmt->close();
}

// Update enrollment status
$update_stmt = $conn->prepare("UPDATE enrollments SET status = ? WHERE id = ?");
$update_stmt->bind_param("si", $status, $id);
$update_stmt->execute();
$update_stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Enrollment status updated']);