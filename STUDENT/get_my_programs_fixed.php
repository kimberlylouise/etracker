<?php
session_start();
header('Content-Type: application/json');
require_once '../FACULTY/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// First, get all enrollments with basic program information
$sql = "SELECT 
            p.id, 
            p.program_name,
            p.description,
            p.start_date,
            p.end_date,
            p.location,
            p.max_students,
            e.status as enrollment_status,
            e.enrollment_date,
            p.status AS program_status,
            u.firstname AS faculty_firstname,
            u.lastname AS faculty_lastname,
            CONCAT(u.firstname, ' ', u.lastname) AS faculty_name
        FROM enrollments e
        JOIN programs p ON e.program_id = p.id
        LEFT JOIN users u ON p.faculty_id = u.id
        WHERE e.user_id = ?
        ORDER BY 
            FIELD(e.status, 'approved', 'pending', 'rejected'),
            FIELD(p.status, 'ongoing', 'ended'),
            e.enrollment_date DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database preparation error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$programs = [];
while ($row = $result->fetch_assoc()) {
    // Initialize default values
    $row['sessions_attended'] = 0;
    $row['total_sessions'] = 0;
    $row['attendance_percentage'] = 0;
    $row['upcoming_sessions'] = [];
    
    // Calculate attendance statistics for approved enrollments
    if ($row['enrollment_status'] === 'approved') {
        // Get user's full name for attendance lookup
        $user_sql = "SELECT firstname, lastname FROM users WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        if ($user_stmt) {
            $user_stmt->bind_param('i', $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            if ($user_data = $user_result->fetch_assoc()) {
                $student_name = $user_data['firstname'] . ' ' . $user_data['lastname'];
                
                // Count attendance records for this student and program
                $attendance_sql = "SELECT 
                                    COUNT(CASE WHEN status IN ('Present', 'Late') THEN 1 END) as sessions_attended,
                                    COUNT(*) as total_attendance_records
                                  FROM attendance 
                                  WHERE student_name = ? AND program_id = ?";
                
                $attendance_stmt = $conn->prepare($attendance_sql);
                if ($attendance_stmt) {
                    $attendance_stmt->bind_param('si', $student_name, $row['id']);
                    $attendance_stmt->execute();
                    $attendance_result = $attendance_stmt->get_result();
                    
                    if ($attendance_data = $attendance_result->fetch_assoc()) {
                        $row['sessions_attended'] = (int)$attendance_data['sessions_attended'];
                        $row['total_sessions'] = (int)$attendance_data['total_attendance_records'];
                        
                        // Calculate attendance percentage
                        if ($row['total_sessions'] > 0) {
                            $row['attendance_percentage'] = ($row['sessions_attended'] / $row['total_sessions']) * 100;
                        }
                    }
                    $attendance_stmt->close();
                }
            }
            $user_stmt->close();
        }
        
        // Get upcoming sessions for active programs
        if ($row['program_status'] === 'ongoing') {
            $session_sql = "SELECT session_title, session_date, session_start, session_end, location 
                           FROM sessions 
                           WHERE program_id = ? AND session_date >= CURDATE() 
                           ORDER BY session_date, session_start LIMIT 3";
            $session_stmt = $conn->prepare($session_sql);
            if ($session_stmt) {
                $session_stmt->bind_param('i', $row['id']);
                $session_stmt->execute();
                $session_result = $session_stmt->get_result();
                
                $upcoming_sessions = [];
                while ($session = $session_result->fetch_assoc()) {
                    $upcoming_sessions[] = $session;
                }
                $row['upcoming_sessions'] = $upcoming_sessions;
                $session_stmt->close();
            }
        }
    }
    
    $programs[] = $row;
}

echo json_encode(['status' => 'success', 'programs' => $programs]);
$stmt->close();
$conn->close();
?>
