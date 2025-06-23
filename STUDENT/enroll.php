<?php
     session_start();
     header('Content-Type: application/json');

     ini_set('display_errors', 0);
     error_reporting(E_ALL);
     file_put_contents('debug.log', "enroll.php called at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

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
     $stmt = $conn->prepare("SELECT id, status FROM enrollments WHERE user_id = ? AND program_id = ? ORDER BY id DESC LIMIT 1");
     $stmt->bind_param('ii', $user_id, $program_id);
     $stmt->execute();
     $result = $stmt->get_result();
     if ($row = $result->fetch_assoc()) {
         if ($row['status'] === 'pending' || $row['status'] === 'approved') {
             file_put_contents('debug.log', "Already enrolled: user_id=$user_id, program_id=$program_id\n", FILE_APPEND);
             echo json_encode(['status' => 'error', 'message' => 'Already enrolled in this program']);
             $stmt->close();
             $conn->close();
             exit;
         }
     }

     $stmt = $conn->prepare("INSERT INTO enrollments (user_id, program_id, reason, status, enrollment_date) VALUES (?, ?, ?, 'pending', NOW())");
     $stmt->bind_param('iis', $user_id, $program_id, $reason);
     if ($stmt->execute()) {
         file_put_contents('debug.log', "Enrollment successful: user_id=$user_id, program_id=$program_id\n", FILE_APPEND);
         echo json_encode(['status' => 'success', 'message' => 'Enrollment successful']);
     } else {
         file_put_contents('debug.log', "Enrollment failed: " . $stmt->error . "\n", FILE_APPEND);
         echo json_encode(['status' => 'error', 'message' => 'Enrollment failed: ' . $stmt->error]);
     }

     $stmt->close();
     $conn->close();
     ?>