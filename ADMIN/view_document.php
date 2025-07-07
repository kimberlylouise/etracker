<?php
// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'db.php';

if (!isset($_GET['id'])) {
    die('Document ID required');
}

$id = intval($_GET['id']);

try {
    $stmt = $conn->prepare("SELECT original_filename, file_blob, document_type, file_path FROM document_uploads WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die('Document not found');
    }
    
    $doc = $result->fetch_assoc();
    
    // If file_blob exists, serve it directly
    if (!empty($doc['file_blob'])) {
        $filename = $doc['original_filename'];
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Set appropriate content type
        switch (strtolower($extension)) {
            case 'pdf':
                header('Content-Type: application/pdf');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'doc':
                header('Content-Type: application/msword');
                break;
            case 'docx':
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                break;
            default:
                header('Content-Type: application/octet-stream');
        }
        
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $doc['file_blob'];
    } 
    // If file_path exists, try to serve the file
    else if (!empty($doc['file_path'])) {
        $file_path = $doc['file_path'];
        
        // Try different possible locations
        $possible_paths = [
            __DIR__ . '/../FACULTY/' . $file_path,
            __DIR__ . '/../' . $file_path,
            __DIR__ . '/' . $file_path,
            $file_path
        ];
        
        $found_file = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $found_file = $path;
                break;
            }
        }
        
        if ($found_file) {
            $filename = $doc['original_filename'];
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            
            // Set appropriate content type
            switch (strtolower($extension)) {
                case 'pdf':
                    header('Content-Type: application/pdf');
                    break;
                case 'jpg':
                case 'jpeg':
                    header('Content-Type: image/jpeg');
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'doc':
                    header('Content-Type: application/msword');
                    break;
                case 'docx':
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    break;
                default:
                    header('Content-Type: application/octet-stream');
            }
            
            header('Content-Disposition: inline; filename="' . $filename . '"');
            readfile($found_file);
        } else {
            die('File not found on server. Paths checked: ' . implode(', ', $possible_paths));
        }
    } else {
        die('No file data or path available for this document');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
