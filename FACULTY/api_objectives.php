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

// Check if tables exist
$table_check = $conn->query("SHOW TABLES LIKE 'project_objectives'");
if ($table_check->num_rows === 0) {
    http_response_code(503);
    echo json_encode(['error' => 'Project objectives table not found. Please run database migration first.']);
    exit();
}

switch ($method) {
    case 'POST':
        handleCreateObjective();
        break;
    case 'PUT':
        handleUpdateObjective();
        break;
    case 'DELETE':
        handleDeleteObjective();
        break;
    case 'GET':
        handleGetObjectives();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function handleCreateObjective() {
    global $conn, $faculty_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['project_id']) || empty($input['objective_title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and objective title are required']);
        return;
    }
    
    // Verify project belongs to faculty
    $project_check_sql = "
        SELECT pr.id FROM projects pr 
        JOIN programs p ON pr.program_id = p.id 
        WHERE pr.id = ? AND p.faculty_id = ?
    ";
    $project_stmt = $conn->prepare($project_check_sql);
    $project_stmt->bind_param("ii", $input['project_id'], $faculty_id);
    $project_stmt->execute();
    if ($project_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Project not found or access denied']);
        return;
    }
    $project_stmt->close();
    
    // Get next priority
    $priority_sql = "SELECT COALESCE(MAX(priority), 0) + 1 as next_priority FROM project_objectives WHERE project_id = ?";
    $priority_stmt = $conn->prepare($priority_sql);
    $priority_stmt->bind_param("i", $input['project_id']);
    $priority_stmt->execute();
    $priority_result = $priority_stmt->get_result();
    $next_priority = $priority_result->fetch_assoc()['next_priority'];
    $priority_stmt->close();
    
    // Insert objective
    $insert_sql = "
        INSERT INTO project_objectives (
            project_id, objective_title, objective_description, priority, status
        ) VALUES (?, ?, ?, ?, 'not_started')
    ";
    
    $stmt = $conn->prepare($insert_sql);
    $description = $input['objective_description'] ?? '';
    
    $stmt->bind_param("issi", 
        $input['project_id'], 
        $input['objective_title'], 
        $description,
        $next_priority
    );
    
    if ($stmt->execute()) {
        $objective_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'objective_id' => $objective_id,
            'message' => 'Objective created successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create objective']);
    }
    
    $stmt->close();
}

function handleUpdateObjective() {
    global $conn, $faculty_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $objective_id = $input['objective_id'] ?? null;
    
    if (!$objective_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Objective ID required']);
        return;
    }
    
    // Verify objective belongs to faculty's project
    $check_sql = "
        SELECT po.id FROM project_objectives po
        JOIN projects pr ON po.project_id = pr.id
        JOIN programs p ON pr.program_id = p.id 
        WHERE po.id = ? AND p.faculty_id = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $objective_id, $faculty_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Objective not found or access denied']);
        return;
    }
    $check_stmt->close();
    
    // Build update query dynamically
    $update_fields = [];
    $values = [];
    $types = '';
    
    $allowed_fields = [
        'objective_title' => 's',
        'objective_description' => 's',
        'status' => 's',
        'target_value' => 'd',
        'current_value' => 'd',
        'unit' => 's',
        'due_date' => 's',
        'completion_date' => 's'
    ];
    
    foreach ($allowed_fields as $field => $type) {
        if (isset($input[$field])) {
            $update_fields[] = "$field = ?";
            $values[] = $input[$field];
            $types .= $type;
        }
    }
    
    // Update completion date if status is completed
    if (isset($input['status']) && $input['status'] === 'completed') {
        $update_fields[] = "completion_date = NOW()";
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }
    
    $values[] = $objective_id;
    $types .= 'i';
    
    $update_sql = "UPDATE project_objectives SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Objective updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update objective']);
    }
    
    $stmt->close();
}

function handleDeleteObjective() {
    global $conn, $faculty_id;
    
    $objective_id = $_GET['id'] ?? null;
    
    if (!$objective_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Objective ID required']);
        return;
    }
    
    // Verify objective belongs to faculty's project
    $check_sql = "
        SELECT po.id FROM project_objectives po
        JOIN projects pr ON po.project_id = pr.id
        JOIN programs p ON pr.program_id = p.id 
        WHERE po.id = ? AND p.faculty_id = ?
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $objective_id, $faculty_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Objective not found or access denied']);
        return;
    }
    $check_stmt->close();
    
    // Delete objective
    $delete_sql = "DELETE FROM project_objectives WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $objective_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Objective deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete objective']);
    }
    
    $stmt->close();
}

function handleGetObjectives() {
    global $conn, $faculty_id;
    
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID required']);
        return;
    }
    
    // Verify project belongs to faculty
    $project_check_sql = "
        SELECT pr.id FROM projects pr 
        JOIN programs p ON pr.program_id = p.id 
        WHERE pr.id = ? AND p.faculty_id = ?
    ";
    $project_stmt = $conn->prepare($project_check_sql);
    $project_stmt->bind_param("ii", $project_id, $faculty_id);
    $project_stmt->execute();
    if ($project_stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Project not found or access denied']);
        return;
    }
    $project_stmt->close();
    
    // Get objectives
    $objectives_sql = "SELECT * FROM project_objectives WHERE project_id = ? ORDER BY priority ASC";
    $stmt = $conn->prepare($objectives_sql);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $objectives = [];
    while ($objective = $result->fetch_assoc()) {
        $objectives[] = $objective;
    }
    
    echo json_encode(['success' => true, 'objectives' => $objectives]);
    $stmt->close();
}
?>
