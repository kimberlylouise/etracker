<?php
// Demonstration script for Admin Project Evaluation System
// This script simulates the workflow and shows how the system works

require_once '../FACULTY/db.php';

echo "<h2>🎯 Admin Project Evaluation System Demo</h2>\n";
echo "<p>This demonstrates the complete workflow of project evaluation by admin.</p>\n\n";

// Check if tables exist
$tables_to_check = ['projects', 'project_evaluations', 'notifications', 'project_objectives'];
$missing_tables = [];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "<h3>❌ Missing Tables</h3>\n";
    echo "<p>Please run the database setup scripts first. Missing tables:</p>\n";
    echo "<ul>\n";
    foreach ($missing_tables as $table) {
        echo "<li>$table</li>\n";
    }
    echo "</ul>\n";
    echo "<p>Run: <code>database_project_evaluation.sql</code> and <code>database_migration_projects.sql</code></p>\n\n";
} else {
    echo "<h3>✅ Database Tables Ready</h3>\n";
    echo "<p>All required tables are present.</p>\n\n";
}

// Show current projects status
echo "<h3>📊 Current Projects Overview</h3>\n";
$projects_sql = "SELECT 
    pr.id,
    pr.project_title,
    pr.status as project_status,
    pr.progress_percentage,
    p.program_name,
    CONCAT(u.firstname, ' ', u.lastname) as faculty_name,
    pr.created_at,
    pe.evaluation_status,
    pe.overall_rating
FROM projects pr
JOIN programs p ON pr.program_id = p.id
LEFT JOIN faculty f ON p.faculty_id = f.id
LEFT JOIN users u ON f.user_id = u.id
LEFT JOIN project_evaluations pe ON pr.id = pe.project_id
ORDER BY pr.status, pr.created_at DESC
LIMIT 10";

$projects_result = $conn->query($projects_sql);

if ($projects_result && $projects_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Project Title</th><th>Program</th><th>Faculty</th><th>Status</th><th>Progress</th><th>Evaluation Status</th><th>Rating</th></tr>\n";
    
    while ($row = $projects_result->fetch_assoc()) {
        $evaluation_status = $row['evaluation_status'] ?: 'Not Evaluated';
        $rating = $row['overall_rating'] ? number_format($row['overall_rating'], 1) . '/5.0' : 'N/A';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['project_title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['program_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['faculty_name'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['project_status']) . "</td>";
        echo "<td>" . $row['progress_percentage'] . "%</td>";
        echo "<td>" . htmlspecialchars($evaluation_status) . "</td>";
        echo "<td>" . $rating . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n\n";
} else {
    echo "<p>No projects found. You may need to run the migration script first.</p>\n\n";
}

// Show evaluation criteria
echo "<h3>📋 Evaluation Criteria</h3>\n";
$criteria_sql = "SELECT * FROM evaluation_criteria ORDER BY display_order";
$criteria_result = $conn->query($criteria_sql);

if ($criteria_result && $criteria_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Criteria</th><th>Description</th><th>Required</th><th>Weight</th></tr>\n";
    
    while ($row = $criteria_result->fetch_assoc()) {
        $required = $row['is_required'] ? 'Yes' : 'No';
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['criteria_name']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['criteria_description']) . "</td>";
        echo "<td>" . $required . "</td>";
        echo "<td>" . $row['weight'] . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n\n";
} else {
    echo "<p>No evaluation criteria found. Please run the database setup script.</p>\n\n";
}

// Show recent notifications
echo "<h3>🔔 Recent Notifications</h3>\n";
$notifications_sql = "SELECT 
    n.type,
    n.title,
    n.message,
    n.created_at,
    CONCAT(u.firstname, ' ', u.lastname) as recipient,
    pr.project_title
FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
LEFT JOIN projects pr ON n.related_id = pr.id
WHERE n.type LIKE '%project_evaluation%'
ORDER BY n.created_at DESC
LIMIT 5";

$notifications_result = $conn->query($notifications_sql);

if ($notifications_result && $notifications_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Type</th><th>Title</th><th>Recipient</th><th>Project</th><th>Created</th></tr>\n";
    
    while ($row = $notifications_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . htmlspecialchars($row['recipient'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['project_title'] ?: 'N/A') . "</td>";
        echo "<td>" . date('M j, Y H:i', strtotime($row['created_at'])) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n\n";
} else {
    echo "<p>No project evaluation notifications found yet.</p>\n\n";
}

// Simulation section
echo "<h3>🎮 Simulation: Complete Project Evaluation Workflow</h3>\n";

if (isset($_GET['simulate']) && $_GET['simulate'] === 'true') {
    echo "<h4>Running Simulation...</h4>\n";
    
    // Step 1: Find a project to mark as completed
    $incomplete_project_sql = "SELECT id, project_title FROM projects WHERE status != 'completed' LIMIT 1";
    $incomplete_result = $conn->query($incomplete_project_sql);
    
    if ($incomplete_result && $incomplete_result->num_rows > 0) {
        $project = $incomplete_result->fetch_assoc();
        $project_id = $project['id'];
        $project_title = $project['project_title'];
        
        echo "<p>✅ Step 1: Found project to complete - '$project_title' (ID: $project_id)</p>\n";
        
        // Step 2: Mark project as completed (this should trigger the database trigger)
        $update_sql = "UPDATE projects SET status = 'completed', progress_percentage = 100 WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $project_id);
        
        if ($update_stmt->execute()) {
            echo "<p>✅ Step 2: Project marked as completed - evaluation required flag should be set</p>\n";
            
            // Step 3: Check if evaluation is required
            $check_sql = "SELECT evaluation_required, evaluation_deadline FROM projects WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $project_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            
            if ($check_data['evaluation_required']) {
                echo "<p>✅ Step 3: Evaluation requirement set. Deadline: " . $check_data['evaluation_deadline'] . "</p>\n";
            } else {
                echo "<p>⚠️ Step 3: Evaluation requirement not set automatically. You may need to check the trigger.</p>\n";
            }
            
            // Step 4: Simulate admin evaluation
            echo "<p>✅ Step 4: Simulating admin evaluation...</p>\n";
            
            $eval_sql = "INSERT INTO project_evaluations 
                (project_id, impact_rating, quality_rating, sustainability_rating, innovation_rating, 
                 collaboration_rating, budget_efficiency, timeliness_rating, overall_rating, 
                 evaluation_comments, recommendations, evaluation_status, admin_id)
                VALUES (?, 4, 4, 3, 4, 3, 4, 3, 3.57, 
                 'Good project implementation with measurable community impact.',
                 'Consider developing sustainability plan for long-term impact.',
                 'approved', 1)";
            
            $eval_stmt = $conn->prepare($eval_sql);
            $eval_stmt->bind_param("i", $project_id);
            
            if ($eval_stmt->execute()) {
                echo "<p>✅ Step 5: Admin evaluation completed with 3.57/5.0 rating</p>\n";
                echo "<p>✅ Step 6: Project status should be updated and notifications sent</p>\n";
            } else {
                echo "<p>❌ Step 5: Failed to create evaluation: " . $eval_stmt->error . "</p>\n";
            }
            
        } else {
            echo "<p>❌ Step 2: Failed to update project status: " . $update_stmt->error . "</p>\n";
        }
        
    } else {
        echo "<p>ℹ️ No incomplete projects found to simulate with. All projects may already be completed.</p>\n";
    }
    
    echo "<h4>Simulation Complete!</h4>\n";
    echo "<p><a href='?'>View Results</a></p>\n";
    
} else {
    echo "<p><a href='?simulate=true' style='background: #1B472B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Run Simulation</a></p>\n";
    echo "<p><em>This will:</em></p>\n";
    echo "<ul>\n";
    echo "<li>Mark a project as completed</li>\n";
    echo "<li>Trigger evaluation requirement</li>\n";
    echo "<li>Create admin evaluation</li>\n";
    echo "<li>Generate notifications</li>\n";
    echo "</ul>\n";
}

// System status summary
echo "<h3>📈 System Status Summary</h3>\n";

$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM projects) as total_projects,
    (SELECT COUNT(*) FROM projects WHERE status = 'completed') as completed_projects,
    (SELECT COUNT(*) FROM projects WHERE evaluation_required = 1) as projects_needing_evaluation,
    (SELECT COUNT(*) FROM project_evaluations) as total_evaluations,
    (SELECT AVG(overall_rating) FROM project_evaluations) as avg_rating";

$stats_result = $conn->query($stats_sql);
if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
    echo "<ul>\n";
    echo "<li><strong>Total Projects:</strong> " . $stats_row['total_projects'] . "</li>\n";
    echo "<li><strong>Completed Projects:</strong> " . $stats_row['completed_projects'] . "</li>\n";
    echo "<li><strong>Projects Needing Evaluation:</strong> " . $stats_row['projects_needing_evaluation'] . "</li>\n";
    echo "<li><strong>Total Evaluations:</strong> " . $stats_row['total_evaluations'] . "</li>\n";
    $avg_rating = $stats_row['avg_rating'] ? number_format($stats_row['avg_rating'], 2) : 'N/A';
    echo "<li><strong>Average Rating:</strong> " . $avg_rating . "</li>\n";
    echo "</ul>\n";
}

echo "<h3>🎯 Next Steps</h3>\n";
echo "<ol>\n";
echo "<li><strong>Database Setup:</strong> Ensure all tables are created by running the SQL scripts</li>\n";
echo "<li><strong>Access Admin Interface:</strong> Go to <code>ADMIN/ProjectEvaluation.html</code></li>\n";
echo "<li><strong>Test Evaluation:</strong> Complete a project and evaluate it through the admin interface</li>\n";
echo "<li><strong>Review Notifications:</strong> Check that faculty receive appropriate notifications</li>\n";
echo "<li><strong>Generate Reports:</strong> Use the evaluation data for reporting and analytics</li>\n";
echo "</ol>\n";

echo "<h3>📋 File Checklist</h3>\n";
$files_to_check = [
    'ADMIN/ProjectEvaluation.html' => 'Main admin evaluation interface',
    'ADMIN/api_projects.php' => 'API backend for evaluation system',
    'ADMIN/project_evaluation_notifications.php' => 'Notification management',
    'database_project_evaluation.sql' => 'Database setup script',
    'ADMIN_PROJECT_EVALUATION_GUIDE.md' => 'Complete implementation guide'
];

echo "<ul>\n";
foreach ($files_to_check as $file => $description) {
    $exists = file_exists($file) ? '✅' : '❌';
    echo "<li>$exists <strong>$file</strong> - $description</li>\n";
}
echo "</ul>\n";

echo "<hr>\n";
echo "<p><em>Admin Project Evaluation System - Ready for Implementation</em></p>\n";
?>
