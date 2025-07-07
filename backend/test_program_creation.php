<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// Test database connection
echo "Testing database connection...\n";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Database connected successfully!\n\n";

// Check if programs table exists
echo "Checking programs table structure...\n";
$result = $conn->query("DESCRIBE programs");
if ($result) {
    echo "Programs table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Programs table doesn't exist or error: " . $conn->error . "\n";
}

echo "\n";

// Check if sessions table exists
echo "Checking sessions table structure...\n";
$result = $conn->query("DESCRIBE sessions");
if ($result) {
    echo "Sessions table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Sessions table doesn't exist or error: " . $conn->error . "\n";
}

$conn->close();
?>
