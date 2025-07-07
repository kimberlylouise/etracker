<?php
// Suppress PHP warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        // Fallback to POST data
        $data = $_POST;
    }
    
    // Get form data
    $program_name = $data['program_name'] ?? '';
    $department = $data['department'] ?? '';
    $program_type = $data['program_type'] ?? '';
    $description = $data['description'] ?? '';
    $location = $data['location'] ?? '';
    $target_audience = $data['target_audience'] ?? '';
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $max_students = $data['max_students'] ?? 0;
    $budget = $data['budget'] ?? 0;
    $requirements = $data['requirements'] ?? '';
    $status = 'planning'; // Default status

    // Validate required fields
    if (empty($program_name) || empty($department) || empty($program_type) || 
        empty($description) || empty($location) || empty($target_audience) ||
        empty($start_date) || empty($end_date) || empty($max_students)) {
        throw new Exception('Please fill in all required fields');
    }

    // Insert program into database
    $sql = "INSERT INTO programs (
        program_name, department, program_type, description, location, 
        target_audience, start_date, end_date, max_students, budget, 
        requirements, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param("ssssssssidss", 
        $program_name, $department, $program_type, $description, $location,
        $target_audience, $start_date, $end_date, $max_students, $budget,
        $requirements, $status
    );

    if (!$stmt->execute()) {
        throw new Exception('Database execution error: ' . $stmt->error);
    }

    $program_id = $conn->insert_id;

    // Handle sessions if provided
    if (!empty($data['session_date'])) {
        $session_dates = $data['session_date'];
        $session_starts = $data['session_start'] ?? [];
        $session_ends = $data['session_end'] ?? [];
        $session_titles = $data['session_title'] ?? [];
        
        if (is_array($session_dates)) {
            foreach ($session_dates as $index => $date) {
                if (!empty($date)) {
                    $start_time = $session_starts[$index] ?? '';
                    $end_time = $session_ends[$index] ?? '';
                    $title = $session_titles[$index] ?? '';
                    
                    $session_sql = "INSERT INTO sessions (program_id, session_date, session_start, session_end, session_title, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                    $session_stmt = $conn->prepare($session_sql);
                    
                    if ($session_stmt) {
                        $session_stmt->bind_param("issss", $program_id, $date, $start_time, $end_time, $title);
                        $session_stmt->execute();
                        $session_stmt->close();
                    }
                }
            }
        }
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Program created successfully',
        'program_id' => $program_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>