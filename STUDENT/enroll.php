<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
file_put_contents('debug.log', "enroll.php called at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Enhanced authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    file_put_contents('debug.log', "Unauthorized: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", role=" . ($_SESSION['role'] ?? 'not set') . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in as a student']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$program_id = $input['program_id'] ?? null;
$reason = $input['reason'] ?? '';

if (!$program_id) {
    file_put_contents('debug.log', "Missing program_id\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Program ID is required']);
    exit;
}

require_once '../FACULTY/db.php';
if ($conn->connect_error) {
    file_put_contents('debug.log', "Database connection failed: " . $conn->connect_error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if program exists and is active
$program_check = $conn->prepare("SELECT id, program_name, status, max_students FROM programs WHERE id = ? AND status = 'ongoing'");
$program_check->bind_param('i', $program_id);
$program_check->execute();
$program_result = $program_check->get_result();

if ($program_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Program not found or not accepting enrollments']);
    $program_check->close();
    $conn->close();
    exit;
}

$program_data = $program_result->fetch_assoc();
$program_check->close();

// Check current enrollment count
$count_stmt = $conn->prepare("SELECT COUNT(*) as current_count FROM enrollments WHERE program_id = ? AND status = 'approved'");
$count_stmt->bind_param('i', $program_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$current_count = $count_result->fetch_assoc()['current_count'];
$count_stmt->close();

if ($current_count >= $program_data['max_students']) {
    echo json_encode(['status' => 'error', 'message' => 'Program is at full capacity']);
    $conn->close();
    exit;
}
// Check for existing enrollment
$stmt = $conn->prepare("SELECT id, status FROM enrollments WHERE user_id = ? AND program_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('ii', $user_id, $program_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'pending') {
        file_put_contents('debug.log', "Already pending: user_id=$user_id, program_id=$program_id\n", FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'You already have a pending enrollment for this program']);
        $stmt->close();
        $conn->close();
        exit;
    } elseif ($row['status'] === 'approved') {
        file_put_contents('debug.log', "Already enrolled: user_id=$user_id, program_id=$program_id\n", FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'You are already enrolled in this program']);
        $stmt->close();
        $conn->close();
        exit;
    }
}
$stmt->close();

// Insert new enrollment
$stmt = $conn->prepare("INSERT INTO enrollments (user_id, program_id, reason, status, enrollment_date) VALUES (?, ?, ?, 'pending', NOW())");
$stmt->bind_param('iis', $user_id, $program_id, $reason);

if ($stmt->execute()) {
    file_put_contents('debug.log', "Enrollment successful: user_id=$user_id, program_id=$program_id\n", FILE_APPEND);
    echo json_encode([
        'status' => 'success', 
        'message' => 'Enrollment request submitted successfully! Your application is pending approval.',
        'program_name' => $program_data['program_name']
    ]);
} else {
    file_put_contents('debug.log', "Enrollment failed: " . $stmt->error . "\n", FILE_APPEND);
    echo json_encode(['status' => 'error', 'message' => 'Failed to submit enrollment request. Please try again.']);
}

$stmt->close();
$conn->close();
?>