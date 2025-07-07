<?php
// Quick database connection test for deployment
require_once 'ADMIN/db.php';

echo "<h2>eTracker Database Connection Test</h2>";

if ($conn) {
    echo "<p style='color: green;'>✅ Database connected successfully!</p>";
    
    // Test a simple query
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>👥 Total users in database: " . $row['count'] . "</p>";
    }
    
    // Test database connection details
    echo "<p>📍 Connected to: " . $host . "</p>";
    echo "<p>🗄️ Database: " . $dbname . "</p>";
    echo "<p>🕒 Connection time: " . date('Y-m-d H:i:s') . "</p>";
    
} else {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
}

echo "<hr>";
echo "<p><a href='register/'>Go to Login Page</a></p>";
echo "<p><a href='ADMIN/'>Go to Admin Panel</a></p>";
echo "<p><a href='STUDENT/'>Go to Student Dashboard</a></p>";
?>
