<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get faculty info
$faculty_sql = "SELECT id FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if (!$faculty_row = $faculty_result->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['error' => 'Faculty not found']);
    exit();
}
$faculty_id = $faculty_row['id'];
$faculty_stmt->close();

$method = $_SERVER['REQUEST_METHOD'];

// Handle table existence check
if (isset($_GET['check'])) {
    $table_check = $conn->query("SHOW TABLES LIKE 'projects'");
    echo json_encode(['exists' => ($table_check->num_rows > 0)]);
    exit();
}

switch ($method) {
    case 'POST':
        handleCreateProject();
        break;
    case 'PUT':
        handleUpdateProject();
        break;
    case 'DELETE':
        handleDeleteProject();
        break;
    case 'GET':
        handleGetProject();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleCreateProject() {
    global $conn, $faculty_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['program_id', 'project_title'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Verify program belongs to faculty
    $program_check_sql = "SELECT id FROM programs WHERE id = ? AND faculty_id = ?";
    $program_stmt = $conn->prepare($program_check_sql);
    $program_stmt->bind_param("ii", $input['program_id'], $faculty_id);
    $program_stmt->execute();
    if ($program_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Program not found or access denied']);
        return;
    }
    $program_stmt->close();
    
    // Get next project index for this program
    $index_sql = "SELECT COALESCE(MAX(project_index), 0) + 1 as next_index FROM projects WHERE program_id = ?";
    $index_stmt = $conn->prepare($index_sql);
    $index_stmt->bind_param("i", $input['program_id']);
    $index_stmt->execute();
    $index_result = $index_stmt->get_result();
    $next_index = $index_result->fetch_assoc()['next_index'];
    $index_stmt->close();
    
    // Insert project
    $insert_sql = "
        INSERT INTO projects (
            program_id, project_title, project_description, project_index, 
            priority, status, assigned_faculty_id
        ) VALUES (?, ?, ?, ?, ?, 'planning', ?)
    ";
    
    $stmt = $conn->prepare($insert_sql);
    $priority = $input['priority'] ?? 'medium';
    $description = $input['project_description'] ?? '';
    
    $stmt->bind_param("issisi", 
        $input['program_id'], 
        $input['project_title'], 
        $description,
        $next_index,
        $priority,
        $faculty_id
    );
    
    if ($stmt->execute()) {
        $project_id = $conn->insert_id;
        
        // Add default objectives if provided
        if (!empty($input['objectives'])) {
            $obj_sql = "INSERT INTO project_objectives (project_id, objective_title, priority) VALUES (?, ?, ?)";
            $obj_stmt = $conn->prepare($obj_sql);
            
            foreach ($input['objectives'] as $index => $objective) {
                $priority = $index + 1;
                $obj_stmt->bind_param("isi", $project_id, $objective, $priority);
                $obj_stmt->execute();
            }
            $obj_stmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'project_id' => $project_id,
            'message' => 'Project created successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create project']);
    }
    
    $stmt->close();
}

function handleUpdateProject() {
    global $conn, $faculty_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $project_id = $input['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
        return;
    }
    
    // Verify project belongs to faculty
    $check_sql = "
        SELECT pr.id FROM projects pr 
        JOIN programs p ON pr.program_id = p.id 
        WHERE pr.id = ? AND p.faculty_id = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $project_id, $faculty_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Project not found or access denied']);
        return;
    }
    $check_stmt->close();
    
    // Build update query dynamically
    $update_fields = [];
    $values = [];
    $types = '';
    
    $allowed_fields = [
        'project_title' => 's',
        'project_description' => 's',
        'status' => 's',
        'priority' => 's',
        'start_date' => 's',
        'end_date' => 's',
        'deadline' => 's',
        'budget_allocated' => 'd',
        'budget_spent' => 'd',
        'progress_percentage' => 'i'
    ];
    
    foreach ($allowed_fields as $field => $type) {
        if (isset($input[$field])) {
            $update_fields[] = "$field = ?";
            $values[] = $input[$field];
            $types .= $type;
        }
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }
    
    $values[] = $project_id;
    $types .= 'i';
    
    $update_sql = "UPDATE projects SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update project']);
    }
    
    $stmt->close();
}

function handleDeleteProject() {
    global $conn, $faculty_id;
    
    $project_id = $_GET['id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
        return;
    }
    
    // Verify project belongs to faculty
    $check_sql = "
        SELECT pr.id FROM projects pr 
        JOIN programs p ON pr.program_id = p.id 
        WHERE pr.id = ? AND p.faculty_id = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $project_id, $faculty_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Project not found or access denied']);
        return;
    }
    $check_stmt->close();
    
    // Delete project (cascading will handle objectives and tasks)
    $delete_sql = "DELETE FROM projects WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $project_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete project']);
    }
    
    $stmt->close();
}

function handleGetProject() {
    global $conn, $faculty_id;
    
    $project_id = $_GET['id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
        return;
    }
    
    // Get project details with objectives
    $project_sql = "
        SELECT 
            pr.*,
            p.program_name,
            p.location as program_location,
            COUNT(po.id) as objectives_count,
            COUNT(CASE WHEN po.status = 'completed' THEN 1 END) as completed_objectives
        FROM projects pr
        JOIN programs p ON pr.program_id = p.id
        LEFT JOIN project_objectives po ON pr.id = po.project_id
        WHERE pr.id = ? AND p.faculty_id = ?
        GROUP BY pr.id
    ";
    
    $stmt = $conn->prepare($project_sql);
    $stmt->bind_param("ii", $project_id, $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($project = $result->fetch_assoc()) {
        // Get objectives
        $obj_sql = "SELECT * FROM project_objectives WHERE project_id = ? ORDER BY priority ASC";
        $obj_stmt = $conn->prepare($obj_sql);
        $obj_stmt->bind_param("i", $project_id);
        $obj_stmt->execute();
        $obj_result = $obj_stmt->get_result();
        
        $objectives = [];
        while ($objective = $obj_result->fetch_assoc()) {
            $objectives[] = $objective;
        }
        $obj_stmt->close();
        
        $project['objectives'] = $objectives;
        
        echo json_encode(['success' => true, 'project' => $project]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
    }
    
    $stmt->close();
}
?>
