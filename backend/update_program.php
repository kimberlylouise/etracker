<?php
header('Content-Type: application/json');
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $program_id = $_POST['program_id'] ?? '';
    $program_name = $_POST['program_name'] ?? '';
    $program_type = $_POST['program_type'] ?? '';
    $department = $_POST['department'] ?? '';
    $description = $_POST['description'] ?? '';
    $objectives = $_POST['objectives'] ?? '';
    $target_participants = $_POST['target_participants'] ?? '';
    $expected_outcome = $_POST['expected_outcome'] ?? '';
    $budget = $_POST['budget'] ?? 0;
    $venue = $_POST['venue'] ?? '';
    $resources_needed = $_POST['resources_needed'] ?? '';
    $max_participants = $_POST['max_participants'] ?? 0;
    $registration_deadline = $_POST['registration_deadline'] ?? '';
    $status = $_POST['status'] ?? 'planning';
    $approval_status = $_POST['approval_status'] ?? 'pending';
    $priority_level = $_POST['priority_level'] ?? 'normal';

    // Validate required fields (removed faculty_id)
    if (empty($program_id) || empty($program_name) || empty($program_type) || 
        empty($department) || empty($description) || empty($objectives) || 
        empty($target_participants)) {
        throw new Exception('Please fill in all required fields');
    }

    // Update program (removed faculty_id from query)
    $stmt = $pdo->prepare("
        UPDATE programs SET 
            program_name = ?, program_type = ?, department = ?, description = ?, 
            objectives = ?, target_participants = ?, expected_outcome = ?, budget = ?, 
            venue = ?, resources_needed = ?, max_participants = ?, registration_deadline = ?, 
            status = ?, approval_status = ?, priority_level = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $program_name, $program_type, $department, $description, $objectives,
        $target_participants, $expected_outcome, $budget, $venue, $resources_needed,
        $max_participants, $registration_deadline, $status, $approval_status, 
        $priority_level, $program_id
    ]);

    // Handle SDGs update
    // First delete existing SDGs
    $stmt = $pdo->prepare("DELETE FROM program_sdgs WHERE program_id = ?");
    $stmt->execute([$program_id]);

    // Then insert new SDGs
    if (!empty($_POST['sdgs'])) {
        $sdgs = $_POST['sdgs'];
        if (is_array($sdgs)) {
            $stmt = $pdo->prepare("INSERT INTO program_sdgs (program_id, sdg_id) VALUES (?, ?)");
            foreach ($sdgs as $sdg_id) {
                $stmt->execute([$program_id, $sdg_id]);
            }
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Program updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>