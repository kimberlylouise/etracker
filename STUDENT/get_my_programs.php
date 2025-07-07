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
                
                // Simple count of attendance records
                $attendance_sql = "SELECT COUNT(*) as total_records FROM attendance WHERE student_name = ? AND program_id = ?";
                $attendance_stmt = $conn->prepare($attendance_sql);
                if ($attendance_stmt) {
                    $attendance_stmt->bind_param('si', $student_name, $row['id']);
                    $attendance_stmt->execute();
                    $attendance_result = $attendance_stmt->get_result();
                    
                    if ($attendance_data = $attendance_result->fetch_assoc()) {
                        $total_records = (int)$attendance_data['total_records'];
                        
                        if ($total_records > 0) {
                            // Count present/late records
                            $present_sql = "SELECT COUNT(*) as present_count FROM attendance WHERE student_name = ? AND program_id = ? AND status IN ('Present', 'Late')";
                            $present_stmt = $conn->prepare($present_sql);
                            $present_stmt->bind_param('si', $student_name, $row['id']);
                            $present_stmt->execute();
                            $present_result = $present_stmt->get_result();
                            $present_data = $present_result->fetch_assoc();
                            
                            $row['sessions_attended'] = (int)$present_data['present_count'];
                            $row['total_sessions'] = $total_records;
                            $row['attendance_percentage'] = ($row['sessions_attended'] / $row['total_sessions']) * 100;
                            
                            $present_stmt->close();
                        } else {
                            $row['sessions_attended'] = 0;
                            $row['total_sessions'] = 0;
                            $row['attendance_percentage'] = 0;
                        }
                    } else {
                        $row['sessions_attended'] = 0;
                        $row['total_sessions'] = 0;
                        $row['attendance_percentage'] = 0;
                    }
                    $attendance_stmt->close();
                } else {
                    $row['sessions_attended'] = 0;
                    $row['total_sessions'] = 0;
                    $row['attendance_percentage'] = 0;
                }
            } else {
                $row['sessions_attended'] = 0;
                $row['total_sessions'] = 0;
                $row['attendance_percentage'] = 0;
            }
            $user_stmt->close();
        } else {
            $row['sessions_attended'] = 0;
            $row['total_sessions'] = 0;
            $row['attendance_percentage'] = 0;
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
    } else {
        // For non-approved enrollments, set default values
        $row['sessions_attended'] = 0;
        $row['total_sessions'] = 0;
        $row['attendance_percentage'] = 0;
        $row['upcoming_sessions'] = [];
    }
    
    $programs[] = $row;
}

echo json_encode(['status' => 'success', 'programs' => $programs]);
$stmt->close();
$conn->close();
?>