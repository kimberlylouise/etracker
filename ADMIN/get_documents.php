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

// Include database connection
include '../FACULTY/db.php';

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    $sql = "SELECT 
                id,
                program_id,
                faculty_id,
                document_type,
                file_path,
                original_filename,
                upload_date,
                status,
                admin_remarks,
                reviewed_by,
                reviewed_at,
                uploaded_by
            FROM document_uploads 
            ORDER BY upload_date DESC";
    
    $res = $conn->query($sql);
    
    if (!$res) {
        throw new Exception('SQL Error: ' . $conn->error);
    }
    
    $docs = [];
    while ($row = $res->fetch_assoc()) {
        $docs[] = $row;
    }
    
    // Log for debugging
    error_log("Documents fetched: " . count($docs) . " records");
    
    echo json_encode($docs);
    
} catch (Exception $e) {
    error_log("Error in get_documents.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch documents: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
