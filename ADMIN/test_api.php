<?php
// Simple test to check if the API is working
echo "Testing api_projects.php...\n";

// Test if we can include the required files
try {
    require_once 'db.php';
    echo "✓ db.php loaded successfully\n";
    
    // Test database connection
    if (isset($conn) && $conn->ping()) {
        echo "✓ Database connection is working\n";
    } else {
        echo "✗ Database connection failed\n";
    }
    
    // Test the API endpoint
    $test_url = "http://localhost/Extension/ADMIN/api_projects.php?action=get_projects_for_evaluation";
    echo "Testing URL: $test_url\n";
    
    // Use file_get_contents for a simple test
    $context = stream_context_create([
        'http' => [
            'timeout' => 10
        ]
    ]);
    
    $result = file_get_contents($test_url, false, $context);
    
    if ($result !== false) {
        echo "✓ API responded\n";
        echo "Response: " . substr($result, 0, 200) . "...\n";
    } else {
        echo "✗ API did not respond\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
