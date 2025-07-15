<?php
require_once 'db.php';
session_start();

// Set content type for JSON response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get faculty_id from user_id
    $faculty_sql = "SELECT id FROM faculty WHERE user_id = ?";
    $faculty_stmt = $conn->prepare($faculty_sql);
    $faculty_stmt->bind_param("i", $user_id);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();
    $faculty_row = $faculty_result->fetch_assoc();
    $faculty_id = $faculty_row['id'] ?? null;
    $faculty_stmt->close();
    
    if (!$faculty_id) {
        throw new Exception('Faculty record not found for this user');
    }
    
    // Debug log
    error_log("Creating program for faculty_id: $faculty_id, user_id: $user_id");
    
    // Validate and sanitize input data
    $program_name = trim($_POST['program_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $program_type = trim($_POST['program_type'] ?? 'Extension Program');
    $target_audience = trim($_POST['target_audience'] ?? 'Students');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    $max_students = intval($_POST['max_students'] ?? 0);
    $male_count = intval($_POST['male_count'] ?? 0);
    $female_count = intval($_POST['female_count'] ?? 0);
    $start_date = $_POST['start_date'] ?? '';
    $previous_date = $_POST['previous_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $dept_approval = 'pending'; // Faculty programs need department approval
    $priority = 'normal';
    
    // Process Project Titles as JSON array
    $project_titles = array_filter([
        trim($_POST['project_title_1'] ?? ''),
        trim($_POST['project_title_2'] ?? ''),
        trim($_POST['project_title_3'] ?? '')
    ]);
    $project_titles_json = json_encode($project_titles);
    
    // Process SDG selection as JSON array
    $selected_sdgs_json = $_POST['selected_sdgs'] ?? '[]';
    $selected_sdgs = json_decode($selected_sdgs_json, true);
    
    // Validate and clean SDG data
    if (!is_array($selected_sdgs)) {
        $selected_sdgs = [];
    }
    
    // Ensure SDGs are integers between 1-17
    $selected_sdgs = array_filter($selected_sdgs, function($sdg) {
        return is_numeric($sdg) && $sdg >= 1 && $sdg <= 17;
    });
    $selected_sdgs = array_map('intval', $selected_sdgs);
    $sdg_goals_json = json_encode(array_values($selected_sdgs));
    
    // Process sessions data as JSON
    $sessions = [];
    if (isset($_POST['session_date']) && is_array($_POST['session_date'])) {
        for ($i = 0; $i < count($_POST['session_date']); $i++) {
            if (!empty($_POST['session_date'][$i])) {
                $sessions[] = [
                    'date' => $_POST['session_date'][$i],
                    'start_time' => $_POST['session_start'][$i] ?? '',
                    'end_time' => $_POST['session_end'][$i] ?? '',
                    'title' => trim($_POST['session_title'][$i] ?? '')
                ];
            }
        }
    }
    $sessions_json = json_encode($sessions);
    
    // Validation
    if (empty($program_name)) {
        throw new Exception('Program name is required');
    }
    if (empty($description)) {
        throw new Exception('Program description is required');
    }
    if (empty($location)) {
        throw new Exception('Program location is required');
    }
    if ($max_students <= 0 || $max_students > 20) {
        throw new Exception('Maximum students must be between 1 and 20');
    }
    if (($male_count + $female_count) > $max_students) {
        throw new Exception('Total gender count cannot exceed maximum students');
    }
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Start date and end date are required');
    }
    
    // Start database transaction
    $conn->begin_transaction();
    
    // Insert into programs table using department-focused schema
    $sql = "INSERT INTO programs (
        program_name,
        department,
        start_date,
        previous_date,
        end_date,
        location,
        max_students,
        male_count,
        female_count,
        description,
        program_level,
        program_category,
        project_titles,
        sessions_data,
        sdg_goals,
        program_type,
        target_audience,
        dept_approval,
        priority,
        budget,
        created_at,
        faculty_id,
        user_id,
        status,
        faculty_certificate_issued
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'beginner', 'extension', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'ongoing', 0)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param(
        "ssssssiiissssssiii",
        $program_name,
        $department,
        $start_date,
        $previous_date,
        $end_date,
        $location,
        $max_students,
        $male_count,
        $female_count,
        $description,
        $project_titles_json,
        $sessions_json,
        $sdg_goals_json,
        $program_type,
        $target_audience,
        $dept_approval,
        $priority,
        $budget,
        $faculty_id,
        $user_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create program: ' . $stmt->error);
    }
    
    $program_id = $conn->insert_id;
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Log the successful creation
    error_log("Program created successfully: ID $program_id, SDGs: $sdg_goals_json");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Program created successfully!',
        'program_id' => $program_id,
        'selected_sdgs' => $selected_sdgs,
        'sdg_goals_json' => $sdg_goals_json,
        'project_titles' => $project_titles,
        'sessions_count' => count($sessions)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Program creation failed: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>