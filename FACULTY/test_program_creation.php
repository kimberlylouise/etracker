<?php
require_once 'db.php';

echo "<h2>🧪 Program Creation Test Results</h2>";

// Test 1: Check latest programs with complete data
echo "<h3>1. Latest Programs Created</h3>";
$recent = $conn->query("
    SELECT 
        id, 
        program_name, 
        sdg_goals, 
        project_titles, 
        sessions_data,
        male_count,
        female_count,
        max_students,
        location,
        created_at 
    FROM programs 
    ORDER BY created_at DESC 
    LIMIT 3
");

if ($recent && $recent->num_rows > 0) {
    while ($row = $recent->fetch_assoc()) {
        echo "<div style='border: 2px solid #28a745; padding: 20px; margin: 15px 0; border-radius: 12px; background: #f8f9fa;'>";
        echo "<h4 style='color: #28a745; margin: 0 0 15px 0;'>📋 " . htmlspecialchars($row['program_name']) . " (ID: " . $row['id'] . ")</h4>";
        
        echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>";
        
        // Left column
        echo "<div>";
        echo "<p><strong>📍 Location:</strong> " . htmlspecialchars($row['location']) . "</p>";
        echo "<p><strong>👥 Participants:</strong> " . $row['max_students'] . " total</p>";
        echo "<p><strong>♂️ Male:</strong> " . $row['male_count'] . " | <strong>♀️ Female:</strong> " . $row['female_count'] . "</p>";
        echo "<p><strong>📅 Created:</strong> " . $row['created_at'] . "</p>";
        echo "</div>";
        
        // Right column
        echo "<div>";
        
        // SDGs
        if (!empty($row['sdg_goals'])) {
            $sdgs = json_decode($row['sdg_goals'], true);
            if (is_array($sdgs) && count($sdgs) > 0) {
                echo "<p><strong>🌍 SDGs:</strong> " . implode(', ', $sdgs) . " (" . count($sdgs) . " goals)</p>";
            } else {
                echo "<p><strong>🌍 SDGs:</strong> ❌ Invalid format</p>";
            }
        } else {
            echo "<p><strong>🌍 SDGs:</strong> None selected</p>";
        }
        
        // Project Titles
        if (!empty($row['project_titles'])) {
            $titles = json_decode($row['project_titles'], true);
            if (is_array($titles) && count($titles) > 0) {
                echo "<p><strong>📝 Projects:</strong> " . implode(', ', array_filter($titles)) . "</p>";
            }
        }
        
        // Sessions
        if (!empty($row['sessions_data'])) {
            $sessions = json_decode($row['sessions_data'], true);
            if (is_array($sessions) && count($sessions) > 0) {
                echo "<p><strong>📅 Sessions:</strong> " . count($sessions) . " scheduled</p>";
            }
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<p>❌ No programs found in database.</p>";
}

// Test 2: Validate JSON data integrity
echo "<h3>2. JSON Data Integrity Check</h3>";
$json_check = $conn->query("
    SELECT 
        id,
        program_name,
        JSON_VALID(sdg_goals) as sdg_valid,
        JSON_VALID(project_titles) as titles_valid,
        JSON_VALID(sessions_data) as sessions_valid,
        JSON_LENGTH(sdg_goals) as sdg_count,
        JSON_LENGTH(project_titles) as title_count,
        JSON_LENGTH(sessions_data) as session_count
    FROM programs 
    WHERE created_at >= CURDATE() - INTERVAL 7 DAYS
    ORDER BY created_at DESC 
    LIMIT 5
");

if ($json_check && $json_check->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #28a745; color: white;'>";
    echo "<th style='padding: 10px;'>Program ID</th>";
    echo "<th style='padding: 10px;'>Program Name</th>";
    echo "<th style='padding: 10px;'>SDG Valid</th>";
    echo "<th style='padding: 10px;'>SDG Count</th>";
    echo "<th style='padding: 10px;'>Titles Valid</th>";
    echo "<th style='padding: 10px;'>Sessions Valid</th>";
    echo "</tr>";
    
    while ($row = $json_check->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 8px; text-align: center;'>" . $row['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($row['program_name']) . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . ($row['sdg_valid'] ? '✅' : '❌') . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . ($row['sdg_count'] ?? 0) . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . ($row['titles_valid'] ? '✅' : '❌') . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . ($row['sessions_valid'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No recent programs to validate.</p>";
}

// Test 3: Check date restrictions
echo "<h3>3. Date Validation Test</h3>";
$today = date('Y-m-d');
$future_programs = $conn->query("
    SELECT COUNT(*) as count 
    FROM programs 
    WHERE start_date >= '$today' 
    AND created_at >= CURDATE() - INTERVAL 7 DAYS
");

$past_programs = $conn->query("
    SELECT COUNT(*) as count 
    FROM programs 
    WHERE start_date < '$today' 
    AND created_at >= CURDATE() - INTERVAL 7 DAYS
");

$future_count = $future_programs->fetch_assoc()['count'];
$past_count = $past_programs->fetch_assoc()['count'];

echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 10px 0;'>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; border: 1px solid #c3e6cb;'>";
echo "<h5 style='color: #155724; margin: 0 0 10px 0;'>✅ Future Programs</h5>";
echo "<p style='margin: 0; font-size: 1.2rem; font-weight: bold;'>{$future_count} programs</p>";
echo "<p style='margin: 5px 0 0 0; color: #155724;'>Start date >= today</p>";
echo "</div>";

echo "<div style='background: #f8d7da; padding: 15px; border-radius: 8px; border: 1px solid #f5c6cb;'>";
echo "<h5 style='color: #721c24; margin: 0 0 10px 0;'>⚠️ Past Programs</h5>";
echo "<p style='margin: 0; font-size: 1.2rem; font-weight: bold;'>{$past_count} programs</p>";
echo "<p style='margin: 5px 0 0 0; color: #721c24;'>Start date < today (should be 0)</p>";
echo "</div>";
echo "</div>";

if ($past_count > 0) {
    echo "<p style='color: #721c24; font-weight: bold;'>⚠️ WARNING: Found programs with past start dates! Date validation may not be working properly.</p>";
} else {
    echo "<p style='color: #155724; font-weight: bold;'>✅ SUCCESS: All programs have valid future dates!</p>";
}

$conn->close();
?>

<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f8f9fa; }
    h2 { color: #28a745; border-bottom: 3px solid #28a745; padding-bottom: 10px; }
    h3 { color: #495057; margin-top: 30px; }
</style>
