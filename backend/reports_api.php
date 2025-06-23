<?php

header('Content-Type: application/json');
require_once 'db.php';

$type = $_GET['type'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$data = [];

switch ($type) {
    case 'participation':
        // Program Participation
        $sql = "SELECT p.program_name, COUNT(pa.id) as participants
                FROM programs p
                LEFT JOIN participants pa ON pa.program_id = p.id
                WHERE p.start_date >= ? AND p.end_date <= ?
                GROUP BY p.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
        break;

    case 'attendance':
        // Attendance Summary
        $sql = "SELECT p.program_name, 
                       COUNT(a.id) as total_logs,
                       SUM(a.status='Present') as present,
                       SUM(a.status='Absent') as absent,
                       SUM(a.status='Late') as late
                FROM programs p
                LEFT JOIN attendance a ON a.program_id = p.id
                WHERE a.date >= ? AND a.date <= ?
                GROUP BY p.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
        break;

    case 'feedback':
        // Evaluation Feedback
        $sql = "SELECT p.program_name, AVG(content) as content, AVG(facilitators) as facilitators, AVG(relevance) as relevance, AVG(organization) as organization, AVG(experience) as experience
                FROM programs p
                LEFT JOIN detailed_evaluations d ON d.program_id = p.id
                WHERE d.eval_date >= ? AND d.eval_date <= ?
                GROUP BY p.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
        break;

    case 'completion':
        // Program Completion
        $sql = "SELECT program_name, end_date, status
                FROM programs
                WHERE end_date >= ? AND end_date <= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
        break;

    case 'faculty':
        // Faculty Performance (programs handled, avg feedback)
        $sql = "SELECT u.firstname, u.lastname, COUNT(p.id) as programs_handled, AVG(d.facilitators) as avg_facilitator_rating
                FROM users u
                JOIN programs p ON p.faculty_id = u.id
                LEFT JOIN detailed_evaluations d ON d.program_id = p.id
                WHERE u.role='faculty' AND p.start_date >= ? AND p.end_date <= ?
                GROUP BY u.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $data[] = $row;
        break;
}

echo json_encode($data);