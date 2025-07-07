<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    // Get unique departments from faculty table
    $stmt = $pdo->prepare("SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department");
    $stmt->execute();
    $departments = $stmt->fetchAll();
    
    // Format the response
    $result = [];
    foreach ($departments as $dept) {
        $result[] = [
            'id' => $dept['department'], // Using department name as ID since you don't have a separate departments table
            'name' => $dept['department']
        ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch departments: ' . $e->getMessage()]);
}
?>