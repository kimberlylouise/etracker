<?php
// Test database connection and table structure
require_once 'db.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "✅ Database connection successful<br><br>";
    
    // Check if detailed_evaluations table exists
    $result = $conn->query("DESCRIBE detailed_evaluations");
    if (!$result) {
        throw new Exception("Table 'detailed_evaluations' does not exist");
    }
    
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for required columns
    $requiredColumns = ['reviewed', 'admin_suggestion', 'admin_suggestion_date'];
    $existingColumns = [];
    
    $result = $conn->query("DESCRIBE detailed_evaluations");
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "<h3>Column Check:</h3>";
    foreach ($requiredColumns as $col) {
        if (in_array($col, $existingColumns)) {
            echo "✅ Column '$col' exists<br>";
        } else {
            echo "❌ Column '$col' missing - Run update_evaluations_table.sql<br>";
        }
    }
    
    // Check if programs table exists
    echo "<br><h3>Programs Table Check:</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'programs'");
    if ($result->num_rows > 0) {
        echo "✅ Programs table exists<br>";
        
        // Check structure
        $result = $conn->query("DESCRIBE programs");
        echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Field</th><th>Type</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($row['Field']) . "</td><td>" . htmlspecialchars($row['Type']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Programs table not found<br>";
    }
    
    // Sample data check
    echo "<br><h3>Data Check:</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM detailed_evaluations");
    $row = $result->fetch_assoc();
    echo "📊 Total evaluations in database: " . $row['count'] . "<br>";
    
    if ($row['count'] > 0) {
        echo "<br><h4>Sample Data (first 3 records):</h4>";
        $result = $conn->query("SELECT id, program_id, student_name, content, facilitators, eval_date FROM detailed_evaluations LIMIT 3");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Program ID</th><th>Student</th><th>Content</th><th>Facilitators</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['program_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['content']) . "</td>";
            echo "<td>" . htmlspecialchars($row['facilitators']) . "</td>";
            echo "<td>" . htmlspecialchars($row['eval_date']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ No evaluation data found in the database<br>";
    }
    
    echo "<br><h3>API Test:</h3>";
    echo "<a href='api_evaluations.php?action=evaluations' target='_blank'>🔗 Test Evaluations API</a><br>";
    echo "<a href='api_evaluations.php?action=programs' target='_blank'>🔗 Test Programs API</a><br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . htmlspecialchars($e->getMessage());
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
