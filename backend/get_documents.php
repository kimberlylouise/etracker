<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../FACULTY/db.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    $sql = "SELECT * FROM document_uploads ORDER BY upload_date DESC";
    $res = $conn->query($sql);
    
    if (!$res) {
        throw new Exception('SQL Error: ' . $conn->error);
    }
    
    $docs = [];
    while ($row = $res->fetch_assoc()) {
        $docs[] = $row;
    }
    
    echo json_encode($docs);
    
} catch (Exception $e) {
    error_log("Error in get_documents.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch documents: ' . $e->getMessage()]);
}
?>