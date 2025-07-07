<?php
// Database configuration
$host = 'extension.c5i2m2mgkbh2.ap-southeast-2.rds.amazonaws.com';
$user = 'admin';
$pass = 'etrackerextension';
$dbname = 'etracker';

// Set a timeout for DNS resolution
ini_set('default_socket_timeout', 10);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        error_log("MySQL Connection failed: " . $conn->connect_error);
        die("Database connection failed. Please try again later.");
    }
    $conn->set_charset('utf8mb4');
    error_log("Database connected successfully to $host");
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error occurred. Please contact support.");
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>