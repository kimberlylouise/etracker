<?php
// Simple server to test the admin panel
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace(dirname($script_name), '', $request_uri);
$path = trim($path, '/');

// Route requests
if ($path === '' || $path === 'admin') {
    // Serve the admin document page
    readfile('Document.html');
} elseif ($path === 'api/documents') {
    // API endpoint for documents
    include '../backend/get_documents.php';
} elseif ($path === 'test') {
    // Test endpoint
    include 'test_documents.php';
} else {
    // Default: serve the document page
    readfile('Document.html');
}
?>
