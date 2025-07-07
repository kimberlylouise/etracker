<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard_stats':
            echo json_encode(getDashboardStats($conn));
            break;
        case 'program_participation':
        case 'participation':
            echo json_encode(getProgramParticipation($conn));
            break;
        case 'attendance_analysis':
        case 'attendance':
            echo json_encode(getAttendanceAnalysis($conn));
            break;
        case 'student_performance':
        case 'performance':
            echo json_encode(getStudentPerformance($conn));
            break;
        case 'evaluation_feedback':
        case 'evaluation':
            echo json_encode(getEvaluationFeedback($conn));
            break;
        case 'program_completion':
        case 'completion':
            echo json_encode(getProgramCompletion($conn));
            break;
        case 'faculty_performance':
        case 'faculty':
            echo json_encode(getFacultyPerformance($conn));
            break;
        case 'chart_data':
            $chart_type = $_GET['chart_type'] ?? 'all';
            echo json_encode(getChartData($conn, $chart_type));
            break;
        case 'get_programs':
            echo json_encode(getPrograms($conn));
            break;
        case 'get_departments':
            echo json_encode(getDepartments($conn));
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function getDashboardStats($conn) {
    // Get total active programs
    $programs_query = "SELECT COUNT(*) as total FROM programs WHERE status = 'ongoing'";
    $programs_result = mysqli_query($conn, $programs_query);
    $total_programs = $programs_result ? mysqli_fetch_assoc($programs_result)['total'] : 0;

    // Get total participants
    $participants_query = "SELECT COUNT(DISTINCT student_name) as total FROM participants WHERE status = 'accepted'";
    $participants_result = mysqli_query($conn, $participants_query);
    $total_participants = $participants_result ? mysqli_fetch_assoc($participants_result)['total'] : 0;

    // Get average attendance rate
    $attendance_query = "SELECT 
        ROUND(AVG(CASE WHEN status = 'Present' THEN 100 ELSE 0 END), 1) as avg_rate 
        FROM attendance";
    $attendance_result = mysqli_query($conn, $attendance_query);
    $avg_attendance = $attendance_result ? (mysqli_fetch_assoc($attendance_result)['avg_rate'] ?? 0) : 0;

    // Get completion rate
    $completion_query = "SELECT 
        ROUND((COUNT(CASE WHEN certificate_issued = 1 THEN 1 END) * 100.0 / COUNT(*)), 1) as completion_rate 
        FROM participants WHERE status = 'accepted'";
    $completion_result = mysqli_query($conn, $completion_query);
    $completion_rate = $completion_result ? (mysqli_fetch_assoc($completion_result)['completion_rate'] ?? 0) : 0;

    return [
        'success' => true,
        'data' => [
            'total_programs' => $total_programs,
            'total_participants' => $total_participants,
            'avg_attendance' => $avg_attendance,
            'completion_rate' => $completion_rate
        ]
    ];
}

function getProgramParticipation($conn) {
    $query = "SELECT 
        p.program_name,
        p.department,
        p.start_date,
        p.end_date,
        p.max_students,
        COUNT(pt.id) as enrolled_count,
        COUNT(CASE WHEN pt.status = 'accepted' THEN 1 END) as accepted_count,
        COUNT(CASE WHEN pt.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN pt.status = 'rejected' THEN 1 END) as rejected_count,
        ROUND((COUNT(CASE WHEN pt.status = 'accepted' THEN 1 END) * 100.0 / p.max_students), 1) as enrollment_rate
        FROM programs p
        LEFT JOIN participants pt ON p.id = pt.program_id
        GROUP BY p.id, p.program_name, p.department, p.start_date, p.end_date, p.max_students
        ORDER BY p.start_date DESC";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_programs' => count($data),
            'total_enrolled' => array_sum(array_column($data, 'enrolled_count')),
            'total_accepted' => array_sum(array_column($data, 'accepted_count'))
        ]
    ];
}

function getAttendanceAnalysis($conn) {
    $query = "SELECT 
        a.student_name,
        p.program_name,
        p.department,
        COUNT(a.id) as total_sessions,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_count,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
        ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 1) as attendance_rate
        FROM attendance a
        JOIN programs p ON a.program_id = p.id
        GROUP BY a.student_name, p.id, p.program_name, p.department
        ORDER BY attendance_rate DESC, a.student_name";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    // Calculate summary statistics
    $total_students = count($data);
    $avg_attendance = $total_students > 0 ? round(array_sum(array_column($data, 'attendance_rate')) / $total_students, 1) : 0;
    $perfect_attendance = count(array_filter($data, function($row) { return $row['attendance_rate'] == 100; }));
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_students' => $total_students,
            'avg_attendance' => $avg_attendance,
            'perfect_attendance' => $perfect_attendance,
            'total_sessions' => $total_students > 0 ? array_sum(array_column($data, 'total_sessions')) : 0
        ]
    ];
}

function getStudentPerformance($conn) {
    $query = "SELECT 
        pt.student_name,
        pt.student_email,
        p.program_name,
        p.department,
        pt.enrollment_date,
        pt.status,
        pt.certificate_issued,
        pt.issued_on,
        COALESCE(de.content, 0) as content_score,
        COALESCE(de.facilitators, 0) as facilitators_score,
        COALESCE(de.relevance, 0) as relevance_score,
        COALESCE(de.organization, 0) as organization_score,
        COALESCE(de.experience, 0) as experience_score,
        COALESCE(ROUND((de.content + de.facilitators + de.relevance + de.organization + de.experience) / 5.0, 1), 0) as overall_score
        FROM participants pt
        JOIN programs p ON pt.program_id = p.id
        LEFT JOIN detailed_evaluations de ON pt.program_id = de.program_id AND pt.student_name = de.student_name
        WHERE pt.status = 'accepted'
        ORDER BY pt.enrollment_date DESC";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_students' => count($data),
            'certified_students' => count(array_filter($data, function($row) { return $row['certificate_issued'] == 1; })),
            'avg_overall_score' => count($data) > 0 ? round(array_sum(array_column($data, 'overall_score')) / count($data), 1) : 0
        ]
    ];
}

function getEvaluationFeedback($conn) {
    $query = "SELECT 
        de.student_name,
        p.program_name,
        p.department,
        de.content,
        de.facilitators,
        de.relevance,
        de.organization,
        de.experience,
        de.suggestion,
        de.recommend,
        de.eval_date,
        ROUND((de.content + de.facilitators + de.relevance + de.organization + de.experience) / 5.0, 1) as overall_rating
        FROM detailed_evaluations de
        JOIN programs p ON de.program_id = p.id
        ORDER BY de.eval_date DESC";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    // Calculate summary statistics
    $total_evaluations = count($data);
    $avg_content = $total_evaluations > 0 ? round(array_sum(array_column($data, 'content')) / $total_evaluations, 1) : 0;
    $avg_facilitators = $total_evaluations > 0 ? round(array_sum(array_column($data, 'facilitators')) / $total_evaluations, 1) : 0;
    $avg_relevance = $total_evaluations > 0 ? round(array_sum(array_column($data, 'relevance')) / $total_evaluations, 1) : 0;
    $avg_organization = $total_evaluations > 0 ? round(array_sum(array_column($data, 'organization')) / $total_evaluations, 1) : 0;
    $avg_experience = $total_evaluations > 0 ? round(array_sum(array_column($data, 'experience')) / $total_evaluations, 1) : 0;
    $avg_overall = $total_evaluations > 0 ? round(array_sum(array_column($data, 'overall_rating')) / $total_evaluations, 1) : 0;
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_evaluations' => $total_evaluations,
            'avg_content' => $avg_content,
            'avg_facilitators' => $avg_facilitators,
            'avg_relevance' => $avg_relevance,
            'avg_organization' => $avg_organization,
            'avg_experience' => $avg_experience,
            'avg_overall' => $avg_overall
        ]
    ];
}

function getProgramCompletion($conn) {
    $query = "SELECT 
        p.program_name,
        p.department,
        p.start_date,
        p.end_date,
        p.status as program_status,
        COUNT(pt.id) as total_enrolled,
        COUNT(CASE WHEN pt.certificate_issued = 1 THEN 1 END) as completed_count,
        COUNT(CASE WHEN pt.certificate_issued = 0 THEN 1 END) as incomplete_count,
        ROUND((COUNT(CASE WHEN pt.certificate_issued = 1 THEN 1 END) * 100.0 / COUNT(pt.id)), 1) as completion_rate
        FROM programs p
        LEFT JOIN participants pt ON p.id = pt.program_id AND pt.status = 'accepted'
        GROUP BY p.id, p.program_name, p.department, p.start_date, p.end_date, p.status
        ORDER BY p.start_date DESC";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_programs' => count($data),
            'total_enrolled' => array_sum(array_column($data, 'total_enrolled')),
            'total_completed' => array_sum(array_column($data, 'completed_count')),
            'overall_completion_rate' => count($data) > 0 ? round(array_sum(array_column($data, 'completion_rate')) / count($data), 1) : 0
        ]
    ];
}

function getFacultyPerformance($conn) {
    $query = "SELECT 
        f.faculty_name,
        f.department,
        f.position,
        COUNT(DISTINCT p.id) as programs_managed,
        COUNT(DISTINCT pt.id) as total_participants,
        COALESCE(AVG(de.content), 0) as avg_content_rating,
        COALESCE(AVG(de.facilitators), 0) as avg_facilitator_rating,
        COALESCE(AVG((de.content + de.facilitators + de.relevance + de.organization + de.experience) / 5.0), 0) as overall_rating
        FROM faculty f
        LEFT JOIN programs p ON f.id = p.faculty_id
        LEFT JOIN participants pt ON p.id = pt.program_id AND pt.status = 'accepted'
        LEFT JOIN detailed_evaluations de ON p.id = de.program_id
        GROUP BY f.id, f.faculty_name, f.department, f.position
        ORDER BY programs_managed DESC, overall_rating DESC";
    
    $result = mysqli_query($conn, $query);
    $data = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $data,
        'summary' => [
            'total_faculty' => count($data),
            'total_programs' => array_sum(array_column($data, 'programs_managed')),
            'avg_rating' => count($data) > 0 ? round(array_sum(array_column($data, 'overall_rating')) / count($data), 1) : 0
        ]
    ];
}

function getChartData($conn, $chart_type = 'all') {
    $charts = [];
    
    if ($chart_type === 'all' || $chart_type === 'program_popularity') {
        // Program popularity
        $program_popularity = "SELECT p.program_name, COUNT(pt.id) as participants 
                              FROM programs p 
                              LEFT JOIN participants pt ON p.id = pt.program_id AND pt.status = 'accepted'
                              GROUP BY p.id, p.program_name 
                              ORDER BY participants DESC 
                              LIMIT 6";
        
        $result = mysqli_query($conn, $program_popularity);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'program_name' => $row['program_name'],
                    'participants' => (int)$row['participants']
                ];
            }
        }
        $charts['program_popularity'] = ['success' => true, 'data' => $data];
    }
    
    if ($chart_type === 'all' || $chart_type === 'attendance_trends') {
        // Attendance trends
        $attendance_trends = "SELECT 
                             WEEK(date) as week_num,
                             CONCAT('Week ', WEEK(date)) as week_label,
                             ROUND(AVG(CASE WHEN status = 'Present' THEN 100 ELSE 0 END), 1) as attendance_rate 
                             FROM attendance 
                             WHERE date >= DATE_SUB(NOW(), INTERVAL 8 WEEK) 
                             GROUP BY WEEK(date) 
                             ORDER BY week_num";
        
        $result = mysqli_query($conn, $attendance_trends);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'week_num' => $row['week_num'],
                    'week_label' => $row['week_label'],
                    'attendance_rate' => (float)$row['attendance_rate']
                ];
            }
        }
        $charts['attendance_trends'] = ['success' => true, 'data' => $data];
    }
    
    if ($chart_type === 'all' || $chart_type === 'feedback_ratings') {
        // Feedback ratings
        $feedback_ratings = "SELECT 
                            'Excellent' as rating_category,
                            COUNT(*) as count
                            FROM detailed_evaluations 
                            WHERE (content + facilitators + relevance + organization + experience) / 5.0 >= 4.5
                            UNION ALL
                            SELECT 
                            'Good' as rating_category,
                            COUNT(*) as count
                            FROM detailed_evaluations 
                            WHERE (content + facilitators + relevance + organization + experience) / 5.0 >= 3.5 
                            AND (content + facilitators + relevance + organization + experience) / 5.0 < 4.5
                            UNION ALL
                            SELECT 
                            'Average' as rating_category,
                            COUNT(*) as count
                            FROM detailed_evaluations 
                            WHERE (content + facilitators + relevance + organization + experience) / 5.0 >= 2.5 
                            AND (content + facilitators + relevance + organization + experience) / 5.0 < 3.5
                            UNION ALL
                            SELECT 
                            'Poor' as rating_category,
                            COUNT(*) as count
                            FROM detailed_evaluations 
                            WHERE (content + facilitators + relevance + organization + experience) / 5.0 < 2.5";
        
        $result = mysqli_query($conn, $feedback_ratings);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'rating_category' => $row['rating_category'],
                    'count' => (int)$row['count']
                ];
            }
        }
        $charts['feedback_ratings'] = ['success' => true, 'data' => $data];
    }
    
    if ($chart_type === 'all' || $chart_type === 'department_participation') {
        // Department participation
        $dept_participation = "SELECT p.department, COUNT(DISTINCT pt.student_name) as participants 
                              FROM programs p 
                              LEFT JOIN participants pt ON p.id = pt.program_id 
                              WHERE pt.status = 'accepted' AND p.department IS NOT NULL
                              GROUP BY p.department 
                              ORDER BY participants DESC";
        
        $result = mysqli_query($conn, $dept_participation);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'department' => $row['department'],
                    'participants' => (int)$row['participants']
                ];
            }
        }
        $charts['department_participation'] = ['success' => true, 'data' => $data];
    }
    
    if ($chart_type === 'all' || $chart_type === 'monthly_performance') {
        // Monthly performance
        $monthly_performance = "SELECT 
                               MONTHNAME(p.start_date) as month_name,
                               COUNT(DISTINCT p.id) as programs_count,
                               COUNT(DISTINCT pt.id) as participants_count,
                               ROUND(AVG(CASE WHEN pt.certificate_issued = 1 THEN 100 ELSE 0 END), 1) as completion_rate
                               FROM programs p
                               LEFT JOIN participants pt ON p.id = pt.program_id AND pt.status = 'accepted'
                               WHERE p.start_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                               GROUP BY MONTH(p.start_date), MONTHNAME(p.start_date)
                               ORDER BY MONTH(p.start_date)";
        
        $result = mysqli_query($conn, $monthly_performance);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'month_name' => $row['month_name'],
                    'programs_count' => (int)$row['programs_count'],
                    'participants_count' => (int)$row['participants_count'],
                    'completion_rate' => (float)$row['completion_rate']
                ];
            }
        }
        $charts['monthly_performance'] = ['success' => true, 'data' => $data];
    }
    
    // Return specific chart data or all charts
    if ($chart_type !== 'all' && isset($charts[$chart_type])) {
        return $charts[$chart_type];
    }
    
    return $charts;
}

function getPrograms($conn) {
    $query = "SELECT id, program_name FROM programs ORDER BY program_name";
    $result = mysqli_query($conn, $query);
    $programs = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $programs[] = $row;
        }
    }
    
    return [
        'success' => true,
        'data' => $programs
    ];
}

function getDepartments($conn) {
    $query = "SELECT DISTINCT department FROM programs WHERE department IS NOT NULL ORDER BY department";
    $result = mysqli_query($conn, $query);
    $departments = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $departments[] = $row['department'];
        }
    }
    
    return [
        'success' => true,
        'data' => $departments
    ];
}
?>
