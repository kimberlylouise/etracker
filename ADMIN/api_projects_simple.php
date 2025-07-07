<?php
require_once 'db.php';

// Set content type for JSON response
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_projects_for_evaluation') {
        // Simple test - just get programs data
        $sql = "SELECT 
            p.id as program_id,
            p.program_name,
            p.project_titles,
            p.department,
            p.status as program_status
        FROM programs p
        WHERE p.project_titles IS NOT NULL 
        LIMIT 5";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $projects = [];
        
        while ($row = $result->fetch_assoc()) {
            $project_titles = json_decode($row['project_titles'], true);
            if ($project_titles && is_array($project_titles)) {
                foreach ($project_titles as $index => $title) {
                    if (!empty(trim($title))) {
                        $projects[] = [
                            'project_id' => (int)($row['program_id'] * 1000 + $index + 1),
                            'project_title' => trim($title),
                            'project_description' => "Project under " . $row['program_name'],
                            'project_status' => $row['program_status'] === 'ended' ? 'completed' : 'in_progress',
                            'priority' => 'medium',
                            'progress_percentage' => $row['program_status'] === 'ended' ? 100 : 75,
                            'program_id' => (int)$row['program_id'],
                            'program_name' => $row['program_name'],
                            'department' => $row['department'],
                            'faculty_name' => 'Faculty Name',
                            'faculty_department' => $row['department'],
                            'participants_count' => 15,
                            'total_objectives' => 3,
                            'completed_objectives' => $row['program_status'] === 'ended' ? 3 : 2,
                            'objectives_completion_rate' => $row['program_status'] === 'ended' ? 100 : 67,
                            'evaluation_id' => null,
                            'evaluation_status' => null,
                            'overall_rating' => null,
                            'evaluation_date' => null,
                            'needs_evaluation' => $row['program_status'] === 'ended',
                            'evaluation_overdue' => false,
                            'project_end_date' => '2024-12-31'
                        ];
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $projects,
            'count' => count($projects),
            'using_new_table' => false,
            'debug' => 'Simple API test working'
        ]);
        
    } else {
        throw new Exception('Invalid action specified: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'action' => $_GET['action'] ?? 'none',
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
}
?>
