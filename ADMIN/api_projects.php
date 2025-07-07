<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling to ensure JSON output
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Include database connection
    include_once 'db.php';
    
    // Get the action from the request
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_projects_for_evaluation':
            echo json_encode(getProjectsForEvaluation());
            break;
        case 'submit_evaluation':
            echo json_encode(submitProjectEvaluation());
            break;
        case 'get_evaluation':
            echo json_encode(getEvaluation());
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

function getProjectsForEvaluation() {
    global $conn;
    
    $items = [];
    
    try {
        // Get all programs with their basic info
        $programQuery = "
            SELECT 
                p.id as program_id,
                p.program_name,
                p.department,
                p.start_date,
                p.end_date,
                p.status,
                p.project_titles,
                p.description,
                p.faculty_id,
                p.location,
                f.faculty_name,
                f.department as faculty_department,
                u.firstname,
                u.lastname
            FROM programs p
            LEFT JOIN faculty f ON p.faculty_id = f.id
            LEFT JOIN users u ON f.user_id = u.id
            ORDER BY p.id DESC
        ";
        
        $programResult = mysqli_query($conn, $programQuery);
        
        if (!$programResult) {
            throw new Exception("Error fetching programs: " . mysqli_error($conn));
        }
        
        while ($program = mysqli_fetch_assoc($programResult)) {
            $facultyName = $program['faculty_name'];
            if (empty($facultyName) && !empty($program['firstname'])) {
                $facultyName = trim($program['firstname'] . ' ' . $program['lastname']);
            }
            
            // Add the program itself as an evaluable item
            $items[] = [
                'project_id' => 'prog_' . $program['program_id'],
                'item_type' => 'program',
                'project_title' => '📋 PROGRAM: ' . $program['program_name'],
                'project_description' => $program['description'] ?: 'Extension Program',
                'project_status' => $program['status'] === 'ended' ? 'completed' : 'in_progress',
                'priority' => 'high',
                'progress_percentage' => $program['status'] === 'ended' ? 100 : 85,
                'budget_allocated' => 0,
                'budget_spent' => 0,
                'project_start_date' => $program['start_date'],
                'project_end_date' => $program['end_date'],
                'program_id' => (int)$program['program_id'],
                'program_name' => $program['program_name'],
                'department' => $program['department'],
                'location' => $program['location'],
                'faculty_name' => $facultyName ?: 'Unknown Faculty',
                'faculty_department' => $program['faculty_department'] ?: $program['department'],
                'participants_count' => 0, // You might want to get this from another table
                'total_objectives' => 5,
                'completed_objectives' => $program['status'] === 'ended' ? 5 : 3,
                'objectives_completion_rate' => $program['status'] === 'ended' ? 100 : 60,
                'evaluation_id' => null,
                'evaluation_status' => null,
                'overall_rating' => null,
                'evaluation_date' => null,
                'needs_evaluation' => $program['status'] === 'ended',
                'evaluation_overdue' => false
            ];
            
            // Add individual projects from this program's project_titles
            if (!empty($program['project_titles'])) {
                $projectTitles = json_decode($program['project_titles'], true);
                if (is_array($projectTitles)) {
                    foreach ($projectTitles as $index => $projectTitle) {
                        if (!empty(trim($projectTitle))) {
                            $projectIndex = (int)$index + 1;
                            $items[] = [
                                'project_id' => 'projt_' . $program['program_id'] . '_' . $projectIndex,
                                'item_type' => 'program_project',
                                'project_title' => '🎯 PROJECT: ' . trim($projectTitle),
                                'project_description' => "Project activity under " . $program['program_name'],
                                'project_status' => $program['status'] === 'ended' ? 'completed' : 'in_progress',
                                'priority' => 'medium',
                                'progress_percentage' => $program['status'] === 'ended' ? 100 : 75,
                                'budget_allocated' => 0,
                                'budget_spent' => 0,
                                'project_start_date' => $program['start_date'],
                                'project_end_date' => $program['end_date'],
                                'program_id' => (int)$program['program_id'],
                                'program_name' => $program['program_name'],
                                'department' => $program['department'],
                                'location' => $program['location'],
                                'faculty_name' => $facultyName ?: 'Unknown Faculty',
                                'faculty_department' => $program['faculty_department'] ?: $program['department'],
                                'participants_count' => 0,
                                'total_objectives' => 3,
                                'completed_objectives' => $program['status'] === 'ended' ? 3 : 2,
                                'objectives_completion_rate' => $program['status'] === 'ended' ? 100 : 67,
                                'evaluation_id' => null,
                                'evaluation_status' => null,
                                'overall_rating' => null,
                                'evaluation_date' => null,
                                'needs_evaluation' => $program['status'] === 'ended',
                                'evaluation_overdue' => false
                            ];
                        }
                    }
                }
            }
        }
        
        // Also check if there are standalone projects in the projects table
        $standaloneQuery = "
            SELECT 
                pr.id as project_id,
                pr.project_title,
                pr.project_description,
                pr.status,
                pr.start_date,
                pr.end_date,
                pr.program_id,
                p.department,
                p.location,
                p.program_name,
                f.faculty_name,
                f.department as faculty_department,
                u.firstname,
                u.lastname
            FROM projects pr
            LEFT JOIN programs p ON pr.program_id = p.id
            LEFT JOIN faculty f ON p.faculty_id = f.id
            LEFT JOIN users u ON f.user_id = u.id
            ORDER BY pr.id DESC
        ";
        
        $standaloneResult = mysqli_query($conn, $standaloneQuery);
        
        if ($standaloneResult) {
            while ($project = mysqli_fetch_assoc($standaloneResult)) {
                $facultyName = $project['faculty_name'];
                if (empty($facultyName) && !empty($project['firstname'])) {
                    $facultyName = trim($project['firstname'] . ' ' . $project['lastname']);
                }
                
                $items[] = [
                    'project_id' => 'sproj_' . $project['project_id'],
                    'item_type' => 'standalone_project',
                    'project_title' => '⚡ STANDALONE: ' . $project['project_title'],
                    'project_description' => $project['project_description'] ?: 'Standalone Project',
                    'project_status' => $project['status'] === 'completed' ? 'completed' : 'in_progress',
                    'priority' => 'medium',
                    'progress_percentage' => $project['status'] === 'completed' ? 100 : 80,
                    'budget_allocated' => 0,
                    'budget_spent' => 0,
                    'project_start_date' => $project['start_date'],
                    'project_end_date' => $project['end_date'],
                    'program_id' => $project['program_id'],
                    'program_name' => $project['program_name'] ?: 'Independent Project',
                    'department' => $project['department'] ?: 'Unknown Department',
                    'location' => $project['location'],
                    'faculty_name' => $facultyName ?: 'Unknown Faculty',
                    'faculty_department' => $project['faculty_department'] ?: $project['department'],
                    'participants_count' => 0,
                    'total_objectives' => 4,
                    'completed_objectives' => $project['status'] === 'completed' ? 4 : 2,
                    'objectives_completion_rate' => $project['status'] === 'completed' ? 100 : 50,
                    'evaluation_id' => null,
                    'evaluation_status' => null,
                    'overall_rating' => null,
                    'evaluation_date' => null,
                    'needs_evaluation' => $project['status'] === 'completed',
                    'evaluation_overdue' => false
                ];
            }
        }
        
        return [
            'success' => true,
            'data' => $items,
            'total_count' => count($items),
            'debug' => [
                'programs_found' => mysqli_num_rows($programResult),
                'standalone_projects_found' => $standaloneResult ? mysqli_num_rows($standaloneResult) : 0,
                'total_items' => count($items)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error fetching projects: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ];
    }
}

function submitProjectEvaluation() {
    global $conn;
    
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $projectId = $input['project_id'] ?? '';
        $impactRating = (int)($input['impact_rating'] ?? 0);
        $qualityRating = (int)($input['quality_rating'] ?? 0);
        $sustainabilityRating = (int)($input['sustainability_rating'] ?? 0);
        $innovationRating = (int)($input['innovation_rating'] ?? 0);
        $collaborationRating = (int)($input['collaboration_rating'] ?? 0);
        $budgetEfficiency = (int)($input['budget_efficiency'] ?? 0);
        $timelinessRating = (int)($input['timeliness_rating'] ?? 0);
        $evaluationComments = $input['evaluation_comments'] ?? '';
        $recommendations = $input['recommendations'] ?? '';
        $evaluationStatus = $input['evaluation_status'] ?? 'pending_review';
        
        if (empty($projectId)) {
            throw new Exception('Project ID is required');
        }
        
        if (!$impactRating || !$qualityRating || !$sustainabilityRating) {
            throw new Exception('Required ratings (Impact, Quality, Sustainability) are missing');
        }
        
        // Calculate overall rating from required criteria
        $requiredRatings = [$impactRating, $qualityRating, $sustainabilityRating];
        $optionalRatings = array_filter([$innovationRating, $collaborationRating, $budgetEfficiency, $timelinessRating]);
        
        // Combine all non-zero ratings
        $allRatings = array_merge($requiredRatings, $optionalRatings);
        $overallRating = round(array_sum($allRatings) / count($allRatings), 2);
        
        // Create evaluation record
        $evaluationData = [
            'project_id' => $projectId,
            'impact_rating' => $impactRating,
            'quality_rating' => $qualityRating,
            'sustainability_rating' => $sustainabilityRating,
            'innovation_rating' => $innovationRating,
            'collaboration_rating' => $collaborationRating,
            'budget_efficiency' => $budgetEfficiency,
            'timeliness_rating' => $timelinessRating,
            'overall_rating' => $overallRating,
            'evaluation_comments' => $evaluationComments,
            'recommendations' => $recommendations,
            'evaluation_status' => $evaluationStatus,
            'evaluation_date' => date('Y-m-d H:i:s'),
            'evaluator_id' => $_SESSION['user_id'] ?? 1
        ];
        
        // For now, store in a simple log file
        // In production, you'd want to store in a proper database table
        $logFile = 'project_evaluations.log';
        $logEntry = date('Y-m-d H:i:s') . " - " . json_encode($evaluationData) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return [
            'success' => true,
            'message' => 'Evaluation submitted successfully',
            'data' => [
                'project_id' => $projectId,
                'overall_rating' => $overallRating,
                'evaluation_status' => $evaluationStatus
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error submitting evaluation: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ];
    }
}

function getEvaluation() {
    $projectId = $_GET['project_id'] ?? '';
    
    if (empty($projectId)) {
        return [
            'success' => false,
            'message' => 'Project ID is required'
        ];
    }
    
    // For now, return a simple response
    // In production, you'd fetch from the evaluation database
    return [
        'success' => true,
        'data' => [
            'project_id' => $projectId,
            'overall_rating' => null,
            'evaluation_comments' => '',
            'evaluation_status' => 'pending_review',
            'evaluation_date' => null
        ]
    ];
}
?>
