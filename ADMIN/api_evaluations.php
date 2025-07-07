<?php
require_once 'db.php';

// Set content type for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'evaluations':
            getEvaluations();
            break;
            
        case 'programs':
            getPrograms();
            break;
            
        case 'mark_reviewed':
            markAsReviewed();
            break;
            
        case 'add_suggestion':
            addAdminSuggestion();
            break;
            
        case 'bulk_suggestions':
            sendBulkSuggestions();
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getEvaluations() {
    global $conn;
    
    try {
        // Get evaluations with program names
        $sql = "
            SELECT 
                de.*,
                p.program_name
            FROM detailed_evaluations de
            LEFT JOIN programs p ON de.program_id = p.id
            ORDER BY de.eval_date DESC
        ";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $evaluations = [];
        while ($row = $result->fetch_assoc()) {
            // Convert admin_suggestion from text to object if it exists
            $adminSuggestion = null;
            if (!empty($row['admin_suggestion'])) {
                // Check if it's already JSON
                $decoded = json_decode($row['admin_suggestion'], true);
                if ($decoded) {
                    $adminSuggestion = $decoded;
                } else {
                    // If it's plain text, convert to object format
                    $adminSuggestion = [
                        'message' => $row['admin_suggestion'],
                        'date' => $row['admin_suggestion_date'] ?? date('Y-m-d H:i:s'),
                        'admin' => 'Admin User'
                    ];
                }
            }
            
            $evaluations[] = [
                'id' => (int)$row['id'],
                'program_id' => (int)$row['program_id'],
                'student_id' => (int)$row['student_id'],
                'student_name' => $row['student_name'] ?? '',
                'program_name' => $row['program_name'] ?? 'Unknown Program',
                'content' => (int)$row['content'],
                'facilitators' => (int)$row['facilitators'],
                'relevance' => (int)$row['relevance'],
                'organization' => (int)$row['organization'],
                'experience' => (int)$row['experience'],
                'suggestion' => $row['suggestion'] ?? '',
                'recommend' => $row['recommend'] ?? '',
                'eval_date' => $row['eval_date'],
                'reviewed' => (int)($row['reviewed'] ?? 0),
                'admin_suggestion' => $adminSuggestion,
                'admin_suggestion_date' => $row['admin_suggestion_date']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $evaluations,
            'count' => count($evaluations)
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch evaluations: ' . $e->getMessage());
    }
}

function getPrograms() {
    global $conn;
    
    try {
        $sql = "SELECT DISTINCT program_name FROM programs WHERE program_name IS NOT NULL ORDER BY program_name";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $programs = [];
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row['program_name'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $programs
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to fetch programs: ' . $e->getMessage());
    }
}

function markAsReviewed() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    
    if (!$id) {
        throw new Exception('Evaluation ID is required');
    }
    
    try {
        $sql = "UPDATE detailed_evaluations SET reviewed = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No evaluation found with the given ID');
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Evaluation marked as reviewed successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to mark as reviewed: ' . $e->getMessage());
    }
}

function addAdminSuggestion() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $suggestion = trim($input['suggestion'] ?? '');
    
    if (!$suggestion) {
        throw new Exception('Suggestion text is required');
    }
    
    if (strlen($suggestion) < 10) {
        throw new Exception('Suggestion must be at least 10 characters long');
    }
    
    try {
        // Create suggestion object
        $suggestionObj = [
            'message' => $suggestion,
            'date' => date('Y-m-d H:i:s'),
            'admin' => 'Admin User'
        ];
        
        $suggestionJson = json_encode($suggestionObj);
        
        if ($id) {
            // Update specific evaluation
            $sql = "UPDATE detailed_evaluations SET admin_suggestion = ?, admin_suggestion_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Prepare statement failed: ' . $conn->error);
            }
            
            $stmt->bind_param("si", $suggestionJson, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Admin suggestion added successfully'
            ]);
            
        } else {
            throw new Exception('Evaluation ID is required');
        }
        
    } catch (Exception $e) {
        throw new Exception('Failed to add suggestion: ' . $e->getMessage());
    }
}

function sendBulkSuggestions() {
    global $conn;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $suggestion = trim($input['suggestion'] ?? '');
    
    if (!$suggestion) {
        throw new Exception('Suggestion text is required');
    }
    
    if (strlen($suggestion) < 10) {
        throw new Exception('Suggestion must be at least 10 characters long');
    }
    
    try {
        // Create suggestion object
        $suggestionObj = [
            'message' => $suggestion,
            'date' => date('Y-m-d H:i:s'),
            'admin' => 'Admin User'
        ];
        
        $suggestionJson = json_encode($suggestionObj);
        
        // Find evaluations with low ratings (average < 2.5) that don't have suggestions yet
        $sql = "
            SELECT id, 
                   (content + facilitators + relevance + organization + experience) / 5 as avg_rating
            FROM detailed_evaluations 
            WHERE (admin_suggestion IS NULL OR admin_suggestion = '')
            HAVING avg_rating < 2.5
        ";
        
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $evaluationIds = [];
        while ($row = $result->fetch_assoc()) {
            $evaluationIds[] = $row['id'];
        }
        
        if (empty($evaluationIds)) {
            echo json_encode([
                'success' => true,
                'message' => 'No evaluations currently need improvement suggestions',
                'affected_count' => 0
            ]);
            return;
        }
        
        // Update all qualifying evaluations
        $placeholders = str_repeat('?,', count($evaluationIds) - 1) . '?';
        $sql = "UPDATE detailed_evaluations SET admin_suggestion = ?, admin_suggestion_date = NOW() WHERE id IN ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $types = 's' . str_repeat('i', count($evaluationIds));
        $params = array_merge([$suggestionJson], $evaluationIds);
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk suggestions sent to $affectedRows evaluations successfully",
            'affected_count' => $affectedRows
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to send bulk suggestions: ' . $e->getMessage());
    }
}

$conn->close();
?>
