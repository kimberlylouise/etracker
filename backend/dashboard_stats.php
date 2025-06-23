<?php

header('Content-Type: application/json');
require_once 'db.php';

// Total Students
$students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='student'")->fetch_assoc()['total'];

// Total Faculty
$faculty = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='faculty'")->fetch_assoc()['total'];

// Ongoing Programs
$programs = $conn->query("SELECT COUNT(*) as total FROM programs WHERE status='ongoing'")->fetch_assoc()['total'];

// Certificates Issued (participants + faculty)
$certificates = $conn->query("SELECT COUNT(*) as total FROM participants WHERE certificate_issued=1")->fetch_assoc()['total'];
$certificates += $conn->query("SELECT COUNT(*) as total FROM programs WHERE faculty_certificate_issued=1")->fetch_assoc()['total'];

// Attendance Rate (last 30 days, % Present)
$attendance = 0;
$res = $conn->query("SELECT 
    (SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate 
    FROM attendance 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$row = $res->fetch_assoc();
if ($row && $row['rate'] !== null) {
    $attendance = $row['rate'];
}

// Upcoming Sessions (next 3)
$sessions = [];
$res = $conn->query("SELECT ps.session_date as date, p.program_name 
    FROM program_sessions ps 
    JOIN programs p ON ps.program_id = p.id 
    WHERE ps.session_date >= CURDATE() 
    ORDER BY ps.session_date ASC LIMIT 3");
while ($row = $res->fetch_assoc()) $sessions[] = $row;

// Feedback Highlights (last 3 from detailed_evaluations)
$feedback = [];
$res = $conn->query("SELECT suggestion FROM detailed_evaluations WHERE suggestion IS NOT NULL AND suggestion != '' ORDER BY eval_date DESC LIMIT 3");
while ($row = $res->fetch_assoc()) $feedback[] = $row['suggestion'];

// Program Trends (top 5 by enrollment)
$trends = ['labels'=>[], 'data'=>[]];
$res = $conn->query("SELECT p.program_name, COUNT(e.user_id) as enrolled 
    FROM enrollments e 
    JOIN programs p ON e.program_id = p.id 
    WHERE e.status='approved'
    GROUP BY e.program_id 
    ORDER BY enrolled DESC LIMIT 5");
while ($row = $res->fetch_assoc()) {
    $trends['labels'][] = $row['program_name'];
    $trends['data'][] = (int)$row['enrolled'];
}

echo json_encode([
    'students' => (int)$students,
    'faculty' => (int)$faculty,
    'programs' => (int)$programs,
    'certificates' => (int)$certificates,
    'attendanceRate' => round($attendance),
    'upcomingSessions' => $sessions,
    'feedback' => $feedback,
    'programTrends' => $trends
]);