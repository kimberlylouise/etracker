<?php

header('Content-Type: application/json');
require_once 'db.php';

// Add attendance entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_attendance') {
    $student_name = $_POST['student_name'];
    $program_id = intval($_POST['program_id']);
    $status = $_POST['status'];
    $time_in = $_POST['time_in'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO attendance (student_name, program_id, status, time_in, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $student_name, $program_id, $status, $time_in, $date);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all active programs for dropdown
if (isset($_GET['action']) && $_GET['action'] === 'get_programs') {
    $now = date('Y-m-d');
    $result = $conn->prepare("SELECT id, program_name FROM programs WHERE start_date <= ? AND end_date >= ?");
    $result->bind_param("ss", $now, $now);
    $result->execute();
    $res = $result->get_result();
    $programs = [];
    while ($row = $res->fetch_assoc()) {
        $programs[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $programs]);
    exit;
}

// Fetch attendance logs
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $result = $conn->query("SELECT a.*, p.program_name FROM attendance a LEFT JOIN programs p ON a.program_id = p.id ORDER BY a.date DESC, a.time_in DESC");
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $logs]);
    exit;
}

// Fetch program attendance summary
if (isset($_GET['action']) && $_GET['action'] === 'get_program_summary') {
    $result = $conn->query("
        SELECT 
            a.program_id,
            p.program_name,
            a.date,
            COUNT(a.id) as registered,
            SUM(a.status = 'Present') as present,
            SUM(a.status = 'Absent') as absent
        FROM attendance a
        LEFT JOIN programs p ON a.program_id = p.id
        GROUP BY a.program_id, a.date
        ORDER BY a.date DESC
    ");
    $summary = [];
    while ($row = $result->fetch_assoc()) {
        $row['attendance_percent'] = $row['registered'] > 0 ? round(($row['present'] / $row['registered']) * 100) : 0;
        $summary[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $summary]);
    exit;
}
?>