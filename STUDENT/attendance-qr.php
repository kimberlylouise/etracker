<?php
session_start();
require_once 'FACULTY/db.php';

$program_id = $_GET['program_id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

if (!isset($_SESSION['user_id'])) {
    // Redirect to login or show login form
    echo "<h2>Please <a href='login.php'>log in</a> to mark attendance.</h2>";
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if enrolled and approved
$stmt = $conn->prepare("SELECT * FROM enrollments WHERE user_id=? AND program_id=? AND status='approved'");
$stmt->bind_param('ii', $user_id, $program_id);
$stmt->execute();
$enroll = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($enroll->num_rows === 0) {
        echo "<h2 style='color:red;'>You are not enrolled in this program.</h2>";
    } else {
        // Get student_name
        $userq = $conn->prepare("SELECT firstname, lastname, mi FROM users WHERE id=?");
        $userq->bind_param('i', $user_id);
        $userq->execute();
        $user = $userq->get_result()->fetch_assoc();
        $student_name = $user['firstname'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['lastname'];

        // Prevent duplicate
        $check = $conn->prepare("SELECT id FROM attendance WHERE student_name=? AND program_id=? AND date=?");
        $check->bind_param('sis', $student_name, $program_id, $date);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo "<h2 style='color:orange;'>Attendance already marked for today.</h2>";
        } else {
            $time_in = date('H:i:s');
            $status = 'Present';
            $ins = $conn->prepare("INSERT INTO attendance (student_name, program_id, status, time_in, date) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param('sisss', $student_name, $program_id, $status, $time_in, $date);
            if ($ins->execute()) {
                echo "<h2 style='color:green;'>Attendance marked successfully!</h2>";
            } else {
                echo "<h2 style='color:red;'>Failed to mark attendance.</h2>";
            }
        }
    }
    echo "<a href='index.html'>Back to Home</a>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f7ffe6; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh;}
        .qr-attendance-box { background: #fff; padding: 32px 24px; border-radius: 18px; box-shadow: 0 4px 18px rgba(46,110,30,0.10); text-align: center; }
        button { padding: 12px 32px; font-size: 1.1rem; background: linear-gradient(90deg, #2e6e1e 60%, #5cb85c 100%); color: #fff; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; margin-top: 18px;}
        button:hover { background: linear-gradient(90deg, #218c21 60%, #7ed957 100%);}
    </style>
</head>
<body>
    <div class="qr-attendance-box">
        <h2>Mark Attendance</h2>
        <form method="post">
            <p>Program ID: <b><?= htmlspecialchars($program_id) ?></b></p>
            <p>Date: <b><?= htmlspecialchars($date) ?></b></p>
            <button type="submit">Submit Attendance</button>
        </form>
    </div>
</body>
</html>