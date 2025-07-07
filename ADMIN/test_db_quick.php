<?php
// Quick database test to see what's in your programs table
echo "<h2>Quick Database Check</h2>";
echo "<pre>";

try {
    include_once 'db.php';
    
    if (!$conn) {
        echo "❌ Database connection failed\n";
        exit;
    }
    
    echo "✅ Database connected successfully\n\n";
    
    // Check programs table
    echo "📋 PROGRAMS TABLE:\n";
    $query = "SELECT id, program_name, status, start_date, end_date, project_titles FROM programs ORDER BY id DESC LIMIT 10";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "❌ Query failed: " . mysqli_error($conn) . "\n";
    } else {
        $count = mysqli_num_rows($result);
        echo "Found {$count} programs:\n\n";
        
        if ($count > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo "ID: {$row['id']}\n";
                echo "Name: {$row['program_name']}\n";
                echo "Status: {$row['status']}\n";
                echo "Dates: {$row['start_date']} to {$row['end_date']}\n";
                
                if (!empty($row['project_titles'])) {
                    $projects = json_decode($row['project_titles'], true);
                    if (is_array($projects)) {
                        echo "Projects: " . count($projects) . " found\n";
                        foreach ($projects as $i => $title) {
                            if (!empty($title)) {
                                echo "  - Project {$i}: {$title}\n";
                            }
                        }
                    }
                }
                echo "------------------------\n";
            }
        } else {
            echo "No programs found in the database.\n";
        }
    }
    
    // Check projects table
    echo "\n⚡ PROJECTS TABLE:\n";
    $projectQuery = "SELECT COUNT(*) as count FROM projects";
    $projectResult = mysqli_query($conn, $projectQuery);
    
    if ($projectResult) {
        $projectCount = mysqli_fetch_assoc($projectResult)['count'];
        echo "Found {$projectCount} standalone projects\n";
    } else {
        echo "Projects table might not exist or is empty\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
