<?php
// Test script to debug document fetching
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Document Database Test</h1>";

// Test database connection
include '../FACULTY/db.php';

if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connected successfully</p>";

// Test if table exists
$table_check = $conn->query("SHOW TABLES LIKE 'document_uploads'");
if ($table_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Table 'document_uploads' exists</p>";
} else {
    echo "<p style='color: red;'>❌ Table 'document_uploads' does not exist</p>";
    exit;
}

// Check table structure
echo "<h2>Table Structure:</h2>";
$structure = $conn->query("DESCRIBE document_uploads");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $structure->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Count total records
$count_result = $conn->query("SELECT COUNT(*) as total FROM document_uploads");
$count = $count_result->fetch_assoc()['total'];
echo "<h2>Total Records: " . $count . "</h2>";

// Get first 5 records
echo "<h2>Sample Data (First 5 Records):</h2>";
$sample_data = $conn->query("SELECT * FROM document_uploads LIMIT 5");

if ($sample_data->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='font-size: 12px; overflow: auto;'>";
    
    // Header
    $first_row = $sample_data->fetch_assoc();
    echo "<tr>";
    foreach (array_keys($first_row) as $column) {
        echo "<th>" . htmlspecialchars($column) . "</th>";
    }
    echo "</tr>";
    
    // Reset and show data
    $sample_data->data_seek(0);
    while ($row = $sample_data->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            if ($key === 'file_blob') {
                echo "<td>[BLOB DATA - " . strlen($value) . " bytes]</td>";
            } else {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No data found in the table</p>";
}

$conn->close();
?>

if ($conn) {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if document_uploads table exists
    $result = $conn->query("SHOW TABLES LIKE 'document_uploads'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table 'document_uploads' exists</p>";
        
        // Check table structure
        $structure = $conn->query("DESCRIBE document_uploads");
        if ($structure) {
            echo "<h3>Table Structure:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td style='padding: 5px; border: 1px solid #ccc;'>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Check for data
        $count_result = $conn->query("SELECT COUNT(*) as count FROM document_uploads");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['count'];
            echo "<p style='color: blue;'>📊 Total documents in table: $count</p>";
            
            if ($count > 0) {
                // Show sample data
                $sample = $conn->query("SELECT * FROM document_uploads LIMIT 5");
                if ($sample) {
                    echo "<h3>Sample Data (first 5 rows):</h3>";
                    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                    $first_row = true;
                    while ($row = $sample->fetch_assoc()) {
                        if ($first_row) {
                            echo "<tr>";
                            foreach (array_keys($row) as $column) {
                                echo "<th style='padding: 5px; border: 1px solid #ccc; background: #f0f0f0;'>" . htmlspecialchars($column) . "</th>";
                            }
                            echo "</tr>";
                            $first_row = false;
                        }
                        echo "<tr>";
                        foreach ($row as $value) {
                            // Truncate long values for display
                            $display_value = is_string($value) ? (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) : $value;
                            echo "<td style='padding: 5px; border: 1px solid #ccc;'>" . htmlspecialchars($display_value ?? '') . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Table exists but has no data</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>✗ Table 'document_uploads' does not exist</p>";
        
        // Show all tables
        $tables = $conn->query("SHOW TABLES");
        if ($tables) {
            echo "<h3>Available Tables:</h3>";
            echo "<ul>";
            while ($row = $tables->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }
            echo "</ul>";
        }
    }
    
} else {
    echo "<p style='color: red;'>✗ Database connection failed</p>";
}

// Test the API endpoint directly
echo "<h3>Testing API Response:</h3>";
try {
    $api_response = file_get_contents(__DIR__ . '/../backend/get_documents.php');
    if ($api_response === false) {
        echo "<p style='color: red;'>Failed to read API response</p>";
    } else {
        echo "<p style='color: green;'>API Response:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;'>";
        echo htmlspecialchars($api_response);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>API Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>Quick Fix Actions:</h3>";
echo "<p>If the table doesn't exist, you can create it with this SQL:</p>";
echo "<pre style='background: #f0f8ff; padding: 10px; border: 1px solid #b0d4f1;'>";
echo "CREATE TABLE document_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT,
    faculty_id INT,
    document_type VARCHAR(100),
    file_path VARCHAR(255),
    original_filename VARCHAR(255),
    file_blob LONGBLOB,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending',
    admin_remarks TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    uploaded_by INT
);";
echo "</pre>";

?>
